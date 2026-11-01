---
name: style-a-list
description: Style A List — Calm Cards for 'X ways' / feature-list talking-head reels: one-word captions, yellow serif accents, a white UI card for every list item, typed end card. Use when the user types /style-a-list with a raw video.
---
# /style-a-list — Style A List

Fixed procedure. Do NOT re-study references, read the playbook, or use any connector other than apify (B-roll only).
Keep replies short. The engine already encodes the style; your only creative job is `plan.json`.

1. Setup once per container: if `editing-engine/reubx/cache/music.wav` is missing, run `bash editing-engine/reubx/setup.sh`.
2. `WORK=<scratchpad>/edit_<video basename>`; run
   `python3 editing-engine/reubx/run.py prep "<video>" --work "$WORK"` and read ONLY its printed transcript + retake hints.
3. Write `$WORK/plan.json` in one pass, following the **Style A** section and the **a-list** density line of
   `editing-engine/reubx/PLANS.md` (read that file once). Fix names/brand words in `fix`, cut retakes in `remove`.
4. Style A uses no B-roll (photo cards may use images the user supplied; put them in `$WORK/broll/`).
5. `python3 editing-engine/reubx/run.py render "<video>" --work "$WORK" --style a-list` (6-10 min; run in background and wait).
6. Read the printed QA. If `pass` is false or anything looks off, look at `$WORK/contact_sheet.png` once, fix `plan.json`, re-render.
7. SendUserFile the final MP4 with a 3-line summary (length, what was cut, anything to double-check).

**Corrections** ("move X", "change the colour", "cut this line"): edit `plan.json` only and re-run step 5 — cached steps are reused.
