# Sound Mix Live Studio (Desktop)

Premium **Studio-only** desktop console — Mixer v2.  
Sign in → live console with **mic + playlist + cue**, Go live / Pause / End.

Separate from the Android listener app in `../mobile/`.

Uses a **native AVAudioEngine + WHIP** mixer on macOS (MethodChannel). Sign-in hits `{API_BASE}/api/v1`.

## Run

```bash
cd studio_desktop
flutter pub get
cd macos && pod install && cd ..

export DEVELOPER_DIR=/Applications/Xcode.app/Contents/Developer
flutter run -d macos --dart-define=API_BASE=https://soundmix.live
```

## Release build

```bash
export DEVELOPER_DIR=/Applications/Xcode.app/Contents/Developer
cd studio_desktop
flutter build macos --release --dart-define=API_BASE=https://soundmix.live
```

App:

`build/macos/Build/Products/Release/Sound Mix Live Studio.app`

## Windows

```bash
flutter run -d windows --dart-define=API_BASE=https://soundmix.live
flutter build windows --release --dart-define=API_BASE=https://soundmix.live
```

## Mic hot-swap while live (regression)

Changing the input device **while ON AIR** must not leave Listen / publish at permanent silence.

Native path (`NativeAudioEngine.hotSwapInputDevice` + `NativeWhipPublisher.pauseCaptureForDeviceSwap`):

1. Pause WebRTC ADM (releases device contention)
2. Stop capture edge → bind new CoreAudio input → rewire mic/playlist → master
3. Reinstall master tap into the same 48 kHz WHIP ring
4. Rebind ADM; `PublishContinuityHold` bridges the brief gap with last good PCM

XCTest: `macos/RunnerTests/RunnerTests.swift` (`PublishContinuityHold`).

## Downloads page

Package and upload to the VM (`public/downloads/`), then set env on production:

```env
DOWNLOAD_MACOS_URL=https://soundmix.live/downloads/Sound-Mix-Live-Studio.dmg
DOWNLOAD_ANDROID_APK_URL=https://soundmix.live/downloads/Sound-Mix-Live.apk
DOWNLOAD_WINDOWS_URL=https://soundmix.live/downloads/Sound-Mix-Live-Studio-Windows.zip
```

Mac DMG (from a release `.app`):

```bash
# see public/downloads/ after packaging
hdiutil create -volname "Sound Mix Live Studio" -srcfolder /path/to/stage -ov -format UDZO public/downloads/Sound-Mix-Live-Studio.dmg
```

Windows: build on Windows or via `.github/workflows/build-windows-studio.yml`, then upload the zip.
