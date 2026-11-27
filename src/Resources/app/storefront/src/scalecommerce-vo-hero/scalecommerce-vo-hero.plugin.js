import {
    attachVideoSource,
    autoplayWhileVisible,
    destroyVideoSource,
} from '../helper/attach-video-source';

// Background video of the background-hero element: HLS with the MP4 rendition as fallback.
// Playback starts only when the hero is visible and is paused when it scrolls away. With
// prefers-reduced-motion or the browser's data saver, nothing plays and the poster stays; the
// toggle button lets visitors pause or start the video themselves (WCAG 2.2.2).
// "Priority" heroes buffer right away, others load once they start playing.
export default class ScalecommerceVoHero extends window.PluginBaseClass {
    init() {
        const video = this.el;
        const hls = video.getAttribute('data-scalecommerce-vo-hero-hls');
        const mp4 = video.getAttribute('data-scalecommerce-vo-hero-mp4');
        if (!hls && !mp4) {
            return;
        }
        this.toggle = video
            .closest('.scalecommerce-vo-hero')
            ?.querySelector('.scalecommerce-vo-hero__toggle');

        const motionReduced = this._motionReduced();
        attachVideoSource(
            video,
            { hls, mp4 },
            {
                // Nothing plays with reduced motion / data saver, so don't buffer even for priority.
                deferLoad:
                    motionReduced ||
                    !video.hasAttribute('data-scalecommerce-vo-hero-priority'),
                onUnplayable: () => {
                    video.removeAttribute('src');
                    this.toggle?.setAttribute('hidden', '');
                },
            }
        );

        if (this.toggle) {
            this._onToggle = () =>
                video.paused ? video.play().catch(() => {}) : video.pause();
            this._syncToggle = () => this._updateToggle(video.paused);
            this.toggle.addEventListener('click', this._onToggle);
            video.addEventListener('play', this._syncToggle);
            video.addEventListener('pause', this._syncToggle);
            this._updateToggle(true);
            this.toggle.removeAttribute('hidden');
        }

        if (!motionReduced) {
            this._stopAutoplay = autoplayWhileVisible(
                video,
                video.closest('.scalecommerce-vo-hero') || video
            );
        }
    }

    _motionReduced() {
        const reduce = window.matchMedia?.(
            '(prefers-reduced-motion: reduce)'
        ).matches;
        const saveData = window.navigator.connection?.saveData === true;
        return !!reduce || saveData;
    }

    _updateToggle(paused) {
        const label = this.toggle.getAttribute(
            paused ? 'data-label-play' : 'data-label-pause'
        );
        this.toggle.setAttribute('aria-label', label);
        this.toggle.setAttribute('title', label);
        this.toggle.classList.toggle('is-paused', paused);
    }

    destroy() {
        if (this._stopAutoplay) {
            this._stopAutoplay();
        }
        if (this.toggle && this._onToggle) {
            this.toggle.removeEventListener('click', this._onToggle);
            this.el.removeEventListener('play', this._syncToggle);
            this.el.removeEventListener('pause', this._syncToggle);
        }
        destroyVideoSource(this.el);
    }
}
