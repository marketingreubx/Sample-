---
name: reubx-studio
description: Reubx Studio — local video-editing app. Watches a folder of raw videos, auto-edits every new one in Reubx Style A/B, and opens a browser editor (fonts, colours, 5 looks, sound, zooms, click-to-cut transcript, notes). Claude watches the notes and applies corrections. Use when the user types /reubx-studio or asks to watch a video folder / open the editing studio.
---
# /reubx-studio <video folder>

Fixed procedure. Be brief. Do not re-study references; the engine already contains the styles.
Use no connectors except apify (only when a note asks for B-roll). ENGINE = the `engine/` folder next to this file
if it exists, otherwise `editing-engine/reubx/` in this repo.

## Start (once per session)
1. If `ENGINE/cache/music.wav` is missing: `bash ENGINE/setup.sh` (installs Python packages, Chromium, fonts, the speech model; needs ffmpeg — tell the user the one-line install command it prints if ffmpeg is missing).
2. Start the app in the background: `python3 ENGINE/studio/server.py --folder "<folder>"` and tell the user to open **http://localhost:8765**.
   New videos dropped in the folder are transcribed and get an automatic plan; "auto-edit new videos" in the app renders them straight away. Finished files go to `<folder>/edited/`.

## Watch loop (repeat until the user stops you)
Run `python3 ENGINE/studio/watch.py --folder "<folder>"` (blocks until there is work, prints ONE JSON event). Then:
- **`note`** — the user typed a correction in the app. Read the note and `plan.json` (and `transcript.txt` only if the note needs word numbers).
  Edit `plan.json` minimally (schema: `ENGINE/PLANS.md`; `look` keys: font_cap, font_main, font_accent, font_lead, font_hand, text, accent_text, cap_size, grad, music, sfx, zoom).
  Then `curl -s -X POST localhost:8765/api/job/<job>/render -d '{}'` and `python3 ENGINE/studio/watch.py --folder "<folder>" --done <note id>`.
  Reply in one line what you changed.
- **`plan`** — a new video has an automatic plan. Upgrade it in ONE pass from `transcript.txt`: fix names/brand spellings (`fix`), sharpen keyword wording,
  add cards/pills that fit the style (PLANS.md density line). Keep the user's settings already in `look`. Save, trigger the render (curl above),
  then `watch.py --planned <job>`.
- **`idle`** — run watch.py again.

Text rules that are always on (engine handles them): contrast scrims behind all text, captions always visible, no overlapping elements,
reference typography (italic lead / heavy italic main / italic tail), no 3D tilts.
