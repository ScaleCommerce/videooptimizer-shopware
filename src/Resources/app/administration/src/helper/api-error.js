/**
 * Classifies an error from the VideoOptimizer proxy for the configuration notice:
 * - tokenMissing / invalidBaseUrl / tokenRejected: plugin configuration (or token) to fix
 * - privilege: the admin user lacks the VideoOptimizer "viewer" permission
 * - unavailable: anything else (API down, plan limits, …), with the server's detail
 */
export function apiErrorKind(error) {
    const response = error?.response;
    const first = response?.data?.errors?.[0] ?? {};
    const code = first.code ?? '';

    if (code === 'SCALECOMMERCE_VO__API_TOKEN_MISSING') {
        return { kind: 'tokenMissing', detail: null };
    }
    if (code === 'SCALECOMMERCE_VO__INVALID_BASE_URL') {
        return { kind: 'invalidBaseUrl', detail: null };
    }
    if (code === 'SCALECOMMERCE_VO__API_TOKEN_REJECTED') {
        return { kind: 'tokenRejected', detail: null };
    }
    // Shopware answers 403 itself when the route's _acl privilege is missing.
    if (response?.status === 403) {
        return { kind: 'privilege', detail: null };
    }

    return {
        kind: 'unavailable',
        detail: first.detail ?? error?.message ?? null,
    };
}

/**
 * The readable message of a failed proxy call: the server's detail, the error message, or the
 * given fallback.
 */
export function apiErrorText(error, fallback = '') {
    return (
        error?.response?.data?.errors?.[0]?.detail ?? error?.message ?? fallback
    );
}

/**
 * The number of videos that still carry a subtitle language when the API refused to delete it
 * (409 subtitle_language_in_use); null for any other error.
 */
export function subtitleLanguageInUse(error) {
    const first = error?.response?.data?.errors?.[0];
    if (first?.code !== 'SCALECOMMERCE_VO__SUBTITLE_LANGUAGE_IN_USE') {
        return null;
    }

    return Number.isInteger(first.meta?.videos) ? first.meta.videos : 0;
}
