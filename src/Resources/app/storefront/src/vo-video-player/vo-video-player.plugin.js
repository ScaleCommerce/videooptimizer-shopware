import Plugin from 'src/plugin-system/plugin.class';
import Hls from 'hls.js';

export default class VoVideoPlayer extends Plugin {
    static options = {};

    init() {
        this.video = this.el.querySelector('.vo-video__player');
        this.config = this._readConfig();
        if (!this.video || !this.config) {
            return;
        }

        this._applyOptions();
        this._attachSource();
    }

    _readConfig() {
        try {
            return JSON.parse(this.el.getAttribute('data-vo-video-player-options'));
        } catch (error) {
            console.error('[VoVideoPlayer] invalid options', error);
            return null;
        }
    }

    _applyOptions() {
        this.video.controls = !!this.config.controls;
        this.video.autoplay = !!this.config.autoplay;
        this.video.loop = !!this.config.loop;
        this.video.muted = !!this.config.muted;
        if (this.config.poster) {
            this.video.poster = this.config.poster;
        }
    }

    _attachSource() {
        const { hls, mp4, webm } = this.config;

        if (hls && Hls.isSupported()) {
            const player = new Hls();
            player.loadSource(hls);
            player.attachMedia(this.video);
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
