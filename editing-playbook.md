# Reubx Short-Form Editing Playbook

Only two registered styles. Everything taught before @itsmegkilgore (the Enrico / Ben Kimball / Jumoke
"Base style", the TikTok and IG links before it) has been **deleted on the owner's instruction**: never use it.

| Say this | Style | Studied from |
|---|---|---|
| **"Style A"**, or **"Calm Cards"** | Calm, premium talking-head with one-word captions and small floating UI cards | @itsmegkilgore ×5 (+ @_anthonygallo promo as a reference) |
| "Style A List" | A for feature lists / "X ways" reels | Kilgore *15 ways Claude edits my reels* (DewrbKMRjlE) |
| "Style A Story" | A for emotional / story / lesson reels | Kilgore *say it out loud* (Dee_frcPZhP), *build the room* (Dej25RgxvOD) |
| "Style A Skit" | A for jokes / demos with a punchline | Kilgore *laundry* (De15Uy2x_tU), *just kidding* (DezH_DVRfIL) |
| **"Style B"**, or **"Motion Pop"** | Dense motion-graphics edit: B-roll on every noun, big keyword blocks, glass UI, compositing | @edita.visuals ×4 |
| "Style B Loud" | B with bold coloured keyword blocks, flying objects, emoji chips | edita *home paid off* lawyer (DewlyZSNcme) |
| "Style B Editorial" | B toned down: serif/sans captions, archive B-roll, text cards | edita interview (DeSeu64RwaW), blueprint (De1uB_sJ8M8), herbalism podcast (DemWl0OtTd8) |
| "Style B Showcase" | B wrapped in the portfolio frame (raw clip vs edit, CTA under the card) | the frame around all 4 edita reels |

If the owner just says "edit this", ask A or B (one line). Default suggestion: A for personal-brand talking heads,
B for education with many nouns/concepts.

---

# House rules (apply to BOTH styles, from the owner's own feedback)

1. **Sound first.** The final file must have the full voice. Build audio in its **own pass** (never inside the big
   video filter graph: that dropped 18s of speech once), then mux with `-c:v copy`. Verify every delivery with a
   transcript-recall check against the kept words (≥97%), an audio packet-gap check, and loudness (-14 LUFS, TP ≤ -1).
2. **Clean the voice** (verified chain, recall unchanged): `highpass 80, lowpass 15k, volume to level, arnndn (sh.rnnn),
   afftdn nr=10 nf=-52, EQ -2.5 dB @300 Hz, +2.5 dB @3.4 kHz, high-shelf +1.5 dB @10 kHz, deesser, compressor 3:1,
   soft gate (range 0.12), limiter`. Music bed about 20 dB under the voice, side-chain ducked; SFX felt, not heard.
3. **Cuts:** keep everything she means to say, in her order. Remove only retakes, false starts, filler ("you know"),
   trailing unfinished lines. Cut inside silences or energy valleys (10 ms RMS envelope, noise floor +10 dB threshold),
   pad 0.26s before soft onsets (/m/, /h/), 0.18s after, shorten long pauses to about 0.4s. Re-transcribe the cut and
   compare with the expected words before going further.
4. **Never cover the face or mouth.** Captions sit below the chin (chest level); cards live above the head or to the
   sides. Detect the face on the finished frames, not the raw clip.
5. **Readable text:** Poppins (SemiBold/Bold) is the house sans; it must read on any background (soft dark shadow, or a
   thin outline when the background is busy). Accent words in a second font (Instrument Serif italic) or colour.
6. **Natural grade:** light lift only, skin true, never orange or over-saturated. Compare with the raw side by side.
7. **No progress bar** at the top. **Professional icons only** (Lucide SVG set), never text glyphs like ✓ ✕ ♥.
8. **Don't shrink or bury the speaker.** She stays big in frame; split screens keep her face large and tracked.

---

# Style A: "Calm Cards" (@itsmegkilgore)

The speaker barely moves and the camera barely cuts. The interest comes from **typography that changes every word**
and **small, realistic UI cards** that illustrate each spoken beat. It feels premium, personal and calm.

## A1. Camera, framing, cuts
- One locked-off **wide seated shot** (full body on the sofa, head in the top third, lots of room above). This wide
  frame is what makes room for cards above the head and words on the chest.
- Cuts are few (a hard cut every 3-8s at most, many stretches with none). Each cut **changes the framing**:
  wide → tighter crop (about 1.3-1.6x) for a key line ("but here's the thing", hands under chin) → back to wide.
- **Handheld selfie close-up** for the hook and the CTA (she leans into the lens, often standing/walking).
- A hard cut to a **different room / lighting** for a gag beat ("Just kidding." in a dark room, with a 😜 sticker).
- Grade: warm practical light as shot (chandelier, lamp). No teal/orange, no vignette.

## A2. Captions (the signature)
- **One word at a time** (sometimes 2-4), centred on the chest, about 45-55% down the frame, below the chin.
  Each word is on screen ~0.25-0.4s, so the text drives the pace.
- **Base word:** clean white sans, medium-bold, lowercase, no outline, very soft shadow (Kilgore uses a Helvetica/SF
  look; for Reubx use **Poppins SemiBold** per house rule 5).
- **Accent word:** serif italic in **butter yellow (#F3E37C-ish)**, slightly bigger: *loud*, *free*, *missing*,
  *thing*, *engine*, *dreamer*. About 1 word in 4, always the meaningful word. Mixed lines are common:
  "it's *free*", "but *here's* the thing.", "*ai* used the right way", "comment *engine*".
- **Numbers become big yellow serif**: "$27,000" (much larger than the word captions).
- **Handwritten marker lines** (pale yellow or white, e.g. Caveat/Gochi Hand style) for asides and sub-lines, small,
  slightly tilted, above the head or under the main word: "AI can never replace your brand!!",
  "wayyy more being given after launch ends!!", "just wait for the ending", "me one month ago".
- **A Story variant:** in emotional passages the caption becomes a **handwritten line of 3-6 words** at chest level,
  with the key phrase in yellow: "saying it out loud doesn't / **make it happen**", "**what's the dream** / that
  you're keeping / hidden".

## A3. Hook (0-3s)
- A stacked header on the chest: small handwritten yellow line + big white sans line + handwritten sub-line:
  `15 ways` / `Claude edits my reels,` / `and not my creative voice.` (sometimes with a small UI card above).
- Or a curiosity line: "send this to a *dreamer*" + handwritten "it's time to say it out loud" + "just wait for the ending".
- Or a provocative line said to camera then flipped ("If you use AI you'll look generic." → "Just kidding.").

## A4. Floating UI cards
- White, rounded (radius ~18px at 1080w), soft shadow, small (25-45% of frame width), realistic UI mock-ups in the
  creator's brand colours. They **pop in** above the head or at the top corners, stay 1.5-3s, then leave.
  Seen: a "Rough cut" timeline card with red waveform clips; three phone thumbnails "my secret to editing reels";
  a "BRAND KIT" card with colour dots; "Cover a face" with two face chips + "Blur" button beside a blurred phone video;
  an "Autopilot" pill with a green "First reel · finished" chip; tilted phone thumbnails for carousel/story outputs.
- **Social proof scatter:** 6-8 tilted comment screenshots (names blacked out) scattered around the torso when she
  talks about community; a tilted rounded **reaction meme/photo** in the top-left corner on a surprising line.
- **Picture-in-picture:** an old clip in a rounded phone frame bottom-left with a handwritten label + arrow
  ("me one month ago"); she watches it on her phone in the main shot.
- **Split screen (Skit):** top/bottom halves (her / the other person) with an iMessage-style bubble.
- Emoji stickers as accents only (🤯 ×3 on "changed my family's life", 😜 on the gag).

## A5. Motion, sound, ending
- Words and cards pop (85% → 104% → 100% in ~0.15s) or fade up with a slight blur; nothing slides across the screen.
- No whooshes between shots; small UI sounds only (pop, click, typing). Light rhythmic electronic music bed, low.
- **End card:** solid black, serif text typed out with a blinking caret ("Do|ors close tonight"), one handwritten
  line under it. CTA keyword said and shown ("comment *engine*").
- Length: list/launch reels 30-65s; story reels can run 100s+ when the story carries it.

## A6. Reference variant: Anthony Gallo 16:9 promo
Light lavender/white gradients, clean motion-graphic cards (timeline, "Edit!" prompt bar, flowchart, leaderboard,
refund badge), big sans headlines with gradient text on one phrase. Use only for landscape promos.

## A7. Lessons from the first Style A build (home-workout reel)
Reference scripts: `editing-engine/style-a-example/`.
- Render **all text in the HTML layer** (one-word captions, serif accents, handwritten lines, cards): fonts, shadows and the
  pop/blur animation then match exactly. ~2,160 frames of 1080x1920 took ~70s with 4 workers.
- Keep the caption **at one height per shot** (chin of that shot + 120px); per-word heights make the words jump.
  Words swap instantly (no fade-out) so two never overlap.
- Tight shots: put the face low (crop y = face centre - 0.56 x crop height) so there is room above the head for cards.
- Concat of per-shot clips must be **frame-exact** (`-frames:v` from rounded cumulative times) or the picture drifts
  against the audio (it drifted 0.5s once).
- Join mixed-font words with a margin, not `&nbsp;` (the space inherits the 16px body size).
- Lucide icons, no emoji, no whooshes. AAC can add ~1.5 dB of peak: limit the WAV to -2.4 dBFS before encoding.

---

# Style B: "Motion Pop" (@edita.visuals)

A new visual every 1-4s: speaker → keyword block → B-roll → UI graphic → speaker. Code-generated motion graphics
(their caption: "every movement, every transition, every sound — Claude"). Lengths 23-51s.

## B1. Showcase frame (only for portfolio/demo reels: "Style B Showcase")
Pale pearly canvas with a faint pastel shape; dark "model" pill at top centre; the edit in a rounded phone card
(~45% width) with soft shadow; a smaller card overlapping on the left showing the **raw clip in black-and-white**
("Raw footage") or a **line-art storyboard sketch** ("Storyboard"); a fixed lower-third:
`Follow & comment "CLAUDE" for the full guide` with the keyword in orange-red. For Reubx: raw vs finished esthetician
reel, CTA keyword under the card.

## B2. The edit itself (what goes on a client's reel, full screen)
- **Base shot stays static**; the graphics move. Slow push-in on text/graphic scenes.
- **Keyword blocks (Loud):** a small white lead-in line ("You throw", "you don't want", "so definitely") over a huge
  **bold italic condensed caps word in the accent colour** with a slight gradient/glow ("A PARTY", "A LAWSUIT",
  "AT LEAST", "PAID OFF"), placed at chest level over the speaker, sometimes partly behind her.
- **Captions (Editorial):** small white sans at chest level with one word switched to serif italic or bold
  ("and things *that* you", "the *tacit*", "TO THE *engineering job?*").
- **Glass UI pills:** frosted dark-blue/white pills with an icon: a progress slider ("Their Homes · $ Paying 88%"),
  a check chip ("I'M FINALLY DONE"), a checklist that ticks row by row with shield icons ("LIABILITY", "FIRE POLICY"),
  a "STRONGLY RECOMMEND" pill with fire emojis, emoji answer chips ("🙂 Yes or No?" / "😊 I Agree").
- **Interstitial cards:** a full-frame gradient card (blue/lavender) with a small face thumbnail and chips; white cards
  with blue list text; a dark purple card with a bullet list ("Content / Distribution / Sales"); black card with a
  serif quote ("*vibe coded*"). Text cards get a slow zoom.
- **B-roll on every noun / list item** (~1s each): stock shelves, pills, each herb in a list, a car POV, a control room;
  for Editorial, grainy **archive/vintage photos and retro computer windows**, logo tiles (Google, Replit).
- **Compositing on the person:** papers flying around his head on "lawsuit", a gavel scene, a polaroid "NOT INSURED"
  card landing on the chest, scan brackets on a house photo.
- **Split screen** of speaker and listener reacting (podcast/interview).
- **Accent colour follows the footage**: blue for the navy-suit lawyer, purple for lavender, warm terracotta for the
  podcast. Pick it from the wardrobe or set.

- **Keyword-block contrast (owner feedback):** never put light-blue gradient text on a similar-coloured background (navy shirt). Use **white heavy italic text on a solid accent box** (e.g. #2F6BFF, radius 0.16em, padding .13em .3em, soft dark shadow), normal letter-spacing (no negative tracking), 12px between stacked lines, ~35px between the lead-in line and the box. Long names go on two lines rather than shrinking. Pick the accent to contrast with the wardrobe, not to match it.

## B3. Motion, transitions, colour, sound
- Elements enter with spring overshoot + blur-to-sharp in 0.3-0.5s and leave fast. Scene swaps by white/lavender card
  wipe, slide from the right, or light sweep; the cut lands under the wipe's midpoint. Whoosh 0.2s before each swap,
  pop on each card, music swells slightly mid-reel.
- Clean, polished, slightly richer colour with soft bloom, skin natural. Archive B-roll can be desaturated/grainy.

## B4. When to use B
Explainers, podcasts/interviews, education with many concepts, and Reubx portfolio reels that sell the $3k service.
It needs a lot of B-roll and graphics; plan a visual for every key noun before building.

## B5. How to build it in this container (verified)
- Motion graphics: HTML/CSS + Web Animations API, rendered per frame with Playwright + the pre-installed Chromium
  (`/opt/pw-browsers/chromium`, `--no-sandbox`), `document.getAnimations().forEach(a=>{a.pause();a.currentTime=t})`,
  `screenshot(omit_background=True)`. Render only frames where something is visible; hard-link a blank PNG for the rest;
  4 parallel workers (~1,150 active 1080x1920 frames in ~40s). Keep `filter` on an inner wrapper.
  `backdrop-filter` does not blur on a transparent capture: use near-solid fills for glass.
- Split screens: pre-render each window as an opaque clip (never `split` a long stream into windowed branches: OOM),
  crop the speaker around her tracked face, captions below the chin.
- Transitions: white card wipe / light sweep timed so the cut sits under the midpoint; also use one to hide any jump
  in posture (e.g. sit-to-stand).
- Depth text behind the speaker: `rembg u2net_human_seg` works, but objects touching the head merge into the mask.
- Icons: Lucide SVGs (`npm pack lucide-static@0.460.0`), recoloured inline.
- Reference scripts: `editing-engine/style-b-example/`.

---

# The premium bar (owner feedback after the first Style B build)

The owner wants the edit to match the references **exactly**: same kind of camera moves, effects, motion and sound design.
What was wrong in the first Style B build, and must not happen again:
- **No real camera animation.** References use keyframed zooms: smooth eased punch-ins on key words, slow push-ins, zoom-out
  reveals, zoom/whip transitions with motion blur. Hard crop switches are not enough.
- **Motion graphics looked AI-generated:** everything popped in the same way, generic UI cards, flat colours, default springs,
  no motion blur, no depth, no texture. Premium means a consistent design system, custom easing, staggered reveals, masks,
  parallax/3D tilt, light sweeps, real glass blur, graphics that interact with the speaker (tracked, behind the head).
- **Sound effects sounded wrong:** a handful of generic whoosh/pop/ding files, not matched to the motion. References use layered,
  subtle sound design matched to each move's length (swooshes, risers, soft UI ticks, low hits on punchlines, music dips).
- To match exactly, study the references **in motion with audio** (uploaded files, frame-by-frame), not from still frames,
  and use a proper SFX library.

---

# Environment recipes (this cloud container; they do not carry over automatically)
- Instagram/TikTok CDN, Apify storage and Higgsfield CloudFront downloads are blocked from the shell. To study a reel:
  `apify/instagram-scraper` for a fresh `videoUrl`, then `tiger19935/video-frame-extractor` (mode `count`, 16 frames,
  maxWidth 360) and read single frames with ReadMcpResourceTool (contact sheets over 256 KB will not load; tile the
  frames locally with PIL). Higgsfield `media_import_url` + `video_analysis_create/status` gives scene-by-scene audio
  transcript and shot descriptions (it does not describe captions or graphics: use frames for those).
- Stock photos: `memo23/pexels-scraper` finds them; pass the image URL through the frame extractor
  (mode `timestamps`, `[0]`) to get a downloadable copy.
- Transcription: Hugging Face is blocked. sherpa-onnx + Parakeet TDT 0.6b int8 from the GitHub release
  `asr-models/sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8.tar.bz2`, 20s chunks (verify any "missing" words at chunk
  boundaries by decoding that span alone).
- Noise removal: `arnndn` model `sh.rnnn` from raw.githubusercontent.com (GregorR/rnnoise-models).
- Fonts that work in libass: Poppins, Montserrat, Inter, Instrument Serif Italic. Copy to `/root/.fonts`, `fc-cache -f`.
- Face tracking: `opencv-python-headless==4.10.0.84` + Haar cascade; filter detections (face in the upper part of the
  frame, sane size, rolling-median outlier rejection) because sweatshirt prints give false faces.
- Rendering: full 1080x1920 composites run out of memory in one pass; render the base per segment, concat, then
  composite overlays; audio separately.
