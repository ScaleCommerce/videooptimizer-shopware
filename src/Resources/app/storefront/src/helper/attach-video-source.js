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
 * Playback state is handed over, not guessed: `autoplay` or `shouldPlay()` (read when the source is
 * ready) start playback, and a video that was playing when hls.js or native HLS failed keeps
 * playing on the MP4 fallback.
 *
 * The hls.js instance is kept on the element (`video.__scvoHls`) so callers can destroy it
 * via destroyVideoSource() without tracking it themselves.
 */
export async function attachVideoSource(
    video,
    { hls = null, mp4 = null },
    {
        autoplay = false,
        deferLoad = false,
        shouldPlay = () => false,
        onUnplayable = () => {},
    } = {}
) {
    const play = () => {
        if (autoplay || shouldPlay()) {
            video.play().catch(() => {});
        }
    };
    const failOnError = () =>
        video.addEventListener('error', () => onUnplayable(), { once: true });
    // `wasPlaying` is read before the failed source is torn down: switching the source pauses the
    // element, and nothing else would start it again.
    // `resumeAt` is the position of the failed source; the MP4 continues there instead of restarting.
    const useMp4 = (wasPlaying = false, resumeAt = 0) => {
        if (!mp4) {
            onUnplayable();
            return;
        }
        failOnError();
        video.src = mp4;
        if (resumeAt > 0) {
            video.addEventListener(
                'loadedmetadata',
                () => {
                    video.currentTime = resumeAt;
                },
                { once: true }
            );
        }
        if (wasPlaying) {
            video.play().catch(() => {});
        } else {
            play();
        }
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
                const wasPlaying = !video.paused;
                const resumeAt = video.currentTime || 0;
                destroyVideoSource(video);
                useMp4(wasPlaying, resumeAt);
            }
            return;
        }
    }

    if (hls && video.canPlayType('application/vnd.apple.mpegurl')) {
        video.addEventListener(
            'error',
            () => useMp4(!video.paused, video.currentTime || 0),
            {
                once: true,
            }
        );
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
        const wasPlaying = !video.paused;
        const resumeAt = video.currentTime || 0;
        destroyVideoSource(video);
        useMp4(wasPlaying, resumeAt);
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

/**
 * Playback settings of a native player: the element's own options combined with the `theme` of the
 * video (GET /embed). The theme can only add behaviour the editor did not set (autoplay, muted start,
 * loop) or switch the controls off; the element's explicit choices are never overridden.
 * Keys the browser's native controls cannot honour (control bar colour, volume, time display,
 * quality menu) are ignored.
 */
export function resolvePlayback(options = {}) {
    const theme =
        options.theme && !Array.isArray(options.theme) ? options.theme : {};
    const autoplay = !!options.autoplay || theme.autoplay === true;
    return {
        controls: options.controls !== false && theme.controls !== false,
        autoplay,
        // Browsers block autoplay with sound, so an autoplaying video starts muted.
        muted: !!options.muted || theme.mutedStart === true || autoplay,
        loop: !!options.loop || theme.loopButton === true,
        preload: ['auto', 'metadata', 'none'].includes(theme.preload)
            ? theme.preload
            : 'metadata',
    };
}

/**
 * Applies the theme to a <video> as far as native controls allow: hides the fullscreen button and
 * the playback-rate menu (Chromium `controlsList`), disables Picture-in-Picture, and makes a
 * non-interactive (`interactive: false`) player ignore the pointer.
 */
export function applyPlayerTheme(video, wrapper, options = {}) {
    const theme =
        options.theme && !Array.isArray(options.theme) ? options.theme : {};
    const list = [];
    if (theme.fullscreenButton === false) {
        list.push('nofullscreen');
    }
    if (theme.playbackSpeed === false) {
        list.push('noplaybackrate');
    }
    if (list.length) {
        video.setAttribute('controlslist', list.join(' '));
    }
    if (theme.pictureInPicture === false) {
        video.disablePictureInPicture = true;
        video.setAttribute('disablepictureinpicture', '');
    }
    if (theme.interactive === false && wrapper) {
        wrapper.classList.add('scalecommerce-vo-video--static');
    }
}
