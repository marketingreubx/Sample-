---
name: style-b-editorial
description: Style B Editorial — Motion Pop, toned down: elegant serif keyword lines, archive-look B-roll, quote/steps cards, calm camera moves. Use when the user types /style-b-editorial with a raw video.
---
# /style-b-editorial — Style B Editorial

Fixed procedure. Do NOT re-study references, read the playbook, or use any connector other than apify (B-roll only).
Keep replies short. The engine already encodes the style; your only creative job is `plan.json`.

1. Setup once per container: if `editing-engine/reubx/cache/music.wav` is missing, run `bash editing-engine/reubx/setup.sh`.
2. `WORK=<scratchpad>/edit_<video basename>`; run
   `python3 editing-engine/reubx/run.py prep "<video>" --work "$WORK"` and read ONLY its printed transcript + retake hints.
3. Write `$WORK/plan.json` in one pass, following the **Style B** section and the **b-editorial** density line of
   `editing-engine/reubx/PLANS.md` (read that file once). Fix names/brand words in `fix`, cut retakes in `remove`.
4. **B-roll** (only the `broll` entries in the plan, max 8): run all `memo23/pexels-scraper` searches in ONE parallel batch
   (`{"mode":"search","searchQuery":"<query>","mediaType":"photos","maxItems":3,"orientation":"portrait"}`), take the best match each,
   then ONE `tiger19935/video-frame-extractor` run on all image URLs (`mode:"timestamps"`, `timestamps:["0"]`, `format:"webp"`, `maxWidth:1080`),
   fetch each frame with ReadMcpResourceTool and copy it to `$WORK/broll/<img name>`.
5. `python3 editing-engine/reubx/run.py render "<video>" --work "$WORK" --style b-editorial` (6-10 min; run in background and wait).
6. Read the printed QA. If `pass` is false or anything looks off, look at `$WORK/contact_sheet.png` once, fix `plan.json`, re-render.
7. SendUserFile the final MP4 with a 3-line summary (length, what was cut, anything to double-check).

**Corrections** ("move X", "change the colour", "cut this line"): edit `plan.json` only and re-run step 5 — cached steps are reused.
