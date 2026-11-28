// Floating mini player: once the visitor has started playback and the player scrolls out of view,
// the player box is pinned bottom right (CSS only - the <video>/<iframe> never moves in the DOM, so
// an iframe does not reload). The element itself stays in the flow as a placeholder with the box's
// height, so the page does not jump. Scrolling back unpins; the close button unpins and stops
// playback; Escape inside the mini player unpins. Not used below 600px viewport width or while the
// lightbox is open.
import { isExpectedPlayback } from '../helper/attach-video-source';

const BOX_SELECTOR =
    '.scalecommerce-vo-video--sized, .scalecommerce-vo-surface';
const MIN_WIDTH = '(min-width: 600px)';
const PINNED = 'is-pinned';
const INTERACTIONS = ['play', 'volumechange', 'seeking'];

export default class ScalecommerceVoMini extends window.PluginBaseClass {
    init() {
        this.box = this.el.querySelector(BOX_SELECTOR);
        if (!this.box) {
            return;
        }
        this.el.classList.add('scalecommerce-vo-mini');
        this.video = this.box.querySelector('video');
        this.frame = this.box.querySelector('iframe');
        this.pinned = false;
        this.dismissed = false;
        this.visible = true;
        // "Started" means started by the visitor. The native control bar sends no pointer or key
        // events into the page, so instead of watching gestures the plugins mark what they do
        // themselves (autoplay, play-while-visible, the initial mute, source attachment): any other
        // play, volumechange or seeking is the visitor's. A scene click counts too, and a hosted
        // player (which cannot report playback) counts once the visitor clicks into it or a scene.
        this.started = false;
        this.hostedStarted = false;

        this._createClose();
        this._bind();
    }

    _createClose() {
        this.close = document.createElement('button');
        this.close.type = 'button';
        this.close.className = 'scalecommerce-vo-mini__close';
        this.close.setAttribute(
            'aria-label',
            this.el.getAttribute('data-vo-label-close') || 'Close'
        );
        this.close.innerHTML = '&times;';
        this.box.appendChild(this.close);
    }

    _bind() {
        // The scene buttons of a chapters element sit outside the player box, inside its root.
        this._onSceneClick = (event) => {
            if (event.target?.closest?.('[data-vo-scene]')) {
                this.hostedStarted = !!this.frame;
            }
        };
        this.sceneRoot = this.el.closest('[data-scalecommerce-vo-chapters]');
        this.sceneRoot?.addEventListener('click', this._onSceneClick, true);
        if (this.video) {
            this._onInteraction = () => {
                if (!isExpectedPlayback(this.video)) {
                    this.video.__scvoOwnPlay = false;
                    this.started = true;
                    this.el.__scvoMiniStarted = true;
                }
            };
            for (const type of INTERACTIONS) {
                this.video.addEventListener(type, this._onInteraction);
            }
        }

        // Opening a lightbox takes over: the mini player goes away (and stops).
        if (typeof window.MutationObserver === 'function') {
            this._lightboxObserver = new window.MutationObserver(() => {
                if (
                    this.pinned &&
                    document.body.classList.contains(
                        'scalecommerce-vo-lightbox-open'
                    )
                ) {
                    this._dismiss(true, false);
                }
            });
            this._lightboxObserver.observe(document.body, {
                attributes: true,
                attributeFilter: ['class'],
            });
        }

        this._onClose = () => this._dismiss(true);
        this.close.addEventListener('click', this._onClose);

        this._onKey = (event) => {
            if (
                event.key === 'Escape' &&
                this.pinned &&
                this.box.contains(document.activeElement)
            ) {
                this._dismiss(false);
            }
        };
        this.box.addEventListener('keydown', this._onKey);

        this._media = window.matchMedia?.(MIN_WIDTH) ?? null;
        this._onMedia = () => {
            if (this.pinned && !this._media.matches) {
                this._unpin();
            }
        };
        this._media?.addEventListener?.('change', this._onMedia);

        if (this.frame) {
            this._onBlur = () => {
                if (document.activeElement === this.frame) {
                    this.hostedStarted = true;
                }
            };
            window.addEventListener('blur', this._onBlur);
        }

        if (typeof window.IntersectionObserver === 'function') {
            this.observer = new window.IntersectionObserver(
                (entries) =>
                    entries.forEach((entry) =>
                        this._onVisible(
                            entry.isIntersecting &&
                                entry.intersectionRatio >= 0.01
                        )
                    ),
                { threshold: [0, 0.01, 0.5] }
            );
            this.observer.observe(this.el);
        }
    }

    _playing() {
        return this.video
            ? this.started && !this.video.paused && !this.video.ended
            : this.hostedStarted;
    }

    _onVisible(visible) {
        this.visible = visible;
        if (visible) {
            // Back in view: unpin, and a pin dismissed earlier may happen again on the next scroll away.
            this.dismissed = false;
            this._unpin();
            return;
        }
        if (this.pinned || this.dismissed || !this._playing()) {
            return;
        }
        if (this._media && !this._media.matches) {
            return;
        }
        if (
            document.body.classList.contains('scalecommerce-vo-lightbox-open')
        ) {
            return;
        }
        this._pin();
    }

    _pin() {
        const { offsetHeight, offsetWidth } = this.box;
        // The placeholder keeps the height of the box that leaves the flow.
        this.el.style.minHeight = `${offsetHeight}px`;
        this.box.classList.toggle('is-portrait', offsetHeight > offsetWidth);
        this.box.classList.add(PINNED);
        this.pinned = true;
    }

    _unpin() {
        if (!this.pinned) {
            return;
        }
        this.box.classList.remove(PINNED, 'is-portrait');
        this.el.style.minHeight = '';
        this.pinned = false;
    }

    /**
     * @param {boolean} stop close button: stop playback too; Escape only unpins
     * @param {boolean} focus move the focus back to the player (not for a lightbox that took over)
     */
    _dismiss(stop, focus = true) {
        this._unpin();
        this.dismissed = true;
        if (focus) {
            this._focusPlayer();
        }
        if (!stop) {
            return;
        }
        if (this.video) {
            this.video.pause();
        } else if (this.frame) {
            // An iframe cannot be paused from outside: reload it without autoplay, which stops it.
            this.hostedStarted = false;
            const url = new URL(
                this.frame.getAttribute('src'),
                window.location.href
            );
            url.searchParams.set('autoplay', '0');
            this.frame.setAttribute('src', url.href);
        }
    }

    // The close button disappears with the pin, so the focus must not fall back to <body>.
    _focusPlayer() {
        const target = this.video || this.frame;
        if (!target) {
            return;
        }
        if (target.tagName === 'VIDEO' && !target.controls) {
            target.setAttribute('tabindex', '-1');
        }
        target.focus({ preventScroll: true });
    }

    destroy() {
        this.sceneRoot?.removeEventListener('click', this._onSceneClick, true);
        for (const type of INTERACTIONS) {
            this.video?.removeEventListener(type, this._onInteraction);
        }
        delete this.el.__scvoMiniStarted;
        this._lightboxObserver?.disconnect();
        this.observer?.disconnect();
        this.close?.removeEventListener('click', this._onClose);
        this.box?.removeEventListener('keydown', this._onKey);
        this._media?.removeEventListener?.('change', this._onMedia);
        if (this._onBlur) {
            window.removeEventListener('blur', this._onBlur);
        }
        this._unpin();
        this.close?.remove();
    }
}
