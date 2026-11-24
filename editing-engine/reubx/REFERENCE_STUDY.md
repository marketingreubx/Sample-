# Reference study: 12 reels

Every reel was downloaded, transcribed, measured (ffmpeg scene detection, signalstats, EBU R128) and looked at frame by frame.

| reel | length | avg shot | words/min | avg luma | saturation | LUFS | speech over bed |
|---|---|---|---|---|---|---|---|
| DNBUWB3om71 | 22.8s | 4.56s | 216 | 64 | 3.6 | -14.3 | 15 dB |
| DOldGdUEVso | 74.1s | 2.96s | 228 | 106 | 12.1 | -14.2 | 11 dB |
| DSFr2cEEo7c | 35.0s | 3.89s | 245 | 100 | 3.8 | -14.1 | 14 dB |
| Ddewmw8PGtf | 67.0s | 6.7s | 156 | 82 | 10.8 | -14.7 | 13 dB |
| DeO6tn3oKg8 | 64.8s | 7.2s | 199 | 61 | 5.2 | -14.1 | 12 dB |
| Deuy9Y5EWK4 | 72.8s | 24.26s | 191 | 130 | 7.8 | -14.6 | 14 dB |
| Df4q6gqkcop | 77.2s | 38.59s | 236 | 101 | 12.3 | -14.1 | 14 dB |
| Df4rQ4IM-S1 | 91.7s | 9.17s | 223 | 46 | 3.8 | -14.1 | 10 dB |
| Df7Qfs2sqa9 | 123.4s | 6.17s | 219 | 61 | 4.2 | -14.3 | 10 dB |
| Dfmrmyvh5ic | 74.6s | 14.91s | 282 | 172 | 5.9 | -14.2 | 9 dB |
| DgC871CDaBS | 70.6s | 70.59s | 255 | 66 | 6.2 | -14.1 | 16 dB |
| DgGHPcdA-4F | 60.1s | 3.54s | 200 | 84 | 14.2 | -14.2 | 4 dB |

## What all 12 do (now engine defaults)
- **Hard cuts only.** Not one whip, flash or glitch. Motion comes from layout changes (talking head, split screen, full-screen graphic, picture card) and from graphics animating in.  defaults to ; the effects stay available.
- **Tight pacing:** 156-282 words/min (median ~220). Pauses are trimmed hard (: max kept pause 0.30 s).
- **Muted, moody grade:** saturation 4-14 (phone footage is 20-40), average luma 46-130. The grade keeps the mood and only fixes extremes; colourful footage is pulled toward the reference range.
- **Loudness -14 LUFS**, music bed ~11-16 dB under the voice.
- **Captions:** 1-3 words, white bold sans with a soft shadow (no heavy outline), one emphasised word per line in a colour (yellow / gold / lime), a script font (millie, deshna) or a red box (deshna).
- **Numbered section headers** in 6 of 12 reels ('1. TRANSFORMATION', '#1 Repetition', 'Mistake #3:'). Detected automatically from 'number one', 'step two', 'the third mistake'.
- **Full-screen word cards** on light paper or black ('6 Months', 'Good News', kallaway's black diagram frames). Auto-made for number + unit phrases.
- **Picture card over the blurred speaker** (chloe.shih, adam.kelada) for photos / proof: B-roll layout .
- **Split screen** graphic-top / speaker-bottom (kallaway, zachdoesai, tumi): layout , used automatically for screen-type B-roll.

## Per reel (preset name -> what it does)
-  (Df4rQ4IM-S1, Df7Qfs2sqa9): dark, red label boxes, black diagram frames, single centred words.
-  (Deuy9Y5EWK4): one locked bright shot, white box headers, typed example cards.
-  (DeO6tn3oKg8): screen recordings on dark stage with gold glow, numbered settings list.
-  (Ddewmw8PGtf): podcast wide shot, yellow uppercase keyword under a small white lead, icon tiles.
-  (DgGHPcdA-4F): lead / huge metallic gold word / tail stacks, light checklist split cards, paper word cards.
-  (Df4q6gqkcop): locked wide shot, handwritten script emphasis, 'Mistake #3:' headers.
-  (Dfmrmyvh5ic): white canvas split screen with UI mock-ups, captions on a dark pill.
-  (DgC871CDaBS): one dark shot, glassy green HUD overlays above the head.
-  (DSFr2cEEo7c): wide/tight hard cuts, sans + script captions, key phrase in a red box, paper cards.
-  (DOldGdUEVso): fastest (cut every 3 s), captions above the head, yellow key words, numbered headers, picture cards.
-  (DNBUWB3om71): very dark desaturated, gold serif words, glass list cards, full-screen 3D B-roll.
