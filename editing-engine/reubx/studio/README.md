# Reubx Studio (local)

A browser editing studio that runs on your own computer, like the "Claude edits my videos in my style" app.

**Install once**
1. Install Claude Code, Python 3.10+ and ffmpeg (Mac: `brew install ffmpeg`, Windows: `winget install ffmpeg`).
2. Either open Claude Code in this repo, or unzip `reubx-studio.zip` into `~/.claude/skills/` so it works from any folder.

**Use**
- In Claude Code type: `/reubx-studio ~/Videos/reels` (any folder with your raw videos).
- Open http://localhost:8765. Drop raw videos into the folder; each one is transcribed and gets an automatic first edit
  (tick "auto-edit new videos" to render them straight away). Edited files appear in `<folder>/edited/`.
- **Look** tab: pick the style (Calm Cards a-*, Motion Pop b-*), generate 5 looks from your own footage, "Use this look".
- **Text** tab: caption / keyword / accent / lead / handwritten fonts, colours, caption size.
- **Sound & camera**: music volume, sound-effect intensity, zoom punch-in strength.
- **Script**: click words to cut, double-click to fix spelling, alt-click to add a zoom.
- **Notes**: type any correction ("make the $700K bigger", "use a laptop B-roll here"). Claude, still running in the
  terminal, picks it up, edits the plan and re-renders.
- Press **Save & render** after changes. Re-renders reuse the cached cut and camera, so they are faster.
