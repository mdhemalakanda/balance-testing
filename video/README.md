# Balance Testing — Video walkthrough

| | |
|---|---|
| **Live MP4** | https://mdhemalakanda.github.io/balance-testing/video/balance-testing-walkthrough.mp4 |
| **Duration** | ~3.7 minutes (38 beats) |
| **Voice** | `en-US-GuyNeural` — clear American English |
| **Sync proof** | `sync-manifest.json` — every beat verified ≤ 0.08s drift |

## v7 — full-page linked exercise shots

- **Full test edit screen** with sidebar Linked exercise box visible (not cropped metabox only)
- **Current link** card, **Change exercise** search + button, dropdown picker
- Narration matches on-screen labels exactly

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
