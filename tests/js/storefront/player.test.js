import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Hls from '../stubs/hls.js';
import { installObserver, installPluginBase, stubMedia } from './support.js';

installPluginBase();
const { default: ScalecommerceVoPlayer } = await import(
    '../../../src/Resources/app/storefront/src/scalecommerce-vo-player/scalecommerce-vo-player.plugin.js'
);

let observers;

function setup(options = {}) {
    document.body.innerHTML = `
        <div class="scalecommerce-vo-video">
            <video class="scalecommerce-vo-video__player"></video>
        </div>`;
    const el = document.querySelector('.scalecommerce-vo-video');
    const plugin = new ScalecommerceVoPlayer(el, options);
    return { el, plugin, video: el.querySelector('video') };
}

beforeEach(() => {
    Hls.reset();
    stubMedia();
    observers = installObserver();
    window.MediaSource = function MediaSource() {};
});

afterEach(() => {
    delete window.MediaSource;
    delete window.IntersectionObserver;
    document.body.innerHTML = '';
});

describe('native player (#34)', () => {
    it('does nothing without a video element', () => {
        document.body.innerHTML = '<div class="empty"></div>';

        expect(() => new ScalecommerceVoPlayer(document.querySelector('.empty'), {})).not.toThrow();
        expect(Hls.instances).toHaveLength(0);
    });

    it('applies the configured options to the video', () => {
        const { video } = setup({ controls: false, loop: true, muted: true, poster: 'https://cdn/poster.jpg', mp4: 'https://cdn/v.mp4' });

        expect(video.controls).toBe(false);
        expect(video.loop).toBe(true);
        expect(video.muted).toBe(true);
        expect(video.preload).toBe('metadata');
        expect(video.getAttribute('poster')).toBe('https://cdn/poster.jpg');
    });

    it('mutes an autoplaying video and never uses the autoplay attribute', () => {
        const { video } = setup({ autoplay: true, mp4: 'https://cdn/v.mp4' });

        expect(video.muted).toBe(true);
        expect(video.autoplay).toBe(false);
    });

    it('streams HLS with hls.js when the browser supports Media Source Extensions', async () => {
        const { video } = setup({ hls: 'https://cdn/master.m3u8', mp4: 'https://cdn/v.mp4' });

        await vi.waitFor(() => expect(Hls.instances).toHaveLength(1));
        expect(Hls.instances[0].calls).toContainEqual(['loadSource', 'https://cdn/master.m3u8']);
        expect(Hls.instances[0].calls).toContainEqual(['attachMedia', video]);
    });

    it('falls back to the MP4 when hls.js reports a fatal error', async () => {
        const { video } = setup({ hls: 'https://cdn/master.m3u8', mp4: 'https://cdn/v.mp4' });
        await vi.waitFor(() => expect(Hls.instances).toHaveLength(1));

        Hls.instances[0].emit(Hls.Events.ERROR, { fatal: true });

        expect(video.src).toBe('https://cdn/v.mp4');
        expect(Hls.instances[0].destroyed).toBe(true);
    });

    it('uses the MP4 directly without HLS and takes the WebM when there is no MP4', async () => {
        const first = setup({ mp4: 'https://cdn/v.mp4' });
        await vi.waitFor(() => expect(first.video.src).toBe('https://cdn/v.mp4'));

        const second = setup({ webm: 'https://cdn/v.webm' });
        await vi.waitFor(() => expect(second.video.src).toBe('https://cdn/v.webm'));
        expect(Hls.instances).toHaveLength(0);
    });

    it('autoplays only while visible', async () => {
        const { video } = setup({ autoplay: true, mp4: 'https://cdn/v.mp4' });
        await vi.waitFor(() => expect(observers).toHaveLength(1));

        observers[0].show(true);
        expect(video.play).toHaveBeenCalled();
        observers[0].show(false);
        expect(video.pause).toHaveBeenCalled();
    });

    it('does not observe visibility without autoplay', async () => {
        const { video } = setup({ mp4: 'https://cdn/v.mp4' });
        await vi.waitFor(() => expect(video.src).toBe('https://cdn/v.mp4'));
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(observers).toHaveLength(0);
    });

    it('releases hls.js and the visibility observer on destroy', async () => {
        const { plugin } = setup({ autoplay: true, hls: 'https://cdn/master.m3u8' });
        await vi.waitFor(() => expect(observers).toHaveLength(1));

        plugin.destroy();

        expect(Hls.instances[0].destroyed).toBe(true);
        expect(observers[0].disconnected).toBe(true);
    });

    it('does not start autoplay when destroyed before the source was attached', async () => {
        const { plugin } = setup({ autoplay: true, hls: 'https://cdn/master.m3u8' });

        plugin.destroy();
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(observers).toHaveLength(0);
    });
});

describe('player theme (#41)', () => {
    it('adds autoplay muted, loop and preload from the theme', async () => {
        const { video } = setup({ mp4: 'https://cdn/v.mp4', theme: { autoplay: true, loopButton: true, preload: 'none' } });

        expect(video.loop).toBe(true);
        expect(video.muted).toBe(true);
        expect(video.preload).toBe('none');
        // Autoplay is driven by visibility, not by the attribute.
        expect(video.autoplay).toBe(false);
        await vi.waitFor(() => expect(observers).toHaveLength(1));
        observers[0].show(true);
        expect(video.play).toHaveBeenCalled();
    });

    it('starts muted with mutedStart and hides the controls when the theme says so', () => {
        const { video } = setup({ mp4: 'https://cdn/v.mp4', controls: true, theme: { mutedStart: true, controls: false } });

        expect(video.muted).toBe(true);
        expect(video.controls).toBe(false);
    });

    it('never overrides what the element sets', () => {
        const { video } = setup({ mp4: 'https://cdn/v.mp4', controls: false, loop: true, theme: { controls: true, loopButton: false } });

        expect(video.controls).toBe(false);
        expect(video.loop).toBe(true);
    });

    it('maps the buttons the native controls can honour', () => {
        const { video } = setup({ mp4: 'https://cdn/v.mp4', theme: { fullscreenButton: false, playbackSpeed: false, pictureInPicture: false } });

        expect(video.getAttribute('controlslist')).toBe('nofullscreen noplaybackrate');
        expect(video.hasAttribute('disablepictureinpicture')).toBe(true);
    });

    it('makes a non-interactive player ignore the pointer', () => {
        const { el } = setup({ mp4: 'https://cdn/v.mp4', theme: { interactive: false } });

        expect(el.classList.contains('scalecommerce-vo-video--static')).toBe(true);
    });

    it('caps the theme preload at metadata', () => {
        expect(setup({ mp4: 'https://cdn/v.mp4', theme: { preload: 'auto' } }).video.preload).toBe('metadata');
        expect(setup({ mp4: 'https://cdn/v.mp4', theme: { preload: 'metadata' } }).video.preload).toBe('metadata');
    });

    it('keeps the defaults without a theme', () => {
        const { video, el } = setup({ mp4: 'https://cdn/v.mp4' });

        expect(video.preload).toBe('metadata');
        expect(video.hasAttribute('controlslist')).toBe(false);
        expect(el.classList.contains('scalecommerce-vo-video--static')).toBe(false);
    });
});
