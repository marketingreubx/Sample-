#!/usr/bin/env bash
# Builds reubx.zip: ONE top-level folder (reubx/) with ONE SKILL.md + the engine. Every style is a /reubx argument.
set -e
H="$(cd "$(dirname "$0")/.." && pwd)"; R="$(cd "$H/../.." && pwd)"; T="$(mktemp -d)"
mkdir -p "$T/reubx/engine"
cp "$R/.claude/skills/reubx/SKILL.md" "$T/reubx/"
(cd "$H" && tar --exclude='./cache' --exclude='./.venv' --exclude='./bin' --exclude='__pycache__' --exclude='SKILL.md' -cf - .) | (cd "$T/reubx/engine" && tar -xf -)
cp "$R"/hormozi-shorts-editor/assets/fonts/*.ttf "$T/reubx/engine/assets/" 2>/dev/null || true
chmod +x "$T/reubx/engine/reubx" "$T/reubx/engine/install.sh"
rm -f "$R/reubx.zip"; (cd "$T" && zip -qr "$R/reubx.zip" reubx)
echo "built $R/reubx.zip"
