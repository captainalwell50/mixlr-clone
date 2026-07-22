# Live Mix (Android)

One Flutter app with **Listen** and **Studio** modes.

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
flutter run --dart-define=API_BASE=https://20.120.113.129.sslip.io
```

Release APK:

```bash
flutter build apk --release --dart-define=API_BASE=https://20.120.113.129.sslip.io
```
