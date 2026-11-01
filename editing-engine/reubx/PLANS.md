# plan.json — the only thing written per video

Word numbers (`i`) come from the numbered transcript that `run.py prep` prints. `"w":[a,b]` = from word a to word b.
Write the plan in one pass from the transcript. Do not re-study references; everything below is already the style.

## Common keys (all styles)
| key | meaning |
|---|---|
| `fix` | `{"i":"Text"}` spelling fixes for captions (names, brand words, "cloud"->"Claude"). `""` hides a word in captions. |
| `remove` | `[[a,b],...]` word ranges to cut: earlier take of a retake (use RETAKE HINTS), false starts, filler ("you know", "um"), unfinished trailing words. Never cut meaning. |
| `shots` | word numbers where the framing changes (hard cut wide <-> tight). Every 4-10 s at a new sentence/section. Jump cuts change framing automatically. |
| `punch` | word numbers that get an eased camera punch-in (+11%, 0.2 s in, hold, 0.35 s out). The 5-9 strongest words: names, numbers, the claim, the payoff. |
| `start_tight` | `true` to open on the tight framing (good for a direct hook). |

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
| `blocks` | `[{"w":[lead_i,big_i,end_i],"lead":"small white lead-in","text":"BIG WORDS","small":"optional line","x":false}]` — punchline keyword blocks; `<br>` for 2 lines when > ~14 chars. One every 4-6 s. `x:true` adds a red ✕ badge. |
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
