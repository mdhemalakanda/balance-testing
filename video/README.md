# Balance Testing — Video walkthrough

| | |
|---|---|
| **Live MP4** | https://mdhemalakanda.github.io/balance-testing/video/balance-testing-walkthrough.mp4 |
| **Duration** | ~3 minutes (34 beats) |
| **Voice** | `en-US-GuyNeural` — clear American English |
| **Sync proof** | `sync-manifest.json` — every beat verified ≤ 0.08s drift |

## v3 fixes

- **No zoom** — full screenshot fits on screen, nothing cropped or animated
- **No on-screen labels or captions** — removed blue/black overlay boxes
- **No gaps** — zero padding between beats; image changes exactly when narration changes
- **One line = one screen** — 34 short beats, each with the matching screenshot
- **Automated sync check** — build fails if any beat audio/video length differs

## Rebuild

```bash
pip3 install edge-tts pillow
npm install @ffmpeg-installer/ffmpeg @ffprobe-installer/ffprobe
python3 build-video.py
```

Edit `beats.json` then re-run.
