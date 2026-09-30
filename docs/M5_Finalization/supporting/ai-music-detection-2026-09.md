# Noticing AI-generated uploads: what is realistic in September 2026

> Published copy for the Catalyst Fund 11 close-out. Individual users, artists and songs are not named: people appear as letters or counts. Aggregate figures are unchanged.

Research note, 2026-09-18. Scope: give a human a reason to ask an artist how a track was made. Nothing here proposes an automatic hold, a public label, or a score shown to anyone but an administrator. The rule in `syncland-ai-review.php` stands: a signal is a reason to ask, never a finding.

## Short version

- The strongest signal available is one we already download and throw away. Suno WAV exports carry a RIFF INFO comment `made with suno; created=<iso>; id=<uuid>`. Three masters in the catalogue have it. Two are declared AI-assisted; one, uploaded in late July before the disclosure field existed, is not declared at all. Our provenance plugin reads `ISFT` and `bext` and skips `ICMT`, so it never saw them.
- No file in the catalogue carries a C2PA manifest, including a Suno WAV created 2026-09-02, after Suno said it attaches Content Credentials. Do not plan around C2PA yet, but read it when present: it is free and cannot false-positive.
- Every audio-statistics heuristic I could test on our own files (spectral rolloff, 16 to 20 kHz energy, stereo correlation, average-spectrum peakiness) does not separate the nine declared-assisted tracks from Pro Tools masters uploaded the same week. The old "Suno has a 16 kHz cutoff" advice is dead for current versions.
- The only open model worth trying is SONICS SpecTTTra (MIT, about 20M parameters, runs on CPU). Published numbers are good in-distribution and poor out of it. It must be measured on our own labelled set before any output reaches an admin.
- Recommended first step (under a week): extend the worker's existing probe to read all provenance tags plus C2PA, report them through the `transcode-integrity` path with no change to verdicts, and show "names a generator" as a separate, explicit class on the Provenance Review page. Precision of that class is effectively 100 percent by construction. Recall is low and will stay low; that is acceptable.

## (a) What we have today

**Disclosure field.** `ai_disclosure` (none / assisted / generated) is required in the upload wizard since early September. Prod on 2026-09-18: 125 `none`, 9 `assisted`, 0 `generated`; 382 of 516 published songs predate the field and have no answer. The nine assisted tracks come from four accounts.

**Provenance triage** (`wp-content/mu-plugins/syncland-provenance.php`). Reads the first 64 KB of the master (else the stream MP3), records `_provenance_encoder`, `_provenance_tagcount`, `_provenance_source`, classifies as ours / daw / converter / named_tool / bare. Measured 2026-08-25: roughly one-in-ten precision. Two gaps found while writing this note:

1. It only reads `ISFT` and `bext Originator`. `ICMT` is where Suno writes its stamp.
2. It only reads the first 64 KB. FL Studio, Mixcraft and Logic put their `LIST INFO` chunk after the `data` chunk (three songs in the pilot), so their DAW signatures were recorded as blank.

**File checks** (`syncland-integrity.php` and `_probe_integrity()` in `~/awen-bridge/awen_bridge/providers/transcoder.py`). The worker downloads every file once, runs ffprobe and a full decode, and POSTs `{verdict, reasons, report}` to `/wp-json/FML/v1/transcode-integrity`; the site stores `report` in `_integrity_report`. Sample rate, bit depth, duration and levels are already captured for 520 songs. Only `flag` and `fail` raise a hold.

**Worker host.** Ubuntu 22.04, Python 3.10.12 (system, no venv), ffmpeg 4.4.2, i7-3770S (4 cores, 8 threads, AVX but no AVX2), 15 GB RAM, 158 GB free, no GPU. No numpy, torch or librosa installed. PyTorch CPU wheels still run on this CPU, only slower.

### Pilot on our own files (38 tracks, 1.6 GB, run 2026-09-18)

9 declared-assisted, 23 declared-none from September 2026 (mostly Pro Tools 24-bit masters, some converter WAVs, five MP3-only), 6 undeclared earlier masters. Findings:

| Signal | Assisted (9) | Declared none / undeclared (29) | Verdict |
|---|---|---|---|
| RIFF `ICMT` = "made with suno" | 2 | 1 (undeclared) | Deterministic. Use it. |
| C2PA manifest (any chunk) | 0 | 0 | Absent everywhere. Read it anyway. |
| Filename names a generator | 1 (`..._MusicGPT-1 (1).wav`) | 0 | Deterministic. Use it. |
| 48 kHz / 16-bit WAV, bare `fmt`+`data` | 4 | 3 (including one from the same artist as four assisted tracks) | Shape of a Suno WAV once anything touched it, and of many converters. Record, never score. |
| ISFT `Lavf*` on the master | 3 | 7 (online mastering services and converters) | Ffmpeg wrote it. Says nothing. |
| 99.9 percent energy rolloff | 12.8 to 17.0 kHz | 3.9 to 16.7 kHz | No separation. |
| Energy above 18 kHz | 0 to 6e-4 | 0 to 5e-4 | No cutoff signature in Suno v4/v5 files. |
| L/R correlation | 0.59 to 0.95 | 0.10 to 1.00 | No separation. |
| Average-spectrum peakiness 5 to 16 kHz (untrained) | 0.12 to 0.20 | 0.10 to 0.52 | No separation without a trained classifier. |

Full numbers are kept with the working files; the range-scan of all 283 masters and 233 MP3-only files found no other generator name in any header.

## (b) Options, ranked

### 1. Read the provenance the files already carry (recommended first)

- Detects: files exported straight from Suno (`ICMT`), any generator that names itself in `ISFT`, `TENC`, `TSSE`, `COMM`, `TXXX`, filename, or a C2PA manifest (Suno says it now attaches one, ElevenLabs Music signs MP3 when `sign_with_c2pa` is set, Udio reportedly since February 2026 under its UMG terms).
- Evidence: our own catalogue, above. Suno's safety page: "We attach Content Credentials to songs generated on Suno" (https://suno.com/safety), applying to new downloads only (https://suno.com/suno-credentials). ElevenLabs API parameter `sign_with_c2pa`, MP3 only (https://elevenlabs.io/docs/api-reference/music/compose).
- False-positive risk: none for `ICMT`/C2PA (a signed or literal statement by the generator). Filename tokens need a word-boundary match; "udio" inside "Studio" and "audio" bit this pilot twice.
- Cost: zero. Licence: `c2patool` is Apache-2.0 / MIT, prebuilt Linux x86_64 binary (https://github.com/contentauth/c2patool). Reads WAV and MP3.
- CPU/RAM: negligible; it is a header parse plus one subprocess.
- Plug-in: extend `_probe_integrity()` (or a sibling `_probe_provenance()` called from `_process()` right after download) and add keys to `report`. No verdict change. Site side, a `syncland_integrity_rest_report` follow-up copies the keys into `_provenance_*` metas.
- Effort: 1 to 2 days including backfill and the admin page change.

### 2. Suno's free credentials API

`POST https://studio-api.prod.suno.com/api/c2pa/detect` (multipart) or `/detect/url`, no key, rate-limited, returns `verified_suno | no_suno_provenance | inconclusive` (https://suno.com/suno-credentials). It does what `c2patool` does offline, except it sends the artist's master to Suno, which our Terms do not cover. Use `c2patool` locally.

### 3. SONICS SpecTTTra (open model)

- Detects: end-to-end synthetic songs. Trained on 49k Suno (v2, v3, v3.5) and Udio (32, 130) tracks versus 48k YouTube songs (https://github.com/awsaf49/sonics, ICLR 2025, https://arxiv.org/abs/2408.14080). Weights on Hugging Face (`awsaf49/sonics-spectttra-alpha-120s` etc.). Code and models MIT; dataset CC BY-NC.
- Evidence for accuracy: in-distribution F1 0.97, specificity 0.99 for the 120 s alpha model. Independent 100-track test (Warsaw University of Technology, ISMIR 2025 LBD, https://arxiv.org/abs/2507.10447): real songs scored 6.1 percent (plus or minus 4) fake, Suno 96 percent, but Udio 50 percent (plus or minus 43), YuE 55 percent, MusicGen 35 percent. A 2-semitone pitch shift down makes Suno read as human; added silence and damaged high frequencies push anything toward fake; an empty file is classified fake. Nothing published on Suno v4, v4.5 or v5, which is what our uploads are.
- False-positive risk: low on clean commercial masters per the numbers above; unknown on lo-fi, ambient, orchestral-with-samples and heavily processed material, the styles iMusician lists as most often wrongly flagged (https://imusician.pro/en/resources/blog/ai-false-positives-in-music).
- Cost: zero. CPU/RAM: torch CPU plus the 20M-parameter model, 16 kHz mono input, 120 s window; expect a few seconds per track on the i7, well under 1 GB RAM. Needs `pip install torch` (about 200 MB CPU wheel) on the worker or a venv.
- Plug-in: same path as option 1, one extra float `report['sonics_p']` plus model id and version. Never a verdict.
- Effort: 2 to 3 days including the evaluation in (d). Ship only if the evaluation passes.

### 4. Deezer-style spectral fakeprint

- Detects: periodic peaks in the averaged 5 to 16 kHz spectrum caused by deconvolution upsampling in neural codecs (Afchar et al., ISMIR 2025 best paper, https://arxiv.org/abs/2506.19108). A 10k-parameter logistic regression matched a 20M transformer: 99.97 percent on real FMA tracks, 100 percent on Suno v3.5 and Udio 130, 39.8 percent on unseen Udio 32. Authors say resampling and pitch shift break it.
- Licence: code CC BY-NC 4.0, two patents filed December 2024, commercial use by contacting research@deezer.com (https://github.com/deezer/ismir25-ai-music-detector). No pretrained weights; the untrained peakiness measure in my pilot separated nothing.
- Cost: zero in money, real in licence risk. Effort 3 to 5 days plus training data. Park it.

### 5. Commercial detectors

| Vendor | Access | Price | Claimed accuracy | Notes |
|---|---|---|---|---|
| Deezer (business.deezer.com/ai-detection) | Contact form, "API-first" | Not published | FP rate below 0.01 percent; 99.8 percent on fully AI tracks | Deezer reports 44 percent of its daily uploads are AI (https://newsroom-deezer.com/2026/04/ai-generated-tracks-represent-44-of-new-uploaded-music/). Likely enterprise-only. |
| Pex / Vobile AI Song Detector (https://pex.com/ai-song-detector/) | Sales demo, API + MCP | Not published | "Higher than competitors", no FP figure | Attributes to Suno, Udio, Boomy, ElevenLabs. |
| Ircam Amplify AIMD (https://aimd.ircamamplify.com/) | Sales | Not published, no trial | 99 percent, under 1 percent FP (vendor) | Enterprise volumes. |
| Cyanite (https://cyanite.ai/ai-music-detection/) | Request form, REST API | Not published | "99 of 100 detected, negligible FP", deliberately conservative | Suno, Udio, Lyria, Mureka, ElevenLabs. Closest fit in philosophy. |
| Hive (https://docs.thehive.ai/reference/ai-generated-music-detection-1) | Self-serve, key in minutes | 10 USD per audio hour listed; 100 requests/day free tier | None published | Returns per-10 s chunks with attribution (Suno, Udio, Mubert, MusicGen, Riffusion, Stable Audio). About 0.70 USD per 4-minute song. |
| AI or Not (https://www.aiornot.com/pricing) | Self-serve, API key | 5 USD/month plus credits | None published | Music included, per-check cost not stated. |

All of these send the artist's file off-site, and none publishes a false-positive rate measured on independent data. At our volume Hive or Cyanite is affordable, but if one is ever used it should be a second opinion an admin requests per song (`wp syncland-ai second-opinion <id>`, about a day), never a pipeline step.

### 6. Watermarks

Nothing readable today. SynthID marks Google Lyria output only and has no public detector API (https://deepmind.google/models/synthid/). AudioSeal is MIT and detects only AudioSeal marks; public MusicGen releases are not watermarked (https://github.com/facebookresearch/audioseal). Suno announced acoustic watermarking on 2026-08-06 "in the coming weeks" with no reader published (https://suno.com/safety). Revisit in Q1 2027.

### 7. Cheap audio heuristics

Sample rate, bit depth, duration, loudness, stereo width, missing ID3: the pilot shows each is shared by real September uploads. Keep recording them in `_integrity_report`; never score on them. Lyrics-based detection (Deezer, ACL 2025, https://github.com/deezer/robust-AI-lyrics-detection) needs a transcription model and is a separate project.

## (c) Recommended first step: provenance v2, one week

**Worker** (`transcoder.py`, new function `_probe_provenance(path, url)` called from `_process()` after the integrity probe, wrapped so a failure never blocks the encode):

1. Walk every RIFF chunk including those after `data`; collect `LIST INFO` (`ISFT`, `ICMT`, `IENG`, `IART`, `INAM`, `ICRD`), `bext` Originator and Description, and note any `C2PA`, `iXML`, `axml` chunk. For MP3 read ID3v2 `TSSE`, `TENC`, `COMM`, `TXXX`, `GEOB` descriptors.
2. Run `c2patool <file> --info` if the binary is present; keep `claim_generator` and issuer.
3. Match, with word boundaries only, against a fixed list: suno, udio, elevenlabs, stable audio, musicgen, musicgpt, riffusion, boomy, soundraw, aiva, mubert, beatoven, mureka, lyria. Apply to tag values, C2PA generator and the basename.
4. Add to `report`: `prov_isft`, `prov_icmt`, `prov_bext`, `prov_id3_encoder`, `prov_id3_comment`, `prov_c2pa` (string, generator or empty), `prov_chunks` (list), `prov_named_tool` (string, empty when none), `prov_named_where` (icmt / c2pa / filename / id3). All under 500 characters, which `syncland_integrity_clean_report()` already enforces. Verdict stays whatever the integrity probe said.

**Site** (`syncland-provenance.php`, no new endpoint):

1. Hook the end of `syncland_integrity_rest_report()` with `do_action('syncland_integrity_report_stored', $song_id, $stored)`, and in the provenance plugin copy the `prov_*` keys into `_provenance_encoder` (prefer ISFT, then bext, then ID3), `_provenance_comment`, `_provenance_c2pa`, `_provenance_named_tool`, `_provenance_named_where`, `_provenance_source = worker`.
2. Fix `syncland_prov_read_fingerprint()` to also fetch the last 64 KB and to read `ICMT`, so the cron path and the worker path agree.
3. `syncland_prov_classify()` returns `named_tool` when `_provenance_named_tool` is set, regardless of era or of whether we transcoded it (the master is what was read).
4. Provenance Review page: a first table "Names a generator" listing tool, where it was found, declared value, and a "Hold for AI disclosure" link that goes through the existing `syncland_air_hold()` with the note prefilled ("File metadata names Suno; artist declared none"). The existing "bare encoder" table stays below it with its one-in-ten warning. The songs-list column adds a small admin-only "tagged: suno" line. Nothing on any public page, the clearance API, the artist dashboard or emails changes.
5. Meaning of "ask": `named_tool` is present AND `ai_disclosure` is `none` or empty. That is the only condition that puts a song in the top table. A declared `assisted` or `generated` track with a Suno tag is consistent and is not listed.

**Backfill.** The worker already offers every file exactly one integrity pass; add `needs_provenance` to `/pending-transcodes` (true when `_provenance_named_where` meta is absent) and let the existing `backlog` throttle drain 283 masters plus 233 MP3s over a few days. The undeclared Suno-stamped song will surface on day one; it should be looked at by a person before anything else ships.

**Effort:** worker 1 day, site 1.5 days, backfill and check 0.5 day. No new dependencies beyond the `c2patool` binary.

**Expected precision** of the top table: about 100 percent that the file passed through the named tool. It does not prove the whole track was generated (two of the stamped tracks are declared assisted and titled "Remastered"), which is why the outcome is a question, not a finding. Recall against a deliberate uploader is near zero, since any DAW bounce or `-map_metadata -1` strips it. Against the honest-but-unaware uploader, which is who we have actually seen, it is the best signal available.

## (d) Evaluation plan, before any model output reaches an admin

**Ground truth we own.** Positives: the one Suno-stamped, undeclared song plus the nine declared assisted (prod query 2026-09-18). Negatives: the 20 Pro Tools masters uploaded 2026-09-12 to 2026-09-17 40 Cullah masters dated before 2023, 10 converter WAVs, 10 September MP3-only uploads. About 10 positives and 80 negatives; every negative predates generative music or carries a DAW signature.

**Generated set we make.** One evening, under Ian's own accounts: 10 Suno v5 tracks (5 WAV, 5 MP3, in the site's real genres: hip-hop, afro house, cinematic, singer-songwriter, instrumental), 5 Udio, 3 ElevenLabs Music, 3 Stable Audio, 3 MusicGen-small rendered locally on CPU. For each, also produce: a DAW bounce (Reaper, 24-bit), a 128 kbps MP3 re-encode, a 48 to 44.1 kHz resample, a minus-2-semitone pitch shift. That is 24 clean positives and 96 processed variants. Keep them out of the repo and out of S3; store under `~/awen-bridge/eval/ai-detection/` on the worker with a CSV of labels.

**Measurements.**

1. Provenance v2: count of positives with a named tool, per variant. Expect: clean Suno exports 100 percent, DAW bounces 0 percent. Any hit on a negative is a bug to fix before ship.
2. SONICS SpecTTTra alpha-120s and gamma-5s: score every file. Report the ROC, and the recall on clean Suno v5, Udio and ElevenLabs at the highest threshold that gives zero false positives on the roughly 80 real tracks. Report how many real tracks exceed 0.5 at all. Pass criterion to show anything to an admin: zero real tracks above the threshold and recall on clean Suno v5 above 50 percent. If it fails on v5, the model is stale and we wait for a retrained release rather than lowering the bar.
3. Robustness: fraction of positives whose score drops below threshold after each variant, and fraction of negatives whose score rises above it after MP3 re-encode (the one transformation our own pipeline applies to every file).
4. Repeat quarterly and whenever Suno or Udio announce a model version; the Warsaw result shows one version change can halve recall.

**Where results go.** `docs/research/ai-detection-eval/<date>.md` plus the CSV; nothing is written to postmeta from an evaluation run. If SONICS passes it ships as `report['sonics_p']`, stored in `_provenance_model_score` with model id and date, shown to admins as a number beside the provenance row, never as a hold trigger.

## What surprised me

- Suno stamps its WAVs in plain text and we had been parsing the chunk next to it for a month.
- The 48 kHz / 16-bit bare-header WAV shape is split evenly between declared-assisted and declared-none, sometimes from the same artist. It says "Suno or a converter", never which.
- Deezer reports 44 percent of daily uploads are AI (18 percent in January 2025) and sells the detector; the one open model everyone benchmarks against was trained on generator versions two years old.
