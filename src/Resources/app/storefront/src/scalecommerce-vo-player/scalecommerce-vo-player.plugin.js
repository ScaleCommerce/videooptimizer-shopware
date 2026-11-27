import {
    attachVideoSource,
    autoplayWhileVisible,
    destroyVideoSource,
    markUnavailable,
} from '../helper/attach-video-source';

export default class ScalecommerceVoPlayer extends window.PluginBaseClass {
    static options = {
        hls: null,
        mp4: null,
        webm: null,
        poster: null,
        controls: true,
        autoplay: false,
        muted: false,
        loop: false,
        unavailableLabel: null,
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
        // Autoplay is driven by visibility (see _attachSource), not by the attribute, so players
        // below the fold don't start loading and playing at page load.
        this.video.autoplay = false;
        this.video.preload = 'metadata';
        this.video.loop = !!this.options.loop;
        // Browsers block autoplay with sound, so a video set to autoplay must start muted
        // (mirrors the hosted VideoOptimizer embed). An explicit "muted" option still applies.
        this.video.muted = !!this.options.muted || !!this.options.autoplay;
        if (this.options.poster) {
            this.video.poster = this.options.poster;
        }
    }

    _attachSource() {
        const { hls, mp4, webm, autoplay, unavailableLabel } = this.options;
        // hls.js loads on demand, so autoplay starts only once the source is attached.
        const attached = attachVideoSource(
            this.video,
            { hls, mp4: mp4 || webm },
            {
                deferLoad: true,
                onUnplayable: () =>
                    markUnavailable(this.el, this.video, unavailableLabel),
            }
        );
        if (autoplay) {
            attached.then(() => {
                if (!this._destroyed) {
                    this._stopAutoplay = autoplayWhileVisible(
                        this.video,
                        this.el
                    );
                }
            });
        }
    }

    destroy() {
        this._destroyed = true;
        if (this._stopAutoplay) {
            this._stopAutoplay();
        }
        destroyVideoSource(this.video);
    }
}
