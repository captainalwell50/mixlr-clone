# Store listing assets — Sound Mix Live

## Shared copy (draft)

**App name:** Sound Mix Live  
**Short description (≤80 chars):** Live audio — listen, scripture, gallery, and Studio mic publish.  
**Full description (draft):**

Sound Mix Live is live audio for gatherings — listen on your phone, follow scripture and gallery when a channel is live, and go on air from Studio with your mic.

What you get in the Android app:
• Listen — discover live channels and keep listening with background audio
• Scripture — follow the live scripture board for the selected channel
• Gallery — view photos/reels shared while a channel is live
• Profile — account, optional profile photo, Studio go-live entry
• Studio — mic preview and go live / pause / end (full playlist mixer stays on the web Studio)

Chat and hearts are on the website listen/event pages when enabled — not inside the mobile Listen tab.

Privacy: https://soundmix.live/privacy  
Terms: https://soundmix.live/terms  
Support: https://soundmix.live/support  

**Category:** Music & Audio (or Social)  
**Content rating:** Complete IARC questionnaire in each console. Note: web chat/UGC may affect rating even if chat is not in the mobile Listen tab.  
**Contact email:** support@soundmix.live  
**Privacy policy URL:** https://soundmix.live/privacy  

## Google Play screenshot sizes

Prepare **phone** screenshots (required):

| Device | Recommended size |
|---|---|
| Phone | **1080 × 1920** (or 1080 × 2340) PNG/JPEG |
| 7" tablet (optional) | 1200 × 1920 |
| 10" tablet (optional) | 1920 × 1200 |

**Feature graphic (required):** **1024 × 500** PNG/JPEG — brand mark + “Sound Mix Live” wordmark on dark stage background. No excessive UI chrome.

**App icon:** 512 × 512 PNG (Play), high-res. Source candidate: `mobile/assets/brand/soundmix-icon-1024.png` (export/crop to store specs).

**Approved screenshot set in `public/listing/play-store/`:**
1. `screenshot-1.png` — Welcome (nav Listen / Scripture / Gallery / Profile)
2. `screenshot-scripture.png` — Scripture tab
3. `screenshot-gallery.png` — Gallery tab

**Still needed (capture from a real device/emulator — do not invent UI):**
4. Listen tab — Discover list of live channels  
5. Listen room — playback UI (likes OK; **no fake chat**)  
6. Studio go-live — mic strip / Go live ( **no multi-channel mixer**; app copy says mixer stays on web)

**Withdrawn (do not upload):** `public/listing/play-store/_withdrawn/` — old mockups with wrong nav, fake chat, and fake mixer.

## Apple App Store screenshot sizes

| Device class | Portrait size (common) |
|---|---|
| 6.7" (iPhone 15 Pro Max class) | **1290 × 2796** |
| 6.5" | **1242 × 2688** |
| 5.5" (legacy if required) | **1242 × 2208** |
| iPad Pro 12.9" (if iPad) | **2048 × 2732** |

Upload at least the latest required iPhone sizes App Store Connect shows for your account year.

**App icon:** 1024 × 1024 (no alpha). Use brand icon asset above.

## Localization

Start with **English (US)** listing. Add more locales later if needed.

## Version string

Mobile `pubspec.yaml` currently drives `versionName`/`CFBundleShortVersionString` (e.g. `1.2.14+25`).  
Bump only when uploading a new binary.
