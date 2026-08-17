# Sync.Land — Milestone 4 evidence index

**Milestone:** M4 · Marketplace Updates & API Launch (20,000 ADA)
**Project:** Cardano Project Catalyst Fund 11, project #1100272
**Prepared:** 2026-08-17

This index maps each M4 acceptance criterion to the artefact that satisfies it.

---

## Criterion 1 — User feedback gathered, analyzed, documented

| Artefact | Location |
|---|---|
| User Feedback Report | `SyncLand_User_Feedback_Report_M4.pdf` |

Covers 4 structured survey responses (NPS avg 8.0), 12 timestamped Terms of
Service signatures, 32 pitches on open briefs, 6,231 behavioral events since
M3, and direct communication samples.

---

## Criterion 2 — Marketplace updates and bugfixes clearly displayed

| Artefact | Location |
|---|---|
| Marketplace Updates changelog | `SyncLand_Marketplace_Updates_M4.pdf` |
| Project Status Report | `SyncLand_Project_Status_Report_M4.pdf` |
| Project Timeline | `SyncLand_Project_Timeline_M4.pdf` |

Five shipping cycles between 2026-06-23 and 2026-08-17, categorised, with
verifiable commit hashes.

---

## Criterion 3 — External application verifies a sync license via public API

**The application:** Sync.Land OBS Player, a DMCA-safe music player that runs
as an OBS Custom Browser Dock.

| Artefact | Location / link |
|---|---|
| Live application | https://sync.land/dock/ |
| Source (public, Apache 2.0) | https://github.com/Awen-online/syncland-obs-player |
| Release commit | `b522ae9` (v0.2.0, 2026-08-17) |
| Architecture document | `SyncLand_OBS_Player_Architecture_M4.pdf` |
| Interactive architecture | `architecture.html` |
| Screenshot — verification on screen | `screenshots/01-dock-licence-verification.jpg` |
| Screenshot — on-stream overlay | `screenshots/02-overlay-lower-third.jpg` |
| Test case recording | ⏳ **PENDING** |

**The API request being demonstrated:**

```
GET https://sync.land/wp-json/FML/v1/streamer/track/{id}/clearance
Authorization: Bearer <personal access token>
```

Returns `can_stream`, `tier`, `tier_label`, `attribution_required`,
`attribution_text`, `stream_url` and song metadata. The dock displays the
endpoint, HTTP status, latency and verdict on screen, and exports a copyable
receipt containing the full request and response.

An unauthenticated request returns `401`, demonstrating the auth boundary:

```
curl -i https://sync.land/wp-json/FML/v1/streamer/me
```

---

## Criterion 4 — Documentation is clearly written

| Artefact | Location |
|---|---|
| API Documentation (REST surface, v1.2.0 + addendum) | `SyncLand_API_Documentation_M4.pdf` |
| OBS Player Architecture | `SyncLand_OBS_Player_Architecture_M4.pdf` |
| Interactive architecture diagram | `architecture.html` |
| Player README + setup guide | https://github.com/Awen-online/syncland-obs-player |

---

## Public verifiability

| What | Link |
|---|---|
| Marketplace | https://www.sync.land |
| Artist directory (with territory filter) | https://www.sync.land/artists/ |
| Open sync briefs | https://www.sync.land/briefs/ |
| Licensing tiers | https://www.sync.land/licensing/ |
| Free Sync License (SLFS-v1) | https://www.sync.land/free-sync-license/ |
| OBS Player (live) | https://sync.land/dock/ |
| OBS Player source | https://github.com/Awen-online/syncland-obs-player |

Marketplace platform code lives in a private repository; the reference external
application is public and Apache 2.0 licensed, since it is the artefact a
reviewer needs to inspect and run.

---

## Platform state at submission (2026-08-17)

| Metric | Value |
|---|---:|
| Registered users | 53 |
| Published artists | 21 |
| Artist territories represented | 6 |
| Published songs | 402 |
| Published albums | 58 |
| Pitches on open briefs | 32 |
| Terms signatures (timestamped) | 12 |
| Behavioral events since M3 | 6,231 |

---

## Direct links — GitHub hosted

Same hosting pattern as the accepted M3 submission
(`github.com/Awen-online/sync.land/docs/M3_Marketplace/`).

| Document | Link |
|---|---|
| Project Status Report | https://github.com/Awen-online/sync.land/raw/main/docs/M4_API/SyncLand_Project_Status_Report_M4.pdf |
| Project Timeline | https://github.com/Awen-online/sync.land/raw/main/docs/M4_API/SyncLand_Project_Timeline_M4.pdf |
| User Feedback Report | https://github.com/Awen-online/sync.land/raw/main/docs/M4_API/SyncLand_User_Feedback_Report_M4.pdf |
| Marketplace Updates | https://github.com/Awen-online/sync.land/raw/main/docs/M4_API/SyncLand_Marketplace_Updates_M4.pdf |
| API Documentation | https://github.com/Awen-online/sync.land/raw/main/docs/M4_API/SyncLand_API_Documentation_M4.pdf |
| OBS Player Architecture | https://github.com/Awen-online/sync.land/raw/main/docs/M4_API/SyncLand_OBS_Player_Architecture_M4.pdf |
| Interactive architecture | https://github.com/Awen-online/sync.land/blob/main/docs/M4_API/architecture.html |
| This index | https://github.com/Awen-online/sync.land/blob/main/docs/M4_API/evidence_links.md |
| Screenshot - licence verification | https://github.com/Awen-online/sync.land/raw/main/docs/M4_API/screenshots/01-dock-licence-verification.jpg |
| Screenshot - on-stream overlay | https://github.com/Awen-online/sync.land/raw/main/docs/M4_API/screenshots/02-overlay-lower-third.jpg |
| Test case recording | pending |

Folder: https://github.com/Awen-online/sync.land/tree/main/docs/M4_API
