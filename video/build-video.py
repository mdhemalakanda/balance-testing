#!/usr/bin/env python3
"""Build walkthrough: one narration line = one full screenshot, zero overlay, exact audio sync."""

from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
ASSETS = ROOT / "assets"
FRAMES = ROOT / "frames"
AUDIO = ROOT / "audio"
SCENES_DIR = ROOT / "scenes"
OUTPUT = ROOT / "balance-testing-walkthrough.mp4"
MANIFEST = ROOT / "sync-manifest.json"
BEATS_JSON = ROOT / "beats.json"

FFMPEG = ROOT / "node_modules/@ffmpeg-installer/darwin-arm64/ffmpeg"
FFPROBE = ROOT / "node_modules/@ffprobe-installer/darwin-arm64/ffprobe"

VOICE = "en-US-GuyNeural"
RATE = "-10%"
PITCH = "+0Hz"
VOLUME = "+0%"

W, H = 1920, 1080
FPS = 30
MAX_DRIFT = 0.08


def run(cmd: list[str], **kwargs) -> None:
    exe = str(cmd[0]) if cmd else ""
    if exe in (str(FFMPEG), str(FFPROBE)):
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

    def slide(title: str, subtitle: str, filename: str) -> None:
        img = Image.new("RGB", (W, H), "#111827")
        draw = ImageDraw.Draw(img)
        try:
            font_l = ImageFont.truetype("/System/Library/Fonts/Supplemental/Arial Bold.ttf", 64)
            font_s = ImageFont.truetype("/System/Library/Fonts/Supplemental/Arial.ttf", 32)
        except OSError:
            font_l = ImageFont.load_default()
            font_s = ImageFont.load_default()
        draw.text((W // 2, H // 2 - 40), title, fill="#ffffff", font=font_l, anchor="mm")
        draw.text((W // 2, H // 2 + 40), subtitle, fill="#9ca3af", font=font_s, anchor="mm")
        img.save(ASSETS / filename)

    slide("Balance Testing", "Administrator walkthrough", "title-intro.png")
    slide("Balance Testing", "You are ready to go", "title-outro.png")


def prepare_frame(source_name: str) -> Path:
    """Fit full screenshot on canvas — no crop, no zoom."""
    try:
        from PIL import Image
    except ImportError:
        subprocess.run([sys.executable, "-m", "pip", "install", "pillow", "-q"], check=True)
        from PIL import Image

    FRAMES.mkdir(exist_ok=True)
    out = FRAMES / source_name
    src = ASSETS / source_name
    if not src.exists():
        raise FileNotFoundError(src)

    canvas = Image.new("RGB", (W, H), "#111827")
    img = Image.open(src).convert("RGB")
    img.thumbnail((W - 80, H - 80), Image.Resampling.LANCZOS)
    x = (W - img.width) // 2
    y = (H - img.height) // 2
    canvas.paste(img, (x, y))
    canvas.save(out, quality=95)
    return out


def generate_audio(beat: dict) -> Path:
    mp3 = AUDIO / f"{beat['id']}.mp3"
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


def build_beat_video(beat: dict, index: int, timeline: float) -> tuple[Path, dict]:
    audio = generate_audio(beat)
    audio_dur = probe_duration(audio)
    frame = prepare_frame(beat["image"])
    out = SCENES_DIR / f"{index:03d}-{beat['id']}.mp4"

    # Static frame for exact audio length — no zoom, no padding silence, no overlays
    run(
        [
            str(FFMPEG),
            "-y",
            "-loop",
            "1",
            "-framerate",
            str(FPS),
            "-t",
            f"{audio_dur:.4f}",
            "-i",
            str(frame),
            "-i",
            str(audio),
            "-vf",
            f"scale={W}:{H},format=yuv420p",
            "-c:v",
            "libx264",
            "-preset",
            "medium",
            "-crf",
            "20",
            "-r",
            str(FPS),
            "-c:a",
            "aac",
            "-b:a",
            "192k",
            "-ar",
            "48000",
            "-ac",
            "2",
            "-shortest",
            "-pix_fmt",
            "yuv420p",
            str(out),
        ]
    )

    video_dur = probe_duration(out)
    drift = abs(video_dur - audio_dur)
    entry = {
        "beat": beat["id"],
        "start_sec": round(timeline, 3),
        "end_sec": round(timeline + video_dur, 3),
        "audio_sec": round(audio_dur, 3),
        "video_sec": round(video_dur, 3),
        "drift_sec": round(drift, 4),
        "image": beat["image"],
        "text": beat["text"],
        "ok": drift <= MAX_DRIFT,
    }
    status = "OK" if entry["ok"] else "FAIL"
    print(f"  [{status}] beat {beat['id']} {video_dur:.2f}s · {beat['image']}")
    if not entry["ok"]:
        raise RuntimeError(f"Sync drift {drift:.3f}s on beat {beat['id']}")
    return out, entry


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
            "-ar",
            "48000",
            "-vsync",
            "cfr",
            "-r",
            str(FPS),
            "-movflags",
            "+faststart",
            str(OUTPUT),
        ]
    )


def verify_final(manifest: list[dict]) -> None:
    final_dur = probe_duration(OUTPUT)
    expected = manifest[-1]["end_sec"] if manifest else 0
    if abs(final_dur - expected) > 0.5:
        raise RuntimeError(f"Final duration mismatch: file={final_dur:.2f}s expected={expected:.2f}s")
    failed = [m for m in manifest if not m["ok"]]
    if failed:
        raise RuntimeError(f"{len(failed)} beats failed sync check")
    MANIFEST.write_text(json.dumps(manifest, indent=2))
    print(f"Sync check passed: {len(manifest)} beats, total {final_dur:.1f}s")


def main() -> None:
    if not FFMPEG.exists():
        sys.exit("Run: npm install @ffmpeg-installer/ffmpeg @ffprobe-installer/ffprobe")

    for d in (AUDIO, SCENES_DIR, FRAMES):
        d.mkdir(exist_ok=True)

    make_title_slides()
    beats = json.loads(BEATS_JSON.read_text())
    parts: list[Path] = []
    manifest: list[dict] = []
    timeline = 0.0

    print(f"Building {len(beats)} beats · {VOICE} · no zoom · no overlays · no gaps")
    for i, beat in enumerate(beats, 1):
        print(f"[{i}/{len(beats)}]")
        part, entry = build_beat_video(beat, i, timeline)
        parts.append(part)
        manifest.append(entry)
        timeline = entry["end_sec"]

    concat_all(parts)
    verify_final(manifest)
    print(f"\nDone: {OUTPUT}")
    print(f"Manifest: {MANIFEST}")


if __name__ == "__main__":
    main()
