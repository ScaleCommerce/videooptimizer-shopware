# Changelog

All notable changes to `scalecommerce/videooptimizer-shopware` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [0.4.5] - 2026-07-20

### Fixed
- Native player: in the video content elements (media split, spotlight, video grid) the per-element **show controls**, **muted** and **loop** options are now applied to the native player as well — previously only the hosted embed player honoured them.
- Element layouts (media split columns, spotlight centering, video grid columns) are now styled via CSS classes instead of inline styles, so a theme can override them.
- Background hero: colour-picker values with an alpha channel (e.g. `#rrggbbaa`) are now accepted instead of being dropped, and the hero shows a neutral dark background instead of an empty black area when a background video cannot be loaded.
- Accessibility: the click-to-play poster button falls back to a descriptive "play video" label when no headline or caption is set.
- Administration: the video grid item list no longer re-requests videos that have no preview image.

## [0.4.4] - 2026-07-20

### Added
- New CMS content element and block **"VideoOptimizer Video grid"** for Shopping Experiences: a responsive grid of multiple click-to-play videos, each with an optional caption, under a grid headline and intro. Videos are added, reordered and removed in a per-element list editor (each row picks a video from the gallery). Click-to-play defaults to a lightbox and supports the same presentation modes (poster/facade, lightbox, embedded) and native/embed player options as the other video layouts. Renders nothing when no videos are added. This completes the set of video content layouts (media split, background hero, spotlight, video grid).

### Fixed
- Placeholder and empty-state icons in the administration (the video, media split, spotlight and background hero elements and the video gallery) rendered blank and logged a console error, because they referenced Meteor icon names that do not exist in the icon kit. Corrected to valid icon names.

## [0.4.3] - 2026-07-19

### Added
- New CMS content element and block **"VideoOptimizer Spotlight"** for Shopping Experiences: a centered, stacked layout with an eyebrow, headline, a click-to-play video and a caption below it. The video defaults to opening in a **lightbox** on click, and supports the same presentation modes (poster/facade, lightbox, embedded) and native/embed player options as the media split element. Renders nothing when no video is selected.

## [0.4.2] - 2026-07-19

### Added
- New CMS content element and block **"VideoOptimizer Background hero"** for Shopping Experiences: a full-bleed, autoplaying, muted, looping background video with an overlay scrim and overlaid content (eyebrow, headline, multi-line subline and a call-to-action button). Configurable overlay (gradient / dark / none), section height (full / large / medium), headline and text colours, and an "above the fold" priority flag that preloads the video for a hero at the top of the page. Renders nothing when no video is selected. The background video streams adaptively (HLS) with an MP4/poster fallback.

## [0.4.1] - 2026-07-19

### Added
- New CMS content element and block **"VideoOptimizer Media split"** for Shopping Experiences: a video paired with editorial text (eyebrow, headline, rich text and a call-to-action button) side by side. The video column can sit on the left or right, and the video can be presented as a click-to-play poster (facade), a poster that opens a full-screen lightbox, or embedded directly. Works with both the native adaptive player and the hosted embed player. This is the first of several planned content layouts built on a shared, reusable video "surface".

### Notes
- In the media split element, the per-element player options (show controls, muted, loop, and autoplay) are currently applied only to the **hosted embed player**. The **native** player shows controls and plays on click but does not yet honour those switches; this is a tracked follow-up. The default combination (native player, click-to-play poster) behaves as expected.

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
