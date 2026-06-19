#!/usr/bin/env python3
"""Build synced Balance Testing walkthrough video from scenes + TTS + screenshots."""

from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
ASSETS = ROOT / "assets"
AUDIO = ROOT / "audio"
SCENES_DIR = ROOT / "scenes"
OUTPUT = ROOT / "balance-testing-walkthrough.mp4"
SCENES_JSON = ROOT / "scenes.json"

FFMPEG = ROOT / "node_modules/@ffmpeg-installer/darwin-arm64/ffmpeg"
FFPROBE = ROOT / "node_modules/@ffprobe-installer/darwin-arm64/ffprobe"
VOICE = "en-US-BrianNeural"
RATE = "-8%"
VOLUME = "+4%"

W, H = 1920, 1080
FPS = 30


def run(cmd: list[str], **kwargs) -> None:
    if str(FFMPEG) in cmd[0] if cmd else "":
        cmd = [cmd[0], "-loglevel", "error", *cmd[1:]]
    subprocess.run(cmd, check=True, **kwargs)


def probe_duration(path: Path) -> float:
    out = subprocess.check_output(
        [
            str(FFPROBE),
            "-v",
            "error",
            "-show_entries",
            "format=duration",
            "-of",
            "default=noprint_wrappers=1:nokey=1",
            str(path),
        ],
        text=True,
    )
    return float(out.strip())


def make_title_slides() -> None:
    try:
        from PIL import Image, ImageDraw, ImageFont
    except ImportError:
        subprocess.run([sys.executable, "-m", "pip", "install", "pillow", "-q"], check=True)
        from PIL import Image, ImageDraw, ImageFont

    def slide(title: str, subtitle: str, filename: str, accent: str = "#1a5e95") -> None:
        img = Image.new("RGB", (W, H), "#0f172a")
        draw = ImageDraw.Draw(img)
        draw.rectangle([0, 0, W, 12], fill=accent)
        draw.rectangle([0, H - 12, W, H], fill=accent)
        try:
            font_l = ImageFont.truetype("/System/Library/Fonts/Supplemental/Arial Bold.ttf", 72)
            font_s = ImageFont.truetype("/System/Library/Fonts/Supplemental/Arial.ttf", 38)
        except OSError:
            font_l = ImageFont.load_default()
            font_s = ImageFont.load_default()
        draw.text((W // 2, H // 2 - 60), title, fill="#ffffff", font=font_l, anchor="mm")
        draw.text((W // 2, H // 2 + 50), subtitle, fill="#94a3b8", font=font_s, anchor="mm")
        img.save(ASSETS / filename)

    slide(
        "Balance Testing",
        "Complete administrator walkthrough · Parempi tasapaino",
        "title-intro.png",
    )
    slide(
        "You are ready",
        "Full guide: mdhemalakanda.github.io/balance-testing",
        "title-outro.png",
        "#059669",
    )


def generate_audio(scene: dict) -> Path:
    scene_id = scene["id"]
    mp3 = AUDIO / f"{scene_id}.mp3"
    if mp3.exists():
        mp3.unlink()
    run(
        [
            "edge-tts",
            "--voice",
            VOICE,
            f"--rate={RATE}",
            f"--volume={VOLUME}",
            "--text",
            scene["text"],
            "--write-media",
            str(mp3),
        ]
    )
    return mp3


def build_scene_video(scene: dict, index: int) -> Path:
    scene_id = scene["id"]
    audio = generate_audio(scene)
    duration = probe_duration(audio)
    out = SCENES_DIR / f"{index:02d}-{scene_id}.mp4"

    images = [ASSETS / name for name in scene["images"]]
    for img in images:
        if not img.exists():
            raise FileNotFoundError(f"Missing asset: {img}")

    if len(images) == 1:
        img = images[0]
        frames = max(int(duration * FPS), 1)
        vf = (
            f"scale={W}:{H}:force_original_aspect_ratio=decrease,"
            f"pad={W}:{H}:(ow-iw)/2:(oh-ih)/2:color=0x0f172a,"
            f"zoompan=z='min(zoom+0.0004,1.06)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':"
            f"d={frames}:s={W}x{H}:fps={FPS},format=yuv420p"
        )
        run(
            [
                str(FFMPEG),
                "-y",
                "-loop",
                "1",
                "-i",
                str(img),
                "-i",
                str(audio),
                "-vf",
                vf,
                "-c:v",
                "libx264",
                "-preset",
                "medium",
                "-crf",
                "20",
                "-c:a",
                "aac",
                "-b:a",
                "192k",
                "-shortest",
                "-t",
                f"{duration:.3f}",
                str(out),
            ]
        )
    else:
        raise ValueError(f"Scene {scene_id} has {len(images)} images; use exactly one for sync.")

    actual = probe_duration(out)
    drift = abs(actual - duration)
    if drift > 0.15:
        print(f"  warn: {scene_id} audio {duration:.2f}s vs video {actual:.2f}s")
    print(f"  scene {scene_id}: {actual:.1f}s")
    return out


def concat_all(parts: list[Path]) -> None:
    lst = ROOT / "concat-all.txt"
    lst.write_text("".join(f"file '{p.resolve()}'\n" for p in parts))
    run(
        [
            str(FFMPEG),
            "-y",
            "-f",
            "concat",
            "-safe",
            "0",
            "-i",
            str(lst),
            "-c",
            "copy",
            str(OUTPUT),
        ]
    )


def main() -> None:
    if not FFMPEG.exists():
        sys.exit("Run npm install @ffmpeg-installer/ffmpeg @ffprobe-installer/ffprobe in docs/video/")

    AUDIO.mkdir(exist_ok=True)
    SCENES_DIR.mkdir(exist_ok=True)
    make_title_slides()

    scenes = json.loads(SCENES_JSON.read_text())
    parts: list[Path] = []
    total = 0.0

    print(f"Building {len(scenes)} scenes with voice {VOICE}...")
    for i, scene in enumerate(scenes, 1):
        print(f"[{i}/{len(scenes)}] {scene['id']}")
        part = build_scene_video(scene, i)
        parts.append(part)
        total += probe_duration(part)

    concat_all(parts)
    final_dur = probe_duration(OUTPUT)
    print(f"\nDone: {OUTPUT}")
    print(f"Duration: {final_dur / 60:.1f} minutes ({final_dur:.0f}s)")
    print(f"Voice: {VOICE} (American English, conversational)")


if __name__ == "__main__":
    main()
