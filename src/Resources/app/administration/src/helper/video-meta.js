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
