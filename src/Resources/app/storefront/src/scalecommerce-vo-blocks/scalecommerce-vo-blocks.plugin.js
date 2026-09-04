import Plugin from 'src/plugin-system/plugin.class';
import Hls from 'hls.js/dist/hls.light.mjs';

// Only one lightbox may be open at a time across the whole page (there can be several
// ScalecommerceVoBlocks instances, e.g. inside a video grid), so this guard lives at
// module scope rather than on the plugin instance.
let lightboxOpen = false;

export default class ScalecommerceVoBlocks extends Plugin {
    init() {
        this.facade = this.el.querySelector('[data-vo-action]');
        if (this.facade) {
            this.facade.addEventListener('click', () => this._onFacadeClick());
        }
    }

    destroy() {
        this.el.querySelectorAll('video').forEach((video) => this._destroyVideo(video));
    }

    _onFacadeClick() {
        const action = this.facade.getAttribute('data-vo-action');
        const playerMode = this.el.getAttribute('data-vo-player-mode');
        const embedUrl = this.el.getAttribute('data-vo-embed-url');
        let nativeOptions = {};
        try { nativeOptions = JSON.parse(this.el.getAttribute('data-vo-native-options') || '{}'); } catch (e) { nativeOptions = {}; }

        if (action === 'lightbox') {
            this._openLightbox(playerMode, embedUrl, nativeOptions);
            return;
        }
        // facade: replace the poster button with the player in place.
        this.facade.remove();
        this.el.appendChild(this._buildPlayer(playerMode, embedUrl, nativeOptions, true));
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
            iframe.setAttribute('allow', 'autoplay; fullscreen');
            iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups allow-presentation');
            iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            iframe.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0';
            return iframe;
        }
        const wrap = document.createElement('div');
        wrap.className = 'scalecommerce-vo-video';
        wrap.style.cssText = 'position:absolute;inset:0;width:100%;height:100%';
        const video = document.createElement('video');
        video.className = 'scalecommerce-vo-video__player';
        video.style.cssText = 'width:100%;height:100%;object-fit:contain;background:#000';
        video.setAttribute('playsinline', '');
        video.controls = nativeOptions.controls !== false;
        video.loop = !!nativeOptions.loop;
        video.muted = !!nativeOptions.muted;
        // The facade/lightbox click is a user gesture, so playback is allowed with the
        // configured muted setting; _attachNative kicks it off once the source is ready.
        if (autoplay) { video.autoplay = true; }
        wrap.appendChild(video);
        this._attachNative(video, nativeOptions, autoplay);
        return wrap;
    }

    _attachNative(video, options, autoplay) {
        const { hls, mp4 } = options;
        if (hls && Hls.isSupported()) {
            const player = new Hls();
            player.loadSource(hls);
            player.attachMedia(video);
            // Keep the instance reachable from the element itself so it can be destroyed
            // later without the plugin having to track every dynamically built player.
            video.__scvoHls = player;
            if (autoplay) { player.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {})); }
            return;
        }
        if (hls && video.canPlayType('application/vnd.apple.mpegurl')) { video.src = hls; return; }
        if (mp4) { video.src = mp4; }
    }

    _destroyVideo(video) {
        if (video.__scvoHls) {
            video.__scvoHls.destroy();
            video.__scvoHls = null;
        }
        video.pause();
        video.removeAttribute('src');
        video.load();
    }

    _openLightbox(playerMode, embedUrl, nativeOptions) {
        if (lightboxOpen) {
            return;
        }
        lightboxOpen = true;

        const closeLabel = this.el.getAttribute('data-vo-label-close') || 'Close';
        const overlay = document.createElement('div');
        overlay.className = 'scalecommerce-vo-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        const stage = document.createElement('div');
        stage.className = 'scalecommerce-vo-lightbox__stage';
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'scalecommerce-vo-lightbox__close';
        close.setAttribute('aria-label', closeLabel);
        close.innerHTML = '&times;';
        const player = this._buildPlayer(playerMode, embedUrl, nativeOptions, true);
        stage.appendChild(player);
        overlay.appendChild(close);
        overlay.appendChild(stage);

        const dismiss = (event) => {
            if (event.target === overlay || event.currentTarget === close) {
                stage.querySelectorAll('video').forEach((video) => this._destroyVideo(video));
                overlay.remove();
                document.removeEventListener('keydown', onKey);
                document.body.classList.remove('scalecommerce-vo-lightbox-open');
                lightboxOpen = false;
                this.facade.focus();
            }
        };
        const onKey = (event) => { if (event.key === 'Escape') { dismiss({ currentTarget: close }); } };
        overlay.addEventListener('click', dismiss);
        close.addEventListener('click', dismiss);
        document.addEventListener('keydown', onKey);
        document.body.classList.add('scalecommerce-vo-lightbox-open');
        document.body.appendChild(overlay);
        close.focus();
    }
}
