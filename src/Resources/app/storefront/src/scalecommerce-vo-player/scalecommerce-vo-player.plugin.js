import Plugin from 'src/plugin-system/plugin.class';
import Hls from 'hls.js';

export default class ScalecommerceVoPlayer extends Plugin {
    static options = {
        hls: null,
        mp4: null,
        webm: null,
        poster: null,
        controls: true,
        autoplay: false,
        muted: false,
        loop: false,
    };

    init() {
        this.video = this.el.querySelector('.scalecommerce-vo-video__player');
        if (!this.video) {
            return;
        }

        this._applyOptions();
        this._attachSource();
    }

    _applyOptions() {
        this.video.controls = !!this.options.controls;
        this.video.autoplay = !!this.options.autoplay;
        this.video.loop = !!this.options.loop;
        // Browsers block autoplay with sound, so a video set to autoplay must start muted
        // (mirrors the hosted VideoOptimizer embed). An explicit "muted" option still applies.
        this.video.muted = !!this.options.muted || !!this.options.autoplay;
        if (this.options.poster) {
            this.video.poster = this.options.poster;
        }
    }

    _attachSource() {
        const { hls, mp4, webm } = this.options;

        if (hls && Hls.isSupported()) {
            const player = new Hls();
            player.loadSource(hls);
            player.attachMedia(this.video);
            // With hls.js the media is fed via MSE; the autoplay attribute alone is unreliable,
            // so kick off playback once the manifest is ready (already muted when autoplay is on).
            if (this.options.autoplay) {
                player.on(Hls.Events.MANIFEST_PARSED, () => this.video.play().catch(() => {}));
            }
            return;
        }

        if (hls && this.video.canPlayType('application/vnd.apple.mpegurl')) {
            this.video.src = hls;
            return;
        }

        const fallback = mp4 || webm;
        if (fallback) {
            this.video.src = fallback;
        }
    }
}
