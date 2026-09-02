<p align="center">
  <img src="https://www.sync.land/wp-content/uploads/2026/03/sync.land-logo-tag_transparent-1-1024x225.png" alt="Sync.Land - Music Licensing for the Metaverse" width="600">
</p>

<p align="center">
  <a href="https://sync.land">sync.land</a> &bull;
  <a href="https://projectcatalyst.io/funds/11/cardano-use-cases-concept/syncland-or-metaverse-and-video-game-music-licensing-awen">Catalyst Fund11</a> &bull;
  <a href="https://awen.online">Awen</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Catalyst-Fund11-2F6ED3?style=flat-square" alt="Catalyst Fund11">
  <img src="https://img.shields.io/badge/grant-100%2C000%20ADA-212C9A?style=flat-square" alt="100,000 ADA">
  <img src="https://img.shields.io/badge/code-MIT-E237B2?style=flat-square" alt="MIT">
  <img src="https://img.shields.io/badge/free%20tier-SLFS--v1-F0914D?style=flat-square" alt="SLFS-v1">
</p>

---

# Sync.Land | Metaverse & Video Game Music Licensing

Sync.Land is an open-source music licensing platform where independent artists list tracks and licensees obtain a free **Sync.Land Free Sync License (SLFS-v1)** or a paid **Non-Exclusive Sync** license, optionally backed by **Cardano NFTs** via NMKR, with **Stripe** payments.

Built on WordPress, powered by blockchain, and funded by **Project Catalyst Fund11**.

## Features

- **Instant Free Licensing** -- The [Sync.Land Free Sync License (SLFS-v1)](https://sync.land/free-sync-license/) with auto-generated PDF certificates. Attribution required; creator-scale live-stream and UGC use permitted
- **Paid Sync Licenses** -- Non-exclusive sync licenses for games, film, and metaverse projects via Stripe checkout
- **NFT-Backed Licenses** -- Mint any license as a Cardano NFT through NMKR for on-chain proof of rights
- **Persistent Music Player** -- Amplitude.js-powered sticky player with queue management, PJAX navigation for uninterrupted playback
- **Artist Profiles** -- Upload tracks, manage releases, and track licensing activity
- **REST API** -- OpenAPI 3.0 spec for external integrations (`/wp-json/FML/v1/`)
- **DreamObjects S3 Storage** -- Audio files, artwork, and license PDFs stored on S3-compatible cloud storage
- **OBS Player** -- A separate open-source app ([syncland-obs-player](https://github.com/Awen-online/syncland-obs-player), Apache-2.0) that verifies a track's sync licence against the public API before playing it on a live stream, and renders the required credit on air

## Repository Structure

```
sync.land/
├── code/                    # Curated open-core subset of the platform
│   └── wp-content/themes/syncland-open-core/
│       ├── functions/api/           # Public REST surface: artists, search, songs, API keys
│       ├── functions/analytics/     # Event schema + on-site survey
│       └── functions/seo/           # Structured data for music pages
├── docs/                    # Project Catalyst milestone evidence
│   ├── M1_Initialization/   # Design docs, timeline, status report
│   ├── M2_Development/      # Pilot marketing, test cases, status report
│   ├── M3_Marketplace/      # Marketplace launch evidence + on-chain licence demo
│   └── M4_API/              # API launch evidence pack, test case recording, play log
└── README.md
```

> **[/code](code/README.md)** -- Full technical documentation: installation, configuration, API endpoints, architecture
>
> **[/docs](docs/README.md)** -- Catalyst milestone reports, design documents, and project timeline

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Platform | WordPress 6.x + Elementor Pro |
| Theme | Hello Elementor Child (open-core subset published as `syncland-open-core`) |
| Audio | Amplitude.js (custom wrapper) |
| Navigation | PJAX (seamless page transitions) |
| 3D Visuals | Three.js (hero planet, particles) |
| Payments | Stripe API |
| Blockchain | Cardano via NMKR |
| Storage | DreamObjects S3 |
| Data | Pods plugin (custom post types) |

## Project Catalyst Fund11

Sync.Land is funded by a **100,000 ADA** grant from [Cardano Project Catalyst Fund11](https://projectcatalyst.io/funds/11/cardano-use-cases-concept/syncland-or-metaverse-and-video-game-music-licensing-awen) under the **Cardano Use Cases** category.

| Milestone | Focus | Status |
|-----------|-------|--------|
| M1 | Initialization -- Infrastructure & design | Delivered, signed off |
| M2 | Development -- Core features & pilot testing | Delivered, signed off |
| M3 | Marketplace Launch & API Development | Delivered, signed off -- [evidence](docs/M3_Marketplace/) |
| M4 | Marketplace Updates & API Launch | Submitted 2026-09-02 -- [evidence](docs/M4_API/) |
| M5 | Finalization & Closeout | Upcoming |

## Related repositories

Sync.Land ships as more than one artefact. This repository is the Catalyst
submission surface; the OBS Player is a standalone application in its own right.

| Repository | What it is | Licence |
|---|---|---|
| **Awen-online/sync.land** (here) | Catalyst milestone evidence and the curated open-core subset of the platform | MIT |
| [**Awen-online/syncland-obs-player**](https://github.com/Awen-online/syncland-obs-player) | The OBS Studio player that verifies a sync licence against the public API before playing a track on stream, and renders the required credit on air. The reference external application for M4 acceptance criterion 3. | Apache-2.0 |

## Getting Started

See **[code/README.md](code/README.md)** for full installation, configuration, and API documentation.

## License

The code released in this repository is licensed under the **[MIT License](LICENSE)**, as committed in the Project Catalyst Fund 11 application. Copyright (c) 2026 Awen LLC.

The hosted Sync.Land marketplace at [sync.land](https://sync.land), along with marketplace-specific business logic that is not part of this repository (Stripe checkout, NMKR minting pipeline, cart, admin tooling, transcoder, etc.), remains proprietary to Awen LLC.

---

<p align="center">
  <a href="https://awen.online">
    <img src="https://awen.online/wp-content/uploads/2025/01/Awen-Logo-2.0-Full-Final.png" alt="Awen" width="120">
  </a>
</p>
<p align="center">
  Built by <a href="https://awen.online">Awen</a>
</p>
