# Live Mix (Android)

One Flutter app with **Listen** and **Studio** modes. Android-first.

## Setup

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_BASE=https://20.120.113.129.sslip.io
```

## Modes

- **Listen** — discover live public streams, play HLS, presence + like
- **Studio** — creator login (Sanctum), mic → WHIP publish, go-live / end

Full console mixer (playlist, cues, multi-input) remains on the web Studio.
