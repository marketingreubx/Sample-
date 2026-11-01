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
