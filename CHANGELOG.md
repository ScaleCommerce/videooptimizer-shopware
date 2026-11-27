# Changelog

All notable changes to this package. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning: [SemVer](https://semver.org/).

## [Unreleased]

## [2.0.1] - 2026-11-27

### Fixed
- `composer.json` carried the version 1.2.2 in the 2.0.0 tag, so Packagist did not publish 2.0.0; 2.0.1 is the same code with the correct version.

## [2.0.0] - 2026-11-27

### Upgrade
- To use webhooks, create a webhook in VideoOptimizer under Integrations, Webhooks with the URL shown in the VideoOptimizer module (`/api/scalecommerce-vo/webhook` on the shop's admin/API host) and set its signing secret in the plugin setting `webhookSecret`; until then the route answers 503 ([#39](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/39)).
- `VideoOptimizerClient::listVideos()`, `listVideosPage()` and `listAllVideos()` take optional `$tags` and `$metadata` arguments; subclasses overriding them must accept and pass them on ([#37](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/37)).

### Added
- Subtitles per video (list, upload .vtt/.srt, download, delete) in the video detail and management of the organization's subtitle languages (list, add, delete; 409 shows how many videos still use a language) in the module ([#36](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/36)).
- Tags and metadata (key/value, e.g. CMS IDs) editable in the video detail; the video lists filter by tag and by metadata value ([#37](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/37)).
- Savings card in the module: file-size reduction and estimated bandwidth saved, for the organization or a library and a period ([#38](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/38)).
- Webhook receiver `POST /api/scalecommerce-vo/webhook` (API route without login, HMAC-SHA256 signature, replay window, duplicate detection, 1 MiB limit) that drops the cached embed and the cached pages of a video on `video.ready` and `video.failed`; the plugin setting `webhookSecret` and a module card with the URL to enter in VideoOptimizer ([#39](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/39)).
- The element configuration and the editor preview flag a selected video that no longer exists ([#35](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/35)).

### Fixed
- Subtitle upload sent the file with a JSON Content-Type and never reached the API; a failed upload now shows its error next to the form and keeps the file ([#36](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/36)).
- The video detail retries the playback lookup (3 times, 5 s apart) for a video that just became ready before it says the video cannot be played.
- A new library is created with the resolutions and codecs the form shows (included options preselected).

### Changed
- GitHub Actions run PHPUnit and PHPStan (level 5) on Shopware 6.6 and 6.7, Vitest and `shopware-cli extension validate` on every push and pull request ([#40](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/40)).
- DOM tests (Vitest/jsdom) for the storefront player, the facade and lightbox blocks and the hero lifecycle ([#34](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/34)).

## [1.2.2] - 2026-11-27

### Upgrade
- Leaving the module or switching the CMS element cancels a running upload in the video gallery; before, it ran on in the background and created the video ([#27](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/27)).
- `EmbedPageCacheSubscriber` takes the `VideoOptimizerClient` as optional fourth constructor argument; subclasses overriding the constructor must pass it on ([#25](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/25)).
- API calls no longer follow redirects (answered as 502), and the plugin's HTTP client refuses connections to private addresses (also behind host names and redirects). Not applied behind an outbound proxy (`HTTPS_PROXY`/`ALL_PROXY` or a configured `proxy`, except hosts in `NO_PROXY`; the proxy has to filter). A self-hosted VideoOptimizer on a private network needs the new plugin setting `blockPrivateNetworks` switched off; a failing DNS lookup on the shop server now also blocks the request ([#33](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/33)).

### Added
- Plugin setting `blockPrivateNetworks` (default on) to allow connections to private networks ([#33](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/33)).

### Changed
- Tests no longer depend on the install method; input-check boundaries covered ([#26](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/26)).
- README notes that `system_config:read` exposes the API token to MCP clients ([#31](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/31)).
- README documents the lightbox keyboard limitation while native controls or the embed iframe hold the focus ([#32](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/32)).
- A redirect answer from the API is reported as 502 with a hint to check the API base URL ([#33](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/33)).

### Fixed
- Unread embed lookups are cancelled at the end of the request and a failed lookup no longer keeps the client alive; fixes the test suite exiting 255 ([#25](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/25)).
- Library delete, reprocess and update invalidate cached pages and cached embeds by a per-library cache tag, also when the video listing is truncated or fails; pages are tagged with the library of their elements and grid items ([#29](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/29)).
- Background hero and autoplaying players keep playing after the MP4 fallback and continue at the same position; a hero play click before the source is attached is no longer lost, and is used only once ([#28](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/28)).
- Closing the video gallery aborts a running upload, so no video is created afterwards ([#27](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/27)).
- Video gallery keeps its loaded pages after rename, delete and poster changes (the changed entry is updated in place); media-library posters with a generic `Content-Type` use the media entity's type or file extension ([#30](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/30)).

### Security
- Base URL guard checks the IDNA-normalised host (full-width `localhost`), resolved IPs and redirect targets via `NoPrivateNetworkHttpClient` ([#33](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/33)).

## [1.2.1] - 2026-11-27

### Upgrade
- Admin proxy forwards `encoding_quality` on library create/update and rejects invalid values with 400 ([#22](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/22)).
- Admin reprocess route reads the JSON body (malformed → 400) and passes `force` ([#22](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/22)).
- Subclasses overriding `VideoOptimizerClient::reprocessLibrary()` must add `bool $force = false` ([#22](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/22)).

### Added
- Library encoding quality (performance/quality) and forced reprocess ([#22](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/22)).

### Changed
- Custom poster upload no longer sends a redundant poster selection ([#23](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/23)).

### Fixed
- URL import explains rejected YouTube links ([#24](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/24)).

## [1.2.0] - 2026-11-27

### Breaking
- Theme JS extending a plugin class via `PluginManager.getPlugin(...).get('class')` must use `PluginManager.override()` ([#17](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/17)).

### Changed
- Storefront loads player code and hls.js only on pages with video ([#17](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/17)).
- hls.js 1.7 ([#19](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/19)).
- README documents current behaviour and the data sent to VideoOptimizer ([#18](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/18)).

### Deprecated
- `EmbedAvailability` → `EmbedRenderState`, `DegradedEmbedCacheSubscriber` → `EmbedPageCacheSubscriber` (aliases kept) ([#21](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/21)).

### Removed
- `getAllVideos()` of the admin API service; `buildVideoSurface()` lost its unused `$presentation` argument ([#21](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/21)).

## [1.1.0] - 2026-11-27

### Breaking
- Proxy/firewall filtering by User-Agent: allow `VideoOptimizer-Shopware/*`.
- Theme overrides of the background hero template: adopt the new markup for the pause button and reduced motion ([#6](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/6)).
- CMS editors need the VideoOptimizer Viewer role to pick videos ([#11](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/11)).

### Added
- `User-Agent` with plugin, Shopware and PHP version and shop URL on every API request.
- Admin gallery pages 60 videos at a time ([#15](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/15)).
- Upload progress, per-part retry and cancel; processing tracked in the background ([#10](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/10)).
- Admin explains missing/rejected token and missing permissions; actions follow the user's role ([#11](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/11)).
- Background hero: pause button, honours `prefers-reduced-motion` and data saver ([#6](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/6)).

### Changed
- Native players load segments only on playback and autoplay only while visible ([#8](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/8)).
- Embed lookups run in parallel; API outages fail fast for 60 s ([#4](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/4)).
- Pages are cache-tagged per video and invalidated on admin changes ([#3](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/3)).
- Server-side list collection is time-boxed and logs truncation ([#15](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/15)).
- Shopware 6.8 preparation: PHP service/route config, inline styles moved to CSS classes ([#16](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/16)).

### Fixed
- Storefront accessibility: iframe titles, focus after facade, lightbox dialog/focus trap ([#7](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/7)).
- Admin accessibility and missing translations ([#13](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/13)).

## [1.0.2] - 2026-11-27

### Breaking
- API/embed base URLs with userinfo, query, fragment, localhost or non-public IPs are rejected ([#5](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/5)).
- Admin proxy: non-UUID ids → 404; upstream 401/403 and transport errors → 502 with error code ([#1](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/1), [#2](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/2), [#5](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/5)).

### Fixed
- Unreachable API or timeouts no longer end in an unhandled 500 ([#1](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/1)).
- A rejected API token no longer triggers an admin session refresh ([#2](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/2)).
- Pages rendered with a failed embed are cached for at most 60 s ([#3](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/3)).
- Videos without playable sources are hidden; fatal HLS errors fall back to MP4 or a notice ([#9](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/9)).
- Admin: saving without a library, stale gallery after switching, poster polling, media-library posters ([#12](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/12)).
- Source maps no longer shipped; tests run on PHPUnit 12+ ([#14](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/14)).

### Security
- Path traversal via `..` in admin proxy route parameters ([#5](https://github.com/ScaleCommerce/videooptimizer-shopware/issues/5)).

## [1.0.1] - 2026-11-26

### Fixed
- Admin video detail dialog plays the video instead of only showing the poster.

## [1.0.0] - 2026-11-26

### Changed
- Default `embedBaseUrl` is `https://player.videooptimizer.eu` (stored values are kept).

### Fixed
- Embed iframes allow picture-in-picture and fullscreen.
- Renaming a video uses a modal instead of the browser prompt.
- `shopware-cli extension validate` passes without errors.

## [0.5.2] - 2026-09-04

### Fixed
- Background hero: wrapped headline lines no longer overlap.

## [0.5.1] - 2026-09-04

### Changed
- Library page uses Shopware's native layout (smart bar, cards, context menus).

## [0.5.0] - 2026-09-04

First public release.

### Added
- Library reprocess, URL import, `embedBaseUrl` setting, Shopware 6.6 admin bundle, delete confirmations.

### Changed
- Embed lookups cached 1 h (failures 60 s), no blocking retry on 429.
- hls.js light build; lightbox and player teardown fixed.

### Fixed
- Upload/poster polling stops on failure; storefront cache invalidated after admin changes.

### Security
- Stored XSS in media split rich text and call-to-action URLs.
- API and embed base URLs must be `https://`; request bodies and content types allowlisted; embed iframes sandboxed.

## [0.4.0] – [0.4.7] - 2026-07-18 – 2026-07-27

Internal pre-releases: library editor, video detail with poster selection, CMS elements Media split, Background hero, Spotlight and Video grid, layout-accurate editor previews.

## [0.2.0] – [0.3.0] - 2026-07-18

Internal pre-releases: admin module (libraries, upload, rename, delete), video gallery, CMS element "Video" with native or embed player, ACL.
