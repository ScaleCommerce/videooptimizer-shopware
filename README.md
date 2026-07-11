# ScaleVideoOptimizer

Deliver fast, adaptive, CDN-hosted videos on your Shopware storefront with **[VideoOptimizer](https://videooptimizer.eu/)** — upload and organize videos in libraries from the administration, then embed them anywhere via a dedicated CMS element. A privacy-friendly, high-performance alternative to YouTube, Vimeo and self-hosting.

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

## Plugin features

- Administration module to browse VideoOptimizer libraries and **upload, rename and delete** videos — uploads are transferred to VideoOptimizer via API and processed automatically.
- A **"VideoOptimizer video" CMS element/block** for Shopping Experiences with a video picker (and optional in-place upload).
- Storefront playback via an **adaptive HLS player** (hls.js, with native HLS fallback on Safari); aspect ratio is derived automatically from the source video.
- Per-element player options: controls, autoplay, muted, loop.
- If no video is selected (or a selected video can't be loaded), the block renders nothing on the storefront — no placeholder and no message.
- The API token is stored server-side; the storefront only uses VideoOptimizer's public embed endpoint, so your token is never exposed to visitors.

## Requirements

- Shopware 6.7
- A VideoOptimizer account and API token (format `vp_...`) — create one at **https://videooptimizer.eu/** (see **https://api.videooptimizer.eu/** for the API).

## Installation

```bash
bin/console plugin:install --activate ScaleVideoOptimizer
```

Build the frontend assets:

```bash
cd custom/plugins/ScaleVideoOptimizer/src/Resources/app/storefront
npm install
```

```bash
bin/console bundle:dump
bin/build-storefront.sh
bin/build-administration.sh
```

## Configuration

Set the API token under **Settings > Plugins > VideoOptimizer** in the administration. The token must follow the `vp_...` format issued by the VideoOptimizer platform. See **https://api.videooptimizer.eu/** for how to create and manage tokens.

## Usage

- Open the **VideoOptimizer** admin module to create libraries and upload, rename or delete videos.
- Add the **VideoOptimizer video** block to any Shopping Experiences layout (category *Video*), pick an uploaded video and configure the player options.
- Assign the layout to a category or page — the video is delivered over the VideoOptimizer CDN on your storefront.

## About the plugin family

ScaleCommerce maintains VideoOptimizer integrations for multiple shop and CMS systems under a consistent naming scheme: `scalecommerce/videooptimizer-<system>` (this package: `scalecommerce/videooptimizer-shopware`).

## License

MIT © ScaleCommerce GmbH
