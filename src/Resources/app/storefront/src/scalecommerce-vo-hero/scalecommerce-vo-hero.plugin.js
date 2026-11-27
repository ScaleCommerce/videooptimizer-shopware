import {
    attachVideoSource,
    destroyVideoSource,
} from '../helper/attach-video-source';

// Attaches an adaptive HLS source to the decorative background <video> of a
// background-hero element, with the MP4 rendition as fallback on a fatal HLS
// error. The video already carries muted/autoplay/loop/playsinline; MP4-only
// heroes get a plain `src` in Twig and never match this plugin's selector.
// When nothing can play, the poster stays visible.
export default class ScalecommerceVoHero extends window.PluginBaseClass {
    init() {
        const hls = this.el.getAttribute('data-scalecommerce-vo-hero-hls');
        if (!hls) {
            return;
        }
        attachVideoSource(
            this.el,
            {
                hls,
                mp4: this.el.getAttribute('data-scalecommerce-vo-hero-mp4'),
            },
            {
                autoplay: true,
                onUnplayable: () => this.el.removeAttribute('src'),
            }
        );
    }

    destroy() {
        destroyVideoSource(this.el);
    }
}
