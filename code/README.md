# Sync.Land — Selected Open Components

> The Sync.Land platform is delivered as a hosted marketplace at
> [https://sync.land](https://sync.land). This directory contains
> **selected open-source components** released under the MIT License
> 2.0 for community use, Catalyst Fund 11 transparency, and to give
> integrators a working reference for the public REST API.
>
> **Marketplace-specific logic — Stripe checkout, NMKR minting, the cart,
> admin tooling, the licensing pipeline, and operational integrations —
> remains proprietary and is not included here.**

## What's in this directory

```
code/
├── README.md                         ← this file
├── LICENSE                           ← MIT
├── docs/                             ← API documentation
│   ├── api-spec.yaml                 ← OpenAPI 3.0 spec (full surface)
│   ├── api-authentication.md         ← API key + nonce auth guide
│   ├── pods-schema-nft-fields.md     ← Pods CPT field reference
│   └── stripe-setup.md               ← Stripe / NFT integration walkthrough
└── wp-content/themes/syncland-open-core/
    ├── README.md                     ← installation + scope notes
    ├── style.css                     ← child-theme header
    ├── functions.php                 ← wires up the open modules
    └── functions/
        ├── seo/
        │   ├── music-schema.php      ← Schema.org JSON-LD (MusicRecording / MusicAlbum / MusicGroup)
        │   └── dynamic-meta.php      ← per-CPT SEO title + description templates
        ├── analytics/
        │   ├── schema.php            ← events + survey table DDL
        │   └── survey.php            ← role-aware survey shortcode + UI
        └── api/
            ├── analytics.php         ← survey + events REST endpoints
            ├── security.php          ← API key auth + rate limiter
            ├── songs.php             ← public-read song endpoint
            ├── artists.php           ← public-read artist endpoint
            └── search.php            ← search endpoint
```

Approximately **2,700 lines of PHP** spread across nine modules.

## What this open-core release demonstrates

| Theme | Files | What you can learn from it |
|---|---|---|
| **Music structured data** | `seo/music-schema.php`, `seo/dynamic-meta.php` | Drop-in Schema.org `MusicRecording`, `MusicAlbum`, `MusicGroup` JSON-LD; how to hook The SEO Framework (autodescription) so per-CPT titles / descriptions / `og:image` resolve from Pods relationships |
| **Role-aware user feedback** | `analytics/schema.php`, `analytics/survey.php`, `api/analytics.php` | A licensee-vs-artist conditional survey, NPS scoring, a non-blocking dismissable banner, an inline shortcode page, and a clean REST submit endpoint backed by a dedicated MySQL table |
| **Public-read REST surface** | `api/security.php`, `api/songs.php`, `api/artists.php`, `api/search.php` | API-key + WP-nonce authentication, per-IP rate limiting, structured success / error envelopes, and the read-only endpoints documented in the OpenAPI spec |

## What is intentionally **not** in this release

- **Stripe checkout** — the `/stripe/create-checkout` and webhook handler, plus the payment business rules
- **NMKR NFT minting** — the CIP-25 metadata builder, project-UID handling, and mint-status state machine
- **Licensing pipeline** — sync license PDF generation, signature, and delivery
- **Cart system** — session storage, pricing, and guest-to-account migration
- **Admin tooling** — analytics dashboard UI, NFT monitor, bulk email, tag coverage, license columns
- **Email / transcoder integrations** — operational pipelines (cross-machine WAV→MP3, OAuth SMTP, notification templates)
- **Forms** — artist registration, song upload, album upload (tightly coupled to Pods + S3)

A reviewer wanting the full hosted experience can use the live marketplace
at [https://sync.land](https://sync.land) — every public surface is reachable
there. The full REST API is documented in `docs/api-spec.yaml`.

## Requirements (if you want to actually run the open-core)

- WordPress 6.0+
- PHP 8.0+
- Hello Elementor parent theme
- Pods plugin (defines the `song`, `artist`, `album`, `playlist` CPTs the
  schema / API endpoints reference)
- The SEO Framework plugin (autodescription) — the SEO module filters its
  generated meta

The open-core theme is a child theme of Hello Elementor. Drop it in
`wp-content/themes/syncland-open-core/`, activate it, and the survey table
will be created on theme switch.

## License

MIT License — see [LICENSE](LICENSE).

## Project Catalyst

This open-core release is published as evidence for
[Project Catalyst Fund 11 — Milestone 3](https://projectcatalyst.io/funds/11/cardano-use-cases-concept/syncland-or-metaverse-and-video-game-music-licensing-awen)
in service of the milestone output "Updated GitHub and documentation,
including API specs."

The full project milestone documentation lives in
[`docs/`](../docs/README.md).
