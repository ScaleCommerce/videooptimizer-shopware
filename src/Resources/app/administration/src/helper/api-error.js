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
