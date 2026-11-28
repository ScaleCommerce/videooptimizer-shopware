import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { markExpectedPlayback } from '../../../src/Resources/app/storefront/src/helper/attach-video-source.js';
import { installObserver, installPluginBase, stubMedia } from './support.js';

installPluginBase();
// The visitor presses play, e.g. on the native control bar: nothing the plugins mark as their own.
async function userPlay(video) {
    await video.play();
}

const { default: ScalecommerceVoMini } = await import(
    '../../../src/Resources/app/storefront/src/scalecommerce-vo-mini/scalecommerce-vo-mini.plugin.js'
);

let observers;
let wide;

function setup({ hosted = false, src = 'https://player.example/embed/abc?autoplay=0&muted=0' } = {}) {
    document.body.innerHTML = `
        <div class="el" data-scalecommerce-vo-mini data-vo-label-close="Schliessen">
            <div class="scalecommerce-vo-video scalecommerce-vo-video--sized">
                ${hosted ? `<iframe class="scalecommerce-vo-frame" src="${src}"></iframe>` : '<video class="scalecommerce-vo-video__player"></video>'}
            </div>
        </div>`;
    const el = document.querySelector('.el');
    const box = el.querySelector('.scalecommerce-vo-video--sized');
    Object.defineProperty(box, 'offsetHeight', { value: 180, configurable: true });
    Object.defineProperty(box, 'offsetWidth', { value: 320, configurable: true });
    const plugin = new ScalecommerceVoMini(el);

    return {
        el,
        box,
        plugin,
        video: el.querySelector('video'),
        frame: el.querySelector('iframe'),
        observer: observers.at(-1),
        close: el.querySelector('.scalecommerce-vo-mini__close'),
    };
}

beforeEach(() => {
    stubMedia();
    observers = installObserver();
    wide = true;
    window.matchMedia = () => ({ get matches() { return wide; }, addEventListener() {}, removeEventListener() {} });
});

afterEach(() => {
    delete window.IntersectionObserver;
    delete window.matchMedia;
    document.body.className = '';
    document.body.innerHTML = '';
});

describe('floating mini player (#49)', () => {
    it('pins a playing native player that scrolls out of view and keeps the height', async () => {
        const { box, el, video, observer } = setup();
        await userPlay(video);

        observer.show(false);

        expect(box.classList.contains('is-pinned')).toBe(true);
        expect(el.style.minHeight).toBe('180px');
        expect(box.contains(video)).toBe(true);
    });

    it('unpins when the player is back in view', async () => {
        const { box, el, video, observer } = setup();
        await userPlay(video);
        observer.show(false);

        observer.show(true);

        expect(box.classList.contains('is-pinned')).toBe(false);
        expect(el.style.minHeight).toBe('');
    });

    it('does not pin a player that was never started or is paused', async () => {
        const { box, video, observer } = setup();

        observer.show(false);
        expect(box.classList.contains('is-pinned')).toBe(false);

        await userPlay(video);
        video.pause();
        observer.show(false);
        expect(box.classList.contains('is-pinned')).toBe(false);
    });

    it('treats less than one percent visible as out of view', async () => {
        const { box, video, observer } = setup();
        await userPlay(video);

        observer.show(true, 0.5);
        expect(box.classList.contains('is-pinned')).toBe(false);
        observer.show(true, 0.004);
        expect(box.classList.contains('is-pinned')).toBe(true);
    });

    it('is not used on narrow viewports', async () => {
        wide = false;
        const { box, video, observer } = setup();
        await userPlay(video);

        observer.show(false);

        expect(box.classList.contains('is-pinned')).toBe(false);
    });

    it('is not used while the lightbox is open', async () => {
        const { box, video, observer } = setup();
        await userPlay(video);
        document.body.classList.add('scalecommerce-vo-lightbox-open');

        observer.show(false);

        expect(box.classList.contains('is-pinned')).toBe(false);
    });

    it('marks a portrait box so the mini player gets a narrow width', async () => {
        const { box, video, observer } = setup();
        Object.defineProperty(box, 'offsetHeight', { value: 400, configurable: true });
        Object.defineProperty(box, 'offsetWidth', { value: 225, configurable: true });
        await userPlay(video);

        observer.show(false);

        expect(box.classList.contains('is-portrait')).toBe(true);
    });

    it('has a labelled close button that unpins and pauses', async () => {
        const { box, video, observer, close } = setup();
        await userPlay(video);
        observer.show(false);

        expect(close.getAttribute('aria-label')).toBe('Schliessen');
        close.click();

        expect(box.classList.contains('is-pinned')).toBe(false);
        expect(video.paused).toBe(true);
    });

    it('pins again only after the player was in view again', async () => {
        const { box, video, observer, close } = setup();
        await userPlay(video);
        observer.show(false);
        close.click();
        await userPlay(video);

        observer.show(false);
        expect(box.classList.contains('is-pinned')).toBe(false);

        observer.show(true);
        observer.show(false);
        expect(box.classList.contains('is-pinned')).toBe(true);
    });

    it('unpins on Escape inside the mini player and leaves the playback alone', async () => {
        const { box, video, observer, close } = setup();
        await userPlay(video);
        observer.show(false);
        close.focus();

        box.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(box.classList.contains('is-pinned')).toBe(false);
        expect(video.paused).toBe(false);
    });

    it('ignores Escape while the mini player is not pinned', () => {
        const { box, plugin } = setup();

        box.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        // Nothing was dismissed, so the player may still be pinned later.
        expect(plugin.dismissed).toBe(false);
    });

    it('does not pin a hosted player that only autoplays at load', () => {
        const { box, observer } = setup({ hosted: true, src: 'https://player.example/embed/abc?autoplay=1' });

        observer.show(false);

        expect(box.classList.contains('is-pinned')).toBe(false);
    });

    it('does not pin a video that autoplays or plays while scrolling (marked by the plugins)', async () => {
        const { box, el, video, observer } = setup();
        markExpectedPlayback(video);
        await video.play();
        video.dispatchEvent(new Event('volumechange'));
        video.dispatchEvent(new Event('seeking'));

        observer.show(false);

        expect(box.classList.contains('is-pinned')).toBe(false);
        expect(el.__scvoMiniStarted).toBeUndefined();
    });

    it('counts unmuting an autoplaying video on the native controls as started', async () => {
        const { box, el, video, observer } = setup();
        markExpectedPlayback(video);
        await video.play();
        // Later, no mark any more: the visitor.
        video.__scvoExpectedUntil = 0;
        video.dispatchEvent(new Event('volumechange'));

        expect(el.__scvoMiniStarted).toBe(true);
        observer.show(false);
        expect(box.classList.contains('is-pinned')).toBe(true);
    });

    it('counts seeking or pause-then-play of an autoplaying video as started', async () => {
        const { box, video, observer } = setup();
        markExpectedPlayback(video);
        await video.play();
        video.__scvoExpectedUntil = 0;
        video.dispatchEvent(new Event('seeking'));

        observer.show(false);

        expect(box.classList.contains('is-pinned')).toBe(true);
    });

    it('pins a click-started video although the editor set it muted', async () => {
        const { box, video, observer } = setup();
        video.muted = true;
        await userPlay(video);

        observer.show(false);

        expect(box.classList.contains('is-pinned')).toBe(true);
    });

    it('counts a click on a scene of hosted chapters as started', () => {
        document.body.innerHTML = `
            <div data-scalecommerce-vo-chapters>
                <div class="el" data-scalecommerce-vo-mini>
                    <div class="scalecommerce-vo-video--sized"><iframe src="https://player.example/embed/abc"></iframe></div>
                </div>
                <button type="button" data-vo-scene="5">A</button>
            </div>`;
        new ScalecommerceVoMini(document.querySelector('.el'));
        const box = document.querySelector('.scalecommerce-vo-video--sized');

        document.querySelector('[data-vo-scene]').click();
        observers.at(-1).show(false);

        expect(box.classList.contains('is-pinned')).toBe(true);
    });

    it('unpins and pauses when a lightbox opens', async () => {
        const { box, video, observer } = setup();
        await userPlay(video);
        observer.show(false);
        expect(box.classList.contains('is-pinned')).toBe(true);

        document.body.classList.add('scalecommerce-vo-lightbox-open');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(box.classList.contains('is-pinned')).toBe(false);
        expect(video.paused).toBe(true);
    });

    it('moves the focus to the player after closing', async () => {
        const { video, observer, close } = setup();
        await userPlay(video);
        observer.show(false);
        close.focus();

        close.click();

        expect(document.activeElement).toBe(video);
    });

    it('pins a started hosted player and restores it without playback on close', () => {
        const { box, frame, observer, close } = setup({ hosted: true, src: 'https://player.example/embed/abc?autoplay=1' });
        frame.focus();
        window.dispatchEvent(new Event('blur'));

        observer.show(false);
        expect(box.classList.contains('is-pinned')).toBe(true);

        close.click();
        expect(new URL(frame.getAttribute('src')).searchParams.get('autoplay')).toBe('0');
        expect(box.classList.contains('is-pinned')).toBe(false);
    });

    it('treats a click into a hosted player as started', () => {
        const { box, frame, observer } = setup({ hosted: true });
        observer.show(false);
        expect(box.classList.contains('is-pinned')).toBe(false);

        frame.focus();
        window.dispatchEvent(new Event('blur'));
        observer.show(true);
        observer.show(false);

        expect(box.classList.contains('is-pinned')).toBe(true);
    });

    it('cleans up on destroy', async () => {
        const { box, plugin, video, observer, close } = setup();
        await userPlay(video);
        observer.show(false);

        plugin.destroy();

        expect(observer.disconnected).toBe(true);
        expect(box.classList.contains('is-pinned')).toBe(false);
        expect(close.isConnected).toBe(false);
    });
});
