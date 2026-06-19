# Balance Testing — Video walkthrough

| | |
|---|---|
| **Live MP4** | https://mdhemalakanda.github.io/balance-testing/video/balance-testing-walkthrough.mp4 |
| **Duration** | ~3.2 minutes (34 beats, 192s) |
| **Voice** | `en-US-GuyNeural` — clear American English |
| **Sync proof** | `sync-manifest.json` — every beat verified ≤ 0.08s drift |

## v4 fixes (clean screenshots)

- **No plugin notices** — Elementor, Rank Math, SEO, and post-type banners hidden during capture
- **Copy To excercise** — dedicated `#submitdiv` crop on beats 9–10 so the button is clearly visible
- **Exercise assignments** — React admin UI rebuilt; captures show Approve, reorder, and **Display exercises**
- **Balance tests** — active test UI with video + rating scale (not the “round complete” message)
- **Harjoitukset waiting message** — separate capture for beat 32
- **No zoom, no overlays, no gaps** — same as v3

## Rebuild

```bash
# 1. Build admin React UI (required for Users Progress captures)
cd ../../frontend/test-user-management && npm install && npm run build

# 2. Capture clean screenshots (Local site + MySQL must be running)
cd ../../docs/video
npm install
node capture-screenshots.mjs

# 3. Build video
pip3 install edge-tts pillow
npm install @ffmpeg-installer/ffmpeg @ffprobe-installer/ffprobe
python3 build-video.py
```

Edit `beats.json` then re-run steps 2–3.
