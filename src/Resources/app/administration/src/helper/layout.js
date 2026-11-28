// Formats of the "format" option of the video elements: config value => CSS aspect-ratio.
export const FORMAT_ASPECTS = {
    '16-9': '16 / 9',
    '9-16': '9 / 16',
    '1-1': '1 / 1',
    '4-5': '4 / 5',
    '21-9': '21 / 9',
};

/**
 * Editor preview ratio of a format; the 16:9 default stands for the original video ratio, which the
 * preview does not know.
 */
export function formatAspect(format) {
    return FORMAT_ASPECTS[format] ?? '16 / 9';
}
