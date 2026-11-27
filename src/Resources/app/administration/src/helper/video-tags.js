/**
 * Tags and metadata of a video (issue #37), mirroring the API's limits: at most 20 tags of 1-50
 * characters without commas; at most 10 metadata keys of 1-40 characters (a-z 0-9 _ . -, starting
 * with a letter or digit) with values of at most 64 characters.
 */
export const MAX_TAGS = 20;
export const MAX_TAG_LENGTH = 50;
export const MAX_METADATA_KEYS = 10;
export const MAX_METADATA_VALUE_LENGTH = 64;
export const METADATA_KEY_PATTERN = /^[a-z0-9][a-z0-9_.-]{0,39}$/;

/**
 * The trimmed tag name, or null when it is not a valid tag.
 */
export function normalizeTag(value) {
    const name = String(value ?? '').trim();
    if (name === '' || name.length > MAX_TAG_LENGTH || name.includes(',')) {
        return null;
    }
    return name;
}

/**
 * Adds a tag; tags are matched case-insensitively (the API keeps the first spelling), so an
 * existing variant is not added twice. Invalid names and a full list leave the list unchanged.
 */
export function addTag(tags, value) {
    const name = normalizeTag(value);
    if (
        name === null ||
        tags.length >= MAX_TAGS ||
        tags.some((tag) => tag.toLowerCase() === name.toLowerCase())
    ) {
        return tags;
    }
    return [...tags, name];
}

export function removeTag(tags, name) {
    return tags.filter((tag) => tag !== name);
}

/**
 * Whether two tag lists name the same tags (order and case ignored).
 */
export function sameTags(a, b) {
    const key = (list) =>
        [...list]
            .map((tag) => tag.toLowerCase())
            .sort()
            .join('\n');
    return key(a) === key(b);
}

/**
 * Editable rows of a metadata object.
 */
export function metadataRows(metadata) {
    return Object.entries(metadata ?? {}).map(([key, value]) => ({
        key,
        value: String(value),
    }));
}

/**
 * The first problem of the rows as snippet suffix ('keyInvalid', 'keyDuplicate', 'valueTooLong',
 * 'tooMany'), or null. Rows that are completely empty are ignored.
 */
export function metadataProblem(rows) {
    const used = rows.filter(
        (row) => row.key.trim() !== '' || row.value.trim() !== ''
    );
    // The limit counts the keys that are set; a row with an empty value is a removal.
    if (
        used.filter((row) => row.value.trim() !== '').length > MAX_METADATA_KEYS
    ) {
        return 'tooMany';
    }
    const seen = new Set();
    for (const row of used) {
        const key = row.key.trim();
        if (!METADATA_KEY_PATTERN.test(key)) {
            return 'keyInvalid';
        }
        if (seen.has(key)) {
            return 'keyDuplicate';
        }
        seen.add(key);
        if (row.value.trim().length > MAX_METADATA_VALUE_LENGTH) {
            return 'valueTooLong';
        }
    }
    return null;
}

/**
 * The `metadata` of a PATCH that turns `original` into the edited rows: the API merges, so
 * removed keys (and keys with an empty value) are sent as null. null when nothing changed.
 */
export function metadataPatch(original, rows) {
    const wanted = {};
    rows.forEach((row) => {
        const key = row.key.trim();
        const value = row.value.trim();
        if (key !== '' && value !== '') {
            wanted[key] = value;
        }
    });
    const patch = {};
    Object.keys(original ?? {}).forEach((key) => {
        if (!(key in wanted)) {
            patch[key] = null;
        }
    });
    Object.entries(wanted).forEach(([key, value]) => {
        if (original?.[key] !== value) {
            patch[key] = value;
        }
    });
    return Object.keys(patch).length > 0 ? patch : null;
}
