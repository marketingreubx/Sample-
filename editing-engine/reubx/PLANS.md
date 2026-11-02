# plan.json — the only thing written per video

Word numbers (`i`) come from the numbered transcript that `run.py prep` prints. `"w":[a,b]` = from word a to word b.
Write the plan in one pass from the transcript. Do not re-study references; everything below is already the style.

## Text visibility rules (owner feedback — always on, built into the engine)
- **Contrast check on every text element:** a feathered dark scrim (no box) sits behind every keyword stack, counter and caption;
  its strength is set automatically from the brightness and busyness of the frame behind the text (stronger on bright or busy
  backgrounds and on full-screen B-roll). Main words use a light white-to-sky gradient with deep shadows, never dark blue on dark clothes.
- **Text is always on screen while someone speaks:** captions only hide while a keyword block already shows those words.
  During glass pills, chips, bars and toggles the caption moves BELOW the element; on full-screen cards it sits at the bottom
  (dark text on light cards).
- **Nothing overlaps:** chest elements (blocks, counters, pills, chips, bars, toggles, CTA) play one at a time — the earlier one
  hands off when the next one arrives.
- **Typography is the reference stack, not boxes:** small italic lead hugging the top-left of a heavy italic main word,
  optional italic tail hugging the bottom-right, tight leading. Entrances are lead slides from the left, main word zooms in
  sharp from a blur, tail slides from the right — no 3D tilts.

## Common keys (all styles)
| key | meaning |
|---|---|
| `fix` | `{"i":"Text"}` spelling fixes for captions (names, brand words, "cloud"->"Claude"). `""` hides a word in captions. |
| `remove` | `[[a,b],...]` word ranges to cut: earlier take of a retake (use RETAKE HINTS), false starts, filler ("you know", "um"), unfinished trailing words. Never cut meaning. |
| `shots` | word numbers where the framing changes (hard cut wide <-> tight). Every 4-10 s at a new sentence/section. Jump cuts change framing automatically. |
| `punch` | word numbers that get an eased camera punch-in (+11%, 0.2 s in, hold, 0.35 s out). The 5-9 strongest words: names, numbers, the claim, the payoff. |
| `start_tight` | `true` to open on the tight framing (good for a direct hook). |

## Reference presets — one per reference reel (use these names as the style)
| preset | reference | engine style | what the plan should contain |
|---|---|---|---|
| `kilgore-list` | @itsmegkilgore DewrbKMRjlE — '15 ways Claude edits my reels' | a-list | Wide locked shot, almost no cuts. Stacked hook header (handwritten yellow line + white sans line + handwritten sub). One white UI card per list item above the head (app/list/notification/stat/photo), 1.5-3 s each. One word at a time, ~1 in 4 yellow serif accents. Selfie-style tight hook and CTA (start_tight, last shot tight). End card typed. |
| `kilgore-story` | @itsmegkilgore Dee_frcPZhP — 'say it out loud' | a-story | Curiosity header ('send this to a dreamer' + handwritten 'it's time to say it out loud' + 'just wait for the ending'). Emotional passages as handwritten white lines with the key phrase in yellow (story). A pip card ('me one month ago') when an old clip/photo is referenced. Very few cuts, gentle zooms, black typed end card. |
| `kilgore-community` | @itsmegkilgore Dej25RgxvOD — 'half a million from one launch / build the room' | a-story | Big yellow serif numbers ($27,000), one-word captions with yellow serif accents, a comments scatter card whenever community/people/DMs are mentioned, a tilted reaction photo card top-left on surprising lines, stickers (🤯) on the emotional payoff, handwritten lines for the lesson at the end. |
| `kilgore-skit` | @itsmegkilgore De15Uy2x_tU — 'I taught my laptop to do my laundry' | a-skit | Tight selfie hook, then cuts every 2-4 s alternating wide/tight, punch-in on every beat. Handwritten yellow instruction lines ('Then you're gonna need to add this connector.'). A chat card (iMessage bubble) for the punchline message. One word at a time. Short tight close ('Next, I'm gonna teach Claude to do the dishes'). |
| `kilgore-flip` | @itsmegkilgore DezH_DVRfIL — 'If you use AI you'll look generic... just kidding' | a-list | Provocative tight hook line, then a hard flip ('Just kidding.') with a 😜 sticker and handwritten yellow 'Just kidding.'. Feature beats get white UI cards (brand kit = app/list cards with colour dots). Accent words like 'free' in yellow serif. Handwritten aside above the head ('wayyy more being given after launch ends!!'). Black typed end card. |
| `gallo-promo` | @_anthonygallo De-C1zEgXrw — premium SaaS course promo | b-editorial | Light lavender full-screen cards (dark:false) with clean product UI: steps/flow/browser/list cards, one key phrase per headline in a blue-purple-orange gradient (gradient_text). Host cut between cards; white bold captions. A refund/guarantee badge pill near the end. |
| `edita-lawyer` | @edita.visuals DewlyZSNcme — 'when your home is paid off' | b-loud | Reference stack keyword blocks (lead 'You throw' / main 'A PARTY' / tail 'somebody') on every punchline; light-blue glass progress bar; dark check/cross pills with a click cursor; a 'Yes or No?' answer card; polaroid card with a red tag ('NOT CARRY'); emoji answer chips; a 'STRONGLY RECOMMEND' pill at the end. B-roll sparse. |
| `edita-interview` | @edita.visuals DeSeu64RwaW — Replit CEO interview | b-editorial | Lime serif italic keywords with a small light lead and tail ('I would say just' / 'go ahead' / 'build the product'). Archive/vintage B-roll (desaturated) on every reference to the past; retro window cards ('TACIT KNOWLEDGE NOT FOUND' warning dialog, media-player style); quote card for the key line. Calm camera. |
| `edita-blueprint` | @edita.visuals De1uB_sJ8M8 — 'welcome to the Attention Economy' | b-showcase | Showcase frame (raw clip beside the edit). Text-card interstitials with slow zoom: light card with blue list text (steps, dark:false) and a dark purple 'Blueprint' list card (Content / Distribution / Sales). Keyword blocks sparse, captions small. |
| `edita-podcast` | @edita.visuals DemWl0OtTd8 — herbalism podcast | b-editorial | Warm terracotta serif keywords. Rapid B-roll montage: one full-screen photo per list item (each herb/product ~1 s), split screens for 'the point is' lines, a quote card for 'the magic and the reality of ...'. Music swells slightly mid-reel. |

Extra elements used by the presets:
- Style A `cards` types: `comments` {texts:[...]} (tilted comment screenshots, names blacked out) · `chat` {messages:[["me"|"them","text"]], y} (iMessage bubbles) ·
  `sticker` {emoji:"🤯" or icon, x, y, size} · `pip` {img, label:"me one month ago"} (phone-frame picture-in-picture with handwritten label + arrow).
- Style B: `pills[].cursor: true` (hand cursor clicks the pill) · `polaroids` [{w, img, caption, tag:"NOT CARRY", x}] (pinned polaroid with red tag) ·
  `cards` types `answer` {question, chips:[["🙂","I agree"]]} (light-blue "Yes or No?" card) and `retro` {imgs:[...], title, window} (vintage collage + warning dialog) ·
  `gradient_text: true` (multicolour gradient headline, Gallo).

## Style A — "Calm Cards" (`a-list`, `a-story`, `a-skit`)
| key | shape |
|---|---|
| `header` | `{"w":[a,b],"top":"handwritten yellow line","main":"white sans line","sub":"handwritten white line"}` — the hook, above the head. |
| `accent` | `[i,...]` words shown in yellow serif italic (~1 in 4 meaningful words: nouns/verbs/emotions). |
| `story` | `[{"w":[a,b],"text":"line one|line two","key":"phrase in yellow"}]` — handwritten multi-word lines. |
| `numbers` | `[{"w":i,"text":"30","sub_w":j,"sub":"& fabulous","until":k}]` — big yellow serif number. |
| `cards` | white UI cards above the head, 1.5-4 s each, one per spoken beat. `type` = `calendar` {title,badge,days} · `app` {title,lines:[[icon,text]],button,img} · `checklist` {kicker,rows:[[text,tick_word_i]]} · `streak` {title,value,days} · `notification` {icon,kicker,title,sub} · `photo` {img,caption,side} · `stat` {label,value,sub,pct} · `list` {title,rows:[[icon,text]]} |
| `asides` | `[{"w":[a,b],"text":"no gym needed!!"}]` small tilted handwritten yellow notes above the head. |
| `end` | `{"text":"Start today.","sub":"handwritten line"}` black end card with typed serif text (+2.4 s). |
| `accent_color` | card accent (default `#ff5a93`); match the creator's brand. |

Variant density:
- **a-list** ("X ways / features"): header, a card for EVERY list item, 1-2 asides, accents, end card. Few story lines.
- **a-story** (lesson / emotional): curiosity header, story lines for the emotional passages, 1-3 photo cards, big numbers, end card. Few UI cards.
- **a-skit** (joke / demo): `start_tight`, more `shots` (every 2-4 s) and `punch` on every beat, `notification`/`app` cards as the punchline props, short end card.

## Style B — "Motion Pop" (`b-loud`, `b-editorial`, `b-showcase`)
| key | shape |
|---|---|
| `accent`,`accent2` | box colour and light tint. Pick to CONTRAST the wardrobe/background (navy shirt -> bright blue `#2F6BFF` box with white text is fine; never light text on a similar colour). |
| `key` | words highlighted in captions. |
| `broll` | `[{"img":"name.webp","lay":"F|S","w":[a,b]}]` F = full-screen photo with wipe, S = split (photo top, speaker bottom). One every 6-10 s on concrete nouns. Images go in `<work>/broll/`. |
| `blocks` | `[{"w":[lead_i,big_i,end_i],"lead":"small italic lead-in","text":"BIG WORDS","tail":"small italic tail","x":false}]` — the reference stack: small italic lead hugging the top-left of a heavy white-to-blue italic word, optional tail hugging the bottom-right (e.g. lead "You throw" / "A PARTY" / tail "somebody"). No boxes; `<br>` for 2 lines when > ~14 chars. One every 4-6 s. `x:true` adds a red ✕ badge. |
| `counts` | `[{"w":[a,b],"to":"$700K","lead":"who made about","small":"US DOLLARS"}]` counting numbers. |
| `pills` | `[{"w":[a,b],"icon":"calendar-days","text":"in the first <b>3 weeks</b>"}]` glass pill. |
| `chips` | `[{"w":[a,b],"items":[["pen-line","captions"],...]}]` 2-4 glass chips. |
| `bars` | `[{"w":[a,b],"icon":"rocket","label":"Launch revenue","value":"$500K","ticks":["week 1","week 2"]}]` filling progress bar. |
| `toggles` | `[{"w":[a,b],"icon":"pen-line","label":"Caption Writer","sub":"Claude skill"}]` iOS switch flips on. |
| `cards` | full-screen interstitials (one per section, 2.5-4 s): `list` {kicker,title,rows:[[initials,name,sub,value]],badge} · `steps` {kicker,title,rows:[[icon,name]]} · `flow` {kicker,title,from:[icon,label,sub],to,lines,done,done_icon} · `browser` {kicker,img,brand,tagline,button,tiles} · `quote` {kicker,title,quote}. `"dark":false` for a light card. |
| `cta` | `{"w":[a,b],"keyword":"CLAUDE","line":"I'll send you ..."}` typed comment box. |
| `showcase_cta` | (b-showcase) text under the phone card. |

Variant density:
- **b-loud**: blocks + counts on every claim/number, pills/chips/bars/toggles between, a card per section, B-roll every 6-10 s.
- **b-editorial**: blocks become elegant serif (automatic), B-roll desaturated archive look (automatic), more `quote`/`steps` cards, fewer pills.
- **b-showcase**: b-loud wrapped in the portfolio frame (raw clip in B&W beside the edit, CTA under it) — for selling the editing service.

Icons: any Lucide name (calendar-days, sparkles, rocket, pen-line, file-text, globe, lightbulb, video, check, x, heart, house, clock, play, flame, bell, map-pin, trending-up, download, send, star, users, dollar-sign, camera, mic, zap, target, shield-check).
Nothing may cover the face: the engine places everything under the chin or above the head automatically.
