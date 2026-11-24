# Reubx editing engine

One command per style: `/style-a-list`, `/style-a-story`, `/style-a-skit`, `/style-b-loud`, `/style-b-editorial`, `/style-b-showcase`
(skills in `.claude/skills/`). Each runs the same fixed pipeline; the only per-video input is `plan.json` (see `PLANS.md`).

Pipeline (`run.py`): transcribe (Parakeet, cached) -> cut in real silences (retakes removed) -> voice clean-up chain ->
camera (wide/tight shots + keyframed eased punch-ins + slow push-in) -> face tracking -> HTML motion-graphics layer
(mask reveals, tilt-ups, slams, slides, light sweeps, grain) -> B-roll windows + wipes -> separate audio pass
(voice + ducked music + procedural sound design matched to each move, -14 LUFS) -> mux -> QA (word recall vs raw cut,
audio gaps, loudness, contact sheet).

Fresh container: `bash editing-engine/reubx/setup.sh` (downloads the speech model, icons, builds the music bed).
Typical render: 6-10 minutes for a 60-90 s reel. Corrections: edit `plan.json`, re-run render (cut/camera steps are cached).

## v2 upgrades
- **Colour grade (`grade.py`)**: the owner's base grade (contrast 4, saturation 6, brilliance 3.5, sharpen 50, clarity 7,
  highlights -13, shadows -23, whites -7.5, hue 9, brightness 2) is measured against every clip first and nudged:
  dark clips lifted, bright clips held back, clipped highlights recovered, already-colourful clips barely saturated.
  Every slider + intensity + auto on/off lives in `plan.look.grade` and the studio **Colour** tab (with a before/after preview).
- **Transitions & effects (`fx.py`)**: whip pan, zoom-through, white flash, RGB glitch, spin, impact shake, defocus blur,
  dip to black, warm light leak, plus impact shake + colour split on punch words. Rendered inside each shot (no overlap,
  sync never moves), each with a matching sound. `look.transitions` = auto | dynamic | minimal | off; `plan.trans`
  `{"word_i": "glitch"}` forces one.
- **Text**: main words are solid colour with an outline and a hard shadow (no default gradient). `look.main_color`,
  `highlight` (key words in captions), `stroke`, `outline`, `main_style` (solid | box | gradient), `box_color`,
  `cap_weight`, `cap_case`. All in the studio **Text** tab.
- **Screen recordings (`screen.py`)**: auto-detected (landscape or no face). Nothing is cut except dead air at the ends and
  ranges the user marks; the recording is shown as a rounded card on a soft backdrop and a smart zoom follows the
  cursor / typing. `plan.screen` = auto | true | false.
- **B-roll suggestions (`broll_suggest.py`)**: written at prep time (`broll_suggestions.md/json`, studio **B-roll** tab)
  with type (screen / stock / photo / graphic), what to add, a search query and a file name. Dropped files (image or
  video) with that name in `<work>/broll/` are placed automatically.
- **Style previews**: studio button "See every style on my video" renders every preset on the user's own graded frame.
- **Speed**: shots render in parallel. New house presets: `reubx-bold`, `reubx-clean`, `reubx-tutorial`.
