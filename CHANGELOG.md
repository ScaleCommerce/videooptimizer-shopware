# Changelog

All notable changes to this package. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning: [SemVer](https://semver.org/).

## [Unreleased]

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
