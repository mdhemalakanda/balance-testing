# Balance Testing — Video walkthrough

| | |
|---|---|
| **Live MP4** | https://mdhemalakanda.github.io/balance-testing/video/balance-testing-walkthrough.mp4 |
| **Duration** | ~3.5 minutes (37 beats) |
| **Voice** | `en-US-GuyNeural` — clear American English |
| **Sync proof** | `sync-manifest.json` — every beat verified ≤ 0.08s drift |

## v5 — linked exercise workflow

- **Copy To excercise** — content only (no automatic link)
- **Linked exercise** sidebar — searchable picker on test edit screen
- **Bulk copy** — creates drafts; links set manually per test
- Clean screenshots (no plugin notices), beat-sync verified

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
