import Plugin from 'src/plugin-system/plugin.class';
import Hls from 'hls.js';

// Attaches an adaptive HLS source to the decorative background <video> of a
// background-hero element. The video already carries muted/autoplay/loop/
// playsinline; this only wires up the HLS stream (MP4-only heroes get a plain
// `src` in Twig and never match this plugin's selector).
export default class ScalecommerceVoHero extends Plugin {
    init() {
        const hlsUrl = this.el.getAttribute('data-scalecommerce-vo-hero-hls');
        if (!hlsUrl) {
            return;
        }
        if (Hls.isSupported()) {
            const player = new Hls();
            player.loadSource(hlsUrl);
            player.attachMedia(this.el);
            player.on(Hls.Events.MANIFEST_PARSED, () => this.el.play().catch(() => {}));
            return;
        }
        if (this.el.canPlayType('application/vnd.apple.mpegurl')) {
            this.el.src = hlsUrl;
            this.el.play().catch(() => {});
        }
    }
}
