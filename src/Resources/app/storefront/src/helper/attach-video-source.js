import Hls from 'hls.js/dist/hls.light.mjs';

/**
 * Attaches a VideoOptimizer source to a <video>: hls.js (MSE), native HLS (Safari) or MP4.
 * A fatal hls.js error or a failing native source falls back to MP4 once; when nothing can play,
 * `onUnplayable` is called so the caller can show the poster and a short notice instead of a
 * black or empty player.
 *
 * The hls.js instance is kept on the element (`video.__scvoHls`) so callers can destroy it
 * via destroyVideoSource() without tracking it themselves.
 */
export function attachVideoSource(
    video,
    { hls = null, mp4 = null },
    { autoplay = false, onUnplayable = () => {} } = {}
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

    if (hls && Hls.isSupported()) {
        const player = new Hls();
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
        video.__scvoHls = player;
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

export function destroyVideoSource(video) {
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
