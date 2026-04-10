<p align="center">
  <img src="https://www.sync.land/wp-content/uploads/2026/03/sync.land-logo-tag_transparent-1-1024x225.png" alt="Sync.Land - Music Licensing for the Metaverse" width="600">
</p>

<p align="center">
  <a href="https://sync.land">sync.land</a> &bull;
  <a href="https://projectcatalyst.io/funds/11/cardano-use-cases-concept/syncland-or-metaverse-and-video-game-music-licensing-awen">Catalyst Fund11</a> &bull;
  <a href="https://awen.online">Awen</a>
</p>

---

# Sync.Land | Metaverse & Video Game Music Licensing

Sync.Land is an open-source music licensing platform where independent artists list tracks and licensees obtain **Creative Commons (CC-BY 4.0)** or paid **Non-Exclusive Sync** licenses, optionally backed by **Cardano NFTs** via NMKR, with **Stripe** payments.

Built on WordPress, powered by blockchain, and funded by **Project Catalyst Fund11**.

## Features

- **Instant CC-BY Licensing** -- Free Creative Commons licenses with auto-generated PDF certificates
- **Paid Sync Licenses** -- Non-exclusive sync licenses for games, film, and metaverse projects via Stripe checkout
- **NFT-Backed Licenses** -- Mint any license as a Cardano NFT through NMKR for on-chain proof of rights
- **Persistent Music Player** -- Amplitude.js-powered sticky player with queue management, PJAX navigation for uninterrupted playback
- **Artist Profiles** -- Upload tracks, manage releases, and track licensing activity
- **REST API** -- OpenAPI 3.0 spec for external integrations (`/wp-json/FML/v1/`)
- **DreamObjects S3 Storage** -- Audio files, artwork, and license PDFs stored on S3-compatible cloud storage

## Repository Structure

```
sync.land/
├── code/                    # Platform source code
│   └── wp-content/
│       ├── themes/hello-elementor-child-sync-land/   # Child theme (all custom code)
│       └── plugins/fml-music-player/                 # Sticky music player plugin
├── docs/                    # Project Catalyst milestone reports
│   ├── M1_Initialization/   # Design docs, timeline, status report
│   ├── M2_Development/      # Pilot marketing, test cases, status report
│   ├── M3_Marketplace/      # Marketplace launch evidence
│   ├── M4_API/              # API launch documentation
│   └── M5_Finalization/     # Closeout report
└── README.md
```

> **[/code](code/README.md)** -- Full technical documentation: installation, configuration, API endpoints, architecture
>
> **[/docs](docs/README.md)** -- Catalyst milestone reports, design documents, and project timeline

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Platform | WordPress 6.x + Elementor Pro |
| Theme | Hello Elementor Child |
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
| M1 | Initialization -- Infrastructure & design | Approved |
| M2 | Development -- Core features & pilot testing | Approved |
| M3 | Marketplace Launch & API Development | In Progress |
| M4 | Marketplace Updates & API Launch | Upcoming |
| M5 | Finalization & Closeout | Upcoming |

## Getting Started

See **[code/README.md](code/README.md)** for full installation, configuration, and API documentation.

## License

Proprietary -- Sync.Land / Awen LLC

---

<p align="center">
  <a href="https://awen.online">
    <img src="https://awen.online/wp-content/uploads/2025/01/Awen-Logo-2.0-Full-Final.png" alt="Awen" width="120">
  </a>
</p>
<p align="center">
  Built by <a href="https://awen.online">Awen</a>
</p>
