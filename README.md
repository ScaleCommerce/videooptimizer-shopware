# VideoOptimizer

Shopware 6 plugin to manage and play VideoOptimizer platform videos: upload and organize videos in libraries via an administration module, and embed them on storefront pages through a dedicated CMS element.

## Requirements

- Shopware 6.7
- A VideoOptimizer API token (format `vp_...`)

## Installation

```bash
bin/console plugin:install --activate VideoOptimizer
```

Build the frontend assets:

```bash
cd custom/plugins/VideoOptimizer/src/Resources/app/storefront
npm install
```

```bash
bin/console bundle:dump
bin/build-storefront.sh
bin/build-administration.sh
```

## Configuration

Set the API token under **Settings > Plugins > VideoOptimizer** in the administration. The token must follow the `vp_...` format issued by the VideoOptimizer platform.

## Usage

- Use the **VideoOptimizer** admin module to create libraries and upload, rename, or delete videos.
- Add the **VideoOptimizer video** CMS element to any Shopping Experiences layout and select an uploaded video to display it on the storefront.
