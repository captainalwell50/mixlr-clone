# Package Flutter Windows Release into a single installer .exe (Inno Setup).
# Requires: Inno Setup 6 (choco install innosetup) and a completed
# `flutter build windows --release`.
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

$release = Join-Path $Root "build\windows\x64\runner\Release"
if (-not (Test-Path (Join-Path $release "soundmix_studio.exe"))) {
  throw "Missing $release\soundmix_studio.exe — run flutter build windows --release first."
}

$iss = Join-Path $Root "windows\installer\studio.iss"
$iscc = Join-Path ${env:ProgramFiles(x86)} "Inno Setup 6\ISCC.exe"
if (-not (Test-Path $iscc)) {
  $iscc = Join-Path $env:ProgramFiles "Inno Setup 6\ISCC.exe"
}
if (-not (Test-Path $iscc)) {
  throw "Inno Setup 6 not found. Install with: choco install innosetup -y"
}

$verLine = Get-Content (Join-Path $Root "pubspec.yaml") | Where-Object { $_ -match '^version:' } | Select-Object -First 1
$ver = ($verLine -split ':', 2)[1].Trim().Split('+')[0]

& $iscc "/DMyAppVersion=$ver" $iss
if ($LASTEXITCODE -ne 0) { throw "ISCC failed with exit $LASTEXITCODE" }

$out = Join-Path $Root "dist\Sound-Mix-Live-Studio.exe"
Write-Host "Installer: $out"
