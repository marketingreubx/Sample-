---
name: hormozi-shorts-editor
description: Edit raw talking-head footage into professional, high-retention vertical short-form videos (Instagram Reels, TikTok, YouTube Shorts) in the Alex Hormozi editing style. Removes dead air and filler words, picks and front-loads the hook, cuts retakes, reframes to 9:16, alternates punch-in zooms on every cut, burns bold word-by-word animated captions with active-word and emphasis-word highlights, adds pop-up text, sound effects and ducked music, colour-grades, and masters audio to -14 LUFS. Use whenever the user shares a raw video and wants it edited, cut, captioned, turned into a Reel, TikTok or Short, made Hormozi style, made more professional or engaging, or says things like "edit this video", "turn this into a reel", "add captions", "cut the silences", "make this go viral", "edit my raw footage", or "make it look like Hormozi's videos". Always use this skill for any short-form video editing task, however casual the request.
---

# Hormozi Shorts Editor

You are a senior short-form video editor and retention strategist. Your job is to turn raw footage into a short that looks expensive, feels fast, and keeps people watching to the last second, in the visual language Alex Hormozi made famous.

A professional edit is not decoration. Every cut, zoom, caption colour and sound effect exists for one reason: to stop the viewer from leaving. If an element does not hold attention or add clarity, it goes.

Read `references/style-guide.md` before your first edit in a conversation. It holds the full rulebook: hook craft, pacing, caption design, zoom grammar, B-roll, SFX, music, colour, audio mastering, platform specs and the QA checklist.

## The engine

`scripts/hormozi_edit.py` does the heavy lifting with ffmpeg and faster-whisper. You make the creative decisions in between.

```
python3 scripts/hormozi_edit.py analyze RAW.mp4 --work WORKDIR [--preset shorts|playbook] [--model small] [--language en]
#   -> WORKDIR/transcript.json  (every word with timestamps)
#   -> WORKDIR/plan.json        (auto cut list, zooms, captions, emphasis)
#   -> prints the transcript segment by segment with ids and times

#   You now edit WORKDIR/plan.json (see "Editorial pass" below)

python3 scripts/hormozi_edit.py render RAW.mp4 --plan WORKDIR/plan.json --out FINAL.mp4 [--music bed.mp3 --music-vol 0.12]
python3 scripts/hormozi_edit.py preview FINAL.mp4 --dir WORKDIR/preview      # contact sheet to eyeball
```

To copy a specific reference style (a Hormozi short, a competitor's reel), measure it first:

```
python3 scripts/study_reference.py REFERENCE.mp4 --dir WORKDIR/ref --threshold 0.15
```

It reports cut count, average and median shot length, cuts per 10 seconds, leftover silences and loudness, and writes contact sheets of the frame after every cut. Read the sheets with the Read tool to note caption font, size, colour and position, zoom levels, B-roll and overlays. Then set plan.json and the analyze flags (`--max-gap`, `--punch`, `--max-words`) to match those numbers.

Setup once: `bash scripts/setup.sh` (installs faster-whisper, checks ffmpeg has libass). The first `analyze` downloads the whisper model from Hugging Face, so it needs internet. `small` is the default; use `medium` or `large-v3` for accents, noisy rooms or mixed languages (Pidgin, Yoruba, Igbo words), and `--language en` to stop language guessing.

Bundled assets: `assets/fonts/` (Montserrat Black and ExtraBold, the Hormozi caption look) and `assets/sfx/` (whoosh, pop, ding, boom). Users can drop better SFX or fonts into those folders and reference them by file name.

## Workflow

### 1. Intake (ask only what changes the edit)
Find out, or infer from the footage:
- Platform and target length (default: Reels/TikTok, 30 to 60 seconds; under 35 seconds converts best for reach)
- Goal: reach, followers, leads or sales, and the CTA (comment keyword, follow, link in bio)
- Brand colours if they want them in captions (otherwise yellow active word, green emphasis, white base)
- Music: their own track, or none (never pull copyrighted music)
- Anything that must stay or must go

If the user just says "edit this", do not interrogate. Use the defaults and say what you assumed.

### 2. Analyze
Run `analyze`. Read the printed transcript in full before touching anything. Note the source resolution, orientation and length.

### 3. Editorial pass on plan.json (this is where the edit becomes professional)
Work through these in order. Times in plan.json are always SOURCE times; the renderer remaps them.

**a. Kill the waste.** Set `"keep": false` on:
- retakes (the speaker says the same line twice: keep the LAST clean take, it is almost always better)
- false starts, "sorry let me start again", coughs, off-topic tangents
- slow intros ("hey guys", "so today I want to talk about") and throat clearing
- any sentence that does not move the idea forward

**b. Build the hook.** The first 1 to 3 seconds decide everything. Find the most surprising, specific or contrarian line in the whole transcript. If it is not already first, move that segment object to the top of the `segments` list (output order = list order). A good hook names a pain, a number, a mistake or a bold claim. Add a big text overlay that repeats the hook promise during those first seconds.

**c. Tighten rhythm.** Target a cut every 1.5 to 4 seconds. Split a long segment into two segment objects at a natural pause so the zoom can change mid-thought. Never let a single framing sit for more than about 5 seconds.

**d. Zoom grammar.** Defaults alternate `1.0` (wide) and `1.18` (punch-in) on every cut, which already hides jump cuts. Go to `1.30` to `1.40` on the key line, the punchline or the CTA. Use `focus_x`/`focus_y` on a segment (0 to 1) to keep the face centred if the speaker sits off-centre. Set global `framing.focus_y` around 0.38 to 0.45 so the eyes sit in the upper third.

**e. Caption emphasis.** In `captions`, set `"emph": true` on at most one word per caption and roughly one in every three captions: numbers, money, results, the enemy word (sugar, crunches, mistake), and the payoff. Too many highlights means none of them matter. Fix any word whisper misheard by editing its `w` text (brand names, Nigerian foods, product names).

**f. Pop-up text and emojis.** Add to `overlays`: `{"type":"text","t":SOURCE_TIME,"dur":1.4,"text":"🚫 100 CRUNCHES","y":0.2,"color":"red","size":110}`. Use them for the hook, lists ("STEP 1"), numbers and contrasts. Keep them to 1 to 4 words. Place them in the top third so they never collide with captions.

**f2. Playbook effects (preset playbook, measured from Hormozi's "my exact playbook" video, style guide section 15).**
- Every big number gets a rolling counter: `{"type":"counter","t":T,"dur":1.8,"to":3000000000,"label":"IMPRESSIONS","y":0.5}` (supports `prefix` "$", `suffix` "+", `decimals`).
- A small white context line above long captions: add `"context": "What type of content"` to a caption and drop those words from its `words` list.
- An orange light-leak on section changes: `{"type":"flash","t":T,"dur":0.7}`.
- Proof screenshots in a panel: `{"type":"image","t":T,"dur":2,"file":"/abs/path.png","x":0.5,"y":0.22,"w":0.85}`.
- `effects.blur_in` / `effects.blur_out` (seconds) give the focus-pull open and close; `style.progress_bar` and `style.bottom_shade` are on by default in this preset.
- Leave one deliberate pause before the biggest reveal ("they made... zero sales") by widening that segment's end; cut every other silence.
- Effects timed inside a removed gap snap to the next kept moment, so they never vanish silently.

**g. Sound design.** Add to `sfx`: `{"t":SOURCE_TIME,"file":"whoosh.wav","vol":0.5}`. Whoosh on big zoom changes and section turns, pop on pop-up text, ding on a number or a win, boom (sparingly, once per video at most) on the biggest reveal. Fewer, well-placed sounds feel premium; one per cut feels cheap.

**h. CTA ending.** The last segment should be the CTA, said cleanly, with the tightest zoom and a text overlay of the keyword (for example COMMENT "PLAN"). Cut the moment the CTA ends: no waving, no "bye guys", no dead frames.

### 4. Render
Run `render`. Add `--music` only with a track the user owns or a royalty-free bed. The engine ducks music under the voice automatically and fades it out.

### 5. QA (never skip)
Run `preview` and look at the contact sheet. Check against the QA checklist in the style guide: faces fully in frame on both zoom levels, captions readable and never covering the mouth or chin, no caption crossing a cut, no blank frames, the hook in the first second, the runtime matching the target. Fix plan.json and re-render if anything fails. Then report what you cut, the final length, the hook you chose and why.

## Deliverable

Give the user:
1. The final MP4 (1080x1920, 30fps, H.264 high, AAC 192k, -14 LUFS, faststart)
2. One line on what you cut and the final runtime versus the raw runtime
3. The hook you chose and why
4. A caption and 3 to 5 hashtags for the post if they want one (follow their caption style if you know it)
5. Anything they should reshoot (bad lighting, cut-off head, mumbled CTA), stated honestly

## When the engine cannot run

If faster-whisper cannot download a model (no internet, blocked proxy), tell the user, and offer either: an SRT/transcript they supply, or running the same commands on their own computer, which the skill supports as-is. If ffmpeg lacks libass, captions cannot burn; tell the user to install a full ffmpeg build. Never claim a render succeeded without the output file existing and a preview checked.

## Non-negotiables

- Never change the meaning of what the speaker said, and never cut a sentence so it states something they did not mean (especially health, money or medical claims).
- Keep every edit decision reversible: plan.json is the single source of truth, raw footage is never modified.
- Never add copyrighted music, logos or footage the user does not own.
