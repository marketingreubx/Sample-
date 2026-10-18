#!/usr/bin/env bash
# One-time setup for the Hormozi Shorts Editor skill.
set -e
command -v ffmpeg >/dev/null || { echo "ffmpeg missing: install it (brew install ffmpeg / apt install ffmpeg / winget install ffmpeg)"; exit 1; }
ffmpeg -hide_banner -filters 2>/dev/null | grep -q " ass " || { echo "your ffmpeg has no libass, captions will not burn. Install a full build."; exit 1; }
python3 -m pip install --quiet --upgrade faster-whisper
echo "ok: ffmpeg + libass + faster-whisper ready. First analyze run downloads the whisper model."
