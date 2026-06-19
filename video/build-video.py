#!/usr/bin/env python3
"""Build walkthrough video: one short narration beat = one screenshot = perfect sync."""

from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
ASSETS = ROOT / "assets"
AUDIO = ROOT / "audio"
SCENES_DIR = ROOT / "scenes"
OUTPUT = ROOT / "balance-testing-walkthrough.mp4"
BEATS_JSON = ROOT / "beats.json"

FFMPEG = ROOT / "node_modules/@ffmpeg-installer/darwin-arm64/ffmpeg"
FFPROBE = ROOT / "node_modules/@ffprobe-installer/darwin-arm64/ffprobe"

# Warm, natural American male — slower and clearer
VOICE = "en-US-AndrewNeural"
RATE = "-14%"
PITCH = "-2Hz"
VOLUME = "+2%"
PAUSE_SEC = 0.35

W, H = 1920, 1080
FPS = 30


def run(cmd: list[str], **kwargs) -> None:
    if cmd and str(FFMPEG) in str(cmd[0]):
        cmd = [cmd[0], "-loglevel", "error", *cmd[1:]]
    elif cmd and str(FFPROBE) in str(cmd[0]):
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


def escape_drawtext(text: str) -> str:
    text = text.replace("\\", "\\\\")
    text = text.replace("'", "'\\''")
    text = text.replace(":", "\\:")
    text = text.replace("%", "\\%")
    return text


def make_title_slides() -> None:
    try:
        from PIL import Image, ImageDraw, ImageFont
    except ImportError:
        subprocess.run([sys.executable, "-m", "pip", "install", "pillow", "-q"], check=True)
        from PIL import Image, ImageDraw, ImageFont

    def slide(title: str, subtitle: str, filename: str, accent: str = "#1a5e95") -> None:
        img = Image.new("RGB", (W, H), "#0f172a")
        draw = ImageDraw.Draw(img)
        draw.rectangle([0, 0, W, 8], fill=accent)
        draw.rectangle([0, H - 8, W, H], fill=accent)
        try:
            font_l = ImageFont.truetype("/System/Library/Fonts/Supplemental/Arial Bold.ttf", 68)
            font_s = ImageFont.truetype("/System/Library/Fonts/Supplemental/Arial.ttf", 34)
        except OSError:
            font_l = ImageFont.load_default()
            font_s = ImageFont.load_default()
        draw.text((W // 2, H // 2 - 50), title, fill="#ffffff", font=font_l, anchor="mm")
        draw.text((W // 2, H // 2 + 45), subtitle, fill="#94a3b8", font=font_s, anchor="mm")
        img.save(ASSETS / filename)

    slide("Balance Testing", "Step-by-step administrator guide", "title-intro.png")
    slide("You're all set", "Written guide on GitHub Pages", "title-outro.png", "#059669")


def generate_audio(beat: dict) -> Path:
    beat_id = beat["id"]
    mp3 = AUDIO / f"{beat_id}.mp3"
    if mp3.exists():
        mp3.unlink()
    run(
        [
            "edge-tts",
            "--voice",
            VOICE,
            f"--rate={RATE}",
            f"--pitch={PITCH}",
            f"--volume={VOLUME}",
            "--text",
            beat["text"],
            "--write-media",
            str(mp3),
        ]
    )
    return mp3


def build_beat_video(beat: dict, index: int) -> Path:
    beat_id = beat["id"]
    audio = generate_audio(beat)
    duration = probe_duration(audio) + PAUSE_SEC
    out = SCENES_DIR / f"{index:03d}-{beat_id}.mp4"

    img = ASSETS / beat["image"]
    if not img.exists():
        raise FileNotFoundError(f"Missing asset: {img}")

    label = escape_drawtext(beat.get("label", ""))
    caption = escape_drawtext(beat["text"][:120])

    vf = (
        f"scale={W}:{H}:force_original_aspect_ratio=decrease,"
        f"pad={W}:{H}:(ow-iw)/2:(oh-ih)/2:color=0x0f172a,"
        f"drawtext=text='{label}':fontfile=/System/Library/Fonts/Supplemental/Arial Bold.ttf:"
        f"fontcolor=0xFFFFFF:fontsize=42:box=1:boxcolor=0x1a5e95@0.92:boxborderw=16:"
        f"x=48:y=48,"
        f"drawtext=text='{caption}':fontfile=/System/Library/Fonts/Supplemental/Arial.ttf:"
        f"fontcolor=0xE2E8F0:fontsize=28:box=1:boxcolor=0x000000@0.55:boxborderw=14:"
        f"x=(w-text_w)/2:y=h-100,"
        f"format=yuv420p"
    )

    run(
        [
            str(FFMPEG),
            "-y",
            "-loop",
            "1",
            "-framerate",
            str(FPS),
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
            "19",
            "-r",
            str(FPS),
            "-c:a",
            "aac",
            "-b:a",
            "192k",
            "-af",
            f"apad=pad_dur={PAUSE_SEC}",
            "-t",
            f"{duration:.3f}",
            "-pix_fmt",
            "yuv420p",
            "-movflags",
            "+faststart",
            str(out),
        ]
    )

    actual = probe_duration(out)
    audio_len = probe_duration(audio)
    print(f"  beat {beat_id} [{beat.get('label','')}]: {actual:.2f}s (voice {audio_len:.2f}s + pause)")
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
            "-movflags",
            "+faststart",
            str(OUTPUT),
        ]
    )


def main() -> None:
    if not FFMPEG.exists():
        sys.exit("Run: npm install @ffmpeg-installer/ffmpeg @ffprobe-installer/ffprobe")

    AUDIO.mkdir(exist_ok=True)
    SCENES_DIR.mkdir(exist_ok=True)
    make_title_slides()

    beats = json.loads(BEATS_JSON.read_text())
    parts: list[Path] = []

    print(f"Building {len(beats)} synced beats · voice {VOICE} · rate {RATE}")
    for i, beat in enumerate(beats, 1):
        print(f"[{i}/{len(beats)}]")
        parts.append(build_beat_video(beat, i))

    concat_all(parts)
    final_dur = probe_duration(OUTPUT)
    print(f"\nDone: {OUTPUT}")
    print(f"Duration: {final_dur / 60:.1f} min ({final_dur:.0f}s)")
    print(f"Beats: {len(beats)} (one image + one narration line each)")


if __name__ == "__main__":
    main()
