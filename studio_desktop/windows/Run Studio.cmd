@echo off
setlocal
cd /d "%~dp0"

REM Files downloaded from the web are marked "from the Internet". Windows can
REM then block Flutter DLLs with no error dialog. Clear that mark first.
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-ChildItem -LiteralPath '%~dp0' -Recurse -File -ErrorAction SilentlyContinue | Unblock-File -ErrorAction SilentlyContinue"

start "" "%~dp0soundmix_studio.exe"
