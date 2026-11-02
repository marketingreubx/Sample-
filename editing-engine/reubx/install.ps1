# Reubx one-shot installer for Windows (PowerShell). No admin rights needed.
$ErrorActionPreference = "Stop"
$H = Split-Path -Parent $MyInvocation.MyCommand.Path; Set-Location $H
function Say($m) { Write-Host "`n[reubx] $m" -ForegroundColor Cyan }
New-Item -ItemType Directory -Force -Path "$H\bin", "$H\cache\fonts" | Out-Null
$env:Path = "$env:USERPROFILE\.local\bin;$env:USERPROFILE\.cargo\bin;$env:Path"

# 1. uv + private Python 3.12 + packages
if (-not (Get-Command uv -ErrorAction SilentlyContinue)) { Say "Installing uv (Python manager)"; irm https://astral.sh/uv/install.ps1 | iex; $env:Path = "$env:USERPROFILE\.local\bin;$env:Path" }
$PY = "$H\.venv\Scripts\python.exe"
if (-not (Test-Path $PY)) { Say "Creating Python 3.12 environment"; uv venv "$H\.venv" --python 3.12 -q }
Say "Installing Python packages"
uv pip install -q --python $PY sherpa-onnx "opencv-python-headless==4.10.0.84" playwright numpy pillow static-ffmpeg

# 2. ffmpeg + ffprobe (system -> winget -> bundled static build)
$hasFF = (Get-Command ffmpeg -ErrorAction SilentlyContinue) -and (Get-Command ffprobe -ErrorAction SilentlyContinue)
if (-not $hasFF -and -not (Test-Path "$H\bin\ffmpeg.exe")) {
  if (Get-Command winget -ErrorAction SilentlyContinue) { Say "Installing ffmpeg with winget"; try { winget install -e --id Gyan.FFmpeg --accept-package-agreements --accept-source-agreements } catch {} }
  $hasFF = (Get-Command ffmpeg -ErrorAction SilentlyContinue) -and (Get-Command ffprobe -ErrorAction SilentlyContinue)
  if (-not $hasFF) {
    Say "Downloading a bundled ffmpeg"
    & $PY -c "import os,shutil,sys,static_ffmpeg.run as r; ff,fp=r.get_or_fetch_platform_executables_else_raise(); [shutil.copy2(s, os.path.join(sys.argv[1], os.path.basename(s))) for s in (ff,fp)]" "$H\bin"
  }
}

# 3. Chromium renderer
Say "Installing Chromium renderer"; & $PY -m playwright install chromium

# 4. fonts, speech model, icons, music
Say "Fonts, speech model, icons"
Copy-Item "$H\assets\*.ttf" "$H\cache\fonts\" -ErrorAction SilentlyContinue
$G = "https://raw.githubusercontent.com/google/fonts/main/ofl"
$fonts = @{
 "Poppins-SemiBold.ttf"="poppins/Poppins-SemiBold.ttf"; "Poppins-Bold.ttf"="poppins/Poppins-Bold.ttf"; "Poppins-ExtraBold.ttf"="poppins/Poppins-ExtraBold.ttf";
 "Poppins-Light.ttf"="poppins/Poppins-Light.ttf"; "Poppins-LightItalic.ttf"="poppins/Poppins-LightItalic.ttf"; "Poppins-Italic.ttf"="poppins/Poppins-Italic.ttf";
 "Poppins-MediumItalic.ttf"="poppins/Poppins-MediumItalic.ttf"; "Montserrat-VF.ttf"="montserrat/Montserrat%5Bwght%5D.ttf"; "Montserrat-Italic-VF.ttf"="montserrat/Montserrat-Italic%5Bwght%5D.ttf";
 "InstrumentSerif-Italic.ttf"="instrumentserif/InstrumentSerif-Italic.ttf"; "GochiHand-Regular.ttf"="gochihand/GochiHand-Regular.ttf"; "Caveat-VF.ttf"="caveat/Caveat%5Bwght%5D.ttf";
 "ReenieBeanie.ttf"="reeniebeanie/ReenieBeanie.ttf"; "Inter-VF.ttf"="inter/Inter%5Bopsz,wght%5D.ttf"; "DMSans-VF.ttf"="dmsans/DMSans%5Bopsz,wght%5D.ttf"; "Manrope-VF.ttf"="manrope/Manrope%5Bwght%5D.ttf";
 "BricolageGrotesque-VF.ttf"="bricolagegrotesque/BricolageGrotesque%5Bopsz,wdth,wght%5D.ttf"; "PlayfairDisplay-Italic-VF.ttf"="playfairdisplay/PlayfairDisplay-Italic%5Bwght%5D.ttf";
 "DMSerifDisplay-Italic.ttf"="dmserifdisplay/DMSerifDisplay-Italic.ttf"; "CormorantGaramond-Italic-VF.ttf"="cormorantgaramond/CormorantGaramond-Italic%5Bwght%5D.ttf";
 "Anton-Regular.ttf"="anton/Anton-Regular.ttf"; "ArchivoBlack-Regular.ttf"="archivoblack/ArchivoBlack-Regular.ttf" }
foreach ($k in $fonts.Keys) { $d = "$H\cache\fonts\$k"; if (-not (Test-Path $d)) { try { Invoke-WebRequest "$G/$($fonts[$k])" -OutFile $d -UseBasicParsing } catch { Write-Host "  font skipped: $k" } } }
foreach ($n in "Montserrat-ExtraBold.ttf","Montserrat-Black.ttf") { if (-not (Test-Path "$H\cache\fonts\$n")) { Copy-Item "$H\cache\fonts\Montserrat-VF.ttf" "$H\cache\fonts\$n" } }
$M = "$H\cache\sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8"
if (-not (Test-Path "$M\tokens.txt")) {
  Say "Downloading the speech model (~650 MB, one time)"
  Invoke-WebRequest "https://github.com/k2-fsa/sherpa-onnx/releases/download/asr-models/sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8.tar.bz2" -OutFile "$H\cache\asr.tar.bz2" -UseBasicParsing
  tar -xjf "$H\cache\asr.tar.bz2" -C "$H\cache"; Remove-Item "$H\cache\asr.tar.bz2"
}
if (-not (Test-Path "$H\cache\lucide")) {
  Invoke-WebRequest "https://registry.npmjs.org/lucide-static/-/lucide-static-0.460.0.tgz" -OutFile "$H\cache\lucide.tgz" -UseBasicParsing
  New-Item -ItemType Directory -Force "$H\cache\lucide" | Out-Null; tar -xzf "$H\cache\lucide.tgz" -C "$H\cache\lucide"; Remove-Item "$H\cache\lucide.tgz"
}
$env:Path = "$H\bin;$env:Path"
if (-not (Test-Path "$H\cache\music.wav")) { & $PY "$H\music.py" "$H\cache\music.wav" 180 }
Say "Reubx is installed. Try: $H\reubx.cmd studio --folder C:\Users\you\Videos\reels"
