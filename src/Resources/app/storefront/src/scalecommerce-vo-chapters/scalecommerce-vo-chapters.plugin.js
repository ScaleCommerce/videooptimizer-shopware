// Chapters and scenes: a row of scene previews under the player; a click jumps to the scene's start
// time and plays. Native player: the source is attached by ScalecommerceVoPlayer (it publishes a
// promise as `__scvoReady` on its element); hosted player: the iframe is reloaded at the start time
// (the embed understands `t` and `autoplay`).

/** Index of the last scene that started at or before `time`; -1 before the first one. */
export function activeSceneIndex(starts, time) {
    let active = -1;
    starts.forEach((start, index) => {
        if (time >= start) {
            active = index;
        }
    });
    return active;
}

/** The hosted embed URL starting at `seconds` with autoplay; '' when the URL is not usable. */
export function embedUrlAt(base, seconds) {
    if (!base) {
        return '';
    }
    try {
        const url = new URL(base, window.location.href);
        if (url.protocol !== 'https:' && url.protocol !== 'http:') {
            return '';
        }
        url.searchParams.set('t', String(Math.max(0, Math.floor(seconds))));
        url.searchParams.set('autoplay', '1');
        return url.href;
    } catch {
        return '';
    }
}

// ScalecommerceVoPlayer and this plugin are registered asynchronously, so the player may still be
// initialising when the visitor clicks a scene.
function whenSourceReady(playerEl) {
    return new Promise((resolve) => {
        let tries = 0;
        const check = () => {
            if (playerEl.__scvoReady) {
                resolve(playerEl.__scvoReady.catch(() => {}));
            } else if (++tries > 50) {
                resolve();
            } else {
                window.setTimeout(check, 100);
            }
        };
        check();
    });
}

export default class ScalecommerceVoChapters extends window.PluginBaseClass {
    init() {
        this.buttons = [...this.el.querySelectorAll('[data-vo-scene]')];
        if (this.buttons.length === 0) {
            return;
        }
        this.starts = this.buttons.map(
            (button) => Number(button.getAttribute('data-vo-scene')) || 0
        );
        this.playerEl = this.el.querySelector('[data-scalecommerce-vo-player]');
        this.video = this.el.querySelector(
            'video.scalecommerce-vo-video__player'
        );
        this.frame = this.el.querySelector('iframe.scalecommerce-vo-frame');

        this._handlers = this.buttons.map((button, index) => {
            const handler = () => this._jump(index);
            button.addEventListener('click', handler);
            return handler;
        });

        if (this.video) {
            this._update = () => this._markActive(this._playingIndex());
            for (const event of [
                'timeupdate',
                'play',
                'pause',
                'ended',
                'seeked',
            ]) {
                this.video.addEventListener(event, this._update);
            }
        }
    }

    _playingIndex() {
        return this.video.paused
            ? -1
            : activeSceneIndex(this.starts, this.video.currentTime);
    }

    _markActive(active) {
        this.buttons.forEach((button, index) => {
            if (index === active) {
                button.setAttribute('aria-current', 'true');
            } else {
                button.removeAttribute('aria-current');
            }
        });
    }

    async _jump(index) {
        const seconds = this.starts[index];
        if (this.video) {
            await this._seekAndPlay(seconds);
        } else if (this.frame) {
            const src = embedUrlAt(
                this.frame.getAttribute('src') || '',
                seconds
            );
            if (src) {
                this.frame.setAttribute('src', src);
                this._markActive(index);
            }
        }
    }

    async _seekAndPlay(seconds) {
        const video = this.video;
        if (this.playerEl) {
            await whenSourceReady(this.playerEl);
        }
        if (this._destroyed) {
            return;
        }
        video.currentTime = seconds;
        if (video.readyState === 0) {
            // hls.js loads nothing before playback, so the position is only known once the first
            // segment is there; take it over again if the player started from the beginning.
            video.addEventListener(
                'loadedmetadata',
                () => {
                    if (Math.abs(video.currentTime - seconds) > 1) {
                        video.currentTime = seconds;
                    }
                },
                { once: true }
            );
        }
        await video.play().catch(() => {});
    }

    destroy() {
        this._destroyed = true;
        if (this.video && this._update) {
            for (const event of [
                'timeupdate',
                'play',
                'pause',
                'ended',
                'seeked',
            ]) {
                this.video.removeEventListener(event, this._update);
            }
        }
        this.buttons?.forEach((button, index) =>
            button.removeEventListener('click', this._handlers[index])
        );
    }
}
