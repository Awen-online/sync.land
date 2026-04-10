# Sync.Land

Music licensing platform where artists upload tracks and licensees obtain Creative Commons (CC-BY 4.0) or paid Non-Exclusive Sync licenses, optionally backed by Cardano NFTs.

**Production:** https://sync.land

## Tech Stack

- **Platform:** WordPress 6.x + Hello Elementor Child Theme
- **Page Builder:** Elementor Pro
- **Audio:** Amplitude.js (custom `fml-music-player` plugin) with PJAX for uninterrupted playback
- **3D Visuals:** Three.js (hero planet, particle backgrounds)
- **Payments:** Stripe
- **NFTs:** NMKR (Cardano blockchain)
- **Storage:** DreamObjects S3 (audio, artwork, PDFs)
- **Custom Data:** Pods (song, artist, album, license, playlist CPTs)

## Architecture

```
functions/
├── api/                  # REST endpoints (/wp-json/FML/v1/*)
│   ├── security.php      # API key auth, rate limiting, CORS
│   ├── songs.php         # Song CRUD
│   ├── stripe.php        # Checkout + webhooks
│   ├── analytics.php     # Event ingestion, survey, admin queries, CSV export
│   └── external.php      # External API v1.1
├── analytics/            # Analytics & Feedback system
│   ├── schema.php        # DB table creation (dbDelta)
│   ├── analytics.php     # Event recording, session, queries, cron cleanup
│   └── survey.php        # Survey modal, shortcode, trigger config
├── admin/                # WP Admin pages
│   ├── admin-menu.php    # Main Sync.Land menu
│   ├── nft-monitor.php   # NFT minting dashboard
│   └── analytics-dashboard.php  # Analytics: Overview, Events, Survey, Settings
├── gravityforms/         # CC-BY PDF generation
├── shortcodes/           # Upload forms, cart, artist listings
├── nmkr.php              # NFT minting logic
├── cart.php              # Shopping cart system
└── myaccount.php         # Account page customizations

assets/
├── brand/                # Brand assets (brand-board.png)
├── fonts/                # Custom web fonts (Anatol MN, Ethnocentric — see Brand System below)
├── css/                  # Stylesheets (main, cart, survey, admin-analytics, etc.)
└── js/                   # Scripts (analytics, survey, cart, search, hero-planet, etc.)

docs/
├── api-spec.yaml         # OpenAPI 3.0 spec
├── api-authentication.md # Auth guide
├── stripe-setup.md       # Stripe integration notes
└── pods-schema-nft-fields.md  # CPT field reference
```

## Brand System

Reference: `assets/brand/brand-board.png`

### Colors

| Token | Hex | Usage |
|-------|-----|-------|
| `--color-deep-blue` | `#212C9A` | Dark backgrounds, deep accents |
| `--color-bright-blue` | `#2F6ED3` | Links, Elementor Accent, Ethnocentric Light labels |
| `--color-ice-white` | `#E9F1FF` | Body text, Elementor Text color |
| `--color-hot-pink` | `#E237B2` | Elementor Primary, logo gradient start, CTAs |
| `--color-orange` | `#F0914D` | Elementor Secondary, gradient mid, Ethnocentric Regular labels |
| `--color-yellow` | `#F6D72E` | Gradient end, highlight accents |
| `--color-black` | `#000000` | Page background |

All tokens are defined in `assets/css/main.css` and mirrored in Elementor Global Colors (WP Admin > Elementor > Global Settings > Colors).

### Typography

| Font | Weight | Role |
|------|--------|------|
| **Anatol MN** | 400 | Display / H1–H2 (Elementor Primary Headline) |
| **Ethnocentric Regular** | 400 | Subtitles, labels, uppercase UI (Elementor Secondary Headline) |
| **Ethnocentric Light** | 300 | Light subtitles (`font-weight: 300` variant) |
| **Inter** | 400–700 | Body text — loaded from Google Fonts |

#### Installing Custom Fonts

Anatol MN and Ethnocentric are not on Google Fonts. To activate them:

1. Obtain `.woff2` and `.woff` files for each face.
2. Place in `assets/fonts/` with these exact names:
   - `anatol-mn.woff2` / `anatol-mn.woff`
   - `ethnocentric-rg.woff2` / `ethnocentric-rg.woff` (Regular 400)
   - `ethnocentric-lt.woff2` / `ethnocentric-lt.woff` (Light 300)
3. The `@font-face` declarations in `main.css` pick them up automatically.
4. Optionally also register them in Elementor: **WP Admin > Elementor > Custom Fonts** so they appear in the font picker.

### Gradients

| Variable | Value |
|----------|-------|
| `--gradient-brand` | Hot Pink → Orange (135°) |
| `--gradient-blue` | Deep Blue → Bright Blue (135°) |
| `--gradient-full` | Hot Pink → Orange → Yellow (135°) |

---

## REST API

**Namespace:** `/wp-json/FML/v1/`

Authentication via API Key (`X-API-Key` header), WordPress Nonce, or JWT Bearer token.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/songs` | Search songs (public) |
| POST | `/licenses/request` | Request CC-BY license |
| POST | `/stripe/create-checkout` | Create Stripe checkout session |
| POST | `/stripe/webhook` | Process Stripe payments |
| POST | `/licenses/{id}/mint-nft` | Mint NFT for a license |
| POST | `/analytics/events` | Batch event ingestion |
| POST | `/analytics/survey` | Survey submission |
| GET | `/analytics/stats` | Aggregated dashboard stats (admin) |
| GET | `/analytics/export` | CSV export (admin) |

## Analytics & Feedback

Self-hosted event tracking with GA4 bridge (`G-RHX5TVXMCT`).

**Tracked events:** page views, song plays/pauses, queue adds, license modal opens, add-to-cart, checkout starts, search queries, hero planet interactions, visualizer toggles.

**Survey system:** 5-step modal (NPS, use case, licensing ease, feature request, discovery source) with configurable triggers (visit count, time on site, post-licensing, manual shortcode). Dark theme, 90-day dismissal, GDPR-compliant.

**Admin dashboard** (WP Admin > Sync.Land > Analytics): stat cards, conversion funnel, daily events chart, top songs, NPS breakdown, CSV export, data retention settings.

## Development

Local development uses [Local by Flywheel](https://localwp.com/).

```bash
# Stripe webhook testing
stripe listen --forward-to localhost:10018/wp-json/FML/v1/stripe/webhook
```

## Deployment

Git tracks the child theme only. Push to DreamHost:

```bash
git push dreamhost main
```

WordPress core, plugins, and `wp-config.php` are managed separately.

## Configuration

Secrets are defined in `wp-config.php` (gitignored):

- `FML_AWS_KEY` / `FML_AWS_SECRET_KEY` — DreamObjects S3
- `FML_NMKR_API_KEY` / `FML_NMKR_PROJECT_UID` / `FML_NMKR_POLICY_ID` — NMKR
- `FML_STRIPE_SECRET_KEY` / `FML_STRIPE_PUBLISHABLE_KEY` / `FML_STRIPE_WEBHOOK_SECRET` — Stripe
- `JWT_AUTH_SECRET_KEY` — External API consumers
