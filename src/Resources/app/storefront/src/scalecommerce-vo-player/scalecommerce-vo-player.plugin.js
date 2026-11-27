import {
    attachVideoSource,
    autoplayWhileVisible,
    destroyVideoSource,
    markUnavailable,
    resolvePlayback,
    applyPlayerTheme,
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
        theme: null,
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
        const playback = resolvePlayback(this.options);
        this._autoplay = playback.autoplay;
        this.video.controls = playback.controls;
        // Autoplay is driven by visibility (see _attachSource), not by the attribute, so players
        // below the fold don't start loading and playing at page load.
        this.video.autoplay = false;
        this.video.preload = playback.preload;
        this.video.loop = playback.loop;
        // Browsers block autoplay with sound, so a video set to autoplay must start muted
        // (mirrors the hosted VideoOptimizer embed). An explicit "muted" option still applies.
        this.video.muted = playback.muted;
        applyPlayerTheme(this.video, this.el, this.options);
        if (this.options.poster) {
            this.video.poster = this.options.poster;
        }
    }

    _attachSource() {
        const { hls, mp4, webm, unavailableLabel } = this.options;
        const autoplay = this._autoplay;
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
        // Other plugins (chapters) wait for the source before they seek.
        this.el.__scvoReady = attached;
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
        if (this.video) {
            destroyVideoSource(this.video);
        }
    }
}
