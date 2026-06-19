# Balance Testing — Video walkthrough

| | |
|---|---|
| **Live MP4** | https://mdhemalakanda.github.io/balance-testing/video/balance-testing-walkthrough.mp4 |
| **Duration** | ~3.7 minutes (38 beats) |
| **Voice** | `en-US-GuyNeural` — clear American English |
| **Sync proof** | `sync-manifest.json` — every beat verified ≤ 0.08s drift |

## v6 — linked exercise UI (full metabox)

- **Current link** card — status, Edit exercise, Remove link
- **Change exercise** — search bar + Search button + dropdown picker
- **Browse all exercises** footer link

## Rebuild

```bash
cd ../../frontend/test-user-management && npm run build
cd ../../docs/video
npm install
node capture-screenshots.mjs
pip3 install edge-tts pillow
npm install @ffmpeg-installer/ffmpeg @ffprobe-installer/ffprobe
rm -rf audio scenes frames && python3 build-video.py
```

Edit `beats.json` then re-run capture + build.
