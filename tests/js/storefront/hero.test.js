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
