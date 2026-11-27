import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { installPluginBase, stubMedia } from './support.js';

installPluginBase();
const { default: ScalecommerceVoChapters, activeSceneIndex, embedUrlAt } = await import(
    '../../../src/Resources/app/storefront/src/scalecommerce-vo-chapters/scalecommerce-vo-chapters.plugin.js'
);

const SCENES = `
    <ol>
        <li><button type="button" data-vo-scene="5">A</button></li>
        <li><button type="button" data-vo-scene="17">B</button></li>
        <li><button type="button" data-vo-scene="35">C</button></li>
    </ol>`;

function nativeSetup({ ready = true } = {}) {
    document.body.innerHTML = `
        <div class="root" data-scalecommerce-vo-chapters>
            <div data-scalecommerce-vo-player><video class="scalecommerce-vo-video__player"></video></div>
            ${SCENES}
        </div>`;
    const root = document.querySelector('.root');
    const playerEl = root.querySelector('[data-scalecommerce-vo-player]');
    if (ready) {
        playerEl.__scvoReady = Promise.resolve();
    }
    const plugin = new ScalecommerceVoChapters(root);

    return { root, plugin, playerEl, video: root.querySelector('video'), buttons: [...root.querySelectorAll('button')] };
}

beforeEach(() => stubMedia());
afterEach(() => {
    document.body.innerHTML = '';
});

describe('helpers (#46)', () => {
    it('finds the last scene started at or before the time', () => {
        expect(activeSceneIndex([5, 17, 35], 0)).toBe(-1);
        expect(activeSceneIndex([5, 17, 35], 5)).toBe(0);
        expect(activeSceneIndex([5, 17, 35], 34.9)).toBe(1);
        expect(activeSceneIndex([5, 17, 35], 90)).toBe(2);
    });

    it('starts the hosted embed at the scene with autoplay', () => {
        const url = new URL(embedUrlAt('https://player.example/embed/abc?autoplay=0&controls=1', 17.8));

        expect(url.searchParams.get('t')).toBe('17');
        expect(url.searchParams.get('autoplay')).toBe('1');
        expect(url.searchParams.get('controls')).toBe('1');
        expect(embedUrlAt('javascript:alert(1)', 3)).toBe('');
        expect(embedUrlAt('', 3)).toBe('');
    });
});

describe('chapters with the native player (#46)', () => {
    it('jumps to the scene start and plays', async () => {
        const { video, buttons } = nativeSetup();

        buttons[1].click();
        await vi.waitFor(() => expect(video.paused).toBe(false));

        expect(video.currentTime).toBe(17);
    });

    it('waits until the player has attached its source', async () => {
        const { video, buttons, playerEl } = nativeSetup({ ready: false });
        let release;
        playerEl.__scvoReady = new Promise((resolve) => {
            release = resolve;
        });

        buttons[2].click();
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(video.currentTime).toBe(0);

        release();
        await vi.waitFor(() => expect(video.paused).toBe(false));
        expect(video.currentTime).toBe(35);
    });

    it('marks the scene that is playing and nothing while paused', async () => {
        const { video, buttons } = nativeSetup();

        buttons[0].click();
        await vi.waitFor(() => expect(video.paused).toBe(false));
        video.currentTime = 20;
        video.dispatchEvent(new Event('timeupdate'));

        expect(buttons.map((button) => button.getAttribute('aria-current'))).toEqual([null, 'true', null]);

        video.pause();
        expect(buttons.some((button) => button.hasAttribute('aria-current'))).toBe(false);
    });

    it('takes the position over again when the player starts from the beginning', async () => {
        const { video, buttons } = nativeSetup();
        Object.defineProperty(video, 'readyState', { value: 0, configurable: true });

        buttons[1].click();
        await vi.waitFor(() => expect(video.paused).toBe(false));
        video.currentTime = 0;
        video.dispatchEvent(new Event('loadedmetadata'));

        expect(video.currentTime).toBe(17);
    });

    it('stops reacting after destroy', async () => {
        const { video, buttons, plugin } = nativeSetup();

        plugin.destroy();
        buttons[1].click();
        await new Promise((resolve) => setTimeout(resolve, 10));

        expect(video.currentTime).toBe(0);
    });
});

describe('chapters with the hosted player (#46)', () => {
    it('reloads the iframe at the scene start and marks the scene', async () => {
        document.body.innerHTML = `
            <div class="root" data-scalecommerce-vo-chapters>
                <iframe class="scalecommerce-vo-frame" src="https://player.example/embed/abc?autoplay=0"></iframe>
                ${SCENES}
            </div>`;
        new ScalecommerceVoChapters(document.querySelector('.root'));
        const buttons = [...document.querySelectorAll('button')];

        buttons[2].click();
        await Promise.resolve();

        const url = new URL(document.querySelector('iframe').getAttribute('src'));
        expect(url.searchParams.get('t')).toBe('35');
        expect(url.searchParams.get('autoplay')).toBe('1');
        expect(buttons.map((button) => button.getAttribute('aria-current'))).toEqual([null, null, 'true']);
    });
});
