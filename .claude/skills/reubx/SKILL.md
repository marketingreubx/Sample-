---
name: reubx
description: Reubx all-in-one video editor. Installs itself (Python, ffmpeg, Chromium, fonts, speech model), cuts retakes and silences, cleans the audio, and edits talking-head videos in the six Reubx styles (a-list, a-story, a-skit Calm Cards; b-loud, b-editorial, b-showcase Motion Pop), with an automatic no-AI mode, a browser studio (fonts, colours, looks, click-to-cut transcript, notes) and a folder watcher. Use for /reubx, /style-*, /reubx-studio, or any request to edit, cut, caption or style a raw video.
---
# /reubx — everything in one skill

Be brief; the engine already contains the styles, cuts, audio clean-up and sound design. Do not re-study references.
Use no connectors except apify, and only for B-roll photos in style b-*.

**ENGINE** = the first that exists: `~/.claude/skills/reubx/engine` · `<repo>/editing-engine/reubx`.
**L** (launcher) = `ENGINE/reubx` on macOS / Linux / Git-Bash, `ENGINE\reubx.cmd` on Windows.
The first call of L installs everything automatically (uv -> private Python 3.12, packages, ffmpeg via Homebrew / winget /
bundled build, Chromium, 16 fonts, speech model). It needs internet and takes a few minutes once. Run it in the background and wait.

| User says | Do |
|---|---|
| `/reubx install` | `L install` |
| `/reubx studio <folder>` (or `/reubx-studio <folder>`) | Studio below |
| `/reubx <style> <video>` (or `/style-<style> <video>`) | Edit one video below |
| `/reubx auto <style> <video or folder>` | Fastest, no AI tokens: `L auto "<video>" --work "<dir>/_reubx/<name>" --style <style> --out "<dir>/edited/<name>_<style>.mp4"` for each video |

Styles — one per reference reel (best match), plus the generic six:
| style | edits like |
|---|---|
| `kilgore-list` | Kilgore "15 ways" feature list: wide calm shot, one-word captions, a UI card per item |
| `kilgore-story` | Kilgore "say it out loud": handwritten story lines, picture-in-picture, typed end card |
| `kilgore-community` | Kilgore "build the room": big yellow numbers, comment scatter, reaction photo, 🤯 sticker |
| `kilgore-skit` | Kilgore laundry skit: tight hook, fast cuts + zooms, handwritten steps, chat bubble punchline |
| `kilgore-flip` | Kilgore "just kidding": provocative hook flipped, feature cards, handwritten asides |
| `gallo-promo` | Anthony Gallo SaaS promo: light lavender product cards, gradient headlines |
| `edita-lawyer` | edita "home paid off": italic keyword stacks, glass bar, cursor-click pills, Yes/No card, polaroid |
| `edita-interview` | edita interview: lime serif keywords, archive B-roll, retro warning windows, quote card |
| `edita-blueprint` | edita blueprint: showcase frame, blue/purple text cards |
| `edita-podcast` | edita podcast: warm serif keywords, rapid B-roll montage, quote card |
Generic: `a-list` `a-story` `a-skit` (Calm Cards) · `b-loud` `b-editorial` `b-showcase` (Motion Pop). Unknown → ask once, suggest the closest preset.
Each preset's fonts, colours, sound and zoom are built in; its "what to include" guide is in `ENGINE/PLANS.md` (Reference presets).

## Edit one video
1. `W="<video folder>/_reubx/<video name>"`; `L prep "<video>" --work "$W"`; read only the printed transcript + retake hints.
2. Write `$W/plan.json` in one pass per `ENGINE/PLANS.md` (read it once): `fix` names/brand words, `remove` retakes/filler,
   then the style's elements. Optional `look` (fonts/colours/caption size/music/sfx/zoom) if the user asked for one.
3. Style b-*: B-roll for each `broll` entry if apify is available (one parallel batch of `memo23/pexels-scraper` searches, one
   `tiger19935/video-frame-extractor` run with `timestamps:["0"]`, `format:"webp"`, then save each frame to `$W/broll/<img>`);
   if apify is not available, skip `broll` or use images the user put in `$W/broll/`.
4. `L render "<video>" --work "$W" --style <style> --out "<video folder>/edited/<name>_<style>.mp4"` (background; 5-10 min).
5. Read the QA JSON; look at `$W/contact_sheet.png` once: every text readable, nothing overlapping, face never covered.
   Fix `plan.json` and re-render if needed (cached steps make it faster). Tell the user the output path in 2-3 lines.
**Corrections**: edit `plan.json` only and re-run step 4.

## Studio (browser app + folder watcher)
1. Start in the background: `L studio --folder "<folder>"` (add `--auto` to render new videos automatically). Tell the user to open
   **http://localhost:8765**. New videos in the folder get a transcript + automatic plan; outputs go to `<folder>/edited/`.
2. Watch loop until the user stops you: `L watch --folder "<folder>"` blocks and prints ONE JSON event:
   - `note` (user typed a correction): edit that job's `plan.json` minimally, then
     `curl -s -X POST localhost:8765/api/job/<job>/render -d '{}'` and `L watch --folder "<folder>" --done <note id>`; reply in one line.
   - `plan` (new video, automatic plan): upgrade it in one pass from `transcript.txt` (spellings, keyword wording, cards that fit
     the style), keep the user's `look`, save, trigger the render (curl above), then `L watch --folder "<folder>" --planned <job>`.
   - `idle`: run watch again.

Always on (engine): contrast scrims behind all text, captions always visible, no overlapping elements, reference typography
(italic lead / heavy italic main / italic tail), eased camera punch-ins, sound design matched to motion, -14 LUFS audio.
