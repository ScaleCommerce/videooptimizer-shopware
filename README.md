# ScaleVideoOptimizer

Deliver fast, adaptive, CDN-hosted videos on your Shopware storefront with **[VideoOptimizer](https://videooptimizer.eu/)** — manage libraries and videos from the administration, then embed them anywhere via five dedicated CMS elements/blocks for Shopping Experiences. A privacy-friendly, high-performance alternative to YouTube, Vimeo and self-hosting.

- 🌐 Platform: **https://videooptimizer.eu/**
- 📚 API documentation: **https://api.videooptimizer.eu/**
- 🏢 Maintained by **ScaleCommerce GmbH** · Composer package `scalecommerce/videooptimizer-shopware`

---

## Why VideoOptimizer instead of YouTube, Vimeo or self-hosting?

Video is the heaviest asset on most shop pages. Where it is hosted decides your page speed, your bandwidth bill, your conversion rate and your data-protection exposure. VideoOptimizer is built specifically for **commerce video delivered over a global CDN**.

### vs. self-hosting (MP4 in your shop's media manager)
- **Traffic stays off your server.** Every play of a self-hosted video streams gigabytes straight from your web/origin server, competing with real shop requests and running up egress cost. VideoOptimizer serves every byte from **edge CDN nodes**, so your Shopware instance stays fast even during campaigns and traffic spikes.
- **Adaptive bitrate (HLS) instead of one big file.** VideoOptimizer automatically transcodes each upload into multiple resolutions (up to 4K) and streams **HLS**: the player picks the optimal quality for each visitor's device and connection — instant start, no buffering, no wasted mobile data. A single self-hosted MP4 forces every visitor to download the same large file.
- **No manual encoding.** Upload once; VideoOptimizer handles transcoding, posters and thumbnails. No ffmpeg pipelines, no storage juggling.
- **Better Core Web Vitals.** Offloading heavy video improves LCP and overall storefront performance — which directly helps SEO and conversion.

### vs. YouTube / Vimeo embeds
- **Your brand, not theirs.** No foreign logos, no "up next" suggestions, no ads and no recommended competitor videos pulling customers out of your shop.
- **Privacy & GDPR-friendly.** YouTube/Vimeo embeds set third-party tracking cookies and load external scripts, forcing intrusive consent banners. VideoOptimizer streams your video from an **EU-based CDN** without ad-tracking — cleaner consent, cleaner compliance.
- **Full control & reliability.** Your content isn't subject to a third-party platform's algorithm, regional blocks, monetisation rules or sudden takedowns. The player and its behaviour are yours to configure.

### In short
| | Self-hosted MP4 | YouTube / Vimeo | **VideoOptimizer** |
|---|---|---|---|
| Server traffic / egress cost | High (from your origin) | Low | **Low (edge CDN)** |
| Adaptive streaming (HLS) | ✗ | ✓ | **✓ (up to 4K)** |
| Storefront performance | ✗ (origin load) | ~ | **✓ (offloaded, optimized)** |
| Own branding, no ads | ✓ | ✗ | **✓** |
| Privacy / GDPR, no tracking cookies | ✓ | ✗ | **✓ (EU CDN)** |
| Automatic transcoding & thumbnails | ✗ | ✓ | **✓** |

## Features

### Administration

An admin module under **Content → VideoOptimizer** manages your VideoOptimizer account without leaving Shopware:

- **Libraries** — create and edit libraries (name, description) and their encoding ladder (codecs and resolutions), with add-on/unavailable options shown but disabled per the organization's plan; requeue all of a library's videos for re-encoding with the current ladder ("reprocess").
- **Videos** — upload via presigned multipart upload (transferred directly to storage, not through Shopware), or import a video from a public `https://` URL; rename, delete. Deleting a video or a library asks for confirmation first.
- **Video detail overlay** — pick the poster from 10 auto-generated frames, or upload a custom poster (from disk or the Shopware media library); shows dimensions, duration and orientation.

### Five CMS elements/blocks

Five content elements/blocks for Shopping Experiences, all built on the same reusable video "surface":

- **Video** — a single video.
- **Media split** — a video paired with editorial text (eyebrow, headline, rich text, call-to-action button) side by side, video column left or right.
- **Background hero** — a full-bleed, autoplaying, muted, looping background video with an overlay scrim and overlaid copy (eyebrow, headline, subline, CTA button).
- **Spotlight** — a centered, stacked layout: eyebrow, headline, click-to-play video, caption.
- **Video grid** — a responsive grid of multiple click-to-play videos with per-item captions.

Each element (except the decorative hero background) offers a **presentation mode** (poster/facade, lightbox, or embedded directly) and a **player mode**: the native adaptive HLS player (hls.js, native HLS fallback on Safari) or the hosted VideoOptimizer embed (iframe, sandboxed), each with its own controls/autoplay/muted/loop options. A video that no longer exists upstream (e.g. deleted after the element was configured) renders nothing — no placeholder, no broken player — and the video grid drops just that tile rather than failing the whole block.

### Under the hood

- The API token stays server-side; all admin and storefront traffic to VideoOptimizer is proxied through Shopware, so the token is never exposed to the browser.
- Embed data for a given video is cached for **1 hour**, keeping storefront rendering fast and reducing API calls.

## Requirements

- Shopware **6.6** or **6.7**
- PHP as required by your Shopware version
- A VideoOptimizer account and API token (format `vp_...`) — create one at **https://videooptimizer.eu/** (see **https://api.videooptimizer.eu/** for the API)
- The token needs the `library:view` permission if you want the org-wide video listing (browsing videos across all libraries); write operations (upload, import from URL, reprocess) require a self-hosted ("media managed") library

## Installation

```bash
composer require scalecommerce/videooptimizer-shopware
bin/console plugin:refresh
bin/console plugin:install --activate ScaleVideoOptimizer
bin/console cache:clear
```

Compiled administration (Shopware 6.6 webpack build and 6.7 Vite build) and storefront assets are shipped with the plugin, so no build step is required for a normal install. If your project recompiles the storefront theme (e.g. after changing SCSS elsewhere), run the usual:

```bash
bin/console theme:compile
# or, depending on your project setup:
bin/build-storefront.sh
```

Alternatively, place the plugin manually under `custom/plugins/ScaleVideoOptimizer` before running `plugin:refresh`.

## Configuration

Under **Settings → System → Plugins → VideoOptimizer**:

| Field | Description |
|---|---|
| `apiToken` | Your VideoOptimizer API token (`vp_...`). |
| `apiBaseUrl` | API base URL, must be `https://`. Default: `https://api.videooptimizer.eu/api/v1`. |
| `embedBaseUrl` | Public host serving the hosted embed player, must be `https://`. Default: `https://videooptimizer.eu`. |

## Permissions (ACL)

The module is gated by the `scalecommerce_vo` privilege group under **Content**, with the usual four roles:

| Role | Grants |
|---|---|
| Viewer | Read libraries, videos, encodings, thumbnails |
| Editor | Update libraries and videos, select/upload posters and thumbnails, reprocess a library |
| Creator | Create libraries, upload/import videos (depends on viewer + editor) |
| Deleter | Delete videos and libraries |

Poster, thumbnail selection and library reprocessing require **update**; deleting videos or libraries requires **delete**.

## Usage

1. Open **Content → VideoOptimizer** to create a library, upload or import videos, and manage posters.
2. In a Shopping Experiences layout, add one of the five VideoOptimizer blocks (category *Video*) and pick a video (or videos, for the grid) via the gallery picker.
3. Configure the presentation mode and player options per element, then assign the layout to a category or page — video is delivered over the VideoOptimizer CDN on your storefront.

## Upgrading

```bash
composer update scalecommerce/videooptimizer-shopware
bin/console plugin:update ScaleVideoOptimizer
bin/console cache:clear
```

Then hard-reload the administration in your browser (`Cmd+Shift+R` / `Ctrl+Shift+R`) so it doesn't keep serving the previous admin bundle from cache.

## Third-party

The storefront bundle includes [hls.js](https://github.com/video-dev/hls.js) (Apache License 2.0). See [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md) for details.

## About the plugin family

ScaleCommerce maintains VideoOptimizer integrations for multiple shop and CMS systems under a consistent naming scheme: `scalecommerce/videooptimizer-<system>` (this package: `scalecommerce/videooptimizer-shopware`).

## License

MIT © ScaleCommerce GmbH — see [LICENSE](LICENSE).
