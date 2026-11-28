import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { installObserver, installPluginBase, stubMedia } from './support.js';

installPluginBase();
const { default: ScalecommerceVoProductVideo } = await import(
    '../../../src/Resources/app/storefront/src/scalecommerce-vo-product-video/scalecommerce-vo-product-video.plugin.js'
);

let observers;

beforeEach(() => {
    stubMedia();
    observers = installObserver();
});
afterEach(() => {
    delete window.IntersectionObserver;
    document.body.innerHTML = '';
});

describe('product video in the gallery (#48)', () => {
    it('pauses a native video when the slide is moved out of view', async () => {
        document.body.innerHTML = '<div class="slide"><video></video></div>';
        new ScalecommerceVoProductVideo(document.querySelector('.slide'));
        const video = document.querySelector('video');
        await video.play();

        observers[0].show(true, 0.9);
        expect(video.paused).toBe(false);

        observers[0].show(true, 0.3);
        expect(video.paused).toBe(true);
    });

    it('stops a hosted player by reloading it without autoplay', () => {
        document.body.innerHTML = '<div class="slide"><iframe src="https://player.example/embed/abc?autoplay=1&muted=0"></iframe></div>';
        new ScalecommerceVoProductVideo(document.querySelector('.slide'));

        observers[0].show(false);

        const url = new URL(document.querySelector('iframe').getAttribute('src'));
        expect(url.searchParams.get('autoplay')).toBe('0');
        expect(url.searchParams.get('muted')).toBe('0');
    });

    it('leaves a hosted player alone that was never started', () => {
        document.body.innerHTML = '<div class="slide"><iframe src="https://player.example/embed/abc?autoplay=0"></iframe></div>';
        new ScalecommerceVoProductVideo(document.querySelector('.slide'));
        const before = document.querySelector('iframe').getAttribute('src');

        observers[0].show(false);

        expect(document.querySelector('iframe').getAttribute('src')).toBe(before);
    });

    it('keeps a click on the poster from reaching the gallery and hands it to the facade button', () => {
        document.body.innerHTML = '<div class="gallery"><div class="slide"><button class="facade"><img alt=""></button></div></div>';
        new ScalecommerceVoProductVideo(document.querySelector('.slide'));
        let reached = 0;
        let facade = 0;
        // Like the gallery's zoom modal: a listener on the <img> itself.
        document.querySelector('img').addEventListener('click', () => reached++);
        document.querySelector('.gallery').addEventListener('click', () => reached++);
        document.querySelector('.facade').addEventListener('click', () => facade++);

        document.querySelector('img').click();

        expect(reached).toBe(0);
        expect(facade).toBe(1);
    });

    it('does nothing without IntersectionObserver and cleans up on destroy', () => {
        delete window.IntersectionObserver;
        document.body.innerHTML = '<div class="slide"><video></video></div>';
        expect(() => new ScalecommerceVoProductVideo(document.querySelector('.slide'))).not.toThrow();

        installObserver();
        const plugin = new ScalecommerceVoProductVideo(document.querySelector('.slide'));
        plugin.destroy();
    });
});
