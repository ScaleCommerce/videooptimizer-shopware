// hls.js is loaded on demand, once per page, when the first HLS source is attached. A single
// dynamic import keeps it in one shared chunk instead of a copy per storefront plugin.
let hlsPromise = null;
function loadHls() {
    hlsPromise ??= import('hls.js/dist/hls.light.mjs')
        .then((module) => module.default)
        .catch(() => {
            // A transient chunk error must not disable hls.js for the rest of the page.
            hlsPromise = null;
            return null;
        });
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

    // hls.js needs Media Source Extensions. Without them (e.g. older iOS) it is not loaded at all,
    // and native HLS / MP4 are attached synchronously - still within the click that started
    // playback, which iOS requires for playback with sound.
    const mse = !!(window.ManagedMediaSource || window.MediaSource);
    if (hls && mse) {
        const Hls = await loadHls();
        if (video.__scvoDestroyed) {
            return;
        }
        if (Hls && Hls.isSupported()) {
            try {
                attachHlsJs(Hls, video, hls, deferLoad, play, useMp4);
            } catch {
                destroyVideoSource(video);
                useMp4();
            }
            return;
        }
    }

    if (hls && video.canPlayType('application/vnd.apple.mpegurl')) {
        video.addEventListener('error', () => useMp4(), { once: true });
        video.src = hls;
        play();
        return;
    }

    useMp4();
}

function attachHlsJs(Hls, video, hls, deferLoad, play, useMp4) {
    // hls.js ignores the video's preload hint and buffers right away; with deferLoad only the
    // playlists are fetched until playback starts. If the visitor already pressed play while
    // hls.js was loading, the "play" event is gone - start loading right away then.
    // capLevelToPlayerSize keeps small tiles from pulling the 4K rendition.
    const playRequested = !video.paused;
    const deferred = deferLoad && !playRequested;
    const player = new Hls({
        autoStartLoad: !deferred,
        capLevelToPlayerSize: true,
    });
    // Reachable for destroyVideoSource() before anything can emit an error.
    video.__scvoHls = player;
    if (deferred) {
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
    player.on(Hls.Events.MANIFEST_PARSED, () => {
        play();
        // Attaching the media interrupts a play() requested while hls.js was loading; resume it.
        if (playRequested && video.paused) {
            video.play().catch(() => {});
        }
    });
    player.loadSource(hls);
    player.attachMedia(video);
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
