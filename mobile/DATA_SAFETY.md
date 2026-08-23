# Google Play Data safety — Sound Mix Live

Paste-ready answers for Play Console → App content → Data safety.  
Aligned with https://soundmix.live/privacy and the Android app as of 2026-08-14.  
**Do not invent practices the app does not perform.**

## Overview declarations

| Question | Answer |
|---|---|
| Does your app collect or share user data? | **Yes** |
| Is all user data encrypted in transit? | **Yes** (HTTPS / TLS) |
| Do you provide a way for users to request deletion? | **Yes** — Profile → Delete account, and https://soundmix.live/account |
| Do you commit to Play Families policies? | **No** (not a Kids app) |
| Is your app designed for children? | **No** |
| Does your app use advertising ID? | **No** (`AD_ID` removed from manifest) |

## Data types collected

| Data type | Collected? | Shared with third parties? | Purpose | Optional? | Ephemeral? | Notes |
|---|---|---|---|---|---|---|
| Name | Yes | No | App functionality | No (account) | No | Display name |
| Email address | Yes | No | App functionality, Account management | No | No | Login identifier |
| User IDs | Yes | No | App functionality | No | No | Internal user id / Sanctum tokens |
| Photos | Yes | No | App functionality | Yes | No | Optional profile photo via system Photo Picker; creators may also publish gallery media via Studio/API |
| Audio files | Yes | No* | App functionality | Yes (Studio) | No* | Live mic broadcast / stream relay. *Delivered to listeners as the product — not sold to brokers |
| Other user-generated content | Yes (web) | No | App functionality | Yes | No | Chat on **website** listen/event pages when enabled — **not** in mobile Listen tab |
| App interactions / diagnostics | Limited | No | Analytics / App functionality | Yes | Varies | Presence, stream health, crash/ops logs — **not** ad analytics SDKs |
| Approximate / precise location | No | — | — | — | — | IP may appear in server logs for security only |
| Device or other IDs (AAID) | No | — | — | — | — | Declare Advertising ID = No |

\* “Shared” in Play Data safety means sold/transferred to third parties for their purposes — not the fact that listeners hear the live stream.

## Security practices

- Encrypted in transit: **Yes**
- Users can request deletion: **Yes**
- Independent security review: **No** (unless obtained later)

## Permissions inventory (merged release manifest)

| Permission | Declared? | Why | Runtime + disclosure |
|---|---|---|---|
| `INTERNET` | Yes | API + live listen/publish | Normal |
| `ACCESS_NETWORK_STATE` | Yes | Connectivity banner / offline cache | Normal |
| `RECORD_AUDIO` | Yes | Studio mic publish only | In-app disclosure → OS prompt before Studio preview |
| `MODIFY_AUDIO_SETTINGS` | Yes | Audio routing for listen/publish | Normal |
| `BLUETOOTH` (maxSdk 30) | Yes | Legacy headset routing | Normal |
| `BLUETOOTH_CONNECT` | Yes | Headset routing on API 31+ | Requested by OS/WebRTC when needed |
| `WAKE_LOCK` | Yes | Keep listen session alive with FGS | Normal |
| `FOREGROUND_SERVICE` | Yes | Background listen | Normal |
| `FOREGROUND_SERVICE_MEDIA_PLAYBACK` | Yes | Listen FGS type = `mediaPlayback` | Matches service declaration |
| `FOREGROUND_SERVICE_MICROPHONE` | **No** | Studio publish runs in foreground Activity — no mic FGS | Do not declare |
| `POST_NOTIFICATIONS` | Yes | Ongoing media notification while listening | In-app disclosure → OS prompt |
| `READ_MEDIA_IMAGES` / `READ_EXTERNAL_STORAGE` | **Removed** | Avatar uses system Photo Picker | Do not re-add |
| `AD_ID` | Removed | No ads / no advertising ID | Declare No in Data safety |

## Support / privacy URLs

- Privacy: https://soundmix.live/privacy
- Terms: https://soundmix.live/terms
- Support: https://soundmix.live/support
- Support email: support@soundmix.live (override with `SUPPORT_EMAIL` on server)
