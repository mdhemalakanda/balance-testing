# Balance Testing — Video walkthrough

**Administrator walkthrough (~4.5 minutes)**

| | |
|---|---|
| **File** | `balance-testing-walkthrough.mp4` |
| **Live URL** | https://mdhemalakanda.github.io/balance-testing/video/balance-testing-walkthrough.mp4 |
| **Voice** | `en-US-AndrewNeural` — slow, clear American English |
| **Sync method** | **32 beats** — each spoken line = one screenshot + on-screen label (no long mismatched sections) |

## What changed (v2)

- **Perfect sync:** One short sentence per screen. When the voice says “Click Copy To excercise”, that screenshot is on screen for that line only.
- **On-screen labels:** Blue title bar + caption at bottom match what is being said.
- **Clearer voice:** Slower pace (−14%), simpler sentences, warmer American tone.

## Coverage

Intro → admin menu → create test → link exercise → verify links → bulk copy → Users Progress → participant login → take tests → ratings → approve → reorder → display → Harjoitukset → summary.

## Rebuild

```bash
cd docs/video
npm install @ffmpeg-installer/ffmpeg @ffprobe-installer/ffprobe
pip3 install edge-tts pillow
python3 build-video.py
```

Edit lines in `beats.json` (one `text` + one `image` per beat), then re-run.
