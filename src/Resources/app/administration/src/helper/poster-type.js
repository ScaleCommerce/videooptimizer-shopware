// Content types the poster upload accepts (mirrors the proxy's allowlist in VideoOptimizerAdminController).
export const POSTER_CONTENT_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

// What a media host or CDN may answer for any file without knowing its type.
const GENERIC_TYPES = [
    '',
    'application/octet-stream',
    'binary/octet-stream',
    'application/binary',
];

const EXTENSION_TYPES = {
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    png: 'image/png',
    webp: 'image/webp',
};

function normalizeType(type) {
    const normalized = String(type ?? '')
        .split(';')[0]
        .trim()
        .toLowerCase();
    return normalized === 'image/jpg' ? 'image/jpeg' : normalized;
}

function extensionOf(value) {
    const path = String(value ?? '').split(/[?#]/)[0];
    const match = /\.([A-Za-z0-9]+)$/.exec(path);
    return match ? match[1].toLowerCase() : '';
}

/**
 * The content type of a poster fetched from the media library. The type of the fetched blob comes
 * from the media host's response; a generic one ("application/octet-stream") is replaced by the
 * media entity's MIME type or its file extension. A real, non-generic type is kept as it is, so
 * unsupported images are still rejected by the caller.
 *
 * @param {string} blobType `blob.type` of the fetched file
 * @param {{mimeType?: string, fileExtension?: string, fileName?: string, url?: string}} [media]
 */
export function resolvePosterContentType(blobType, media = {}) {
    const type = normalizeType(blobType);
    if (!GENERIC_TYPES.includes(type)) {
        return type;
    }

    const fromMime = normalizeType(media?.mimeType);
    if (POSTER_CONTENT_TYPES.includes(fromMime)) {
        return fromMime;
    }

    const extensions = [
        String(media?.fileExtension ?? '').toLowerCase(),
        extensionOf(media?.fileName),
        extensionOf(media?.url),
    ];
    for (const extension of extensions) {
        if (EXTENSION_TYPES[extension]) {
            return EXTENSION_TYPES[extension];
        }
    }

    return type;
}
