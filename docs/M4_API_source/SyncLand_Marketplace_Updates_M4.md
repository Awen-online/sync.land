## Executive summary

Between M3 submission (2026-06-23) and this M4 draft (2026-07-15), the Sync.Land engineering effort delivered **32 substantive commits** across seven functional areas. The following table summarizes the shipped work; a full changelog by category is in §2, and every commit's SHA + date is in §3 for verification.

| Area | Commits | Highlights |
|---|---:|---|
| Licensing model | 6 | SLFS-v1 free-tier shipped, replacing CC-BY 4.0; versioned ToS + admin consent audit |
| External application API | 4 | New `/streamer/*` REST namespace + OBS Player integration |
| Marketplace UX | 8 | Brief teaser + Register CTA, artist-form CTA, licensing / about pages |
| Mobile presentation | 4 | Dark drawer, single-hamburger, edge-to-edge fixes |
| Communications | 5 | Transactional license/payout emails, artist nudge batch |
| Bug fixes | 5 | Songs discovery shortcode + wpautop conflicts |

---

## 1 · Acceptance criteria mapping

M4 acceptance criteria (approved 2024-03-01) and where each is satisfied:

| Criterion | Delivered by |
|---|---|
| User feedback gathered, analyzed, put into a document | `SyncLand_User_Feedback_Report_M4.pdf` (companion document) |
| Marketplace updates + bugfixes clearly displayed in documentation | This document (§2, §3) |
| Test case: external application verifies a sync license via public API | OBS Player deployed at `sync.land/dock/`; every playback triggers `/streamer/track/{id}/clearance` |
| Documentation clearly written | This document + `SyncLand_API_Documentation_M4.pdf` + repo READMEs + inline docstrings |

---

## 2 · Full changelog by category

### 2.1 Licensing model — Sync.Land Free Sync License (SLFS-v1)

The M3 marketplace launched with CC-BY 4.0 as the free tier. In M4, that was replaced by the **Sync.Land Free Sync License (SLFS-v1.0-2026-07-11)**, a purpose-built license for creator-scale sync usage with required attribution. Key differences from CC-BY: explicit permission for Twitch VOD and YouTube monetized-video use under the UGC clause, tighter attribution format spec, and a commercial-tier upgrade path built in.

- `41f954e` (2026-07-11) — Free tier switched from CC-BY 4.0 to SLFS-v1
- `ca3dfcb` (2026-07-11) — SLFS-v1.0-2026-07-11 PDF certificate rendered + committed
- `9f9346a` (2026-07-12) — Cleaner SLFS-v1 PDF render (no Chrome timestamp header)
- `c1fd188` (2026-07-08) — Version-controlled snapshots of Terms + Privacy pages
- `733da8b` (2026-07-08) — Song-upload flow: Terms link corrected to `/terms-and-conditions/`
- `7c2c562` (2026-07-10) — Song rights metadata schema + Terms Signatures admin + licensing controls

### 2.2 External application API — the `/streamer/*` REST namespace

New in M4: a Personal Access Token-authenticated REST namespace at `/wp-json/FML/v1/streamer/*` that lets external applications verify a sync license, fetch clearance, and log playback. The **Sync.Land OBS Player** at `sync.land/dock/` is the reference implementation and lives in its own public repo (`Awen-online/syncland-obs-player`, Apache 2.0).

- `4cac343` (2026-07-13) — `syncland-streamer-api.php` mu-plugin: PAT auth, 7 REST endpoints, admin token UI
- `ceb86f7` (2026-07-13) — API spec v1.2.0 + CHANGELOG.md for marketplace updates since M3
- `5698e18` (2026-07-15) — CORS on `/streamer/*` + enriched playlist/clearance responses (adds cover_url, duration, album per track)
- `b659fb7` (2026-07-13) — Per-song clearance PDF attachments visible in the song CPT edit UI

### 2.3 Marketplace UX — conversion + discoverability

- `3d0c3dc` (2026-07-13) — `/brief/*` anon prospect rescue: teaser page + Register CTA replacing the 302-to-login dead-end (fixes the observed UK anon-prospect bounce pattern)
- `61d174e` (2026-07-13) — `/account/artist-registration/` dead-end message replaced with a CTA card linking to `/registration/`
- `78f2cb2` (2026-07-12) — Dual-audience `[syncland_licensing_v1]` licensing page (mu-plugin)
- `bdb7518` `6629281` (2026-07-12) — Real `/about/` page shipped; lead with Sync.Land, Awen contextualized at bottom
- `41be290` (2026-07-12) — Licensing page: mobile edge-to-edge padding fix matching `/about/`
- `dbe4259` (2026-07-07) — Opportunities board: signup link fix + duplicate title hide
- `2f6409d` (2026-07-13) — Songs discovery: license filter + mobile layout
- `bc9f0f5` (2026-07-12) — Stripe conversion tracking: completed license sale recorded as behavior conversion

### 2.4 Mobile presentation

- `3d73044` (2026-07-12) — Single-hamburger, right-anchored mobile layout
- `0dad10b` (2026-07-12) — Dark-theme the dropdown drawer
- `f194d8d` (2026-07-12) — Purge white background on hamburger drawer

### 2.5 Communications & artist nudges

- `8a97d5a` (2026-07-07) — `syncland-opportunities.php` mu-plugin — the opportunities board core
- `07346fd` (2026-07-07) — One-off artist nudge batch (2026-07-08 8am UTC send)
- `79e18c2` (2026-07-07) — Nudges self-verify: confirm to `cullah@` on send, alert at 9am if it never fired
- `eead3a7` (2026-07-07) — Transactional emails: notify the artist when their track is licensed + on payout

### 2.6 Bug fixes

The `[songs_discovery]` shortcode was rendering with intermittent `<p>` tag pollution on certain Elementor + WordPress-content combinations. Multi-commit resolution:

- `8c48a95` (2026-07-13) — Collapse inter-tag whitespace to defeat wpautop
- `b8e88f1` (2026-07-13) — Strip HTML comments (wpautop was wrapping them in `<p>`)
- `5f97168` (2026-07-13) — Suppress wpautop on pages containing `[songs_discovery]`
- `45cb768` (2026-07-13) — Use `do_shortcode_tag` filter to scrub wpautop damage
- `b4ab772` (2026-07-13) — Force `filemtime` cache-bust on songs-discovery asset

Result: shortcode output is now stable across all page-composition contexts.

### 2.7 Analytics discipline

- `1eaa88c` (2026-07-12) — Analytics: stand down when `awen-client` owns behavior tracking (prevents double-counting)

---

## 3 · Verifiable commit history

Every commit SHA below is in the private dev repo `Awen-online/sync-land`. Public commits corresponding to milestone-appropriate open-core inclusions will be curated into `Awen-online/sync.land` (this repo) prior to M4 submission.

| SHA | Date (UTC) | Subject |
|---|---|---|
| 5698e18 | 2026-07-15 | streamer-api: CORS + enrich playlist/clearance responses |
| 3d0c3dc | 2026-07-13 | briefs: replace anon 302-to-login with rich teaser + Register CTA |
| 61d174e | 2026-07-13 | artist-form: replace anon dead-end message with CTA |
| 45cb768 | 2026-07-13 | songs discovery: use do_shortcode_tag filter to scrub wpautop damage |
| 5f97168 | 2026-07-13 | songs discovery: kill wpautop on pages containing [songs_discovery] |
| b8e88f1 | 2026-07-13 | songs discovery: strip HTML comments |
| 8c48a95 | 2026-07-13 | songs discovery: collapse inter-tag whitespace |
| b4ab772 | 2026-07-13 | asset-version fix: force filemtime cache-bust |
| 2f6409d | 2026-07-13 | songs discovery: license filter + mobile layout |
| 4cac343 | 2026-07-13 | streamer-api: mu-plugin backing the OBS Player |
| ceb86f7 | 2026-07-13 | M4 docs: api-spec v1.2.0 + CHANGELOG.md |
| b659fb7 | 2026-07-13 | song CPT: attach clearance PDFs as WP attachments |
| bc9f0f5 | 2026-07-12 | stripe: record completed license sale as behavior conversion |
| f194d8d | 2026-07-12 | mobile header: purge white background on hamburger drawer |
| 0dad10b | 2026-07-12 | mobile header: dark-theme the dropdown drawer |
| 3d73044 | 2026-07-12 | header UX: single-hamburger, right-anchored mobile layout |
| 1eaa88c | 2026-07-12 | analytics: stand down when awen-client owns behavior tracking |
| 41be290 | 2026-07-12 | licensing page: same mobile edge-to-edge fixes as about |
| 6629281 | 2026-07-12 | about page: lead with Sync.Land, bury Awen at bottom |
| bdb7518 | 2026-07-12 | about + licensing: real /about/ page + mobile padding fix |
| 78f2cb2 | 2026-07-12 | licensing: dual-audience [syncland_licensing_v1] pitch page |
| 9f9346a | 2026-07-12 | docs/legal: cleaner SLFS-v1 PDF render |
| ca3dfcb | 2026-07-11 | docs/legal: add rendered SLFS-v1.0-2026-07-11.pdf |
| 41f954e | 2026-07-11 | free-tier: replace CC-BY 4.0 with Sync.Land Free Sync License |
| 7c2c562 | 2026-07-10 | Song rights metadata, terms signatures admin, licensing controls |
| c1fd188 | 2026-07-08 | docs/legal: version-controlled snapshots of Terms + Privacy |
| 733da8b | 2026-07-08 | songupload: fix Terms link |
| 79e18c2 | 2026-07-07 | nudges: self-verify — confirm on send, alert if never fired |
| 07346fd | 2026-07-07 | mu-plugin: one-off artist nudge batch |
| eead3a7 | 2026-07-07 | emails: notify artist on license + on payout |
| dbe4259 | 2026-07-07 | opportunities board: correct signup link + hide duplicate title |
| 8a97d5a | 2026-07-07 | mu-plugin: sync.land opportunities board |

Repository: `git@github.com:Awen-online/sync-land.git` (private) — reviewer access on request. Public Catalyst-open-core mirror: `Awen-online/sync.land`.

---

## 4 · Post-draft addendum (2026-07-15 → 2026-07-29)

Fourteen days of continued shipping between draft and submission. Additional commits in the private dev repo:

| SHA | Date (UTC) | Subject | Area |
|---|---|---|---|
| 6706c17 | 2026-07-29 | GA4 conversion tracking: `sign_up` server-side cookie + `song_upload` client event | Analytics |
| dd8bd00 | 2026-07-27 | pitches: sortable/filterable table + honest status collapse + prominent CTA | Marketplace UX |
| 143737a | 2026-07-27 | account: dedupe landing nav — gallery-only on dashboard, tabs on sub-pages | Account UX |
| d3721d5 | 2026-07-27 | account: `/account/pitches/` — artists see their own pitches + brief lifecycle | Marketplace UX |
| 90f81e5 | 2026-07-27 | mu-plugins: check in `login-hardening` + `survey-pulse` from prod | Repo hygiene |
| 8c6b78c | (prod, 2026-07-27) | terms: capture acceptance on registration (was backfill-only) | Licensing model |

**What these strengthen for M4:**

- **`/account/pitches/`** — artist-facing UI to observe the full pitch lifecycle (Awaiting review · Forwarded · Passed · Interested · Licensed), addressing the Shane Harvey follow-up thread about "how do briefs expire and where do I see status?" — closes the last visible loop on the pitch mechanism.
- **GA4 conversion tracking** — `sign_up` and `song_upload` now flow to the Awen Pulse GA4 property, enabling quantitative validation of the funnel between M4 close and M5 submission.
- **Terms-capture on registration** — new registrants sign the current ToS at the moment of account creation (not just legacy backfill), aligning consent capture with account activation.
- **Repo hygiene** — the two prod-only mu-plugins were reconciled into the private dev repo; prod ↔ repo is now in full parity (see `reference_syncland_git_deploy` for verification recipe).

### 4.1 Live API verification (2026-07-29)

The `/streamer/*` REST namespace was re-verified end-to-end against production on 2026-07-29 with a freshly-minted PAT. All four public endpoints returned expected payloads:

- `GET /streamer/me` → HTTP 200 (identity confirmed)
- `GET /streamer/playlists` → HTTP 200 (4 playlists, full track metadata)
- `GET /streamer/track/10505/clearance` → HTTP 200 (SLFS-v1 tier, attribution text populated, signed stream URL)
- `POST /streamer/track/10505/played` → HTTP 200 (play event logged to `wp_fml_analytics_events`)

Full response transcript in `SyncLand_API_Documentation_M4.pdf` §7.

### 4.2 Current production numbers (2026-07-29)

| Metric | Value |
|---|---:|
| Published songs | 380 |
| Registered users | 47 |
| Published sync licenses | 7 |
| Post-M3 artist pitches (cumulative) | 30 |
| Terms signatures (real, versioned) | 9 across 5 users |
| Post-M3 analytics events (cumulative) | 3,400+ |

---

## 5 · Second addendum (2026-07-29 → 2026-08-07)

Work shipped between the first addendum and M4 submission. Three of these are defect fixes found by auditing the M4 evidence itself, which is worth stating plainly: preparing this submission surfaced real production bugs, and they were fixed rather than papered over.

### 5.1 Commits

| Commit | Date | Change |
|---|---|---|
| `54f3b12` | 2026-08-02 | **feat:** self-service delete for empty artist profiles — used in the wild by a real artist two days later (see *User Feedback Report* §10.2) |
| `487fab1` | 2026-08-04 | **fix:** `wp_unslash` POST titles so notification emails no longer render `\"` escapes |
| `0a28f67` | 2026-08-04 | **content:** `/about/` rebuilt full-width; Creative Commons phrasing removed from all public marketing surfaces |
| `a0b09c7` | 2026-08-07 | **fix:** album playback was silent — missing `crossOrigin` pre-stamp |
| `dca5503` | 2026-08-07 | **fix:** public license-verification endpoint never reported NFT verification |

### 5.2 Defect: album playback was silent (`a0b09c7`)

Pressing play on an album cover loaded the track and flipped the control to "pause", but produced **no audio and no error**. Root cause: two of the three playback paths set the `crossOrigin` attribute on the audio element *before* assigning a source; the album-grid path did not. The visualiser then attached a Web Audio `MediaElementSource` to a stream fetched without CORS, which outputs silence while the element reports playing. Fixed by aligning the third path, plus hardening a fallback guard that had been masking its own repair.

### 5.3 Defect: audio served with the wrong MIME type

36 audio objects were being served as `application/octet-stream` rather than `audio/mpeg` — a MIME that Safari and iOS refuse to decode in an `<audio>` element. All 36 were rewritten in place (content type corrected, public ACL and CORS preserved, byte content and URLs unchanged) and verified. The upload service that omits the header is logged for M5 remediation so new uploads land correctly at source.

### 5.4 Defect: license verification never reported on-chain status (`dca5503`)

**This one directly affected M4's headline evidence.** The public verification endpoint (*API Documentation* §1b) returned `nft_verified: false` for **every license ever issued**, omitted the blockchain block entirely, and mislabelled every license type.

Cause: the CMS field accessor returns a single-element array on first access and normalises to a scalar only on subsequent reads. The endpoint reads each field exactly once, so every strict comparison silently failed — `['minted'] === 'minted'` is false.

Separately, the mint transaction hash for the M3 demonstration license was never persisted. The minting service no longer holds that project's testnet records, but **the asset is still on the Cardano preprod chain**, so the hash was recovered directly from public chain indexers and restored. The license now verifies correctly and returns a working block-explorer URL.

That the on-chain record could be recovered *after* the minting provider's own copy was gone is an unplanned but pointed demonstration of the architecture's central claim: the blockchain record is authoritative independent of any intermediary, including us.

### 5.5 Content accuracy pass

Creative Commons language was removed from all public marketing surfaces (`/about/`, `/licensing/`, `/briefs/`), completing the SLFS-v1 transition begun in §2.1. The single remaining reference is deliberate: a licensing-page FAQ entry stating that licenses issued before 2026-07-11 were issued under CC-BY 4.0 and remain valid under their original terms. Removing that would misrepresent existing license holders.

A legacy page from the platform's pre-Sync.Land era, which described CC-BY attribution rules as if current, was unpublished — it was giving creators instructions for a license they could no longer obtain.

### 5.6 Production numbers

Read from production on each date. The final column is the state at submission.

| Metric | 2026-07-29 | 2026-08-07 | 2026-08-17 |
|---|---:|---:|---:|
| Published songs | 380 | 398 | **402** |
| Published albums | 53 | 56 | **58** |
| Published artist profiles | 18 | 19 | **21** |
| Registered users | 47 | 48 | **53** |
| Published sync licenses | 7 | 7 | **8** |
| Post-M3 artist pitches (cumulative) | 30 | 30 | **32** |
| Artist territories represented | — | — | **6** |

Growth to 2026-08-07 came from a single organic self-service onboarding. The
final window added four more self-service artist signups (Bergs, EmmyFL,
ItsHIRAD, MarioSalseo), and territory became measurable for the first time when
artist location shipped.

---

*Prepared for Sync.Land / Awen LLC · Cardano Project Catalyst Fund 11*

---

## 6 · Third addendum (2026-08-07 → 2026-08-17)

Work shipped after the second addendum. Site commits in `Awen-online/sync-land`,
dock commits in `Awen-online/syncland-obs-player` (public, Apache 2.0).

### Defects found and fixed

| What | Detail |
|---|---|
| **Duplicate artist profiles** | Create-versus-edit was decided solely by the presence of `?artist_edit_id`, with the only ownership check inside the edit branch. Returning to `/account/artist-registration/` silently minted a second artist. **Every artist who signed up in August hit it**, including one who renamed their duplicate to "I ACCIDENTLY MADE 2 JUST IGNORE THIS ONE" and left it published for three days. Access logs confirmed the click path. Fixed with a guard counting every non-trashed status, plus a confirmation step for genuine second acts. |
| **`/account/edit-profile/` rendered no form** | It delegated to User Registration and produced a page saying "you can edit your profile details" with zero inputs. This is what sent users hunting, and then to the registration page. Rebuilt as a real screen that leads with the artist profile. |
| **Song cover art missing on new uploads** | The album-upload flow attaches artwork to the album and never to the songs, and the API only read the song thumbnail. Every track from the five newest artists returned an empty `cover_url`. Added an album fallback: coverage 365/402 → **390/402**. |
| **Playlist track cap** | A hard `'limit' => 50` that truncated silently rather than paginating. Lifted to 2,000 for administrators; the underlying endpoint still needs real pagination. |
| **`/licensing/` and `/briefs/` not full width** | The theme caps `main.site-main` at 1140px; the existing reset only cleared `.page-content` padding and never touched the cap. |
| **Brief intake used the wrong palette** | The "Post a brief" card was `#1f1f33` on a page whose brief cards are `#141a3a`, and its primary button rendered the theme's link blue on a pink gradient. |
| **Dock built against the dev API** | Vite ranks `.env.local` above `.env.production`, so every deployed bundle since July baked in `http://sync.local`. The deployed dock had never been able to reach production. |

### Features

- **Artist location** — `country_code` + `location` on the artist pod, a flag on the profile via `[syncland_artist_location]`, a country filter and card badge on `/artists/`, and country in the artists REST payload. All 21 published artists backfilled from signup and legacy IPs, three provenances recorded (`artist-bio`, `signup-ip`, `artist-ip`).
- **`/briefs/` page chrome** — hero, live stat strip, section rhythm, full-width.
- **Streaming Tokens menu entry** — closes Task #33.
- **OBS Player v0.2.0** — persistent transport, overlay lower third, OBS audio routing, five themes, settings screen, fade and duck. Detailed in the architecture document.

### Verifiable commits

| Repo | Commit | Summary |
|---|---|---|
| sync-land | `af37b54` | artist location, briefs chrome, account profile, duplicate guard |
| sync-land | `572a728` | signup country capture; pitch dead end for new artists |
| sync-land | `42bd528` | render saved bios; album editing after upload |
| sync-land | `6939b28` | NMKR duplicate-mint guard verifies rather than infers |
| sync-land | `0ec5888` | Tier-0 receipt copy, ISRC at upload, declared usage at checkout |
| syncland-obs-player | `b522ae9` | dock v0.2.0 |
