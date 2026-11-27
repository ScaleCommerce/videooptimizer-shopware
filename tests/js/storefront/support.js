import { vi } from 'vitest';

/**
 * What the Shopware storefront provides at runtime: PluginBaseClass merges the static options
 * with the passed ones (instead of the data attributes) and calls init().
 */
export function installPluginBase() {
    window.PluginBaseClass = class {
        constructor(el, options = {}) {
            this.el = el;
            this.options = { ...this.constructor.options, ...options };
            this.init();
        }
    };
}

/**
 * jsdom does not implement media playback; the plugins only need these calls to exist.
 */
export function stubMedia() {
    const proto = window.HTMLMediaElement.prototype;
    // Playing and pausing keep the `paused` state and fire the events, like a real element.
    const setPaused = (video, paused) => {
        Object.defineProperty(video, 'paused', { value: paused, configurable: true });
        video.dispatchEvent(new Event(paused ? 'pause' : 'play'));
    };
    vi.spyOn(proto, 'play').mockImplementation(function play() {
        setPaused(this, false);
        return Promise.resolve();
    });
    vi.spyOn(proto, 'pause').mockImplementation(function pause() {
        setPaused(this, true);
    });
    vi.spyOn(proto, 'load').mockImplementation(() => {});
    vi.spyOn(proto, 'canPlayType').mockReturnValue('');
}

/**
 * IntersectionObserver stand-in that tests drive by hand.
 */
export function installObserver() {
    const observers = [];
    window.IntersectionObserver = class {
        constructor(callback) {
            this.callback = callback;
            this.disconnected = false;
            observers.push(this);
        }

        observe() {}

        disconnect() {
            this.disconnected = true;
        }

        show(isIntersecting) {
            this.callback([{ isIntersecting }]);
        }
    };
    return observers;
}
