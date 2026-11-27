/**
 * Subtitle upload: the API takes .vtt and .srt files up to 2 MB.
 */
export const SUBTITLE_MAX_BYTES = 2 * 1024 * 1024;

export function isSubtitleFileName(name) {
    return /\.(vtt|srt)$/i.test(name ?? '');
}

/**
 * The reason a file cannot be uploaded as subtitle ('type' or 'size'), or null.
 */
export function subtitleFileProblem(file) {
    if (!isSubtitleFileName(file?.name)) {
        return 'type';
    }
    return file.size > SUBTITLE_MAX_BYTES ? 'size' : null;
}

/**
 * Select options for the organization's subtitle languages. A new upload for a language the video
 * already has replaces that subtitle; `replaces` lets the form say so.
 */
export function languageOptions(languages, subtitles) {
    const present = new Set((subtitles ?? []).map((s) => s.language));
    return (languages ?? []).map((language) => ({
        value: language.code,
        label: `${language.label} (${language.code})`,
        replaces: present.has(language.code),
    }));
}
