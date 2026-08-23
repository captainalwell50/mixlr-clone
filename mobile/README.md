# Sound Mix Live

Flutter app with **Listen** and **Studio** modes — Android, iOS, macOS, and Windows.

API host: **https://soundmix.live**

Store prep: see `STORE_SUBMISSION.md`, `STORE_LISTING.md`, and `DATA_SAFETY.md`.

## Features

- Branded splash + first-run welcome
- Network health pill + offline banner
- Discover cache for offline browsing
- **Studio (phone + desktop):** mic preview, signal meter, go live / pause / end via WHIP
- Listen: duration, presence, likes
- Desktop: native window, NavigationRail, clean mic capture (no AGC/NS)

Playlist / library / cues stay on the **web Studio** for now. Desktop Studio v1 is the rugged mic publisher.

## Setup

```bash
cd mobile
flutter pub get
```

### Android

```bash
flutter run --dart-define=API_BASE=https://soundmix.live
flutter build apk --release --dart-define=API_BASE=https://soundmix.live
# Play upload (with upload keystore configured):
flutter build appbundle --release --dart-define=API_BASE=https://soundmix.live
```

### iOS

Requires full **Xcode**. Bundle id `com.livemixaudio.liveMix`.

```bash
cd mobile/ios && pod install && cd ..
flutter run -d ios --dart-define=API_BASE=https://soundmix.live
flutter build ipa --release --dart-define=API_BASE=https://soundmix.live \
  --export-options-plist=ios/ExportOptions.plist
```

TestFlight steps (Apple ID, Team, upload): see **TestFlight — build & upload runbook** in `STORE_SUBMISSION.md`. Replace `YOUR_TEAM_ID` in `ios/ExportOptions.plist` first.

### macOS (Desktop Studio)

Requires a full **Xcode** install (not only Command Line Tools):

```bash
sudo xcode-select --switch /Applications/Xcode.app/Contents/Developer
sudo xcodebuild -runFirstLaunch
cd mobile/macos && pod install && cd ..
flutter run -d macos --dart-define=API_BASE=https://soundmix.live
flutter build macos --release --dart-define=API_BASE=https://soundmix.live
```

App bundle:

`build/macos/Build/Products/Release/Sound Mix Live.app`

### Windows

```bash
flutter run -d windows --dart-define=API_BASE=https://soundmix.live
flutter build windows --release --dart-define=API_BASE=https://soundmix.live
```

## Downloads page

When you have a signed/notarized Mac build, upload it and set:

```env
DOWNLOAD_MACOS_URL=https://soundmix.live/downloads/Sound-Mix-Live-Studio.dmg
```

## Note on Electron

`../desktop/` (Electron shell) is **deprecated** in favor of this Flutter desktop app. Prefer Flutter for a native feel.
