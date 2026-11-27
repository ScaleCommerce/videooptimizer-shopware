import {
    attachVideoSource,
    destroyVideoSource,
    markUnavailable,
    resolvePlayback,
    applyPlayerTheme,
} from '../helper/attach-video-source';

// Only one lightbox may be open at a time across the whole page (there can be several
// ScalecommerceVoBlocks instances, e.g. inside a video grid), so this guard lives at
// module scope rather than on the plugin instance.
let lightboxOpen = false;

export default class ScalecommerceVoBlocks extends window.PluginBaseClass {
    init() {
        this.facade = this.el.querySelector('[data-vo-action]');
        if (this.facade) {
            this.facade.addEventListener('click', () => this._onFacadeClick());
        }
    }

    destroy() {
        // The lightbox overlay lives on document.body, outside this.el, so it would
        // otherwise survive plugin teardown — leaking its Hls instance and leaving the
        // module-scoped lightboxOpen guard stuck "open" for the rest of the page.
        if (this._closeLightbox) {
            this._closeLightbox();
        }
        this.el
            .querySelectorAll('video')
            .forEach((video) => this._destroyVideo(video));
    }

    _onFacadeClick() {
        const action = this.facade.getAttribute('data-vo-action');
        const playerMode = this.el.getAttribute('data-vo-player-mode');
        const embedUrl = this.el.getAttribute('data-vo-embed-url');
        let nativeOptions;
        try {
            nativeOptions = JSON.parse(
                this.el.getAttribute('data-vo-native-options') || '{}'
            );
        } catch {
            nativeOptions = {};
        }

        if (action === 'lightbox') {
            this._openLightbox(playerMode, embedUrl, nativeOptions);
            return;
        }
        // facade: replace the poster button with the player in place and move the focus there,
        // otherwise it falls back to <body> once the focused button is removed.
        const player = this._buildPlayer(
            playerMode,
            embedUrl,
            nativeOptions,
            true
        );
        this.facade.remove();
        this.el.appendChild(player);
        this._focusPlayer(player);
    }

    _title() {
        return this.el.getAttribute('data-vo-title') || 'Video';
    }

    _focusPlayer(player) {
        const target =
            player.tagName === 'IFRAME'
                ? player
                : player.querySelector('video');
        if (!target) {
            return;
        }
        // A <video> without controls is not focusable on its own.
        if (target.tagName === 'VIDEO' && !target.controls) {
            target.setAttribute('tabindex', '-1');
        }
        target.focus({ preventScroll: true });
    }

    _buildPlayer(playerMode, embedUrl, nativeOptions, autoplay) {
        if (playerMode === 'embed') {
            const iframe = document.createElement('iframe');
            // The user clicked to play, so force autoplay (muted to satisfy browser
            // autoplay policies), overriding the defaults already in the embed URL
            // rather than appending duplicate query keys.
            const url = new URL(embedUrl);
            url.searchParams.set('autoplay', '1');
            url.searchParams.set('muted', '1');
            iframe.src = url.toString();
            iframe.title = this._title();
            iframe.setAttribute(
                'allow',
                'autoplay; fullscreen; picture-in-picture'
            );
            iframe.setAttribute('allowfullscreen', '');
            iframe.setAttribute(
                'sandbox',
                'allow-scripts allow-same-origin allow-popups allow-presentation'
            );
            iframe.setAttribute(
                'referrerpolicy',
                'strict-origin-when-cross-origin'
            );
            iframe.className = 'scalecommerce-vo-frame';
            return iframe;
        }
        const wrap = document.createElement('div');
        wrap.className = 'scalecommerce-vo-video scalecommerce-vo-fill';
        const video = document.createElement('video');
        video.className =
            'scalecommerce-vo-video__player scalecommerce-vo-video__player--contain';
        video.setAttribute('playsinline', '');
        const playback = resolvePlayback(nativeOptions);
        video.controls = playback.controls;
        video.loop = playback.loop;
        // The click is a gesture, so playback with sound is allowed unless muted is asked for.
        video.muted =
            !!nativeOptions.muted || nativeOptions.theme?.mutedStart === true;
        video.preload = playback.preload;
        applyPlayerTheme(video, wrap, nativeOptions);
        // Keeps the poster visible while loading and behind the "cannot be played" notice.
        if (nativeOptions.poster) {
            video.poster = nativeOptions.poster;
        }
        // The facade/lightbox click is a user gesture, so playback is allowed with the
        // configured muted setting; _attachNative kicks it off once the source is ready.
        if (autoplay) {
            video.autoplay = true;
        }
        if (!video.controls) {
            video.setAttribute('tabindex', '-1');
        }
        wrap.appendChild(video);
        this._attachNative(video, nativeOptions, autoplay);
        return wrap;
    }

    _attachNative(video, options, autoplay) {
        attachVideoSource(
            video,
            { hls: options.hls, mp4: options.mp4 },
            {
                autoplay,
                onUnplayable: () =>
                    markUnavailable(
                        video.parentElement,
                        video,
                        options.unavailableLabel
                    ),
            }
        );
    }

    // Keeps Tab/Shift+Tab inside the lightbox (close button and player).
    _trapFocus(event, overlay, close) {
        const player = overlay.querySelector('iframe, video');
        const focusables = [close, player].filter(Boolean);
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const active = document.activeElement;
        if (!overlay.contains(active)) {
            event.preventDefault();
            first.focus();
        } else if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        } else if (
            !event.shiftKey &&
            active === last &&
            !(last.tagName === 'VIDEO' && last.controls)
        ) {
            // A native <video controls> keeps the focus while Tab moves through its own controls
            // (volume, fullscreen); the page behind is inert, so the focus comes back to "close".
            event.preventDefault();
            first.focus();
        }
    }

    _destroyVideo(video) {
        destroyVideoSource(video);
        video.pause();
        video.removeAttribute('src');
        video.load();
    }

    _openLightbox(playerMode, embedUrl, nativeOptions) {
        if (lightboxOpen) {
            return;
        }
        lightboxOpen = true;

        const closeLabel =
            this.el.getAttribute('data-vo-label-close') || 'Close';
        const overlay = document.createElement('div');
        overlay.className = 'scalecommerce-vo-lightbox';
        if (nativeOptions.theme?.accentColor) {
            overlay.style.setProperty(
                '--vo-accent',
                nativeOptions.theme.accentColor
            );
        }
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', this._title());
        const stage = document.createElement('div');
        stage.className = 'scalecommerce-vo-lightbox__stage';
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'scalecommerce-vo-lightbox__close';
        close.setAttribute('aria-label', closeLabel);
        close.innerHTML = '&times;';
        const player = this._buildPlayer(
            playerMode,
            embedUrl,
            nativeOptions,
            true
        );
        stage.appendChild(player);
        overlay.appendChild(close);
        overlay.appendChild(stage);

        const dismiss = (event) => {
            if (event.target === overlay || event.currentTarget === close) {
                stage
                    .querySelectorAll('video')
                    .forEach((video) => this._destroyVideo(video));
                overlay.remove();
                inertSiblings.forEach((element) =>
                    element.removeAttribute('inert')
                );
                document.removeEventListener('keydown', onKey);
                document.body.classList.remove(
                    'scalecommerce-vo-lightbox-open'
                );
                lightboxOpen = false;
                this._closeLightbox = null;
                this.facade.focus();
            }
        };
        const onKey = (event) => {
            if (event.key === 'Escape') {
                dismiss({ currentTarget: close });
                return;
            }
            if (event.key === 'Tab') {
                this._trapFocus(event, overlay, close);
            }
        };
        overlay.addEventListener('click', dismiss);
        close.addEventListener('click', dismiss);
        document.addEventListener('keydown', onKey);
        document.body.classList.add('scalecommerce-vo-lightbox-open');
        // Everything behind the dialog becomes inert, so neither Tab nor screen readers leave it.
        const inertSiblings = [...document.body.children].filter(
            (element) =>
                !element.hasAttribute('inert') &&
                !['SCRIPT', 'STYLE', 'LINK'].includes(element.tagName)
        );
        inertSiblings.forEach((element) => element.setAttribute('inert', ''));
        document.body.appendChild(overlay);
        close.focus();
        this._closeLightbox = () => dismiss({ currentTarget: close });
    }
}
