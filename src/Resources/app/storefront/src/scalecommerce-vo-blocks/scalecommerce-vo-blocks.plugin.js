import Plugin from 'src/plugin-system/plugin.class';
import Hls from 'hls.js';

export default class ScalecommerceVoBlocks extends Plugin {
    init() {
        this.facade = this.el.querySelector('[data-vo-action]');
        if (this.facade) {
            this.facade.addEventListener('click', () => this._onFacadeClick());
        }
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
            const url = embedUrl + (embedUrl.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1&muted=1';
            iframe.src = url;
            iframe.setAttribute('allow', 'autoplay; fullscreen');
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
        video.controls = true;
        if (autoplay) { video.muted = true; video.autoplay = true; }
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
            if (autoplay) { player.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {})); }
            return;
        }
        if (hls && video.canPlayType('application/vnd.apple.mpegurl')) { video.src = hls; return; }
        if (mp4) { video.src = mp4; }
    }

    _openLightbox(playerMode, embedUrl, nativeOptions) {
        const overlay = document.createElement('div');
        overlay.className = 'scalecommerce-vo-lightbox';
        const stage = document.createElement('div');
        stage.className = 'scalecommerce-vo-lightbox__stage';
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'scalecommerce-vo-lightbox__close';
        close.setAttribute('aria-label', 'Schließen');
        close.innerHTML = '&times;';
        const player = this._buildPlayer(playerMode, embedUrl, nativeOptions, true);
        stage.appendChild(player);
        overlay.appendChild(close);
        overlay.appendChild(stage);
        const dismiss = (event) => {
            if (event.target === overlay || event.currentTarget === close) {
                overlay.remove();
                document.removeEventListener('keydown', onKey);
            }
        };
        const onKey = (event) => { if (event.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', onKey); } };
        overlay.addEventListener('click', dismiss);
        close.addEventListener('click', dismiss);
        document.addEventListener('keydown', onKey);
        document.body.appendChild(overlay);
    }
}
