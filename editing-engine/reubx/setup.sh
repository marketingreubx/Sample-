#!/usr/bin/env bash
# One-time setup (cloud container or your laptop: macOS / Linux / Windows Git-Bash). Safe to re-run.
# Needs: python3 (3.10+), ffmpeg on PATH, internet.
set -e
H="$(cd "$(dirname "$0")" && pwd)"
command -v ffmpeg >/dev/null || { echo "ffmpeg is missing. Mac: brew install ffmpeg | Windows: winget install ffmpeg | Linux: apt install ffmpeg"; exit 1; }
PY=python3; command -v python3 >/dev/null || PY=python
mkdir -p "$H/cache/fonts"
$PY -m pip -q install sherpa-onnx "opencv-python-headless==4.10.0.84" playwright numpy pillow
[ -x /opt/pw-browsers/chromium ] || $PY -m playwright install chromium
# fonts (bundled + Google Fonts, OFL)
cp -n "$H"/assets/*.ttf "$H/cache/fonts/" 2>/dev/null || true
[ -d "$H/../../hormozi-shorts-editor/assets/fonts" ] && cp -n "$H"/../../hormozi-shorts-editor/assets/fonts/*.ttf "$H/cache/fonts/" 2>/dev/null || true
G=https://raw.githubusercontent.com/google/fonts/main/ofl
get() { [ -f "$H/cache/fonts/$2" ] || curl -sSfL -o "$H/cache/fonts/$2" "$G/$1" || echo "font skipped: $2"; }
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
# Montserrat static weights used for text measuring
[ -f "$H/cache/fonts/Montserrat-ExtraBold.ttf" ] || cp "$H/cache/fonts/Montserrat-VF.ttf" "$H/cache/fonts/Montserrat-ExtraBold.ttf"
[ -f "$H/cache/fonts/Montserrat-Black.ttf" ] || cp "$H/cache/fonts/Montserrat-VF.ttf" "$H/cache/fonts/Montserrat-Black.ttf"
# speech model, icons, music bed
M="$H/cache/sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8"
if [ ! -f "$M/tokens.txt" ]; then
  curl -sSL -o "$H/cache/asr.tar.bz2" https://github.com/k2-fsa/sherpa-onnx/releases/download/asr-models/sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8.tar.bz2
  tar -xjf "$H/cache/asr.tar.bz2" -C "$H/cache" && rm -f "$H/cache/asr.tar.bz2"
fi
if [ ! -d "$H/cache/lucide" ]; then
  (cd "$H/cache" && curl -sSfL -o lucide.tgz https://registry.npmjs.org/lucide-static/-/lucide-static-0.460.0.tgz && mkdir -p lucide && tar -xzf lucide.tgz -C lucide && rm -f lucide.tgz)
fi
[ -f "$H/cache/music.wav" ] || $PY "$H/music.py" "$H/cache/music.wav" 180
echo "reubx engine ready"
