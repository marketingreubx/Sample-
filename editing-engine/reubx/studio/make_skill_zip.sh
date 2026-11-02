#!/usr/bin/env bash
# Builds reubx.zip: unzip into ~/.claude/skills/ on any computer -> /reubx, /style-*, /reubx-studio all work.
set -e
H="$(cd "$(dirname "$0")/.." && pwd)"; R="$(cd "$H/../.." && pwd)"; T="$(mktemp -d)"
mkdir -p "$T/reubx/engine"
cp "$R/.claude/skills/reubx/SKILL.md" "$T/reubx/"
(cd "$H" && tar --exclude='./cache' --exclude='./.venv' --exclude='./bin' --exclude='__pycache__' -cf - .) | (cd "$T/reubx/engine" && tar -xf -)
cp "$R"/hormozi-shorts-editor/assets/fonts/*.ttf "$T/reubx/engine/assets/" 2>/dev/null || true
for s in style-a-list style-a-story style-a-skit style-b-loud style-b-editorial style-b-showcase reubx-studio kilgore-list kilgore-story kilgore-community kilgore-skit kilgore-flip gallo-promo edita-lawyer edita-interview edita-blueprint edita-podcast; do mkdir -p "$T/$s"; cp "$R/.claude/skills/$s/SKILL.md" "$T/$s/"; done
chmod +x "$T/reubx/engine/reubx" "$T/reubx/engine/install.sh"
rm -f "$R/reubx.zip"; (cd "$T" && zip -qr "$R/reubx.zip" .)
echo "built $R/reubx.zip"
