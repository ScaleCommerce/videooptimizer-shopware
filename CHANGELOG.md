# Changelog

All notable changes to `scalecommerce/videooptimizer-shopware` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [0.4.0] - 2026-07-18

### Added
- Video detail overlay (opened by clicking a video in the management gallery): choose the video's preview image from the 10 auto-generated frames or upload a custom image (from a file or the Shopware media library), and remove a custom one. Shows a poster preview and the video's dimensions, duration and orientation.

## [0.3.0] - 2026-07-18

### Added
- Library editor in the admin module: create and edit libraries (name, description) and their encoding ladder (codecs + resolutions) via pickers built from the organization's available encoding options. Add-on/unavailable codecs are shown but disabled; delivery-only libraries (`media_managed`) show a read-only ladder. Each library shows its video count, storage usage and creation date.

## [0.2.2] - 2026-07-18

### Fixed
- The VideoOptimizer admin module now appears in the sidebar under "Content"; it was previously only reachable via its direct URL because its navigation entry had no parent (which also caused a console error in the admin menu).

## [0.2.1] - 2026-07-18

### Added
- Video metadata (dimensions, duration, orientation) shown on the gallery cards, in the CMS element preview, and in a detail panel when a video is selected.

### Changed
- The upload tile now has a clear "Upload a new video" heading, making it obvious the tile uploads a video (the optional title is the video title).

### Fixed
- The native storefront player now starts muted when autoplay is enabled, so autoplay actually plays (browsers block autoplay with sound). Playback is kicked off once the HLS manifest is ready.

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
