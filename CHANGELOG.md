# Changelog

All notable changes to `scalecommerce/videooptimizer-shopware` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [0.2.0] - 2026-07-18

First tagged release. Runs on the current VideoOptimizer API and supports Shopware 6.6 and 6.7.

### Added
- Administration module **VideoOptimizer** to browse libraries and upload, rename and delete videos via the VideoOptimizer API (uploads are transcoded on the platform; the module polls until a video is `ready`).
- Reusable **video gallery** in the administration: a poster-thumbnail grid with status badges and an inline upload tile, used both to manage a library and to pick a video for a CMS element.
- CMS content element and block **"VideoOptimizer video"** for Shopping Experiences, with the thumbnail gallery picker. The editor preview shows the selected video's **poster and title** (not a raw ID).
- Per-element **player choice**: the native adaptive HLS player (hls.js, native HLS fallback on Safari) or the hosted **VideoOptimizer embed** (iframe). Player options (controls, autoplay, muted, loop) apply to both modes; in embed mode they are passed to the hosted player as URL parameters.
- Plugin configuration for the VideoOptimizer API token; the token is kept server-side and never exposed to storefront visitors.
- ACL privileges (`scalecommerce_vo`) gating the admin module and API routes.

### Changed
- Video upload uses the VideoOptimizer **presigned multipart** flow (initiate → direct-to-storage part uploads → complete), replacing the removed direct `POST /videos` upload.
- The API client resolves **cursor-paginated** list endpoints server-side and retries once on HTTP 429 (`Retry-After`, capped).
- Added an org-wide `GET /videos` proxy endpoint alongside the per-library listing.
- Video field mapping aligned to the current API (snake_case; embed `sources` list).

### Notes
- Requires Shopware **6.6 or 6.7** and a VideoOptimizer account (https://videooptimizer.eu/, API: https://api.videooptimizer.eu/). Shopware 6.6 support rests on the dependency constraints and code review; a 6.6 runtime smoke test is a tracked follow-up.
- The per-element player choice persists for CMS elements created with this version. A video element placed with a pre-release build (before the player option existed) keeps the native player; re-add the element to switch it to the embed player.
