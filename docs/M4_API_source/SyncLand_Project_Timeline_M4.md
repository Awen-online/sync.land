## Purpose

This document records the delivery timeline for **Sync.Land**, Cardano Project Catalyst Fund 11 project **#1100272**, as of the Milestone 4 submission. It states the funded milestone schedule, what was actually delivered against it, where the project ran ahead or behind, and the honest reasons why.

---

## 1 · Funded milestone schedule

Total award: **100,000 ADA** across five milestones.

| # | Milestone | Cost | Share | Delivery month (planned) | Completion after |
|---|---|---:|---:|---|---:|
| M1 | Initialization | 20,000 ADA | 20% | Month 1 — Apr 2024 | 15% |
| M2 | Development | 25,000 ADA | 25% | Month 3 — Jun 2024 | 40% |
| M3 | Marketplace Launch & API Development | 20,000 ADA | 20% | per proposal | 60% |
| M4 | Marketplace Updates & API Launch | 20,000 ADA | 20% | Month 21 — Dec 2025 | 80% |
| M5 | Finalization | 15,000 ADA | 15% | Month 22 — Jan 2026 | 100% |

---

## 2 · Actual delivery against plan

| Milestone | Criteria submitted | Criteria signoff | Deliverables submitted | Status |
|---|---|---|---|---|
| M1 | 2024-02-29 | 2024-03-04 · 2 approvals, 0 refusals | — | **Approved** |
| M2 | 2024-02-29 | 2024-03-04 · 2 approvals, 0 refusals | — | **Approved** |
| M3 | — | — | 2026-06-23 | **Approved** — second reviewer signed 2026-08-06; awaiting staff sign-off |
| M4 | 2024-02-25 | 2024-03-01 · 2 approvals, 0 refusals | **this submission** | Submitted |
| M5 | 2024-02-29 | 2 approvals, 0 refusals | — | Outstanding |

All five milestones had their **acceptance criteria** approved by reviewers in early 2024. Criteria approval is a funding gate, not deliverable acceptance; the deliverable record is the column that matters above.

**The project is behind its original schedule.** M4 was planned for Month 21 (December 2025) and is being submitted in August 2026. This is stated plainly rather than elided: the delay is real, the work is real, and Section 4 documents what the additional time produced.

---

## 3 · M4 delivery timeline

The M4 working period runs from the M3 submission to this document.

### 3.1 Foundation — licensing model

| Date | Event |
|---|---|
| 2026-06-11 | Mie uploads *The Cave* (5 WAV tracks) — exposes a gap in the WAV ingest path |
| 2026-06-13 | Cross-machine WAV→MP3 transcode + waveform + duration worker built and deployed |
| 2026-06-23 | **M3 deliverables submitted.** On-chain demonstration mint: *"Ice"* by Mie, Cardano preprod, CIP-25 |
| 2026-07-10 | Versioned Terms of Use gate ships; 5 real artists accept, creating the first audited permission grant |
| 2026-07-11 | **SLFS-v1 replaces CC-BY 4.0 as the free licensing tier** — 30 files, new public licensing page, no database migration required by design |

### 3.2 The API module — M4's principal deliverable

| Date | Event |
|---|---|
| 2026-07-12 | `/wp-json/FML/v1/streamer/*` namespace designed — 8 routes |
| 2026-07-13 | Personal Access Token authentication implemented (`sk_syncland_` prefix, SHA-256 at rest) |
| 2026-07-14 | Sync.Land OBS Player built as the reference external application |
| 2026-07-15 | **OBS Player deployed to `sync.land/dock/`.** Licensed Apache 2.0, source public at `github.com/Awen-online/syncland-obs-player`. CORS + enriched playlist/clearance responses shipped alongside |
| 2026-07-15 | M4 evidence pack drafted — five documents |

### 3.3 Marketplace updates and hardening

| Date | Event |
|---|---|
| 2026-07-27 | Artist pitch table rebuilt — sortable, filterable, honest status collapse |
| 2026-07-29 | Live API verification run against production; GA4 conversion tracking reconciled into the repository |
| 2026-08-01 | **First fully organic artist registration** (see *User Feedback Report* §10.1) |
| 2026-08-02 | Self-service delete for empty artist profiles ships |
| 2026-08-04 | That artist self-publishes 3 albums / 18 tracks, unaided — and uses the delete feature shipped two days earlier |
| 2026-08-04 | Public content accuracy pass; Creative Commons phrasing removed from all marketing surfaces |
| 2026-08-06 | **M3 second-reviewer approval signed** |
| 2026-08-07 | Three production defects found by auditing this evidence pack, fixed, and documented (*Marketplace Updates* §5.2–5.4) |
| 2026-08-07 | **M4 deliverables submitted** |

---

## 4 · Why the schedule slipped, and what the time bought

The original plan assumed the free tier would remain CC-BY 4.0 and that the marketplace would launch on that basis. In practice CC-BY proved structurally unfit for sync licensing: it is irrevocable, unbounded, silent on PRO / mechanical / neighbouring / moral rights, and offers no upgrade path from creator-scale use to broadcast or paid advertising. Shipping a marketplace on it would have permanently given away the commercial rights the platform exists to transact.

Replacing it required drafting an original license instrument (SLFS-v1), building a versioned consent-capture system with audit trail, and rewriting every licensing surface. That work was not in the funded plan and consumed a substantial share of the overrun.

The remainder went to work that was in the plan but under-scoped: a cross-machine audio processing pipeline, an authenticated public API with token management, and a reference external application built and open-sourced to prove the API rather than merely describe it.

**What the extra time produced, measured at submission:** 398 published songs, 56 albums, 19 artist profiles, 48 registered users, 30 artist pitches, 7 issued licenses, a live public API with an open-source reference client, an original license instrument, and a verifiable on-chain licensing record.

---

## 5 · Remaining to project completion

| Item | Milestone | Status |
|---|---|---|
| M3 staff sign-off | M3 | Awaiting Catalyst staff — reviewer approvals complete |
| M4 deliverable review | M4 | This submission |
| Closeout report | M5 | To be produced |
| Closeout video | M5 | To be produced |
| Final bug fixes and documentation | M5 | Ongoing — open items carried from M4 are listed in *Marketplace Updates* §5 |

**Outstanding funding:** M4 (20,000 ADA) + M5 (15,000 ADA) = **35,000 ADA**. M3's 20,000 ADA is delivered and through reviewer approval.

---

*Prepared for Sync.Land / Awen LLC · Cardano Project Catalyst Fund 11*

---

## 6 · Addendum — 2026-08-07 to 2026-08-17

The submission window extended by ten days. What the time bought:

| Date | Work |
|---|---|
| 08-09 → 08-12 | Commerce honesty pass (Tier-0 receipts, ISRC at upload, declared usage), NMKR mint failure path, signup country capture |
| 08-16 | Duplicate-artist defect identified from access logs; guard shipped; `/account/edit-profile/` rebuilt |
| 08-17 | Artist location + flags; `/briefs/` and `/licensing/` UX; cover-art fallback; admin track cap; OBS Player v0.2.0 |

**Why it mattered rather than merely slipped:** the duplicate-artist defect was
affecting 100% of new artist signups and had been live for roughly two weeks.
Submitting M4 before finding it would have shipped a marketplace whose primary
onboarding path silently corrupted its own catalogue. The dock rewrite likewise
moved the M4 reference application from a scaffold to something that survives
navigation, routes audio correctly in OBS, and shows its licence verification
on screen — which is the criterion being demonstrated.

**Outstanding to submit:** the test-case screen recording. Everything it
records is live.
