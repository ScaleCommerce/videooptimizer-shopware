/**
 * Helpers for the savings card (issue #38).
 */

const UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

/**
 * Binary-scaled size, e.g. 1536 -> "1.5 KB".
 */
export function formatBytes(bytes, locale = undefined) {
    const value = bytes === null || bytes === undefined ? NaN : Number(bytes);
    if (!Number.isFinite(value) || value < 0) {
        return '-';
    }
    let unit = 0;
    let scaled = value;
    while (scaled >= 1024 && unit < UNITS.length - 1) {
        scaled /= 1024;
        unit += 1;
    }
    const digits = unit === 0 || scaled >= 100 ? 0 : 1;
    return `${scaled.toLocaleString(locale, {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    })} ${UNITS[unit]}`;
}

export function formatPercent(percent, locale = undefined) {
    const value =
        percent === null || percent === undefined ? NaN : Number(percent);
    if (!Number.isFinite(value)) {
        return '-';
    }
    return `${value.toLocaleString(locale, { maximumFractionDigits: 1 })} %`;
}

const day = (date) => date.toISOString().slice(0, 10);

/**
 * The UTC day range of a preset ('7d', '30d', '90d', 'month'): the API wants whole UTC days, at
 * most 366, `to` not in the future. Unknown presets (e.g. 'all') give no range.
 */
export function rangeForPreset(preset, now = new Date()) {
    const today = new Date(
        Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate())
    );
    const days = { '7d': 7, '30d': 30, '90d': 90 }[preset];
    if (days) {
        const from = new Date(today);
        from.setUTCDate(from.getUTCDate() - (days - 1));
        return { from: day(from), to: day(today) };
    }
    if (preset === 'month') {
        return {
            from: day(
                new Date(
                    Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1)
                )
            ),
            to: day(today),
        };
    }
    return null;
}
