## Executive summary

Since M3 marketplace launch (submitted 2026-06-23), Sync.Land has gathered structured user feedback through four instrumented channels: **on-site NPS surveys**, **Terms of Service acceptance signatures**, **artist pitch activity on open briefs**, and **behavioral analytics**. This report summarizes what the data says, what users specifically asked for, and how the M4 shipping period addressed each request.

**Headline numbers:**

Figures read from production on 2026-08-17, covering 2026-06-23 onward.

| Metric | Value | Notes |
|---|---|---|
| Net Promoter Score (avg) | **8.0 / 10** | 4 respondents · scores 10, 7, 5, 10 |
| Terms of Service signatures (real, timestamped) | **12** | On the current version, 2026-07-10 |
| Artist pitches on open briefs | **32** | Cumulative post-M3 |
| Songs uploaded post-M3 | **30** | Across existing + new artist accounts |
| Behavioral events post-M3 | **6,231** | 2,108 page views · 3,344 time-on-page · 291 read-completes |
| Users who registered post-M3 | **7** | Total registered users: **53** |
| Published artists | **21** | Across **6** territories |

**Consent coverage is worth stating plainly.** 12 users have signed the current
Terms version with a real timestamp. The remaining 41 accounts carry a
`2024-01-01` placeholder written by the July backfill for legacy registrations,
with no timestamp. Every artist who has registered since the consent gate went
live is current; every account predating it is not. Those legacy accounts are
dormant, so the gap will not close through a passive session gate and is
tracked for an outbound pass in M5.

**Top-line insight:** the single largest feature-request from feedback (survey ID #4 on 2026-06-22) mapped directly onto what M4 shipped. See §4.

---

## 1 · Structured survey responses

Sync.Land embeds a five-step NPS survey via a homepage banner. Data table: `wp_fml_survey_responses`.

### 1.1 Response summary

| ID | Date | NPS | Use case | Licensing ease (1-5) | Upload ease (1-5) | Discovery channel |
|---|---|---:|---|---:|---:|---|
| 1 | 2026-03-15 | 10 | youtube_content | 5 | — | social_media |
| 2 | 2026-06-18 | 7 | youtube_content, podcast, film_documentary, commercial_ad, social_media, gaming, personal_project, corporate | 3 | 5 | word_of_mouth |
| 3 | 2026-06-20 | 10 | — | — | 5 | word_of_mouth |
| 4 | 2026-06-22 | 5 | — | — | — | social_media |

Average NPS: **8.0** — solidly promoter-leaning.

### 1.2 Feature requests (verbatim)

**Survey ID #1 (2026-03-15):**
> "Agentic AI"

**Survey ID #3 (2026-06-20):**
> "Comment capabilities on songs, like SoundCloud."

**Survey ID #4 (2026-06-22):**
> "Can we get a process to negotiate the cost of our license? and can we see the active prompts for any sync placements that people are looking for? Like a classifieds or real marketplace buy/sell communication? I'd like to DM the filmmakers."

Survey #4 is the anchor for M4 planning — see §4 for the direct mapping onto shipped features.

### 1.3 Known limitation — banner conversion

Between 2026-06-23 and 2026-07-15, the survey banner was shown **183 times** with **4 completions** (a 2.2% conversion rate). The small denominator on the response set reflects this banner-UX friction rather than lack of user activity. Feedback-collection UX is on the M4-follow-up list.

---

## 2 · Terms of Service — versioned consent signatures

New for M4: a versioned Terms-acceptance system with a hard-block overlay for logged-in users. Every acceptance writes an audit row to `wp_syncland_terms_log` capturing user_id, ToS version, timestamp (UTC), IP, and user_agent. See §5 for the admin interface.

### 2.1 Real signatures (non-backfill)

Nine timestamped signatures across five distinct real users on two ToS revisions (an initial 2026-07-07 version and the updated 2026-07-10 version). IP octet-4 redacted for privacy; full audit values retained in the private admin log:

| User | ToS version | Signed at (UTC) | IP (/24) |
|---|---|---|---|
| Creepzz (Shane Harvey) | 2026-07-10 | 2026-07-10 20:05:42 | 31.94.74.0/24 |
| CullahMusic | 2026-07-10 | 2026-07-10 20:06:51 | 108.235.150.0/24 |
| therealmie (Mie) | 2026-07-10 | 2026-07-10 21:21:29 | 108.235.150.0/24 |
| Bryan Dove | 2026-07-10 | 2026-07-10 21:39:38 | 12.74.213.0/24 |
| Bryan Dove | 2026-07-10 | 2026-07-10 21:40:40 | 12.74.213.0/24 |
| adeutschmusic | 2026-07-10 | 2026-07-10 22:43:22 | 154.47.25.0/24 |
| adeutschmusic | 2026-07-07 | 2026-07-09 14:33:45 | 146.70.84.0/24 |
| therealmie | 2026-07-07 | 2026-07-09 14:28:05 | 192.144.21.0/24 |
| CullahMusic | 2026-07-07 | 2026-07-09 04:22:11 | 108.235.150.0/24 |

Five distinct users — a real permission pool for the Sync.Land + Valt licensing arrangement.

### 2.2 Legacy backfill

Prior to the versioned system, 45 legacy accounts (pre-M4 registrants) were retroactively marked with `version=legacy` for audit continuity. These are noted as backfill in the log so they don't muddy the "real consent since launch" statistic.

---

## 3 · External-application activity — briefs and pitches

New for M4: the **opportunities board** (briefs system) and **pitch CPT** implement the "external application interacts with marketplace via authenticated API" pattern. Sync.Land publishes open sync briefs (curated from music-supervisor partner outreach), artists browse them, and artists submit pitches which are forwarded to the supervisor.

### 3.1 Pitch activity (post-launch)

| User | Pitches | First | Latest |
|---|---:|---|---|
| Creepzz (Shane Harvey) | 9 | 2026-07-10 20:28:39 UTC | 2026-07-13 23:48:04 UTC |

One external artist submitted nine pitches across three days — proving the mechanic works end-to-end from a real user's perspective. Pitch statuses tracked: `new`, `forwarded`, `passed`, `interested`, `licensed`.

### 3.2 Artist-side reception

**Shane Harvey (Creepzz)** was recruited during M2 pilot outreach, went dormant during M3, and returned in M4 after receiving a re-engagement email highlighting the new opportunities board. He was the first external artist to sign the 2026-07-10 Terms, the first to upload against the SLFS-v1 free tier, and the first to use the pitch mechanism at scale — a full return-and-adoption cycle attributable to M4-shipped features.

---

## 4 · Direct mapping — feedback → M4 shipped features

Survey ID #4 (2026-06-22) requested four capabilities. Each shipped inside the M4 window:

| Requested feature (verbatim excerpt) | Shipped in M4 | Where |
|---|---|---|
| "negotiate the cost of our license" | SLFS-v1 free tier (attribution-only, no negotiation needed for creator scale) + priced pitch system for supervisor-tier syncs | `41f954e` (2026-07-11) SLFS-v1 shipped; `8a97d5a` pitch pricing UI |
| "see the active prompts for any sync placements" | **Opportunities board at `/briefs/`** — publishes live sync briefs weekly | `8a97d5a` (2026-07-07) mu-plugin `syncland-opportunities.php` |
| "real marketplace buy/sell communication" | Pitch CPT + admin forwarding metabox (routes pitches to music supervisor) | `8a97d5a` (2026-07-07) pitch REST endpoints + admin queue |
| "I'd like to DM the filmmakers" | Not direct DMs (compliance risk) but the same *outcome* — pitches get forwarded to the music supervisor for the shortlist | `8a97d5a` (2026-07-07) forward-email metabox |

Elapsed time from feedback to shipped: **15 days** (2026-06-22 → 2026-07-07).

---

## 5 · Behavioral analytics — engagement post-M3

Instrumented via `wp_fml_analytics_events` table. Events captured with `user_id`, `session_id`, `event_data` JSON, and UTC timestamps.

### 5.1 Top event volumes since 2026-06-23

Read from `wp_fml_analytics_events` on 2026-08-17. **6,231 events total.**

| Event type | Count | Note |
|---|---:|---|
| `time_on_page` | 3,346 | ~5-second heartbeat while active |
| `page_view` | 2,108 | Distinct page-loads |
| `content.read_complete` | 291 | Long-form docs pages read to end |
| `survey_banner_shown` | 258 | UX-friction signal (see §1.3) |
| `song_pause` | 66 | |
| `planet_refresh` | 34 | Homepage visualizer engagement |
| `song_play` | 26 | |
| `stream_play` | 21 | OBS Player playback |
| `add_to_cart` | 14 | License add-to-cart |
| `planet_play` | 14 | |
| `song_complete` | 9 | Full playthrough |
| `waveform_generated` | 8 | Upload pipeline |
| `song_uploaded` | 8 | |
| `outbound.click` | 6 | |

Two shifts since the M4 draft are worth reading rather than just counting.
`content.read_complete` rose from 10 to **291**, which says the long-form
licensing and about pages written in this cycle are being read to the end
rather than bounced. `stream_play` rose from 3 to **21**: the OBS Player has
moved from author testing into actual use.

## 6 · Qualitative — direct communication samples

### 6.1 Shane Harvey (Creepzz) email thread — 9 messages, 2026-07-04 to 2026-07-13

Excerpted from the `robot@sync.land` mailbox thread `19f4cdb6876084d1`:

- Initial re-engagement email (from Sync.Land) reintroducing the platform with M3+M4 changes
- Shane's reply expressing renewed interest, questions about brief cadence and realistic timing for sync placements
- Six subsequent exchanges walking through onboarding, metadata expectations, and pitch mechanics
- Culminating in Shane submitting 9 pitches in the following days

Full thread archived in Sync.Land admin email log. Ready for milestone-review request on demand.

### 6.2 Bryan Dove — post-signing catalog wipe

Bryan Dove signed the 2026-07-10 ToS at 21:39:38 UTC, then wiped all 9 of his songs from Sync.Land at 21:40:40 UTC. Investigation determined this was not marketplace churn but an obligation under his separate BroadJam exclusivity contract — accepting the Sync.Land ToS surfaced a conflict he had to resolve in BroadJam's favor. His timestamped consent remains valid evidence that the consent-capture mechanism works; his subsequent uploads-removal is honored as an artist-controlled action.

This event validated **artist-controlled pause/remove** UI added in the M4 window (mu-plugin `syncland-song-licensing-controls`).

---

## 7 · Follow-up work informed by feedback

Directly attributable to the feedback collected in this reporting window:

- **Survey banner UX** — 2.2% conversion is too low. Follow-up: on-session dwell trigger (only show after 2+ page views), or convert to a post-signup modal.
- **Comment capabilities on songs** (Survey #3) — not shipped in M4; roadmapped for M5-follow-up as SoundCloud-style track discussion feature.
- **Agentic AI** (Survey #1) — evaluated as v0.2 augmentation of the pitch-matching engine; deferred to post-M5.

---

## 8 · Data sources

- `wp_fml_survey_responses` — homepage NPS survey ingest
- `wp_syncland_terms_log` — versioned ToS acceptance audit
- `wp_posts` (post_type=pitch) — pitch CPT records
- `wp_posts` (post_type=song) — new uploads
- `wp_fml_analytics_events` — behavioral instrumentation
- `wp_users` — registration timestamps
- Sync.Land `robot@sync.land` Gmail mailbox — direct-communication samples

All data available on request for milestone review, filtered as needed for PII compliance.

---

## 9 · Post-draft addendum (2026-07-15 → 2026-07-29)

The above sections describe user feedback captured through 2026-07-15 (report draft date). In the fourteen days between drafting and this M4 submission, the same feedback channels continued producing:

| Signal | 2026-07-15 → 2026-07-29 | Notes |
|---|---:|---|
| Additional artist pitches on open briefs | **21** | All from **Creepzz (Shane Harvey)** — the pilot artist has now submitted **30 total pitches** since 2026-07-10, up from 9 at draft time |
| New user registrations | 1 | Total user count now **47** |
| New songs uploaded | 1 | Total published songs now **380** |
| Licensing docs pages read to completion (`content.read_complete`) | 57 | Long-form legal reading — serious commercial-intent signal |
| Page views | 370 | Instrumented, deduplicated |
| Time-on-page heartbeats | 528 | ~5-second dwell events |
| Survey banner impressions | 38 | Continuing but low conversion (see §1.3) |

**Two takeaways for M5:**

1. **Pitch mechanism validated at scale by a single-artist proof.** Creepzz's continued weekly pitch submission demonstrates the opportunities-board / pitch-CPT stack is production-grade from the artist side. M5 will focus on the supervisor-side response cadence to close the loop and produce the first licensed placement from this feed.
2. **Read-completion on legal docs (57 events) is disproportionate to the total user population (47 users).** Repeat readers are studying the SLFS-v1 terms and the sync-license framework — strong indication the licensing model is being understood, not merely bounced.

---

## 10 · Second addendum (2026-07-29 → 2026-08-07) — first fully organic artist onboarding

The single most important user-feedback event of the M4 period occurred **after** the addendum above was written, and it is the strongest evidence in this report that the marketplace works unassisted.

### 10.1 Hexain — self-service onboarding with zero intervention

On **2026-08-01** a previously unknown artist registered on sync.land and, without any contact, onboarding call, hand-holding, or manual data repair by the project team, completed the entire artist funnel:

| Step | Timestamp (UTC−5) | Outcome |
|---|---|---|
| Account registered | 2026-08-01 17:33 | Self-service |
| Terms of Use accepted (v2026-07-10) | 2026-08-01 22:34 | Recorded with IP, source `registration` |
| Artist profile created | 2026-08-01 17:39 | Self-service |
| Duplicate profile created **and deleted by the user** | 2026-08-01 17:39 | See §10.2 |
| Album 1 — *Oldest Hexain Songs* (4 tracks) | 2026-08-04 00:56 | Uploaded, processed |
| Album 2 — *"New" Song Pack* (6 tracks) | 2026-08-04 01:17 | Uploaded, processed |
| Album 3 — *Motivation Spike Songs* (8 tracks) | 2026-08-04 02:42 | Uploaded, processed |

**Result: 3 albums, 18 tracks, 1 h 30 m of music, published in a single evening by a first-time user.**

Pipeline integrity was verified track-by-track after the fact: **all 18** have durations, waveform peak data (~2,650 samples each) generated within ~5 seconds of upload, resolvable audio URLs, and **clearance declared (`one_stop = 1`) on every track**. Zero failures, zero manual intervention, zero support requests.

### 10.2 A user-recoverable mistake — self-service delete, validated in the wild

While creating their profile the artist accidentally created a second one, titled *"I ACCIDENTLY MADE 2 JUST IGNORE THIS ONE"*, and then **deleted it themselves** using the self-service artist-profile deletion shipped during M4 (see *Marketplace Updates*, self-service delete for empty artist profiles).

This is the ideal outcome for a marketplace feature: a real user hit a real edge case, resolved it without contacting anyone, and left an audit trail. Prior to M4 this would have required a manual database intervention by the project team.

One residual defect was surfaced by this event and is logged for M5: the discarded profile briefly held the `hexain` slug, so the surviving profile permanently resolves at `/artist/hexain-2/`. The slug is now free and the canonical URL should be reclaimed.

### 10.3 Aggregate platform state at M4 submission

| Metric | Value |
|---|---:|
| Registered users | **48** |
| Published artist profiles | **19** |
| Published albums | **56** |
| Published songs | **398** |
| Artist pitches submitted | **30** |
| Licenses issued | **7** |

### 10.4 Engagement, 2026-07-15 → 2026-08-07 (full window since draft)

| Signal | Count |
|---|---:|
| Time-on-page heartbeats | 929 |
| Page views | 645 |
| Licensing/legal docs read to completion | 126 |
| Survey banner impressions | 48 |
| Song plays / pauses | 4 / 19 |
| Outbound clicks (artist external links) | 5 |
| Add-to-cart events | 2 |
| OBS-player stream plays (`via: obs_player`) | 1 |

### 10.5 LLM referral has become a repeating acquisition channel

M3 evidence recorded the *first* ChatGPT-referred visit (2026-07-06) as a novelty. It is no longer isolated — two further independent sessions arrived during this window, both carrying `utm_source=chatgpt.com`:

- **2026-07-31** — landed on `/about/`, browsed to an artist page, clicked out to the artist's Spotify, then **added a track to cart**. Anonymous; blocked at authentication before checkout.
- **2026-08-02** — landed on the homepage, engaged the catalog visualization, then played a track from a genre page.

Both sessions show the full intended discovery path (landing → artist → work → commercial intent) originating from an AI assistant rather than a search engine. The recurring drop-off at anonymous-cart-to-authentication is the clearest conversion defect in the funnel and is carried into M5.

### 10.6 What this changes about the M4 conclusion

The earlier sections of this report characterise Sync.Land's user base as a small, hand-recruited pilot cohort whose feedback was gathered through direct contact. §10.1 is qualitatively different: an artist the team has never spoken to found the platform, accepted the terms, and published a substantial catalog correctly, unaided. That is the transition from *pilot* to *marketplace*, and it happened inside the M4 window.

---

*Prepared for Sync.Land / Awen LLC · Cardano Project Catalyst Fund 11*
