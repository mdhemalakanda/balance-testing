# Balance Testing — Video walkthrough

**Full administrator walkthrough (~6.5 minutes)**

| | |
|---|---|
| **File** | `balance-testing-walkthrough.mp4` |
| **Voice** | American English — `en-US-BrianNeural` (conversational, not overly formal) |
| **Sync** | Each scene’s video length matches its narration exactly (audio-driven timing) |
| **Live URL** | https://mdhemalakanda.github.io/balance-testing/balance-testing-walkthrough.mp4 |

## What the video covers

1. Introduction — what Balance Testing does  
2. Admin menu (Tests & Exercises)  
3. Create a balance test  
4. Link test → exercise (Copy To excercise)  
5. Verify links (Identifier & Linked test columns)  
6. Bulk copy for initial setup  
7. Users Progress & participants  
8. Participant login & account menu  
9. Taking tests & rating 1–6  
10. How ratings drive suggestions  
11. Approve exercise suggestions  
12. Reorder exercises  
13. Display exercises to participant  
14. Harjoitukset tab (participant view)  
15. Closing & documentation link  

## Rebuild the video

```bash
cd docs/video
npm install @ffmpeg-installer/ffmpeg @ffprobe-installer/ffprobe
pip3 install edge-tts pillow
python3 build-video.py
```

Edit narration in `scenes.json`, then re-run `build-video.py`.
