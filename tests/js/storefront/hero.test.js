import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Hls from '../stubs/hls.js';

// The hero plugin extends Shopware's PluginBaseClass, which the storefront provides at runtime.
window.PluginBaseClass = class {
    constructor(el) {
        this.el = el;
        this.init();
    }
};
const { default: ScalecommerceVoHero } = await import(
    '../../../src/Resources/app/storefront/src/scalecommerce-vo-hero/scalecommerce-vo-hero.plugin.js'
);

function setup() {
    document.body.innerHTML = `
        <div class="scalecommerce-vo-hero">
            <video data-scalecommerce-vo-hero-hls="https://cdn/master.m3u8"
                   data-scalecommerce-vo-hero-mp4="https://cdn/1080p.mp4"></video>
            <button class="scalecommerce-vo-hero__toggle" data-label-play="Play" data-label-pause="Pause" hidden></button>
        </div>`;
    const video = document.querySelector('video');
    // jsdom defines these as read-only; the plugin only reads them.
    for (const [name, value] of [['paused', true], ['ended', false]]) {
        Object.defineProperty(video, name, { value, writable: true, configurable: true });
    }
    Object.assign(video, {
        canPlayType: () => '',
        play: vi.fn(() => {
            video.paused = false;
            video.dispatchEvent(new Event('play'));
            return Promise.resolve();
        }),
        pause: vi.fn(() => {
            video.paused = true;
            video.dispatchEvent(new Event('pause'));
        }),
    });
    return { video, toggle: document.querySelector('.scalecommerce-vo-hero__toggle') };
}

beforeEach(() => {
    Hls.reset();
    window.MediaSource = function MediaSource() {};
    window.matchMedia = () => ({ matches: false });
    window.IntersectionObserver = class {
        observe() {}

        disconnect() {}
    };
});

afterEach(() => {
    delete window.MediaSource;
    delete window.IntersectionObserver;
});

describe('hero play button', () => {
    it('plays once the source is attached when it was pressed before (#28)', async () => {
        const { video, toggle } = setup();
        new ScalecommerceVoHero(video);

        // hls.js is still loading: the click must not be lost on a video without a source.
        toggle.click();
        expect(video.play).not.toHaveBeenCalled();
        expect(toggle.getAttribute('aria-label')).toBe('Pause');

        await vi.waitFor(() => expect(Hls.instances).toHaveLength(1));
        Hls.instances[0].emit(Hls.Events.MANIFEST_PARSED);

        expect(video.play).toHaveBeenCalled();
    });

    it('does not restart an off-screen hero on a later fatal error (#28)', async () => {
        const { video, toggle } = setup();
        new ScalecommerceVoHero(video);
        toggle.click();
        await vi.waitFor(() => expect(Hls.instances).toHaveLength(1));
        Hls.instances[0].emit(Hls.Events.MANIFEST_PARSED);
        expect(video.play).toHaveBeenCalled();

        // Scrolled out of view: autoplay-while-visible pauses it (not the visitor).
        video.pause();
        video.play.mockClear();
        Hls.instances[0].emit(Hls.Events.ERROR, { fatal: true });

        expect(video.src).toBe('https://cdn/1080p.mp4');
        expect(video.play).not.toHaveBeenCalled();
    });

    it('a second early click cancels the request', async () => {
        const { video, toggle } = setup();
        new ScalecommerceVoHero(video);

        toggle.click();
        toggle.click();
        expect(toggle.getAttribute('aria-label')).toBe('Play');

        await vi.waitFor(() => expect(Hls.instances).toHaveLength(1));
        Hls.instances[0].emit(Hls.Events.MANIFEST_PARSED);

        expect(video.play).not.toHaveBeenCalled();
    });

    it('toggles playback normally once the source is attached', async () => {
        const { video, toggle } = setup();
        new ScalecommerceVoHero(video);
        await vi.waitFor(() => expect(Hls.instances).toHaveLength(1));
        await vi.waitFor(() => expect(video.__scvoHls).toBeTruthy());
        await new Promise((resolve) => setTimeout(resolve, 0));

        toggle.click();
        expect(video.play).toHaveBeenCalledOnce();
        toggle.click();
        expect(video.pause).toHaveBeenCalledOnce();
    });
});

describe('hero lifecycle (#34)', () => {
    it('does nothing without a video source', () => {
        document.body.innerHTML = '<div class="scalecommerce-vo-hero"><video></video></div>';

        expect(() => new ScalecommerceVoHero(document.querySelector('video'))).not.toThrow();
        expect(Hls.instances).toHaveLength(0);
    });

    it('shows the toggle button as a play button initially', () => {
        const { video, toggle } = setup();
        new ScalecommerceVoHero(video);

        expect(toggle.hasAttribute('hidden')).toBe(false);
        expect(toggle.getAttribute('aria-label')).toBe('Play');
        expect(toggle.classList.contains('is-paused')).toBe(true);
    });

    it('keeps the toggle label in step with the video state', async () => {
        const { video, toggle } = setup();
        new ScalecommerceVoHero(video);
        await vi.waitFor(() => expect(video.__scvoHls).toBeTruthy());

        video.play();
        expect(toggle.getAttribute('aria-label')).toBe('Pause');
        video.pause();
        expect(toggle.getAttribute('aria-label')).toBe('Play');
    });

    it('plays when it scrolls into view and pauses when it leaves', async () => {
        const observers = [];
        window.IntersectionObserver = class {
            constructor(callback) {
                this.callback = callback;
                observers.push(this);
            }

            observe() {}

            disconnect() {}
        };
        const { video } = setup();
        new ScalecommerceVoHero(video);
        await vi.waitFor(() => expect(observers).toHaveLength(1));

        observers[0].callback([{ isIntersecting: true }]);
        expect(video.play).toHaveBeenCalled();
        observers[0].callback([{ isIntersecting: false }]);
        expect(video.pause).toHaveBeenCalled();
    });

    it('plays nothing on its own with reduced motion', async () => {
        window.matchMedia = () => ({ matches: true });
        const observed = vi.fn();
        window.IntersectionObserver = class {
            observe() {
                observed();
            }

            disconnect() {}
        };
        const { video } = setup();
        new ScalecommerceVoHero(video);
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(observed).not.toHaveBeenCalled();
        expect(video.play).not.toHaveBeenCalled();
    });

    it('releases listeners, observer and hls.js on destroy', async () => {
        const observers = [];
        window.IntersectionObserver = class {
            constructor() {
                this.disconnected = false;
                observers.push(this);
            }

            observe() {}

            disconnect() {
                this.disconnected = true;
            }
        };
        const { video, toggle } = setup();
        const plugin = new ScalecommerceVoHero(video);
        await vi.waitFor(() => expect(observers).toHaveLength(1));
        await vi.waitFor(() => expect(Hls.instances).toHaveLength(1));

        plugin.destroy();
        toggle.click();

        expect(observers[0].disconnected).toBe(true);
        expect(Hls.instances[0].destroyed).toBe(true);
        // The toggle no longer controls the video after destroy.
        expect(video.play).not.toHaveBeenCalled();
        expect(video.pause).not.toHaveBeenCalled();
    });
});
