# Changelog

All notable changes to `scalecommerce/videooptimizer-shopware` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.5.0] - 2026-09-04

### Security
- Sanitized the media-split element's rich text server-side (mirroring Core's `TextCmsElementResolver`) and in the admin editor preview, closing a stored-XSS hole where the editor-authored text was rendered with `|raw` on the storefront.
- Allowlisted the URL scheme of the media-split and background-hero call-to-action URLs (`http`/`https`/`mailto`/`tel`/relative only), closing a stored-XSS hole via `javascript:`/`data:`/`vbscript:` URLs entered in the CTA URL field.
- The configured API base URL and the new embed base URL are now required to be `https://`; a non-https value is rejected instead of silently sending the API token or storefront traffic in plaintext.
- Hardened the admin thumbnail image proxy: only allowlisted image content types (jpeg/png/webp/gif) are passed through, anything else becomes `application/octet-stream`, and responses carry `X-Content-Type-Options: nosniff`.
- Every admin controller endpoint that forwards a JSON request body upstream now allowlists its keys before forwarding, dropping unexpected fields instead of passing them through unvalidated.
- Path segments (video UUID, library ID, thumbnail index) are now URL-encoded before being interpolated into admin API service request URLs.
- Sandboxed the hosted-embed `<iframe>`s (direct surface, single-video element, blocks facade/lightbox) with `sandbox="allow-scripts allow-same-origin allow-popups allow-presentation"`.

### Added
- Library "reprocess": requeue all of a self-hosted/media-managed library's videos for re-encoding with its current encoding ladder, from a confirm-first button in the library editor.
- Import a video from a public `https://` URL directly in the video gallery, alongside file upload.
- New `embedBaseUrl` plugin configuration field (default `https://videooptimizer.eu`) so self-hosted VideoOptimizer instances can point the hosted embed player at their own host.
- Shopware 6.6 administration bundle (webpack) shipped alongside the existing 6.7 (Vite) bundle, plus a `.shopware-extension.yml` declaring the 6.6 build constraint.
- Storefront snippets (`de-DE`/`en-GB`) for the facade play button and lightbox close button labels, replacing hardcoded German text.
- Confirmation dialogs before deleting a video or a library.

### Changed
- `getEmbed()` responses are now cached per video for **1 hour**, and no longer block storefront rendering with a `sleep()` retry on HTTP 429 — a rate limit during rendering now fails fast instead of stalling the response.
- Embed-mode elements (and the single video/media-split/spotlight/video-grid elements) now verify the video still exists upstream and render nothing if it doesn't, instead of only checking in native player mode; the video grid drops just the unresolvable tile instead of rendering the whole block in an error state.
- Admin-facing API requests now default to a 30 second timeout when the caller didn't set one (the storefront `getEmbed()` call keeps its existing 3 second cap).
- Switched the storefront bundle to hls.js's light build and pinned it to `~1.6.16`, shrinking the compiled storefront JS by roughly a third.
- hls.js instances are now torn down (`destroy()`) on plugin destroy and on every lightbox dismiss path, instead of leaking across navigations and open/close cycles; only one lightbox may be open at a time, and it gets `role="dialog"`, focus management and a body scroll lock.
- The library editor's encoding ladder now only allows selecting a codec/resolution that is on the active library's allowlist (falling back to the org-wide `available` flag), shows an "ADD-ON" hint driven by the option's `access` field, and surfaces a hint to reprocess once the selection differs from the stored ladder.

### Fixed
- Upload/poster polling now stops on a `failed` status (with a notification) and on component unmount, and rejects instead of silently resolving when the attempt cap is hit, instead of proceeding as if the upload had finished.
- The upload file input is reset after a successful upload so the same file can be re-selected.
- `selectThumbnail()` and `completePosterUpload()` now return the intended 400 response for malformed JSON instead of an uncaught 500.

## [0.4.7] - 2026-07-27

### Changed
- The CMS element previews in the Shopping Experiences editor now **mirror their storefront layout** instead of showing one large poster per element: media split renders two reversible columns with its text column, background hero renders the poster with its overlay and centred copy (heights capped for the editor), spotlight renders a centred column, and video grid renders a real tile grid with one tile per video. Editors can tell the layouts apart and judge a page without opening the storefront.
- Video status and metadata (resolution, duration, orientation) moved from a row underneath the element into unobtrusive overlay badges on the poster — subdued while a video is `ready`, clearly highlighted while it is still processing or failed.
- The play affordance in the previews now reflects the configured **presentation mode** (poster/facade, lightbox, embedded directly) and **player mode** (native, embed) instead of a generic icon.
- Empty text fields render labelled placeholders (eyebrow, headline, subline, caption, intro, button, label), so a freshly placed element shows which fields it offers.

### Internal
- Extracted the video lookup and metadata formatting duplicated across four element previews into a shared mixin and a shared `scalecommerce-vo-preview-surface` component — the editor counterpart to the storefront surface macro.

## [0.4.6] - 2026-07-20

### Changed
- The video CMS blocks now show a **distinct schematic preview** in the block picker (single video, media split, background hero, spotlight, video grid) instead of the identical VideoOptimizer logo, so editors can tell the layouts apart at a glance.

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
