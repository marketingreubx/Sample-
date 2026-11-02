# Reubx — install once, everything included

1. Install **Claude Code** on your laptop (Mac or Windows).
2. Unzip **reubx.zip** into your skills folder: Mac `~/.claude/skills/`, Windows `C:\Users\<you>\.claude\skills\`.
3. In Claude Code type `/reubx install` (or just use any command below; the first one installs automatically).
   It sets up its own Python 3.12, ffmpeg, Chromium, 16 fonts, the speech model and icons. No admin password,
   no Homebrew needed (it uses Homebrew/winget if you already have them). One time, a few minutes.

**Commands**
- `/reubx studio ~/Videos/reels` (or `/reubx-studio ...`): browser studio at http://localhost:8765, watches the folder,
  auto-edits new videos; change fonts, colours, looks, sound, zooms, cut words, send notes to Claude.
- `/reubx b-loud ~/Videos/raw.mov` (or `/style-b-loud ...`): full edit with Claude's plan. Styles: a-list, a-story, a-skit, b-loud, b-editorial, b-showcase.
- `/reubx auto a-story ~/Videos/raw.mov`: fastest, no AI tokens (automatic plan).

Every edit: retakes and silences cut, voice cleaned (noise, hum, EQ, de-ess, compression), -14 LUFS, eased zooms,
sound design, captions always readable, no overlapping graphics. Outputs go to `<folder>/edited/`.
