# Hormozi Style Editing Rulebook

The style that made Alex Hormozi's shorts recognisable is simple on the surface: big bold captions, constant zooms, no dead air. Underneath, every choice serves retention. Learn the reasons, not just the look, and you can apply it to any speaker.

> Reference video analysis: the user's chosen reference (https://youtu.be/21flGkcZO3A) has not been measured yet. When the file is available, run `scripts/study_reference.py` on it and replace the defaults in section 12 with its real numbers.

---

## 1. The retention mindset

- Viewers decide in about 1 to 2 seconds whether to stay. The edit's first job is to make leaving feel like missing something.
- Every 2 to 4 seconds something must change: a cut, a zoom, a caption colour, a pop-up, a sound. Change resets attention.
- Clarity beats flash. Viewers watching on mute must still get the whole message from the captions alone, because most do watch on mute.
- The edit serves the words. If an effect pulls attention away from what is being said, it is wrong even if it looks cool.

## 2. Structure of a winning short

1. **Hook (0 to 3 s):** the boldest, most specific line in the footage. A number, a mistake, a contrarian claim, or a direct call-out ("Ladies, stop doing crunches"). It is often said in the middle or end of the raw clip, so move it to the front.
2. **Setup (3 to 8 s):** why this matters to the viewer, the stakes.
3. **Value (body):** steps, proof, examples. One idea per beat, with a cut between beats.
4. **Rehook (around the middle):** "but the second one is the one nobody does". Give it a zoom and a sound.
5. **Payoff:** the result or the surprising answer.
6. **CTA (last 2 to 4 s):** one ask only, said cleanly, ending on the last word. Nothing after it.

Target runtime: 20 to 45 seconds for reach, up to 60 to 90 seconds for teaching content. Cut at least 20 to 40 percent of a raw take, usually more.

## 3. Cutting and pacing

- **Remove every breath gap.** Pauses over roughly 0.25 to 0.3 seconds are cut. Keep 40 to 80 ms of padding so words do not clip.
- **Remove fillers:** um, uh, erm, "you know", "basically", "so yeah", repeated words, lip smacks.
- **Retakes:** keep the last clean take of a repeated line.
- **Jump cuts are a feature,** not a flaw, but always hide them by changing framing: zoom in or out on every cut.
- **Shot length:** hook shots 0.8 to 2 s, body 1.5 to 4 s, never above 5 to 6 s without a zoom, pop-up or B-roll.
- **Speed:** a slight 1.05 to 1.1x speed-up works for slow speakers. Never so fast that it sounds unnatural.
- **Cut on the word, not the silence:** a new shot starts on the first syllable, which keeps energy up.

## 4. Framing and zoom grammar

- 9:16 vertical, 1080x1920. Eyes on the upper-third line, top of the head close to the frame edge but never cut by the tighter zoom.
- **Two-level alternation:** wide (100 percent) and punched-in (115 to 120 percent) on alternate cuts. This is the core of the look.
- **Emphasis zoom:** 130 to 140 percent on the punchline, the number, or the CTA.
- **Slow push-in** (2 to 5 percent over a shot) on emotional or serious moments. Fast snap zooms on jokes or reveals.
- Off-centre speaker: set focus_x per segment so the face stays centred after cropping.
- Never zoom so far that the image goes soft. With 1080p sources stay at 140 percent or below; with 4K sources you can go further.

## 5. Captions (the signature)

- **Font:** heavy geometric sans: Montserrat Black or ExtraBold (bundled), or The Bold Font, Komika Axis, Anton. All caps.
- **Size:** big, about 100 to 120 px on a 1080-wide frame. Roughly 1 to 3 words per screen and 16 characters at most per line.
- **Colour system:**
  - Base: white
  - Active word as it is spoken: yellow (classic Hormozi) or the brand colour
  - Emphasis words: green for good, results or money; red for bad, mistakes or "stop"
  - Thick black outline (6 to 8 px) and a soft shadow so captions read on any background
- **Placement:** centred, lower-middle of the frame, roughly 62 to 72 percent down. Never over the mouth, never in the bottom 15 percent (the platform UI covers it) and never in the right-edge button column.
- **Animation:** each new caption pops in (scaled from 70 to 105 to 100 percent over about 140 ms). Word-by-word highlight follows the speech exactly.
- **Emphasis discipline:** at most one emphasised word per caption and about one in three captions. Numbers almost always get it.
- **Punctuation:** none, except question marks when they matter. Use numerals ("100", not "one hundred"), and "$" and "%".
- **Accuracy:** fix every misheard word, especially names, brands, foods and numbers. A wrong caption destroys trust.
- **Emojis:** one occasionally, beside a key word or in a pop-up. Never inside every caption.

## 6. Pop-up text and graphics

- 1 to 4 word overlays in the top third: the hook promise, "STEP 1", a number, a contrast ("❌ CRUNCHES ✅ PROTEIN").
- Same font family as the captions, with colour coding (red for wrong, green for right).
- Animation: pop or slide in, holding 1 to 2 seconds.
- Optional progress cues for lists: "1/3", "2/3", "3/3".

## 7. B-roll and cutaways

- Cut away on nouns: when the speaker names a thing (eggs, a treadmill, a receipt), show it for 0.8 to 1.5 seconds with captions still running.
- Use real, relevant footage: the creator's own clips first, then licensed stock. Never footage they do not have rights to.
- Screen recordings and screenshots work for proof: DMs, results, before and afters (with consent).
- Keep the speaker's audio continuous under B-roll. Return to the face on the punchline.

## 8. Sound design

- **Whoosh:** on big zoom changes, section transitions and B-roll entries.
- **Pop or click:** when a pop-up text appears.
- **Ding or cash register:** on a number, a win or money.
- **Boom or bass hit:** at most once, on the biggest reveal.
- Sit SFX about 10 to 15 dB below the voice. Never let them cover a word.
- Sparse beats constant: 4 to 10 sound effects in a 30 to 60 second short.

## 9. Music

- Optional. If used: low-energy instrumental for teaching, upbeat for lists and hype. No vocals under speech.
- About -25 to -30 dB under the voice, ducked further when the person talks (the engine sidechains it automatically).
- Fade out over the last 1 to 1.5 seconds. Only royalty-free tracks or tracks the user owns.

## 10. Voice and audio mastering

- High-pass around 80 Hz to remove rumble, light compression (about 3:1) for even level, a small presence boost around 3 to 4 kHz for clarity.
- Loudness: **-14 LUFS integrated, true peak at or below -1 dBTP** (Reels, TikTok and Shorts targets).
- Remove clicks, plosives and room echo where possible. Bad audio loses viewers faster than bad video.

## 11. Colour and image

- Slight contrast and saturation lift (5 to 12 percent) and light sharpening. Skin must stay natural.
- Match exposure and white balance between takes so cuts do not flicker.
- Fix crooked framing and avoid a busy background. Reshoot rather than polish truly bad footage, and say so.

## 12. Default numbers (replace with reference measurements when available)

| Parameter | Default |
|---|---|
| Max pause kept | 0.28 s |
| Word padding | 0.06 s |
| Average shot | 1.5 to 3 s |
| First cut | before 2 s |
| Zoom levels | 1.00 / 1.18, emphasis 1.30 to 1.40 |
| Caption size | 112 px (1080 wide), 1 to 3 words, max 16 chars |
| Caption position | 68% down, centred |
| Colours | white / yellow active / green and red emphasis, black 7 px outline |
| Loudness | -14 LUFS, -1 dBTP |
| Export | 1080x1920, 30 fps, H.264 high, CRF 18, AAC 192k 48 kHz, faststart |

## 13. Platform safe zones (1080x1920)

- Top 220 px: platform username and status bar. Avoid text there.
- Bottom 380 px: caption, audio label and buttons. Keep captions above it.
- Right 160 px: like, comment and share column. Keep important text away from it.

## 14. Final QA checklist

- [ ] The hook lands within the first second, both visually and in audio
- [ ] No pause over 0.3 s anywhere, no fillers, no retakes, no "bye guys"
- [ ] Every cut changes framing; no shot over about 5 s without a change
- [ ] The face is fully in frame on every zoom level; nothing soft or pixelated
- [ ] Captions are 100% accurate, in sync, in the safe zone, and never cross a cut
- [ ] Emphasis words are sparse and meaningful
- [ ] SFX and music never mask a word; loudness is -14 LUFS
- [ ] The CTA is clear, with one ask, and the video ends on its last word
- [ ] The meaning of every statement is preserved (critical for health and money claims)
- [ ] Export spec matches section 12
