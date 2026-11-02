@echo off
rem Reubx launcher for Windows (Command Prompt / PowerShell). Same commands as the bash launcher.
setlocal
set "H=%~dp0"
set "PY=%H%.venv\Scripts\python.exe"
if /I "%1"=="install" goto install
if not exist "%PY%" goto install
if not exist "%H%cache\music.wav" goto install
goto run
:install
powershell -ExecutionPolicy Bypass -NoProfile -File "%H%install.ps1" || exit /b 1
if /I "%1"=="install" exit /b 0
:run
set "PATH=%H%bin;%USERPROFILE%\.local\bin;%PATH%"
set PYTHONIOENCODING=utf-8
set "CMD=%1"
shift
if /I "%CMD%"=="studio" ( "%PY%" "%H%studio\server.py" %1 %2 %3 %4 %5 %6 & exit /b )
if /I "%CMD%"=="watch" ( "%PY%" "%H%studio\watch.py" %1 %2 %3 %4 %5 %6 & exit /b )
"%PY%" "%H%run.py" %CMD% %1 %2 %3 %4 %5 %6 %7 %8
