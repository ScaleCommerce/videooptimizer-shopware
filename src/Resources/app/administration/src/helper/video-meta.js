export function parseResolution(resolution) {
    const match = /^(\d+)\s*x\s*(\d+)$/i.exec(String(resolution || '').trim());
    if (!match) {
        return null;
    }
    return { width: parseInt(match[1], 10), height: parseInt(match[2], 10) };
}

export function formatDuration(seconds) {
    const total = Math.round(Number(seconds) || 0);
    const minutes = Math.floor(total / 60);
    const rest = total % 60;
    return `${minutes}:${String(rest).padStart(2, '0')}`;
}

export function orientationKey(resolution) {
    const parsed = parseResolution(resolution);
    if (!parsed) {
        return null;
    }
    if (parsed.height > parsed.width) {
        return 'portrait';
    }
    if (parsed.width > parsed.height) {
        return 'landscape';
    }
    return 'square';
}

/**
 * Builds the "1920×1080 · 0:42 · Landscape" line shown on preview surfaces. Takes the
 * translate function from the calling component so this stays a pure helper.
 */
export function formatMetaLine(video, translate) {
    if (!video) {
        return '';
    }
    const parsed = parseResolution(video.resolution);
    const dimensions = parsed ? `${parsed.width}×${parsed.height}` : null;
    const duration = video.duration === null || video.duration === undefined
        ? null
        : formatDuration(video.duration);
    const key = orientationKey(video.resolution);
    const labels = {
        portrait: 'scalecommerce-vo.gallery.orientationPortrait',
        landscape: 'scalecommerce-vo.gallery.orientationLandscape',
        square: 'scalecommerce-vo.gallery.orientationSquare',
    };
    const orientation = key ? translate(labels[key]) : null;

    return [dimensions, duration, orientation].filter((part) => part !== null).join(' · ');
}
