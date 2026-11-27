// hls.js is loaded on demand, once per page, when the first HLS source is attached. A single
// dynamic import keeps it in one shared chunk instead of a copy per storefront plugin.
let hlsPromise = null;
function loadHls() {
    hlsPromise ??= import('hls.js/dist/hls.light.mjs')
        .then((module) => module.default)
        .catch(() => null);
    return hlsPromise;
}

/**
 * Attaches a VideoOptimizer source to a <video>: hls.js (MSE), native HLS (Safari) or MP4.
 * A fatal hls.js error or a failing native source falls back to MP4 once; when nothing can play,
 * `onUnplayable` is called so the caller can show the poster and a short notice instead of a
 * black or empty player.
 *
 * The hls.js instance is kept on the element (`video.__scvoHls`) so callers can destroy it
 * via destroyVideoSource() without tracking it themselves.
 */
export async function attachVideoSource(
    video,
    { hls = null, mp4 = null },
    { autoplay = false, deferLoad = false, onUnplayable = () => {} } = {}
) {
    const play = () => {
        if (autoplay) {
            video.play().catch(() => {});
        }
    };
    const failOnError = () =>
        video.addEventListener('error', () => onUnplayable(), { once: true });
    const useMp4 = () => {
        if (!mp4) {
            onUnplayable();
            return;
        }
        failOnError();
        video.src = mp4;
        play();
    };

    // Set by destroyVideoSource(); the element may be torn down while hls.js is still loading.
    video.__scvoDestroyed = false;
    const Hls = hls ? await loadHls() : null;
    if (video.__scvoDestroyed) {
        return;
    }

    if (hls && Hls && Hls.isSupported()) {
        // hls.js ignores the video's preload hint and buffers right away; with deferLoad only the
        // playlists are fetched until playback starts. capLevelToPlayerSize keeps small tiles from
        // pulling the 4K rendition.
        const player = new Hls({
            autoStartLoad: !deferLoad,
            capLevelToPlayerSize: true,
        });
        // Reachable for destroyVideoSource() before anything can emit an error.
        video.__scvoHls = player;
        if (deferLoad) {
            video.addEventListener(
                'play',
                () => {
                    if (video.__scvoHls === player) {
                        player.startLoad();
                    }
                },
                { once: true }
            );
        }
        player.on(Hls.Events.ERROR, (event, data) => {
            if (!data.fatal) {
                return;
            }
            destroyVideoSource(video);
            useMp4();
        });
        player.on(Hls.Events.MANIFEST_PARSED, play);
        player.loadSource(hls);
        player.attachMedia(video);
        return;
    }

    if (hls && video.canPlayType('application/vnd.apple.mpegurl')) {
        video.addEventListener('error', () => useMp4(), { once: true });
        video.src = hls;
        play();
        return;
    }

    useMp4();
}

/**
 * Calls onChange(true|false) when the element enters or leaves the viewport (25 % visible).
 * Returns a function that stops observing. Without IntersectionObserver the element counts as
 * visible.
 */
export function observeVisibility(element, onChange) {
    if (typeof window.IntersectionObserver !== 'function') {
        onChange(true);
        return () => {};
    }
    const observer = new window.IntersectionObserver(
        (entries) => entries.forEach((entry) => onChange(entry.isIntersecting)),
        { threshold: 0.25 }
    );
    observer.observe(element);
    return () => observer.disconnect();
}

/**
 * Plays a muted, autoplaying video only while it is visible and pauses it when it scrolls out
 * of view. Once the visitor pauses it themselves, it is not resumed automatically.
 * Returns a function that stops observing.
 */
export function autoplayWhileVisible(video, observed = video) {
    let pausedForViewport = false;
    let pausedByUser = false;
    const onPause = () => {
        if (pausedForViewport) {
            pausedForViewport = false;
            return;
        }
        if (!video.ended) {
            pausedByUser = true;
        }
    };
    const onPlay = () => {
        pausedByUser = false;
    };
    video.addEventListener('pause', onPause);
    video.addEventListener('play', onPlay);
    const stop = observeVisibility(observed, (visible) => {
        // A finished non-looping video is not restarted when it scrolls back into view.
        if (visible && video.paused && !pausedByUser && !video.ended) {
            video.play().catch(() => {});
        } else if (!visible && !video.paused) {
            pausedForViewport = true;
            video.pause();
        }
    });
    return () => {
        stop();
        video.removeEventListener('pause', onPause);
        video.removeEventListener('play', onPlay);
    };
}

export function destroyVideoSource(video) {
    video.__scvoDestroyed = true;
    if (video.__scvoHls) {
        video.__scvoHls.destroy();
        video.__scvoHls = null;
    }
}

/**
 * Marks a player wrapper as unavailable: the poster stays visible, controls are removed and a
 * short notice (CSS ::after from data-vo-unavailable) is shown.
 */
export function markUnavailable(wrapper, video, label) {
    video.controls = false;
    video.removeAttribute('src');
    wrapper.classList.add('scalecommerce-vo-video--unavailable');
    wrapper.setAttribute('data-vo-unavailable', label || 'Video unavailable');
}
