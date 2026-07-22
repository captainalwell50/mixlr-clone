# Live Mix (Android)

One Flutter app with **Listen** and **Studio** modes.

API host: **https://soundmix.live**

## Features

- Branded splash + first-run welcome
- Network health pill + offline banner
- Discover cache for offline browsing
- Studio: live duration, signal meter, mic preview
- Listen: duration, presence, likes

## Setup

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_BASE=https://soundmix.live
```

Release APK:

```bash
flutter build apk --release --dart-define=API_BASE=https://soundmix.live
```
