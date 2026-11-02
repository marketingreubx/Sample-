#!/usr/bin/env bash
# Builds reubx-studio.zip: a self-contained skill (skill + engine) to unzip into ~/.claude/skills/ on any computer.
set -e
H="$(cd "$(dirname "$0")/.." && pwd)"; R="$H/../.."; T="$(mktemp -d)/reubx-studio"
mkdir -p "$T/engine"
cp "$R/.claude/skills/reubx-studio/SKILL.md" "$T/"
(cd "$H" && tar --exclude='./cache' --exclude='__pycache__' -cf - .) | (cd "$T/engine" && tar -xf -)
mkdir -p "$T/engine/assets" && cp "$R"/hormozi-shorts-editor/assets/fonts/*.ttf "$T/engine/assets/" 2>/dev/null || true
(cd "$(dirname "$T")" && zip -qr "$R/reubx-studio.zip" reubx-studio)
echo "built $R/reubx-studio.zip"
