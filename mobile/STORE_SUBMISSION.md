# Store submission checklist — Sound Mix Live

Operator guide for **Google Play** and **Apple App Store**.  
Media pipelines (WHEP listen, loudspeaker routing, FGS, scripture/gallery algorithms, Studio publish) were **not** changed for this prep — only legal pages, account deletion, permissions packaging, and store metadata.

## Required public URLs

| Item | URL |
|---|---|
| Privacy Policy | https://soundmix.live/privacy |
| Terms of Service | https://soundmix.live/terms |
| Support | https://soundmix.live/support |
| Account (web deletion) | https://soundmix.live/account (login required) |
| Support email | `support@soundmix.live` (server: `SUPPORT_EMAIL`) |

Set `SUPPORT_EMAIL` in production `.env` if you use a different inbox.

---

## What was implemented in the repo

### Web (Laravel)
- Public `/privacy`, `/terms`, `/support` pages covering account data, streaming, mic, gallery, speech (where used), analytics, MediaMTX/hosting, deletion
- Footer links + support email on marketing pages
- Account page: legal links + **Delete account** (password + type `DELETE`)
- API: `DELETE /api/v1/auth/account` with password (for the mobile app)
- Registration spam hardening untouched

### Android (`mobile/`)
- `compileSdk` / `targetSdk` **36**, `minSdk` **24** (Play Target API policy: API 36 required for new apps/updates from 2026-08-31)
- `applicationId` `com.livemixaudio.live_mix`
- Cleartext disabled in release; debug may allow cleartext for LAN testing
- Network security config (HTTPS-only base)
- Permissions: mic + mediaPlayback FGS + notifications; **no** `FOREGROUND_SERVICE_MICROPHONE`; **no** broad `READ_MEDIA_IMAGES` (Photo Picker for avatar)
- In-app disclosures before mic / notification runtime prompts
- `AD_ID` permission removed (`tools:node="remove"`)
- 64-bit ABI filters (`arm64-v8a`, `x86_64`)
- Release signing via `android/key.properties` or env:
  - `LIVE_MIX_KEYSTORE`, `LIVE_MIX_KEYSTORE_PASSWORD`, `LIVE_MIX_KEY_ALIAS`, `LIVE_MIX_KEY_PASSWORD`
- See `android/key.properties.example`
- In-app Privacy / Terms / Support links + Account deletion UI

### iOS (`mobile/ios/`)
- Bundle id: `com.livemixaudio.liveMix`
- Display name: **Sound Mix Live**
- `NSMicrophoneUsageDescription`, `NSPhotoLibraryUsageDescription` (optional avatar), `UIBackgroundModes: audio`
- `ITSAppUsesNonExemptEncryption` = false (standard HTTPS exemption declaration)
- `PrivacyInfo.xcprivacy` (no tracking; account/audio/photos/user id for app functionality)
- Minimum iOS **13.0**
- Podfile permission macros: microphone + notifications only
- **No ATT** (no tracking / no IDFA ads)

### Docs
- `mobile/DATA_SAFETY.md` — Play Data safety + permissions inventory (paste-ready)
- `mobile/STORE_LISTING.md` — accurate copy; withdrawn deceptive screenshots
- This file — console checklists

---

## Google Play — Data safety & permissions (cheat-sheet)

Full tables live in **`mobile/DATA_SAFETY.md`**. Summary for Console:

1. **Collects data:** Yes · **Encrypted in transit:** Yes · **Deletion:** Yes (in-app + https://soundmix.live/account) · **Ads / Advertising ID:** No  
2. **Declare:** Name, Email, User IDs, Photos (optional avatar / gallery), Audio (Studio), limited diagnostics — purposes = App functionality (+ Account management for email).  
3. **Do not declare:** Location, Advertising ID, in-app mobile chat (chat is web-only).  
4. **Permission justifications:**
   - **Microphone:** Creators publish live audio from Studio (disclosure shown in-app before OS prompt).  
   - **Notifications:** Ongoing media notification for background listen (disclosure before OS prompt).  
   - **Foreground service (mediaPlayback):** Continue live audio when backgrounded — service type `mediaPlayback` only.  
   - **Bluetooth connect:** Route audio to headsets where supported.  

---

## Google Play Console — human steps

1. **Developer account** paid and verified.
2. Create app **Sound Mix Live**, package `com.livemixaudio.live_mix`.
3. **Play App Signing**: enroll; upload an **upload keystore** (never commit it). Keep recovery of upload key offline.
4. Build AAB (preferred):
   ```bash
   cd mobile
   # with key.properties or LIVE_MIX_* env set:
   flutter build appbundle --release --dart-define=API_BASE=https://soundmix.live
   ```
5. **Store listing**: title, short/full description, icon, feature graphic 1024×500, phone screenshots — see `STORE_LISTING.md`. **Do not upload** files under `public/listing/play-store/_withdrawn/`.
6. **Privacy policy URL**: https://soundmix.live/privacy
7. **Data safety**: copy from `DATA_SAFETY.md` (Advertising ID = No).
8. **App content**:
   - Content rating (IARC questionnaire)
   - Target audience / News / COVID / Data safety / Ads (**No ads**)
   - **Account deletion**: declare in-app path (Profile → Delete) + web https://soundmix.live/account
9. **Permissions declarations**: justify mic, notifications, foreground service mediaPlayback (see cheat-sheet above).
10. **16 KB page size / 64-bit**: release build uses 64-bit ABIs; when Play flags native 16 KB alignment, rebuild with a current Flutter/NDK that meets the requirement for your submission window.
11. Submit to **Internal testing** first, then Production.

### Permissions justifications (paste-ready)

- **Microphone:** Creators publish live audio from Studio.  
- **Notifications:** Show ongoing media notification while listening in background.  
- **Foreground service (mediaPlayback):** Continue live audio when the app is backgrounded.  
- **Bluetooth connect:** Route audio to headsets where supported.

---

## TestFlight — build & upload runbook

Prefer **TestFlight** over website IPA / OTA. This machine cannot finish upload without your Apple Developer signing.

### Confirmed project settings

| Item | Value |
|---|---|
| Bundle ID | `com.livemixaudio.liveMix` |
| Display name | Sound Mix Live |
| Version / build | from `pubspec.yaml` (e.g. `1.2.13+24` → version `1.2.13`, build `24`) |
| Code sign style | Automatic (`Runner` target) |
| `DEVELOPMENT_TEAM` | **not set** in the Xcode project (fill in Xcode after Apple ID sign-in) |
| Export options template | `ios/ExportOptions.plist` (replace `YOUR_TEAM_ID`) |

### One-time Mac setup (you must run)

1. Point the active developer directory at full Xcode (needs admin password):
   ```bash
   sudo xcode-select --switch /Applications/Xcode.app/Contents/Developer
   sudo xcodebuild -runFirstLaunch
   ```
   Session workaround if `sudo` is unavailable:
   ```bash
   export DEVELOPER_DIR=/Applications/Xcode.app/Contents/Developer
   ```
2. Open **Xcode → Settings → Accounts** → add your Apple ID → download certificates / manage teams.
3. Confirm a signing identity exists:
   ```bash
   security find-identity -v -p codesigning
   ```
   You need at least one **Apple Distribution** (or Apple Development for device debug) identity.
4. Open `mobile/ios/Runner.xcworkspace` → **Runner** target → **Signing & Capabilities** → Team → enable **Automatically manage signing**.
5. [App Store Connect](https://appstoreconnect.apple.com) → create app **Sound Mix Live**, bundle id `com.livemixaudio.liveMix` (Identifiers → App IDs if missing).

### Build IPA (after signing works)

```bash
export DEVELOPER_DIR=/Applications/Xcode.app/Contents/Developer
cd /Applications/projects/mixlr_clone/mobile
flutter pub get
cd ios && pod install && cd ..
# Replace YOUR_TEAM_ID in ios/ExportOptions.plist first, or omit --export-options-plist and let Flutter/Xcode use automatic export.
flutter build ipa --release \
  --dart-define=API_BASE=https://soundmix.live \
  --export-options-plist=ios/ExportOptions.plist
```

IPA output (typical): `build/ios/ipa/*.ipa`

### Upload to TestFlight

**Option A — Flutter (when Apple credentials available):**
```bash
flutter build ipa --release \
  --dart-define=API_BASE=https://soundmix.live \
  --export-options-plist=ios/ExportOptions.plist
# Then either open the IPA in Transporter, or:
xcrun altool --upload-app --type ios -f build/ios/ipa/*.ipa \
  --apiKey YOUR_API_KEY_ID --apiIssuer YOUR_ISSUER_UUID
```
Create an App Store Connect API key under Users and Access → Integrations → App Store Connect API. Place the `.p8` where `altool` expects (`~/.appstoreconnect/private_keys/AuthKey_KEYID.p8`) or pass path per Apple docs.

**Option B — Xcode Organizer:** Product → Archive → Distribute App → App Store Connect → Upload.

**Option C — Transporter.app:** drag the `.ipa` and deliver.

`notarytool` is for Mac notarization, **not** required for iOS TestFlight IPA upload.

### After upload (App Store Connect)

1. Wait for processing (email / Activity tab).
2. TestFlight → Internal Testing → add build → add internal testers (App Store Connect users).
3. Optional: External testing + Beta App Review.
4. Fill compliance / encryption (already `ITSAppUsesNonExemptEncryption` = false).

### Blockers on this prep machine (2026-07-26)

- Agent could not run `sudo` (password / policy). If `xcode-select -p` is still Command Line Tools, run the switch commands above. Session workaround: `export DEVELOPER_DIR=/Applications/Xcode.app/Contents/Developer` (Xcode **26.6** is installed).
- Also run once: `sudo xcodebuild -runFirstLaunch` (Flutter doctor reported missing Xcode components).
- **0** code-signing identities; no provisioning profiles; no Xcode team accounts on disk (`IDEProvisioningTeams` empty).
- No `DEVELOPMENT_TEAM` written into the project (would invent a fake team id — set Team in Xcode instead).
- System Flutter at `/Applications/flutter` is **x86_64 3.5.3** and crashes on this arm64 Mac. Prefer **`/opt/homebrew/bin/flutter` (3.44.8, installed via Homebrew)** — put `/opt/homebrew/bin` first on `PATH` (ahead of `/Applications/flutter`).
- No IPA produced or uploaded from this pass (blocked on Apple signing + Xcode first-launch components).

---

## App Store Connect — human steps

1. **Apple Developer Program** membership.
2. Create app with bundle id `com.livemixaudio.liveMix`, name **Sound Mix Live**.
3. On a Mac with full **Xcode** (not only Command Line Tools):
   ```bash
   cd mobile
   flutter pub get
   cd ios && pod install && cd ..
   flutter build ipa --release --dart-define=API_BASE=https://soundmix.live
   ```
   Or open `ios/Runner.xcworkspace` and archive.
4. Upload via Transporter / Xcode Organizer.
5. **Privacy Policy URL** + **Support URL** in App Store Connect.
6. **App Privacy** questionnaire: align with `PrivacyInfo.xcprivacy` + web privacy (email, name, audio for app functionality; no tracking).
7. **Account deletion**: point to in-app Account deletion and/or https://soundmix.live/account.
8. **Export compliance**: app uses only standard HTTPS/TLS → answer that you use encryption exempt under US ERN (Info.plist already sets `ITSAppUsesNonExemptEncryption` = false).
9. Age rating questionnaire (chat/UGC may affect rating — answer accurately).
10. Screenshots per `STORE_LISTING.md`; 1024 icon.
11. TestFlight internal → App Review.

> **Note:** This environment had Command Line Tools only (no full Xcode). iOS project files are in-repo; **you must archive on a Mac with Xcode**.

---

## Preflight verification (engineering)

```bash
# Web
php artisan test --filter='LegalPagesTest|AccountDeletionTest'

# Mobile
cd mobile && flutter analyze
# Optional smoke (debug signing OK without upload keystore):
flutter build apk --release
```

Deploy web legal pages:

```bash
npm ci && npm run build
./deploy/sync-app.sh
# Ensure production .env has SUPPORT_EMAIL=...
```

---

## Explicitly out of scope / untouched

- WHEP listen client logic  
- Loudspeaker / audio session routing  
- Android FGS listen service behavior (types/permissions only declared)  
- Scripture / gallery media algorithms  
- Studio WHIP publish algorithms  
- MediaMTX configuration  

---

## Quick “done when”

- [x] https://soundmix.live/privacy|terms|support load in production (deployed 2026-07-26; `SUPPORT_EMAIL` set on server)  
- [ ] Web account deletion works for a test non-admin user  
- [ ] Mobile shows Privacy/Terms/Support + can delete account via API (build/ship binary with current `mobile/` tree)  
- [ ] Play listing + Data safety + AAB uploaded  
- [ ] App Store listing + privacy + IPA via TestFlight  
- [ ] Screenshots + feature graphic uploaded  
- [ ] Paid developer accounts active on both stores  

### Binary note (human)

Store-facing app changes (in-app legal links, account deletion, permission disclosures, targetSdk 36) are in the working tree at `pubspec` `1.2.14+25`.  
Local AAB (release, upload-key signed when `android/key.properties` is present):

`mobile/build/app/outputs/bundle/release/app-release.aab`

Rebuild before Play upload if you change versionCode:

```bash
export PATH="/opt/homebrew/bin:$PATH"
export FLUTTER_ROOT=/opt/homebrew/share/flutter
cd mobile
flutter build appbundle --release --dart-define=API_BASE=https://soundmix.live
```

Deploy web privacy/listing updates with `./deploy/sync-app.sh` (not auto-run from this pass — run when ready).  
Do **not** commit `android/key.properties` or keystores.