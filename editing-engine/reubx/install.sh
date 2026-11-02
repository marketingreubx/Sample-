#!/usr/bin/env bash
# Reubx one-shot installer for macOS / Linux / Windows Git-Bash. No admin rights needed.
# Installs: uv (Python manager) -> private Python 3.12 venv -> packages -> ffmpeg (+ffprobe) -> Chromium -> fonts, speech model, icons, music.
# Safe to re-run; skips what is already there. Usage: bash install.sh
set -e
H="$(cd "$(dirname "$0")" && pwd)"; cd "$H"
say() { printf "\n\033[1m[reubx] %s\033[0m\n" "$*"; }
OS="$(uname -s)"; case "$OS" in MINGW*|MSYS*|CYGWIN*) WIN=1 ;; *) WIN=0 ;; esac
mkdir -p "$H/bin" "$H/cache/fonts"

# 1. uv (no sudo; installs into ~/.local/bin or ~/.cargo/bin)
export PATH="$HOME/.local/bin:$HOME/.cargo/bin:$PATH"
if ! command -v uv >/dev/null 2>&1; then
  say "Installing uv (Python manager)"
  if [ "$WIN" = 1 ]; then powershell -ExecutionPolicy Bypass -NoProfile -Command "irm https://astral.sh/uv/install.ps1 | iex"
  else curl -LsSf https://astral.sh/uv/install.sh | sh; fi
  export PATH="$HOME/.local/bin:$HOME/.cargo/bin:$PATH"
fi

# 2. private Python 3.12 + packages
if [ "$WIN" = 1 ]; then PY="$H/.venv/Scripts/python.exe"; else PY="$H/.venv/bin/python"; fi
if [ ! -x "$PY" ]; then say "Creating Python 3.12 environment"; uv venv "$H/.venv" --python 3.12 -q; fi
say "Installing Python packages"
uv pip install -q --python "$PY" sherpa-onnx "opencv-python-headless==4.10.0.84" playwright numpy pillow static-ffmpeg

# 3. ffmpeg + ffprobe (system -> Homebrew/winget -> bundled static build)
has_ff() { command -v ffmpeg >/dev/null 2>&1 && command -v ffprobe >/dev/null 2>&1 && ffmpeg -hide_banner -filters 2>/dev/null | grep -q arnndn; }
if ! has_ff && [ ! -x "$H/bin/ffmpeg" ] && [ ! -f "$H/bin/ffmpeg.exe" ]; then
  if [ "$OS" = "Darwin" ] && command -v brew >/dev/null 2>&1; then say "Installing ffmpeg with Homebrew"; brew install ffmpeg || true
  elif [ "$WIN" = 1 ] && command -v winget >/dev/null 2>&1; then say "Installing ffmpeg with winget"; winget install -e --id Gyan.FFmpeg --accept-package-agreements --accept-source-agreements || true; fi
  if ! has_ff; then
    say "Downloading a bundled ffmpeg"
    "$PY" - "$H/bin" <<'EOF'
import os, shutil, sys, static_ffmpeg.run as r
ff, fp = r.get_or_fetch_platform_executables_else_raise()
for src in (ff, fp): shutil.copy2(src, os.path.join(sys.argv[1], os.path.basename(src))); os.chmod(os.path.join(sys.argv[1], os.path.basename(src)), 0o755)
print("ffmpeg ready:", ff)
EOF
  fi
fi

# 4. Chromium for the motion-graphics renderer
if [ ! -x /opt/pw-browsers/chromium ]; then say "Installing Chromium renderer"; "$PY" -m playwright install chromium; fi

# 5. fonts (bundled + Google Fonts, OFL), speech model, icons, music bed
say "Fonts, speech model, icons"
cp -n "$H"/assets/*.ttf "$H/cache/fonts/" 2>/dev/null || true
G=https://raw.githubusercontent.com/google/fonts/main/ofl
get() { [ -f "$H/cache/fonts/$2" ] || curl -sSfL -o "$H/cache/fonts/$2" "$G/$1" || echo "  font skipped: $2"; }
get "poppins/Poppins-SemiBold.ttf" Poppins-SemiBold.ttf; get "poppins/Poppins-Bold.ttf" Poppins-Bold.ttf; get "poppins/Poppins-ExtraBold.ttf" Poppins-ExtraBold.ttf
get "poppins/Poppins-Light.ttf" Poppins-Light.ttf; get "poppins/Poppins-LightItalic.ttf" Poppins-LightItalic.ttf; get "poppins/Poppins-Italic.ttf" Poppins-Italic.ttf
get "poppins/Poppins-MediumItalic.ttf" Poppins-MediumItalic.ttf
get "montserrat/Montserrat%5Bwght%5D.ttf" Montserrat-VF.ttf; get "montserrat/Montserrat-Italic%5Bwght%5D.ttf" Montserrat-Italic-VF.ttf
get "instrumentserif/InstrumentSerif-Italic.ttf" InstrumentSerif-Italic.ttf; get "gochihand/GochiHand-Regular.ttf" GochiHand-Regular.ttf
get "caveat/Caveat%5Bwght%5D.ttf" Caveat-VF.ttf; get "reeniebeanie/ReenieBeanie.ttf" ReenieBeanie.ttf
get "inter/Inter%5Bopsz,wght%5D.ttf" Inter-VF.ttf; get "dmsans/DMSans%5Bopsz,wght%5D.ttf" DMSans-VF.ttf; get "manrope/Manrope%5Bwght%5D.ttf" Manrope-VF.ttf
get "bricolagegrotesque/BricolageGrotesque%5Bopsz,wdth,wght%5D.ttf" BricolageGrotesque-VF.ttf
get "playfairdisplay/PlayfairDisplay-Italic%5Bwght%5D.ttf" PlayfairDisplay-Italic-VF.ttf; get "dmserifdisplay/DMSerifDisplay-Italic.ttf" DMSerifDisplay-Italic.ttf
get "cormorantgaramond/CormorantGaramond-Italic%5Bwght%5D.ttf" CormorantGaramond-Italic-VF.ttf
get "anton/Anton-Regular.ttf" Anton-Regular.ttf; get "archivoblack/ArchivoBlack-Regular.ttf" ArchivoBlack-Regular.ttf
[ -f "$H/cache/fonts/Montserrat-ExtraBold.ttf" ] || cp "$H/cache/fonts/Montserrat-VF.ttf" "$H/cache/fonts/Montserrat-ExtraBold.ttf"
[ -f "$H/cache/fonts/Montserrat-Black.ttf" ] || cp "$H/cache/fonts/Montserrat-VF.ttf" "$H/cache/fonts/Montserrat-Black.ttf"
M="$H/cache/sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8"
if [ ! -f "$M/tokens.txt" ]; then
  say "Downloading the speech model (~650 MB, one time)"
  curl -sSL -o "$H/cache/asr.tar.bz2" https://github.com/k2-fsa/sherpa-onnx/releases/download/asr-models/sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8.tar.bz2
  tar -xjf "$H/cache/asr.tar.bz2" -C "$H/cache" && rm -f "$H/cache/asr.tar.bz2"
fi
if [ ! -d "$H/cache/lucide" ]; then
  (cd "$H/cache" && curl -sSfL -o lucide.tgz https://registry.npmjs.org/lucide-static/-/lucide-static-0.460.0.tgz && mkdir -p lucide && tar -xzf lucide.tgz -C lucide && rm -f lucide.tgz)
fi
export PATH="$H/bin:$PATH"
[ -f "$H/cache/music.wav" ] || "$PY" "$H/music.py" "$H/cache/music.wav" 180
chmod +x "$H/reubx" 2>/dev/null || true
say "Reubx is installed. Try: $H/reubx studio --folder ~/Videos/reels"
