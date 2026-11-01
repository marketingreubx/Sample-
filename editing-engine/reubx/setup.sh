#!/usr/bin/env bash
# One-time setup for a fresh cloud container (~1-2 min). Safe to re-run.
set -e
H="$(cd "$(dirname "$0")" && pwd)"; R="$H/../.."
mkdir -p /root/.fonts "$H/cache"
cp -n "$R"/hormozi-shorts-editor/assets/fonts/*.ttf /root/.fonts/ 2>/dev/null || true
cp -n "$H"/assets/*.ttf /root/.fonts/ 2>/dev/null || true
fc-cache -f >/dev/null 2>&1 || true
pip -q install sherpa-onnx "opencv-python-headless==4.10.0.84" playwright numpy pillow 2>/dev/null || true
M="$H/cache/sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8"
if [ ! -f "$M/tokens.txt" ]; then
  curl -sSL -o "$H/cache/asr.tar.bz2" https://github.com/k2-fsa/sherpa-onnx/releases/download/asr-models/sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8.tar.bz2
  tar -xjf "$H/cache/asr.tar.bz2" -C "$H/cache" && rm -f "$H/cache/asr.tar.bz2"
fi
if [ ! -d "$H/cache/lucide" ]; then
  (cd "$H/cache" && npm pack -s lucide-static@0.460.0 >/dev/null && mkdir -p lucide && tar -xzf lucide-static-0.460.0.tgz -C lucide && rm -f lucide-static-0.460.0.tgz)
fi
[ -f "$H/cache/music.wav" ] || python3 "$H/music.py" "$H/cache/music.wav" 180
echo "reubx engine ready"
