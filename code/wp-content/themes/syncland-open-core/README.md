# Sync.Land Open Core — Theme

Curated open-source modules from the Sync.Land marketplace theme. See the
top-level [code/README.md](../../../README.md) for the wider scope, license,
and what is intentionally **not** included.

## Install

1. Install and activate the [Hello Elementor](https://elementor.com/hello-theme/)
   parent theme.
2. Copy `syncland-open-core/` into `wp-content/themes/`.
3. Activate **Sync.Land Open Core** in **Appearance &rarr; Themes**.
4. On activation, the analytics + survey tables are created in your
   database (see `functions/analytics/schema.php`).

## Module map

| Module | Loaded by `functions.php` |
|---|---|
| `functions/seo/music-schema.php` | Schema.org JSON-LD graph (`MusicRecording`, `MusicAlbum`, `MusicGroup`, `AudioObject`, license). Hooks `the_seo_framework_schema_graph_data`. |
| `functions/seo/dynamic-meta.php` | Per-CPT title + description + `og:image` automation; hooks `the_seo_framework_title_from_generation`, `the_seo_framework_generated_description`, `pre_get_document_title`. |
| `functions/analytics/schema.php` | `dbDelta` table creation for `wp_*_fml_analytics_events` and `wp_*_fml_survey_responses`. |
| `functions/analytics/survey.php` | Front-end role-aware survey UI: banner, dismissable, inline shortcode `[fml_survey_inline]`. |
| `functions/api/analytics.php` | REST: `POST /FML/v1/analytics/survey`, `GET /FML/v1/analytics/survey-results`, CSV export. |
| `functions/api/security.php` | API key creation / verification, rate limiting per IP and per key, response envelopes (`fml_api_success` / `fml_api_error`). |
| `functions/api/songs.php` | Public-read song endpoints (`GET /FML/v1/songs`, `GET /FML/v1/songs/{id}`). |
| `functions/api/artists.php` | Public-read artist endpoints. |
| `functions/api/search.php` | Search endpoint with mood / genre / BPM filters. |

The full REST surface (including the proprietary write endpoints) is
documented in [`code/docs/api-spec.yaml`](../../../docs/api-spec.yaml).

## Dependencies (third-party plugins)

- **Pods** &mdash; defines the `song`, `artist`, `album`, `playlist` custom post
  types; the SEO and API modules read fields like `song.album` and
  `album.artist` via the Pods relationship API.
- **The SEO Framework (autodescription)** &mdash; the SEO module attaches
  filters to its generation pipeline; without it, the dynamic meta and
  schema fall back to whatever WordPress emits by default.

No paid plugins are required for the open-core modules.

## What you can do with this on its own

- Add Schema.org music markup to any Pods-driven catalog
- Reuse the dynamic-meta pattern for per-CPT SEO automation against The SEO
  Framework
- Drop the role-aware survey into an existing marketplace to gather
  audience-segmented feedback
- Stand up a public-read music API with API-key authentication and per-IP
  rate limiting

## What's missing (by design)

This theme does not boot a working marketplace on its own. The cart,
checkout, license generation, NFT minting, and admin tooling all live
behind the proprietary curtain. The live experience at
[https://sync.land](https://sync.land) is the canonical reference.

## License

MIT License &mdash; see [code/LICENSE](../../../LICENSE).
