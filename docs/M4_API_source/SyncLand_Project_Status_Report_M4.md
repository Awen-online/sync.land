## 1 · Milestone status

| Milestone | Cost (ADA) | % of project | Delivery month | Status |
|---|---:|---:|---:|---|
| M1 · Initialization | 20,000 | 20% | 1 (Apr 2024) | ✅ Delivered + signed off |
| M2 · Development | 25,000 | 25% | 3 (Jun 2024) | ✅ Delivered + signed off |
| M3 · Marketplace Launch & API Development | 20,000 | 20% | 21 (Dec 2025) | 📮 Delivered 2026-06-23, awaiting reviewer signoff |
| **M4 · Marketplace Updates & API Launch** | **20,000** | **20%** | **21 (Dec 2025)** | 📄 **This submission** |
| M5 · Finalization | 15,000 | 15% | 22 (Jan 2026) | ⏳ In preparation |

**Total project ADA:** 100,000 · **M4 ADA:** 20,000

---

## 2 · M4 acceptance criteria — status

| # | Criterion | Status | Evidence |
|---|---|---|---|
| 1 | User feedback is gathered, analyzed, and put into a document | ✅ Complete | `SyncLand_User_Feedback_Report_M4.pdf` |
| 2 | Marketplace updates + bugfixes clearly displayed in documentation | ✅ Complete | `SyncLand_Marketplace_Updates_M4.pdf` (32 commits, 7 categories) |
| 3 | Test case: external application verifies a sync license via public API | ✅ Shipped, ⏳ recording pending | OBS Player v0.2.0 at `sync.land/dock/`, live and verified. The dock surfaces the request, HTTP status, latency and verdict on screen and exports a copyable receipt. Screen recording is the last artefact outstanding. |
| 4 | Documentation is clearly written | ✅ Complete | This pack + repo READMEs + API spec |

---

## 3 · M4 deliverables inventory

| Deliverable | File | Format | Location |
|---|---|---|---|
| Project status | `SyncLand_Project_Status_Report_M4.pdf` | PDF | `docs/M4_API/` |
| Timeline update | `SyncLand_Project_Timeline_M4.pdf` | PDF | `docs/M4_API/` |
| User feedback | `SyncLand_User_Feedback_Report_M4.pdf` | PDF | `docs/M4_API/` |
| Marketplace updates | `SyncLand_Marketplace_Updates_M4.pdf` | PDF | `docs/M4_API/` |
| API documentation | `SyncLand_API_Documentation_M4.pdf` | PDF | `docs/M4_API/` |
| OBS Player architecture | `SyncLand_OBS_Player_Architecture_M4.pdf` | PDF | `docs/M4_API/` |
| Test case video | `Test_Case_External_API_License_Verification.mp4` | MP4 | `docs/M4_API/` ⏳ pending |
| Interactive architecture | `architecture.html` | HTML | `docs/M4_API/` |
| Evidence link index | `evidence_links.md` | Markdown | `docs/M4_API/` |
| Screenshots (verification, overlay) | `screenshots/*.jpg` | JPEG | `docs/M4_API/screenshots/` |

---

## 4 · Task progress since M3 submission

Substantive shipping cycles between 2026-06-23 (M3 submitted) and 2026-07-15 (M4 draft):

**Cycle 1 · 2026-07-07 → 2026-07-11 — Legal + Communications infrastructure**
- Versioned Terms of Service + Privacy pages committed under version control
- Legacy artist accounts backfilled into the consent-audit table
- Opportunities board (`/briefs/`) mu-plugin shipped
- Transactional emails (artist notifications on licensing + payout events)
- One-off artist re-engagement nudge (2026-07-08 8am UTC)
- Sync.Land Free Sync License (SLFS-v1.0-2026-07-11) shipped as the free tier, replacing CC-BY 4.0

**Cycle 2 · 2026-07-12 → 2026-07-13 — Marketplace UX + Bug fixes**
- New `/about/` page shipped; brand hierarchy corrected (Sync.Land lead)
- Licensing page mobile fixes + dual-audience `[syncland_licensing_v1]` shortcode
- Mobile header UX overhaul (single-hamburger, dark drawer)
- `/brief/*` anon-prospect teaser + Register CTA (fixes UK-prospect bounce pattern)
- Artist-form dead-end CTA fix
- `[songs_discovery]` shortcode wpautop stabilization (4 commits)
- Stripe conversion tracking (behavior events)

**Cycle 3 · 2026-07-13 → 2026-07-15 — External API launch**
- `syncland-streamer-api.php` mu-plugin — PAT-authenticated REST namespace
- OBS Player SPA v0.1 (`Awen-online/syncland-obs-player`, Apache 2.0)
- OpenAPI spec bump to v1.2.0
- CORS + enriched playlist/clearance response fields
- Sync.Land Dock deployed to `sync.land/dock/` — the reference external application

**Cycle 4 · 2026-08-09 → 2026-08-12 — Commerce honesty, catalogue and onboarding**
- Tier-0 receipt copy corrected; ISRC captured at upload; declared usage at checkout
- PayPal tipping retired ahead of tips at checkout
- Artist contact messages routed to the signup address rather than the tip jar
- Survey banner capped at once per session (was re-firing within a session)
- Seeded Random sort on the catalogue
- NMKR mint pipeline given a real failure path, and its duplicate-mint guard
  changed to verify rather than infer
- Signup country captured at registration; the pitch dead end for artists with
  no profile fixed
- Production nginx rules tracked in version control, including one that stops
  source leaking

**Cycle 5 · 2026-08-16 → 2026-08-17 — Artist identity, page UX, and the dock rewrite**
- **Duplicate-artist defect found and fixed.** Create-versus-edit was decided
  purely by the presence of `?artist_edit_id`, so returning to the registration
  page silently minted a second artist. Access logs confirmed the path. Every
  artist who signed up in August hit it. A guard now counts every non-trashed
  status and turns `?new_project` into a confirmation step.
- `/account/edit-profile/` rebuilt. It had delegated to User Registration and
  rendered zero forms while telling the user they could edit there.
- Artist **location** added to the artist pod (`country_code` + `location`),
  with a flag on the profile, a country filter and card badge on `/artists/`,
  and country in the artists REST payload. All 21 published artists backfilled.
- `/briefs/` given page chrome (hero, live stat strip, section rhythm) and a
  full-width reset; `/licensing/` given the same width fix.
- Song cover art now falls back to the album's when the song has none. The
  upload flow only ever set artwork on the album, so 25 of 37 art-less tracks
  were needlessly blank. Coverage 365/402 → **390/402**.
- Streamer playlist cap lifted for administrators (was a hard 50 that truncated
  silently rather than paginating).
- **OBS Player v0.2.0** — see `SyncLand_OBS_Player_Architecture_M4.pdf` §9.

---

## 5 · Real users and platform activity — M4 window

Reporting period: 2026-06-23 → 2026-08-17 (56 days). Figures read from
production on 2026-08-17.

| Metric | At M4 draft (07-15) | Now (08-17) |
|---|---:|---:|
| Registered users (total) | 46 | **53** |
| New registrations in window | 1 | **7** |
| Published artists | 16 | **21** |
| Published songs | 385 | **402** |
| Songs uploaded in window | 17 | **30** |
| Terms of Service signatures (real, timestamped) | 9 | **12** |
| Pitches submitted on open briefs | 9 | **32** |
| Behavioral analytics events (window) | 2,300+ | **6,231** |
| Survey responses | 4 (NPS avg 8.0) | 4 (NPS avg 8.0) |

**Artist geography** — a new field this cycle, so this is the first time the
roster can be described by territory, which is what supervisors filter on:

| Country | Artists |
|---|---:|
| United States | 15 |
| United Kingdom | 2 |
| Finland, Türkiye, Nigeria, Colombia | 1 each |

Five artists joined between 27 July and 16 August (MerVes, Hexain, EmmyFL,
Mario Salseo, plus Creepzz in May), adding 25 tracks across hip-hop, afro
house, electronic and cinematic.

Detail: `SyncLand_User_Feedback_Report_M4.pdf` §5.

---

## 6 · Known open items — not blocking M4

**Closed since the draft**

- ~~Task #33 — menu link to `/account/tokens/`~~ — shipped 2026-08-17 as
  **Streaming Tokens** in the account menu.

**Still open**

- **Task #38** — OPTIONS preflight CORS on `/streamer/*`. Does not affect the
  same-origin dock, only self-hosted third-party mirrors.
- **Test case recording** — the only M4 artefact not yet produced. The
  application it records is live and verified.
- **Overlay audio handover** — the dock hands audio to the overlay Browser
  Source so it lands on an OBS mixer channel. Verified by construction and in a
  browser; **not yet exercised end to end inside OBS with a live token.**
- **Loudness normalisation** ships **disabled by default**. It is RMS, not
  LUFS, and is opt-in until measured against real audio in a stream.
- **Stream URLs are unsigned** S3 links and now also travel to the overlay
  context. Signing them is scoped for M5.
- **Survey banner** 2.2% conversion; optimization pass roadmapped.
- **Comment capabilities on songs** (survey request #3) roadmapped post-M5.

All non-blocking. M4 acceptance criteria 1, 2 and 4 are satisfied by shipped
work and this pack; criterion 3 is satisfied by shipped, verifiable software
whose demonstration recording is pending.

---

*Prepared for Sync.Land / Awen LLC · Cardano Project Catalyst Fund 11*
