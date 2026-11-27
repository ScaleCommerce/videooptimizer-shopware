// Video platform page links (YouTube) are refused by the API for URL import.
const PLATFORM_DOMAINS = ['youtube.com', 'youtu.be', 'youtube-nocookie.com'];

export function isVideoPlatformUrl(url) {
    try {
        const host = new URL(url).hostname.toLowerCase();
        return PLATFORM_DOMAINS.some(
            (domain) => host === domain || host.endsWith(`.${domain}`)
        );
    } catch {
        return false;
    }
}
