import {
    attachVideoSource,
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
        const { hls, mp4, webm, autoplay, unavailableLabel } = this.options;
        attachVideoSource(
            this.video,
            { hls, mp4: mp4 || webm },
            {
                autoplay: !!autoplay,
                onUnplayable: () =>
                    markUnavailable(this.el, this.video, unavailableLabel),
            }
        );
    }

    destroy() {
        destroyVideoSource(this.video);
    }
}
