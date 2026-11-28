// The video of a product lives in two custom fields (no entity, no migration); the storefront reads
// them from the translated custom fields, where variants inherit the main product's.
export const FIELD = 'scalecommerce_vo_product_video';
export const FIELD_LIBRARY = 'scalecommerce_vo_product_video_library';

/** The chosen video and library of a product (empty values when none). */
export function productVideo(product) {
    const fields = product?.customFields ?? {};
    return {
        uuid:
            typeof fields[FIELD] === 'string' && fields[FIELD]
                ? fields[FIELD]
                : null,
        libraryId:
            typeof fields[FIELD_LIBRARY] === 'string' && fields[FIELD_LIBRARY]
                ? fields[FIELD_LIBRARY]
                : null,
    };
}

/**
 * New custom fields with the video and library set. Cleared values are `null`, not missing keys: the
 * save has to overwrite the stored ones (a variant then inherits the main product's again). Other
 * custom fields stay untouched.
 */
export function withProductVideo(
    customFields,
    { uuid = null, libraryId = null }
) {
    return {
        ...(customFields ?? {}),
        [FIELD]: uuid || null,
        [FIELD_LIBRARY]: libraryId || null,
    };
}
