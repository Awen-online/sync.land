<p align="center">
  <img src="./assets/syncland-header.png" alt="Sync.Land Header">
</p>

# Sync.Land| Metaverse & Video Game Music Licensing
Visit our domain: https://sync.land

## Documentation

Here you can find links to all neccessary documentation for each of the milestones throughout the progress of this project.
### [Milestone 1 - Initialization](https://github.com/Awen-online/sync.land/tree/main/docs/M1_Initialization)
>**Documents**
>
>[Design Document](M1_Initialization/SyncLand_Design_Report.pdf) - information about initial design, including infrastructure setup
>
>[Project Status Report](M1_Initialization/SyncLand_Project_Status.pdf) - Current project status
>
>[Project Timeline](M1_Initialization/SyncLand_Project_Timeline.pdf) - project roadmap, timeline, and responsibilities
>
>[Notion Kanban Board](https://awen-online.notion.site/2196b85886f941cf9f1d951b59ea230c?v=e71479497a5a4ea1810e4bf2c1018451) - board of all tasks, stories, and epics for this project


### [Milestone 2 - Development](https://github.com/Awen-online/sync.land/tree/main/docs/M2_Development)
>[Project Status Report](M2_Development/SyncLand_Project_Status_Report_M2.pdf) - Current project status
>
>[Project Timeline](M2_Development/SyncLand_Project_Timeline_M2.pdf) - project roadmap, timeline, and responsibilities
>
>[Pilot Marketing Document](M2_Development/SyncLand_Pilot_Marketing_Report.pdf) - information about pilot users selection & marketing setup
>
>[Test Case: Sync NFT License Metadata](M2_Development/Test_Case_-_Simple_NFT_Transaction_Sync_Metadata.mp4) - a simple Cardano transaction of an NFT on Cardano's Pre-production Network that includes metadata for a sync license
>
>[Test Case: User Registration & Song Upload](M2_Development/Test_Case_-_User_Registration_and_Song_Upload.mp4) - a user can register and upload a song to the marketplace
### [Milestone 3 - Marketplace Launch & API Development](https://github.com/Awen-online/sync.land/tree/main/docs/M3_Marketplace)
>[Project Status Report](M3_Marketplace/Sync.Land%20Project%20Status%20Report_M3.pdf) - Current project status
>
>[Project Timeline](M3_Marketplace/Sync.Land%20Project%20Timeline_M3.pdf) - updated project roadmap and timeline
>
>[User Feedback Report](M3_Marketplace/SyncLand_User_Feedback_Report_M3.pdf) - launch survey results, NPS, role-split feedback (licensee vs artist), admin dashboard screenshot
>
>[Marketplace & On-Chain Evidence](M3_Marketplace/SyncLand_Marketplace_and_OnChain_Evidence_M3.pdf) - visual tour of the live marketplace (homepage, browse, song page, album page) plus the verbatim CIP-25 metadata, policy ID, asset name, and Cardanoscan link for the test case mint
>
>[Test Case: NFT License Purchase](M3_Marketplace/Test_Case_-_NFT_License_Purchase.mp4) - end-to-end demo of a public user purchasing a sync license NFT on Cardano's Pre-production Network (mirror: [YouTube unlisted](https://www.youtube.com/watch?v=yt-R_F1wTGM))
>
>[API Documentation](M3_Marketplace/SyncLand_API_Documentation_M3.pdf) - REST API reference for the Sync.Land platform (songs, artists, albums, licensing, NFT, payments, playlists) generated from the [OpenAPI 3.0 spec](../code/wp-content/themes/hello-elementor-child-sync-land/docs/api-spec.yaml)
>
>[Live Marketplace](https://sync.land) - the public website
>
>**Launch announcements** (Awen socials, 2026-06-22):
>- [awen.online news post](https://awen.online/news/sync-land-launches-awens-peer-to-peer-music-licensing-platform-goes-live/) - Sync.Land launches: Awen's peer-to-peer music licensing platform goes live
>- [X / Twitter](https://x.com/awen_online/status/2069076274098606574) - launch thread by @awen_online
>- [LinkedIn](https://www.linkedin.com/posts/awenonline_the-wait-is-over-syncland-is-live-activity-7474841972634165248-u4_y) - "The wait is over — Sync.Land is live"
>- [Instagram](https://www.instagram.com/p/DZ5Iym_ks9i/) - launch announcement post
>
>**Platform code (open-core release, MIT)**:
>- [`code/`](../code/) - top-level docs, LICENSE, and the curated open-core subset (~2,860 lines of PHP across 9 modules; the proprietary marketplace logic stays behind the hosted site at https://sync.land)
>- [`code/wp-content/themes/syncland-open-core/`](../code/wp-content/themes/syncland-open-core/) - the open theme module map
>  - [`functions/seo/music-schema.php`](../code/wp-content/themes/syncland-open-core/functions/seo/music-schema.php) - Schema.org JSON-LD (MusicRecording / MusicAlbum / MusicGroup)
>  - [`functions/seo/dynamic-meta.php`](../code/wp-content/themes/syncland-open-core/functions/seo/dynamic-meta.php) - per-CPT title / description / og:image automation
>  - [`functions/analytics/`](../code/wp-content/themes/syncland-open-core/functions/analytics/) - role-aware feedback survey schema + UI
>  - [`functions/api/`](../code/wp-content/themes/syncland-open-core/functions/api/) - REST endpoints (analytics, security, songs, artists, search)
>- [`code/docs/api-spec.yaml`](../code/docs/api-spec.yaml) - full OpenAPI 3.0 spec (also rendered as the API Documentation PDF above)
>- [`LICENSE`](../LICENSE) / [`code/LICENSE`](../code/LICENSE) - MIT License (as declared in the Catalyst Fund 11 application)

### Milestone 4 - Marketplace Updates & API Launch
### Milestone 5 - Finalization