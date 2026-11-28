import { destroyVideoSource } from '../helper/attach-video-source';

// The product video in the image gallery stops when the visitor moves to another slide: the slide
// is clipped by the gallery, so it counts as hidden once less than half of it is visible. A native
// video is paused, a hosted player (iframe) is reloaded without autoplay, which stops it.
export default class ScalecommerceVoProductVideo
    extends window.PluginBaseClass
{
    init() {
        // A click on the video slide belongs to the player: the gallery would open its zoom modal.
        // The zoom modal listens on every <img> of the gallery, the poster included, so a click on
        // the poster is caught before it gets there and handed to the facade button instead.
        this._onCapture = (event) => {
            if (event.target?.tagName === 'IMG') {
                event.stopPropagation();
                event.target.closest('button')?.click();
            }
        };
        this._onClick = (event) => event.stopPropagation();
        this.el.addEventListener('click', this._onCapture, true);
        this.el.addEventListener('click', this._onClick);

        if (typeof window.IntersectionObserver !== 'function') {
            return;
        }
        this.observer = new window.IntersectionObserver(
            (entries) =>
                entries.forEach((entry) => {
                    if (
                        !entry.isIntersecting ||
                        entry.intersectionRatio < 0.5
                    ) {
                        this._stop();
                    }
                }),
            { threshold: [0, 0.5] }
        );
        this.observer.observe(this.el);
    }

    _stop() {
        const video = this.el.querySelector('video');
        if (video && !video.paused) {
            video.pause();
        }
        const frame = this.el.querySelector('iframe');
        if (frame) {
            try {
                const url = new URL(
                    frame.getAttribute('src'),
                    window.location.href
                );
                if (url.searchParams.get('autoplay') === '1') {
                    url.searchParams.set('autoplay', '0');
                    frame.setAttribute('src', url.href);
                }
            } catch {
                // Nothing to restore.
            }
        }
    }

    destroy() {
        this.el.removeEventListener('click', this._onCapture, true);
        this.el.removeEventListener('click', this._onClick);
        this.observer?.disconnect();
        this.el
            .querySelectorAll('video')
            .forEach((video) => destroyVideoSource(video));
    }
}
