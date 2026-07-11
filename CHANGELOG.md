# Changelog

All notable changes to `scalecommerce/videooptimizer-shopware` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [0.1.0] - Unreleased

Initial release.

### Added
- Administration module **VideoOptimizer** to browse libraries and upload, rename and delete videos via the VideoOptimizer API (uploads are transcoded on the platform; the module polls until a video is `ready`).
- CMS content element and block **"VideoOptimizer video"** for Shopping Experiences, with a library/video picker and optional in-place upload.
- Adaptive HLS storefront player (hls.js, with native HLS fallback on Safari); aspect ratio is derived automatically from the source video.
- Per-element player options: controls, autoplay, muted, loop.
- Plugin configuration for the VideoOptimizer API token; the token is kept server-side and never exposed to storefront visitors (playback uses the public embed endpoint only).
- ACL privileges (`scalecommerce_vo`) gating the admin module and API routes.

### Notes
- Requires Shopware 6.7 and a VideoOptimizer account (https://videooptimizer.eu/, API: https://api.videooptimizer.eu/).
