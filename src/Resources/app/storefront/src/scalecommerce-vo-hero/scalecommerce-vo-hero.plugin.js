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

        // Until the source is attached (hls.js may still be loading) a click on the button cannot
        // play anything; it is remembered here and honoured once the source is ready.
        this._sourceReady = false;
        this._playRequested = false;

        const motionReduced = this._motionReduced();
        // hls.js loads on demand, so autoplay starts only once the source is attached.
        const attached = attachVideoSource(
            video,
            { hls, mp4 },
            {
                // Nothing plays with reduced motion / data saver, so don't buffer even for priority.
                deferLoad:
                    motionReduced ||
                    !video.hasAttribute('data-scalecommerce-vo-hero-priority'),
                // Consumed once: a later source change must not restart a hero that was paused
                // (e.g. scrolled out of view) in the meantime.
                shouldPlay: () => {
                    const requested = this._playRequested;
                    this._playRequested = false;
                    return requested;
                },
                onUnplayable: () => {
                    video.removeAttribute('src');
                    this.toggle?.setAttribute('hidden', '');
                },
            }
        );

        if (this.toggle) {
            this._onToggle = () => this._togglePlayback();
            this._syncToggle = () => this._updateToggle(video.paused);
            this.toggle.addEventListener('click', this._onToggle);
            video.addEventListener('play', this._syncToggle);
            video.addEventListener('pause', this._syncToggle);
            this._updateToggle(true);
            this.toggle.removeAttribute('hidden');
        }

        attached.then(() => {
            this._sourceReady = true;
        });

        if (!motionReduced) {
            attached.then(() => {
                if (!this._destroyed) {
                    this._stopAutoplay = autoplayWhileVisible(
                        video,
                        video.closest('.scalecommerce-vo-hero') || video
                    );
                }
            });
        }
    }

    _togglePlayback() {
        const video = this.el;
        if (!this._sourceReady) {
            this._playRequested = !this._playRequested;
            this._updateToggle(!this._playRequested);
            return;
        }
        // From here on the video's own state is the truth; an early request must not restart it later.
        this._playRequested = false;
        if (video.paused) {
            video.play().catch(() => {});
        } else {
            video.pause();
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
        this._destroyed = true;
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
