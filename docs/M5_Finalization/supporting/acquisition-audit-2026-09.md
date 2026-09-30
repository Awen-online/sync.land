# Sync.Land acquisition and funnel audit, September 2026

> Published copy for the Catalyst Fund 11 close-out. Individual users, artists and songs are not named: people appear as letters or counts. Aggregate figures are unchanged.

Prepared 2026-09-17 from prod, read-only. Section labels below such as Q1a, S3, L2 point into the raw query outputs, which are not published because they hold personal data.

Sources: `wp_yk5m4c_fml_analytics_events` (19,488 events, 2026-03-15 to 2026-09-17), `wp_yk5m4c_users` + usermeta, post tables (artist/album/song/pitch/sync_brief/license), `wp_yk5m4c_fml_survey_responses`, and the nginx access logs for 2026-09-10 to 2026-09-17 (110,890 requests). Google Search Console and GA are not connected, so nothing here comes from them.

The founder's two accounts and one closed account are excluded from every "people" count. Clocks: analytics, `user_registered` and `post_date` are site time (UTC-5); the access log is UTC-7. "September" means 2026-09-01 to 2026-09-17, 17 days.

## 0. Headline findings

1. **ChatGPT is now the largest identifiable source of new artists.** 23 of the 50 accounts created since 1 June (46%) first arrived via a ChatGPT link (`utm_source=chatgpt.com`); in September it is 19 of 40 (48%). Google brought 7, the untagged/direct bucket 18, everything else 2. (Part E, Q1d)
2. **The ChatGPT channel is compounding week on week.** New ChatGPT-referred visitors by week: 3, 4, 8, 5, 15, 13, 8, 31, 40, then 18 in the partial week to 17 Sep. Their share of new visitors went 7% (Jun), 8% (Jul), 17% (Aug), 28% (Sep). (S1, S4)
3. **ChatGPT visitors are the best-quality traffic on the site.** Since 10 July: 145 visitors, 21% bounce, median 3 pages, 17% end up logged in, 14% return on a second day. Google: 42 visitors, 40% bounce, 29% logged in. Direct/none: 470 visitors, 81% bounce, 6% logged in, and most of it is unidentified bots. (S3)
4. **Landing on /about/ converts 4x better than landing on the homepage.** Of ChatGPT visitors, 101 landed on `/` and 10 registered (10%); 29 landed on `/about/` and 11 registered (38%). Yet ChatGPT's live fetcher (`ChatGPT-User`) reads the homepage 248 times out of 281 in 8 days and `/about/` 4 times. The page ChatGPT reads is not the page that converts. (S5, Part E, L3)
5. **The funnel is fast at the top and leaks at the uploader.** September: 40 registered, 30 made a profile (75%), 20 uploaded a track (67% of profiles), 11 pitched (55% of uploaders), 0 licences sold. Median time from registration to profile is 6 minutes and to first track 39 minutes; people who are going to upload do it in the first session. Of the 12 profiles with no track, 7 opened the uploader (one of them 4 times) and none ever submitted; there were no errors. (Q5, Q5b, Q6b)
6. **There is no buyer side yet.** 48 of 50 registrants since June identify as "Musician". Zero third-party licence purchases in the analytics era (the one Stripe payment on 2026-06-23 is the founder's own test). Four anonymous visitors added a song to the cart and stopped at the login wall. All 109 pitches are still in status `new`; none has ever been answered. (Q5, Q10f, Part E)
7. **The analytics session id is being served from the nginx page cache**, so one `session_id` is shared by many visitors: 64 sessions carry 4,308 events (22% of the table) from 2 to 42 different browsers each. Session-level numbers in the admin dashboard are therefore unreliable; visitor (`anon_id`) numbers are used in this report. ClaudeBot also executes the tracker (94 "sessions", including an `add_to_cart`). (Q10, S8, bot table in Part A)
8. **Google is small and mostly brand.** 43 Google visitors since June; 26 of 49 Google-referred landing hits in the last 8 days were on `/` (a brand search), 7 on one artist's page. Googlebot fetched about 180 content pages in 8 days; Bingbot and ClaudeBot each crawl the catalogue harder than Google does. (L1, L3, L3b)

## 1. Referral sources over time

### 1a. How attribution works here (and its limits)

The tracker stores a first-touch cookie (`awen_campaign`, 90 days) on the first request that carries UTM parameters or an external referrer, and attaches it as `_campaign` to every later event from that browser. ChatGPT appends `?utm_source=chatgpt.com` to the links it shows, so ChatGPT is tagged reliably even though it sends no referrer. The cookie only exists from **2026-07-10**; June sessions can only use `document.referrer`, so June is understated for every tagged source.

Because a `session_id` is shared across visitors on cached pages (finding 7), the primary unit below is the **visitor** (the `anon_id` cookie, falling back to IP+UA when absent). Session counts are shown for comparison with the admin dashboard.

### 1b. New visitors by first-seen month and source (S1)

| Source | Jun | Jul | Aug | Sep 1-17 | Total | Sep share |
|---|---|---|---|---|---|---|
| direct/none (untagged) | 23 | 143 | 176 | 156 | 498 | 49% |
| chatgpt.com | 3 | 14 | 44 | 88 | 149 | 28% |
| google | 1 | 3 | 10 | 29 | 43 | 9% |
| social (ig, meta click ids, l.instagram) | 4 | 3 | 6 | 27 | 40 | 8% |
| other referral (mostly referrer-spam hosts) | 10 | 16 | 21 | 14 | 61 | 4% |
| other search (bing, ddg, baidu, yahoo) | 1 | 2 | 5 | 4 | 12 | 1% |
| **All** | 42 | 184 | 262 | 319 | 807 | |

Visitor-days (visitor x calendar day) tell the same story with less bot noise: chatgpt 11, 22, 62, 143; google 1, 10, 17, 34; direct 56, 158, 178, 178 (S2).

The direct/none bucket is mostly not people: 379 of its 470 visitors since 10 July viewed exactly one page, 264 of them the homepage, and the two spikes (101 and 80 new "direct" visitors in the weeks of 27 Jul and 3 Aug) coincide with no signups (S4, S7). Real direct traffic is probably a few visitors a day.

Sessions (the dashboard's unit) since June, real UAs only (Q1a): direct 39 / 77 / 155 / 167; chatgpt 1 / 19 / 65 / 158; google 1 / 7 / 15 / 33; social 0 / 2 / 7 / 33. ChatGPT's share of sessions: 2%, 15%, 24%, 38%.

### 1c. ChatGPT visitors per week (S4)

| Week of | ChatGPT | Google | Social | Direct | Other | Total |
|---|---|---|---|---|---|---|
| 2026-07-13 | 3 | 1 | 1 | 17 | 5 | 27 |
| 2026-07-20 | 4 | 1 | 1 | 15 | 6 | 27 |
| 2026-07-27 | 8 | 2 | 1 | 101 | 7 | 119 |
| 2026-08-03 | 5 | 2 | 0 | 80 | 9 | 96 |
| 2026-08-10 | 15 | 2 | 3 | 24 | 5 | 49 |
| 2026-08-17 | 13 | 2 | 3 | 36 | 1 | 55 |
| 2026-08-24 | 8 | 3 | 0 | 27 | 8 | 46 |
| 2026-08-31 | 31 | 9 | 9 | 72 | 7 | 128 |
| 2026-09-07 | 40 | 11 | 13 | 65 | 10 | 139 |
| 2026-09-14 (4 days) | 18 | 9 | 5 | 25 | 3 | 60 |

The access log agrees: 2 to 9 distinct human IPs per day landed with `utm_source=chatgpt.com` between 10 and 16 Sep (42 over 7 days), and `chatgpt.com` was the referrer for 21 IP-days, second only to Google's 52 (L1, L1d).

### 1d. Registrations by first-touch source (Part E; own events only)

| Source | Jun | Jul | Aug | Sep 1-17 | Total | Profile | Uploaded | Pitched |
|---|---|---|---|---|---|---|---|---|
| chatgpt.com | 0 | 1 | 3 | 19 | 23 | 18 (78%) | 12 (52%) | 7 (30%) |
| google | 0 | 0 | 1 | 6 | 7 | 6 | 4 | 2 |
| untagged (no cookie, no referrer) | 1 | 0 | 4 | 13 | 18 | 12 | 8 | 2 |
| briefs_gate (internal UTM from the briefs teaser) | 0 | 0 | 0 | 1 | 1 | 1 | 1 | 1 |
| mail.google.com | 0 | 0 | 0 | 1 | 1 | 0 | 0 | 0 |
| **All** | 1 | 1 | 8 | 40 | 50 | 37 | 25 | 12 |

Notes: this table reads the cookie from each user's own events, which is cleaner than the anon-linked version in Q1d/Q1e (that version mis-attributes two ChatGPT registrants to a referrer-spam host because a cache-shared session linked them to a stranger's July visit). "Untagged" includes people who typed the URL, came from an app that strips referrers (WhatsApp, Discord, Messenger, email clients), or cleared cookies between visits. Given ChatGPT's share of tagged traffic, some of the untagged registrants probably also came via a chatbot that does not append a UTM (Claude, Gemini, Copilot and Perplexity do not).

### 1e. Upload sessions by source (Q1f, Q1g)

Sessions containing an upload event, Jul / Aug / Sep: chatgpt 0 / 1 / 16; direct 0 / 1 / 11; google 1 / 2 / 4; social 0 / 0 / 1. Uploading users registered since June by source: chatgpt 10 (12 by the clean table), direct 6, google 4, social 2, other 2.

### 1f. Referrer domains in the access log, 10 to 17 Sep, deduped by IP+day, bot UAs removed (L1)

| Referrer | IP-days | Note |
|---|---|---|
| freemusic.land / www.freemusic.land | 84 | the old domain; every hit is a 301 on `/`, mostly one iPhone UA (a monitor, not people) |
| www.google.com | 52 | 26 landed on `/`, 7 on one artist's page, 3 on `/briefs/` |
| chatgpt.com | 21 | referrer only survives in some browsers; the UTM count (42 IPs) is the better number |
| www.facebook.com + m.facebook.com | 21 | 13 Sep spike; landing on one artist's song and album pages |
| m.baidu.com | 15 | Chinese search, no signups |
| l.instagram.com + instagram.com | 10 | link-in-bio clicks, all to artist/album pages |
| android-app://com.google.android.gm + mail.google.com | 27 | email clicks (nudges and notifications) |
| milestones.projectcatalyst.io | 5 (analytics) | Catalyst reviewers |

Everything else (five.co.in, trooker.com, roozzy.com, virtuallrc.com, cluuz.com, similarsitesearch.com and about 30 more) is referrer spam or SEO-tool crawling with browser UAs: 1 to 3 hits each, single page, no signups. The "other referral" bucket can be ignored.

## 2. Landing pages

Landing path from the first-touch cookie, visitors since 10 July (S5, S6, Q2a):

| Landing | ChatGPT (145) | Google (42) | Direct (470) | Social (36) |
|---|---|---|---|---|
| `/` | 101 (70%) | 22 (52%) | 264 | 8 |
| `/about/` | 29 (20%) | 2 | 9 | 0 |
| `/licensing/` | 11 (8%) | 0 | 6 | 0 |
| `/briefs/` | 4 | 6 | 15 | 0 |
| `/contact-us/` | 2 | 0 | 3 | 0 |
| `/free-sync-license/` | 1 | 0 | 2 | 0 |
| `/account/` (returning users) | 0 | 3 | 21 | 0 |
| `/registration/` | 0 | 1 | 30 | 0 |
| artist / song / album pages | 1 | 9 | 10 | 32 |

ChatGPT sends people to exactly seven URLs, and 98% of them to three: the homepage, About and Licensing. It never deep-links to a song, artist or brief (one artist page in 145). Google sends the homepage (brand queries), briefs, and individual artist/song pages (artist and song name queries). Social traffic is artists sharing their own pages: 32 of 36 land on artist/album/song pages, 71% mobile, 75% bounce, 1 registration.

First page actually viewed (Q2d) matches the cookie for ChatGPT (121 `/`, 33 `/about/`, 24 `/registration/`, 12 `/licensing/`), which means ChatGPT visitors who registered often went home > /account > /registration in one go.

**What ChatGPT-landers do next (Q8e).** Of 43 ChatGPT sessions that viewed `/about/`: 17 exited there, 11 went to `/briefs/`, 4 straight to `/account/artist-registration/`, 3 to `/artists/`, 2 to `/licensing/`. Median time on About is 21 seconds; 6 sessions scrolled to the end (`content.read_complete`). People arrive already sold and look for the door.

## 3. What ChatGPT referrals actually do

Behaviour by source, visitors since 10 July (S3) and sessions (Q3):

| | ChatGPT | Google | Direct/none | Social |
|---|---|---|---|---|
| Visitors | 145 | 42 | 470 | 36 |
| Bounced (1 page view) | 31 (21%) | 17 (40%) | 379 (81%) | 27 (75%) |
| Median page views | 3 | 3.5 | 1 | 1 |
| Viewed `/registration/` | 36 (25%) | 11 (26%) | 48 (10%) | 1 |
| Ended up logged in | 25 (17%) | 12 (29%) | 28 (6%) | 1 |
| Returned on a second day | 20 (14%) | 6 (14%) | 19 (4%) | 2 |
| Registered (since 10 Jul, clean attribution) | 23 | 7 | 17 | 0 |
| Made an artist profile | 18 | 6 | 12 | 0 |
| Uploaded a track | 12 | 4 | 8 | 0 |
| Pitched a brief | 7 | 2 | 2 | 0 |
| Median session duration (Q3, seconds) | 81 | 125 | 4 | 24 |
| Median summed time_on_page (seconds) | 67 | 65 | 10 | 28 |
| Sessions with a song play | 23 of 241 | 1 of 54 | 5 of 395 | 4 of 42 |
| Mobile share of sessions (Q10i) | 40% | 37% | 24% | 71% |

Most-viewed pages by ChatGPT sessions (Q3): `/` 144, brief detail pages 81, artist pages 47, `/registration/` 44, `/about/` 43, `/account/` 42, `/artists/` 37, `/briefs/` 37, `/songs/` 36, `/licensing/` 35, `/account/artist-registration/` 26, `/account/album-upload-add-songs/` 22. Google sessions: brief detail 48, `/` 21, `/briefs/` 17, `/account/` 16, `/account/artist-registration/` 15.

Registrants convert fast: median 0.1 h between first analytics touch and `user_registered`, 36 of 47 within the hour (Q10e). The long tails are five people who came back weeks later (152 h to 1,390 h); the 90-day cookie still credited the original source.

Read together: ChatGPT is sending musicians who were told "Sync.Land lets you list free, keep 70%, non-exclusive, pitch to briefs", and who behave accordingly: register, build a profile, look at briefs. They are 5 to 10 times more likely to become an artist than a Google or direct visitor.

## 4. Crawlers: who reads the site (L2, L3, L3b, L3c, L4, L5)

Requests by crawler family, 10 to 17 Sep (8 days; 17 Sep is partial):

| Crawler | Requests | What it fetched |
|---|---|---|
| Applebot | 1,795 | 1,450 assets; ~50 content pages (Siri/Spotlight rendering) |
| ClaudeBot | 1,192 | 845 distinct paths: 424 song, 111 album, 52 artist, 45 genre, 12 brief; 126 robots.txt, 104 sitemap.xml; **261 x 404** on `/song/<slug>/feed/` and slash-less duplicates |
| Googlebot | 1,037 | 344 assets, 92 song, 33 album, 22 artist, 13 genre, 8 `/artists/`, 2 briefs; 47 robots.txt, 16 ads.txt |
| Bingbot | 699 | 191 song, 184 album, 92 artist, 27 brief; it re-fetches one artist's pages daily |
| ChatGPT-User (live fetch while answering) | 281 | **248 x `/`**, 11 `/briefs/`, 4 `/licensing/`, 4 `/about/`, 3 `/registration/`, 2 `/artists/`; 243 distinct IPs; 15 to 21 homepage fetches per day |
| OAI-SearchBot (ChatGPT search index) | 168 | 117 robots.txt; 7 each of `/licensing/`, `/free-sync-license/`, `/about/`; 5 `/artists/`, 5 `/`, 9 song, 4 genre |
| PerplexityBot | 75 | 68 song pages on 11 and 15 Sep |
| Claude-User | 3 | `/` twice |
| GPTBot, CCBot, Amazonbot, meta-externalagent, Google-Extended | 0 | blocked in robots.txt and they comply |
| Bytespider | 22 | robots.txt only (blocked, complies) |
| Sogou, AhrefsBot, PetalBot, ShapBot, SERanking, HubSpot, "Amzn-SearchBot", ev-crawler, ExaSearchBot | 20,000+ | SEO tools and scrapers; 43% of all requests and 51% of HTML page views are bots (L2b) |

Also in the log: `python-requests` 3,068 (the awen-bridge transcoder worker) and `blockfrost-webhook` 17,955 POSTs to `/` (2,000 to 2,700 a day, the Cardano webhook; harmless but it is 16% of all requests).

**What the LLMs read.** `ChatGPT-User` is the fetch ChatGPT makes when a user's question needs a live page. It read the homepage 248 times in 8 days and essentially nothing else. So the recommendation a user sees is generated from the homepage HTML: the H1 "Original music for film, games & podcasts. Licensed in one click.", two CTA lines, then the genre and country lists of the planet visualiser. The facts that convert (free listing, 70% to artist, non-exclusive, $49 commercial tier, open briefs, pitch flow) live on `/about/`, `/licensing/` and `/llms.txt`, which ChatGPT-User fetched 4, 4 and 0 times. The 70/30 split is stated in numbers **only** in `/llms.txt`; About says "our platform fee comes out of the sale" and Licensing says "Sync.Land takes a platform fee on each sale" without the number.

**robots.txt and policy** (L4, L5): `wp-content/mu-plugins/awen-ai-crawler-policy.php` appends the AI section to robots.txt and serves a virtual `/llms.txt`. Policy: `Content-Signal: search=yes, ai-input=yes, ai-train=no`; Allow for Google-Extended, ClaudeBot, Claude-SearchBot, Claude-User, OAI-SearchBot, ChatGPT-User, PerplexityBot, Perplexity-User; Disallow for GPTBot, CCBot, Bytespider, Amazonbot, Applebot-Extended, meta-externalagent, anthropic-ai. The log shows the blocked agents honour it (0 GPTBot requests). `/llms.txt` returns 200 (12 lines: tagline, six core links, three about links, a note on Cardano and Awen); it was fetched 4 times in 8 days, none by an AI crawler. `/ai.txt` is 404. `blog_public` is 1. The sitemap (The SEO Framework, `autodescription`) lists pages, songs and albums; Google did not fetch it in the window, Bing and Ahrefs did.

**Structured data** (L5): every page carries Organization, WebSite, WebPage and BreadcrumbList JSON-LD; artist pages add MusicGroup, Person and MusicAlbum; song pages add MusicRecording and AudioObject (generated by `functions/seo/music-schema.php`). There is no FAQPage, no Offer/Product schema on the licensing tiers, and About has no `sameAs` links to Catalyst or GitHub.

## 5. The funnel (Q5, Q5b)

| Step | All-time (analytics era, since 15 Mar) | September 1-17 |
|---|---|---|
| Visitors (anon ids since June: 807; sessions since March: 924) | 924 sessions | 319 new visitors / 421 sessions |
| Registered | 51 | 40 |
| Artist profile | 38 (75% of registered) | 30 (75%) |
| First track | 26 (68% of profiles) | 20 (67%) |
| First pitch | 13 (50% of uploaders) | 11 (55%) |
| Licence purchased by a third party | 0 | 0 |

All users ever (93): 51 profiles, 36 uploaders, 13 pitchers, 10 licence records of which 9 are pre-2024 or admin-created and 1 is Ian's own Stripe test.

Visitor-to-registration in September: 40 of 319 new visitors (13%); 86 distinct visitors viewed `/registration/` and 40 registered (47%). 49 of 50 registrants since June confirmed their email; the one who did not has no events and no profile.

**Median time between steps, September registrants** (n in brackets): registration to profile **0.1 h** (30); profile to first track **0.5 h** (20); first track to first pitch **1.0 h** (11); registration to first track **0.65 h** (20). The slowest profile-to-track conversions took 44 h, 38 h, 17 h and 14.5 h; in two cases pitches came 49 h and 154 h after the first track. Everyone else did the whole thing in one sitting.

Output volume: 106 songs published in September by 22 artists (23 in August by 4); 72 pitches in September by 13 artists (7 in August); 58 briefs added in September, 10 live on the day of the audit.

## 6. Where people drop (Q6)

**Registered, no profile: 13 of 50 since June.** 7 opened `/account/artist-registration/` and left; 0 hit a JS error; 1 has no events at all (unconfirmed email). Last page seen: `/account/` (3), `/briefs/` or a brief (2), `/registration/`, `/artists/`, `/terms-and-conditions/`, `/album/...`, `/account/forgot-password/` (one artist with two accounts created 7 minutes apart on 14 Sep: a duplicate signup followed by a password reset; the second account then made a profile). ChatGPT-attributed among the 13: 5. None of the 13 ever submitted the artist form (no `upload_request_started kind=artist` event exists for any of them), so the form was opened and abandoned, not rejected. Time on the form for five of them: 627 s over 3 visits, 37 s, 20 s, 13 s and 3 s. The form asks for a profile image up front; every successful profile in September carried one (the `kind=artist` events all include a `profile_image` file), which is consistent with people leaving to find a picture and not coming back.

**Profile, no track: 12 of 37 since June.** 7 opened `/account/album-upload-add-songs/`; 0 submitted the wizard (`upload_request_started kind=wizard` never fired for any of them); 0 `transcode_failed`; 0 `integrity_flagged`. Visits to the uploader: artist G 2 (55 s, 120 s), artist F 3 (11 s, 7 s, 34 s), artist H 4 (9 s to 70 s in 10 minutes), artist E 2 across two days. This is abandonment at the form, not a bug. The wizard asks for cover art, album metadata and a WAV/MP3 up front; a solo artist with one demo has to invent an album to get past step 1.

**Track, no pitch: 13 of 25 since June.** Only 5 of the 13 ever viewed `/briefs/` or a brief; 4 opened `/account/pitches/`. 8 never saw a brief at all. The 12 who did pitch got nothing back: all 109 pitches are `status=new`.

**Registration page without registering.** Since 10 July, 44 ChatGPT sessions viewed `/registration/` and 23 left without a user id; direct 55 viewed, 46 left (Q6d). September: 86 visitors saw the page, 40 registered.

**Errors that actually fired** (Q6d): `registration_js_error` 38 events in 10 sessions, of which 30 are one user (12 Sep 10:28 to 10:34) hitting "Loading chunk 557/212/334 failed" (a script chunk timing out on a slow connection; he registered anyway); 6 are "Script error." with no detail; 3 `php_fatal` (an awen-client Gmail write path, not user-facing). `transcode_failed` 219 events on 10 and 11 Sep are the EWWW image-editor incident already fixed. Nothing in the trail shows a broken step; the drops are decisions.

## 7. Geography (Q7)

Signup country is recorded for 60 of 93 users (33 legacy accounts have none). All time: US 29, NG 5, GB 4, BR 3, AU 3, DE 2, then 1 each for TR, IR, CO, PY, NL, KE, BE, IE, IT, CH, LT, ZA, CA, FR.

By month since June:

| Country | Jun | Jul | Aug | Sep | Total |
|---|---|---|---|---|---|
| US | 0 | 0 | 1 | 20 | 21 |
| NG | 0 | 0 | 1 | 4 | 5 |
| GB | 1 | 0 | 0 | 2 | 3 |
| AU | 0 | 0 | 1 | 2 | 3 |
| BR, DE | 0 | 0 | 1 each | 1 each | 2 each |
| TR, IR, CO, PY | 0 | 1 (TR) | 3 | 0 | 1 each |
| NL, KE, BE, IE, IT, CH, LT, ZA, CA, FR | 0 | 0 | 0 | 1 each | 1 each |

September: 20 countries served, 12 in one month. ChatGPT registrants: 10 of 19 US, the rest one each from BR, KE, BE, IE, AU (2), ZA, DE, NG, CA. Google registrants: 4 of 6 US. Daily signups in September ran 1 to 6 with no day at zero after 8 Sep (Q7c). Visitor country is not recorded (no geo on analytics rows).

## 8. Content that gets read (Q8)

**Top pages by distinct real sessions since June** (Q8a): `/` 460, brief detail pages 166, `/account/` 149, artist pages 146, `/registration/` 124, song pages 103, `/briefs/` 102, `/songs/` 101, album pages 84, `/artists/` 82, `/about/` 79, `/licensing/` 75, `/account/artists/` 63, `/account/artist-registration/` 63, `/account/album-upload-add-songs/` 46, `/cart/` 25, `/account/pitches/` 21, `/contact-us/` 19, genre pages 18, `/free-sync-license/` 6.

**Songs by plays from non-owner sessions, all time** (Q8c): the most any track has is 3 plays from 3 sessions (two tracks). Total non-owner `song_play` events: Jun 2, Jul 1, Aug 24 (14 sessions), Sep 60 (24 sessions). Listening is happening but by dozens, not thousands, and mostly by other artists browsing.

**Briefs by sessions** (Q8d): coke-march-madness-r2 9, twngo 7, genghis-r2-indie 7, jm-autumn 5, then 4 each for amazon-x-2, 00s-cool-rock, march-madness, coke-march-madness-r3, wwl-throwback-vibe. 90 distinct briefs were viewed since June; pitches per live brief on the audit day: twngo 5, coke-march-madness-r2 5, fash-fem-punk-new-wave 3, coke-march-madness-r3 2, genghis-r2-indie 2, wwl-throwback-vibe 2, beach-bar-music 1, fitness-co 1, cvcf 0, ldr-radio-alts 0. Brief detail pages are the second most-viewed page type on the site and the first thing ChatGPT visitors go to after About: briefs are the product for this cohort.

**About's role** (Q8e): 79 sessions since June; of the 43 ChatGPT ones 38 landed there directly. It is the conversion page: 11 of 23 ChatGPT registrants landed on it. It is read for a median 21 seconds and 40% exit from it; the ones who stay go to `/briefs/` (11) or straight to the artist form (4). The page already says the right things (free, no exclusivity, briefs, founder, Catalyst, open code) but not the fee number, the payout mechanics in numbers, what files to bring, or what happens after a pitch.

**Read-to-the-end events** (Q8f): `/registration/` 69, artist pages 53, `/account/` 43, artist form 41, `/artists/` 38, `/songs` 36, uploader 35; `/about/` 9. People scroll registration and profile pages, not marketing pages.

**Outbound clicks**: 54 events since June, all with an empty URL payload (the event does not record the href), so link targets cannot be reported. **Search box**: 4 queries ever ("post-punk", "VOLTA", "Moe Shop", "Snail Hause").

## 9. Survey: how did you find us (Q9)

11 responses in total carry `how_found_us`: search_engine 4, social_media 3, word_of_mouth 2, other 2. Five of the eleven are from September (average NPS 4.6, down from 10 in July and August; the 4 came from one artist, who asked for "more descriptions on the briefs. easier upload process"). The survey offers no "AI assistant / ChatGPT" option, so the largest channel is invisible to it; the two "other" answers in September are probably that.

## 10. Surprises

1. **The page cache is handing out one analytics session id to everyone.** `awen-analytics.js` reads `cfg.session_id` inlined in the HTML; nginx FastCGI cache serves that HTML to the next visitors, so 64 sessions (4,308 events) contain 2 to 42 different `anon_id`s and up to 13 IPs, with durations up to 894,930 s. Session counts, durations and "bounce" in the dashboard are wrong for cached pages; user ids and the campaign cookie are computed per request and remain correct. Fix: mint the session id client-side (`crypto.randomUUID()` into the cookie) or exclude the config from cached output.
2. **ClaudeBot runs the tracker.** 94 analytics sessions with a `ClaudeBot/1.0` UA fired `page_view`, `time_on_page`, `content.read_complete` and one `add_to_cart` (April to September). AhrefsBot, YandexRenderResourcesBot and Baiduspider-render do the same. The dashboard does not filter by UA.
3. **ChatGPT reads the homepage 15 to 21 times a day** (`ChatGPT-User`, 243 distinct IPs in 8 days) versus 2 to 9 human click-throughs a day. Roughly one in three ChatGPT conversations that fetch Sync.Land produces a visit. That fetch is of `/` only.
4. **GPTBot is blocked and obeys, so ChatGPT's knowledge of Sync.Land comes from live fetches and the light OAI-SearchBot index** (about 50 pages in 8 days). This is fine while the homepage says the right things; it also means changes to the homepage propagate to ChatGPT answers within a day.
5. **The marketplace has one side.** 48 of 50 registrants since June are musicians; there is no inbound buyer traffic at all (Google landings are brand or artist-name searches, no "license music for film" style landings on song or genre pages). Four anonymous cart attempts died at the login wall (Q10f).
6. **Pitches are a black hole.** 109 pitches, 13 artists, status `new` on all of them, one `artist_notified_interested`. The strongest cohort (ChatGPT registrants: 7 of 23 pitched, 28 pitches in 10 days) has received nothing back.
7. **Referrer spam dominates "other referral"**: trooker.com, roozzy.com, virtuallrc.com, picfog.com, cluuz.com, etc. They carry browser UAs so they pass the bot filter, and one of them polluted the attribution of two real artists through a cache-shared session.
8. **`blockfrost-webhook` POSTs `/` 2,000 to 2,700 times a day** (17,955 in 8 days), all 200. It is 16% of requests; worth confirming the endpoint is meant to be the site root.
9. **freemusic.land, the old domain, is still being polled** 60 IP-days a week by what looks like an uptime monitor on an iPhone 13 UA; each hit is a 301. Harmless, but it inflates any naive referrer count.
10. **Instagram works for artists, not for the site.** 27 new social visitors in September (two artists' link-in-bio), 71% mobile, 75% bounce, 1 registration, 0 listens beyond the shared track.
11. **Google is used as a second step.** Half of Google landings are the homepage from a search for the brand, which is the classic "heard about it in ChatGPT, verifying it in Google" pattern; the timing (Google registrations 1 in August, 6 in September) tracks the ChatGPT curve.
12. **The 70% figure is only stated in llms.txt.** About and Licensing describe the fee in words. An LLM asked "what does Sync.Land charge" has to find `/llms.txt`, which no AI crawler fetched in the window.

## 11. Recommendations, ranked

Each item names the number it is tied to. Items 1 to 4 protect and grow the channel that is already working; 5 to 8 fix the funnel it feeds; 9 to 11 are measurement; 12 is what to leave alone.

### 1. Put the facts ChatGPT needs on the homepage, in server-rendered text (finding 4; L3: 248 of 281 ChatGPT-User fetches are `/`)

ChatGPT builds its answer from the homepage HTML, and the homepage above the fold is two sentences plus genre counts. Add a plain HTML block (not canvas, not an image, not lazy-loaded) under the hero, in the order an assistant would quote it:

- What it is: "Sync.Land is a direct music-licensing marketplace: filmmakers, game developers, podcasters and creators license original music from independent artists."
- For artists, in numbers: free to list, no subscription, no exclusivity, artist sets the price, **artist keeps 70% of every commercial licence**, paid out by Stripe or PayPal, pitch tracks to open briefs (with the live count), pause or remove any track any time, catalogue from N countries.
- For licensees, in numbers: Free Sync License (SLFS-v1.1) for personal, education, UGC under 100k views and podcasts; Commercial Sync from $49 per track, per project, forever; PDF certificate, optional Cardano record.
- How to start, two links: `/for-artists/` (item 2) and `/licensing/`.
- Who runs it: Awen LLC, Milwaukee; founder Ian McCullough (Cullah); Catalyst Fund11 project 1100272; open code on GitHub.

Keep the same figures on `/about/`, `/licensing/` and `/llms.txt` (finding 12). Any inconsistency between pages is what makes an assistant hedge instead of recommend. Expected effect: better ChatGPT answers within a day (ChatGPT-User re-fetches daily), and some of the 101-lander cohort should behave like the 29-lander cohort (10% versus 38% registration).

### 2. Build `/for-artists/` and make it the page ChatGPT links to (findings 1, 4; Q3; 48 of 50 registrants are musicians)

ChatGPT currently sends artists to `/about/` because there is no better page. Create a page written as questions and answers, each answer a short factual paragraph, with FAQPage JSON-LD emitted by `music-schema.php`:

- Is Sync.Land free for artists? (yes; no listing, submission or subscription fee; fee only on a sale)
- How much does Sync.Land pay? (70% of a commercial licence; Free Sync licences pay nothing until upgrade; payout via Stripe/PayPal; when payouts are sent)
- Is it exclusive? (no; keep Spotify, Bandcamp, BroadJam, other libraries)
- What do I need to upload? (formats accepted, cover art optional, one track is enough, metadata list, time from upload to live, the file check and AI review states)
- How do briefs and pitches work? (who posts them, how many are live, what a pitch contains, what happens next and how fast, item 6)
- Who licenses music here? (honest: filmmakers, game devs, podcasters; catalogue size; the on-chain record)
- What about AI-generated music? (the disclosure field; fully generated tracks not licensed under SLFS)
- Which countries? (all; list the 20 already on the site)
- Who is behind it? (Awen LLC, Catalyst, GitHub links with `sameAs`)

Link it from the header, from the homepage block, first in `/llms.txt`, and from the "I make music" card on About. Once it exists, the About page can stay as the trust page and the artist CTA moves to the new one.

### 3. Fix the profile-to-first-track drop (finding 5; Q6b: 12 of 30 September profiles never uploaded, 7 opened the wizard and left, 0 errors)

- Let a new artist upload **one track with an MP3 and no cover art** (fall back to the profile image), and defer album fields. The wizard's first screen should ask for the file and the title only.
- Show the requirements before the form ("You will need: an audio file, a title, ownership confirmation; cover art optional").
- Save the draft server-side and email a deep link 24 hours after profile creation if no track exists ("Your profile is live; add your first track"): the 7 who reopened the wizard 2 to 4 times are the audience. The nudge tooling already exists (commit 2871e97).
- Ask the two artists who abandoned most visibly (artist H, 4 visits in 10 minutes; artist G, 3 minutes on the page) what stopped them.

### 4. Close the pitch loop (finding 6; 109 pitches, all `new`)

Artists who pitched are the retention asset; nothing has ever come back to them. Ship, in order: an automatic "received" state with a stated review window; a weekly digest to Ian of new pitches per brief; a "reviewed / shortlisted / not this time" status the artist sees on `/account/pitches/` and gets by email; and a public note on `/briefs/` saying what the response time is. Even "not selected" within 7 days keeps them pitching. Brief detail pages are already the second most-viewed page type (166 sessions).

### 5. Fix the registration-to-profile drop (Q6a: 13 of 50, 7 stopped on the artist form)

Make the profile image optional on `/account/artist-registration/` (every aborted submission in this cohort was the image post), keep the form to name, bio, location and links, and send the profile-not-started nudge 24 hours later. Fix the duplicate-signup path: two accounts in 7 minutes then forgot-password (artist F) is the artist dupe-guard gap already on file.

### 6. Let people listen and buy without an account (finding 6; Q10f: 4 anonymous carts died at the login wall; song plays 60 in September)

Guest checkout for the Free Sync tier (email only, licence PDF by mail) and for Commercial via Stripe Checkout with the account created after payment. Zero licences have been sold to a third party; until one is, the artists being recruited have no proof the model works. This ranks below 1 to 5 only because there is no buyer traffic yet to convert (item 8).

### 7. Crawler hygiene (Q4; ClaudeBot 261 x 404, Googlebot 47 robots.txt fetches and 16 ads.txt against ~180 content pages)

- Remove the `<link rel="alternate" type="application/rss+xml">` comment-feed links on song, album, artist, genre and mood singles (or serve them); ClaudeBot and OAI-SearchBot are burning a fifth of their budget on `/feed/` 404s.
- Emit canonical trailing-slash URLs everywhere (ClaudeBot fetched one artist page with and without the trailing slash as two pages).
- Drop bot UAs at the analytics ingest (`ClaudeBot`, `AhrefsBot`, `*Render*`, `Baiduspider`, `HeadlessChrome`, `python-requests` on page events) so the dashboard counts people.
- Fix the cache-shared session id (finding 7) by generating it in the browser.
- Add FAQPage schema (items 1, 2), Offer schema on the three licensing tiers, and `sameAs` (Catalyst, GitHub, cullah.com) on the Organization node. The rest of the schema (MusicGroup, MusicRecording, AudioObject) is already better than most competitors and should be left alone.
- Keep the robots policy as is: the blocked training crawlers comply, and the allowed answer engines are the ones sending people. Do not block ClaudeBot or PerplexityBot to save bandwidth; they are the next ChatGPT.

### 8. Google: set up Search Console, then write for the queries the landings imply (finding 8; L1: 26 of 49 Google landings on `/`, 7 on one artist page)

Search Console is free, takes ten minutes (DNS or the HTML tag through The SEO Framework), and is the only way to see the queries; nothing here can substitute for it. Until then, the landing pattern says Google is mostly brand ("sync.land", "sync land music licensing", "is sync.land legit") and artist names. The one content bet that fits the evidence is the artist side: `/for-artists/` (item 2) targeting "submit music for sync licensing", "free music licensing sites for independent artists", "music licensing sites that pay 70%", "non-exclusive sync licensing platform". Song and genre pages are indexed but do not rank for licensing queries; do not spend on them yet. Confirm Google is reading the sitemap (it did not fetch it in the 8-day window) by submitting it in Search Console.

### 9. Add "AI assistant (ChatGPT, Claude, Gemini, Perplexity)" to the survey's how-found-us list, with a free-text "what did you ask it?" (finding: Q9 has no such option; 11 responses ever)

This is the only way to learn which prompts produce the recommendation, since ChatGPT sends a UTM and no query. Ask it on the registration confirmation page too, one optional field: "What did you search for or ask to find us?". Ten answers would be worth more than the whole survey table.

### 10. Tag the untagged (Q1d: 18 of 50 registrants have no source)

Claude, Gemini, Copilot and Perplexity send no UTM and, from apps, no referrer. Add a "how did you hear about us" select to the registration form (artist form is fine too) with AI assistant, Google, Instagram/TikTok, a friend or artist, other. It costs nothing and turns the biggest unknown bucket into data.

### 11. Watch these five numbers weekly (all computable from the queries in the data file)

New ChatGPT visitors (S4), registrations by source (Part E), profile-to-track conversion (Q5), pitches answered within 7 days (needs item 4), and ChatGPT-User homepage fetches per day from the log (L3c). If the first falls for two weeks, check what the homepage says and what ChatGPT says about Sync.Land.

### 12. What not to bother with

- Bing, DuckDuckGo, Baidu, Yandex: 12 visitors in four months, 0 signups.
- Perplexity and Claude as referral channels: 0 human referrals so far; keep them crawlable and wait.
- Instagram and Facebook as acquisition: they bring artists' fans to artists' pages (75% bounce, 1 signup in 40 visitors). Give artists good OG images and share buttons and let them do it; do not run posts aimed at recruiting.
- Paid ads, AMP, email newsletters to the general public, the "direct traffic spike" in late July (bots), and the referrer-spam hosts.
- The NFT/on-chain receipt as a headline claim in any of the above copy: it is not what the converting cohort came for (they came for free listing, 70%, non-exclusive, briefs). Keep it as a supporting line.
- Rewriting About: it converts already. Add the numbers and the link to `/for-artists/`; leave the structure.

## 12. What could not be measured, and why

- **Search queries** on Google or Bing: no Search Console or GA property. Landing pages are the only proxy.
- **Which ChatGPT prompts produce the recommendation, and what ChatGPT says**: ChatGPT sends only `utm_source=chatgpt.com`. The survey and registration form do not ask. Item 9 fixes this going forward.
- **Traffic from Claude, Gemini, Copilot, Perplexity users**: these send no UTM; from apps they send no referrer. They are inside the "untagged" 18 registrants and 156 September direct visitors and cannot be separated.
- **True session counts, durations and bounce**: the cache-shared session id (finding 7) corrupts 22% of events; visitor-level numbers were used instead and are sound, but "sessions" in the admin dashboard should not be quoted until the id is minted client-side.
- **Visitor geography**: analytics rows have IPs but no country; only signups have a country (60 of 93 users).
- **June and earlier attribution**: the campaign cookie starts 2026-07-10; before that only `document.referrer` exists, which ChatGPT does not send.
- **Outbound link targets**: 54 `outbound.click` events all have an empty `url`, so where people leave to is unknown.
- **Email open or click rates** for nudges and notifications: not in these tables (Gmail referrer hits, 27 IP-days, are the only trace).
- **Why the 12 profile-only artists did not upload**: no error fired and no draft was saved; the wizard was opened and closed. Only asking them will answer it.
- **Buyer intent**: with zero purchases and 4 anonymous carts, nothing about pricing or the licence tiers can be inferred from behaviour yet.
