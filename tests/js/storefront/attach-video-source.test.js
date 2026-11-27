import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Hls from '../stubs/hls.js';
import {
    attachVideoSource,
    autoplayWhileVisible,
    destroyVideoSource,
    markUnavailable,
} from '../../../src/Resources/app/storefront/src/helper/attach-video-source.js';

// A <video> stand-in: jsdom does not implement media playback.
function fakeVideo({ nativeHls = false } = {}) {
    const video = new EventTarget();
    Object.assign(video, {
        paused: true,
        ended: false,
        controls: true,
        src: '',
        canPlayType: (type) =>
            nativeHls && type === 'application/vnd.apple.mpegurl' ? 'maybe' : '',
        play: vi.fn(() => {
            video.paused = false;
            video.dispatchEvent(new Event('play'));
            return Promise.resolve();
        }),
        pause: vi.fn(() => {
            video.paused = true;
            video.dispatchEvent(new Event('pause'));
        }),
        removeAttribute: vi.fn((name) => {
            if (name === 'src') {
                video.src = '';
            }
        }),
    });
    return video;
}

beforeEach(() => {
    Hls.reset();
    // Browsers with Media Source Extensions use hls.js; jsdom has none.
    window.MediaSource = function MediaSource() {};
});

afterEach(() => {
    delete window.MediaSource;
});

describe('attachVideoSource', () => {
    it('attaches HLS via hls.js and defers segment loading until playback', async () => {
        const video = fakeVideo();
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8' }, { deferLoad: true });

        const [player] = Hls.instances;
        expect(player.config).toMatchObject({ autoStartLoad: false, capLevelToPlayerSize: true });
        expect(player.calls.map(([call]) => call)).toEqual(['loadSource', 'attachMedia']);

        video.dispatchEvent(new Event('play'));
        expect(player.calls.at(-1)).toEqual(['startLoad']);
    });

    it('starts playback once the manifest is parsed when autoplay is on', async () => {
        const video = fakeVideo();
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8' }, { autoplay: true });

        Hls.instances[0].emit(Hls.Events.MANIFEST_PARSED);
        expect(video.play).toHaveBeenCalledOnce();
    });

    it('falls back to MP4 on a fatal hls.js error', async () => {
        const video = fakeVideo();
        const onUnplayable = vi.fn();
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8', mp4: 'https://cdn/1080p.mp4' }, { onUnplayable });

        const [player] = Hls.instances;
        player.emit(Hls.Events.ERROR, { fatal: false });
        expect(video.src).toBe('');

        player.emit(Hls.Events.ERROR, { fatal: true });
        expect(player.destroyed).toBe(true);
        expect(video.src).toBe('https://cdn/1080p.mp4');
        expect(onUnplayable).not.toHaveBeenCalled();

        // The MP4 failing as well leaves nothing to play.
        video.dispatchEvent(new Event('error'));
        expect(onUnplayable).toHaveBeenCalledOnce();
    });

    it('resumes playback on the MP4 fallback when the video was playing (#28)', async () => {
        const video = fakeVideo();
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8', mp4: 'https://cdn/1080p.mp4' });

        // Autoplay-while-visible or the visitor started playback.
        video.play();
        video.play.mockClear();
        Hls.instances[0].emit(Hls.Events.ERROR, { fatal: true });

        expect(video.src).toBe('https://cdn/1080p.mp4');
        expect(video.play).toHaveBeenCalledOnce();
    });

    it('continues at the same position on the MP4 fallback', async () => {
        const video = fakeVideo();
        video.currentTime = 0;
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8', mp4: 'https://cdn/1080p.mp4' });
        video.play();
        video.currentTime = 12.5;

        Hls.instances[0].emit(Hls.Events.ERROR, { fatal: true });
        // The new source has no position yet; it is applied once the metadata is known.
        video.currentTime = 0;
        video.dispatchEvent(new Event('loadedmetadata'));

        expect(video.currentTime).toBe(12.5);
    });

    it('does not seek when the fallback starts from the beginning', async () => {
        const video = fakeVideo();
        video.currentTime = 0;
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8', mp4: 'https://cdn/1080p.mp4' });

        Hls.instances[0].emit(Hls.Events.ERROR, { fatal: true });
        video.dispatchEvent(new Event('loadedmetadata'));

        expect(video.currentTime).toBe(0);
    });

    it('keeps a paused video paused on the MP4 fallback', async () => {
        const video = fakeVideo();
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8', mp4: 'https://cdn/1080p.mp4' });

        Hls.instances[0].emit(Hls.Events.ERROR, { fatal: true });

        expect(video.src).toBe('https://cdn/1080p.mp4');
        expect(video.play).not.toHaveBeenCalled();
    });

    it('resumes playback when the native HLS source fails over to MP4 (#28)', async () => {
        Hls.supported = false;
        const video = fakeVideo({ nativeHls: true });
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8', mp4: 'https://cdn/720p.mp4' });
        video.play();
        video.play.mockClear();

        video.dispatchEvent(new Event('error'));

        expect(video.src).toBe('https://cdn/720p.mp4');
        expect(video.play).toHaveBeenCalledOnce();
    });

    it('starts playback once the source is ready when the caller wants it (#28)', async () => {
        const video = fakeVideo();
        let wanted = false;
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8' }, { shouldPlay: () => wanted });

        Hls.instances[0].emit(Hls.Events.MANIFEST_PARSED);
        expect(video.play).not.toHaveBeenCalled();

        // The intent is read when the source becomes ready, not when attaching starts.
        wanted = true;
        Hls.instances[0].emit(Hls.Events.MANIFEST_PARSED);
        expect(video.play).toHaveBeenCalledOnce();
    });

    it('starts the MP4 when the caller wants playback (#28)', async () => {
        const video = fakeVideo();
        await attachVideoSource(video, { mp4: 'https://cdn/720p.mp4' }, { shouldPlay: () => true });

        expect(video.src).toBe('https://cdn/720p.mp4');
        expect(video.play).toHaveBeenCalledOnce();
    });

    it('reports an unplayable video on a fatal error without MP4', async () => {
        const video = fakeVideo();
        const onUnplayable = vi.fn();
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8' }, { onUnplayable });

        Hls.instances[0].emit(Hls.Events.ERROR, { fatal: true });
        expect(onUnplayable).toHaveBeenCalledOnce();
    });

    it('starts loading right away when playback started before hls.js was loaded', async () => {
        const video = fakeVideo();
        const attached = attachVideoSource(video, { hls: 'https://cdn/master.m3u8' }, { deferLoad: true });
        // The visitor presses play while the hls.js chunk is still loading.
        video.play();
        await attached;

        expect(Hls.instances[0].config.autoStartLoad).toBe(true);

        // Attaching the source interrupts the pending play(); resume once the manifest is parsed.
        video.paused = true;
        Hls.instances[0].emit(Hls.Events.MANIFEST_PARSED);
        expect(video.play).toHaveBeenCalledTimes(2);
    });

    it('without Media Source Extensions uses native HLS synchronously, without loading hls.js', () => {
        delete window.MediaSource;
        const video = fakeVideo({ nativeHls: true });

        // No await: the source must be set within the user gesture (iOS autoplay rules).
        attachVideoSource(video, { hls: 'https://cdn/master.m3u8' }, { autoplay: true });

        expect(video.src).toBe('https://cdn/master.m3u8');
        expect(video.play).toHaveBeenCalledOnce();
        expect(Hls.instances).toHaveLength(0);
    });

    it('uses native HLS (Safari) when hls.js is not supported, with MP4 as fallback', async () => {
        Hls.supported = false;
        const video = fakeVideo({ nativeHls: true });
        await attachVideoSource(video, { hls: 'https://cdn/master.m3u8', mp4: 'https://cdn/720p.mp4' });

        expect(Hls.instances).toHaveLength(0);
        expect(video.src).toBe('https://cdn/master.m3u8');

        video.dispatchEvent(new Event('error'));
        expect(video.src).toBe('https://cdn/720p.mp4');
    });

    it('does not attach anything when the video is destroyed while hls.js loads', async () => {
        const video = fakeVideo();
        const attached = attachVideoSource(video, { hls: 'https://cdn/master.m3u8' });
        destroyVideoSource(video);
        await attached;

        expect(Hls.instances).toHaveLength(0);
    });

    it('reports an unplayable video without any source', async () => {
        const onUnplayable = vi.fn();
        await attachVideoSource(fakeVideo(), {}, { onUnplayable });
        expect(onUnplayable).toHaveBeenCalledOnce();
    });
});

describe('autoplayWhileVisible', () => {
    let observerCallback;

    beforeEach(() => {
        observerCallback = null;
        window.IntersectionObserver = class {
            constructor(callback) {
                observerCallback = callback;
            }

            observe() {}

            disconnect() {
                observerCallback = null;
            }
        };
    });

    afterEach(() => {
        delete window.IntersectionObserver;
    });

    const setVisible = (visible) => observerCallback([{ isIntersecting: visible }]);

    it('plays while visible and pauses when scrolled away', () => {
        const video = fakeVideo();
        autoplayWhileVisible(video);

        setVisible(true);
        expect(video.paused).toBe(false);

        setVisible(false);
        expect(video.paused).toBe(true);

        setVisible(true);
        expect(video.play).toHaveBeenCalledTimes(2);
    });

    it("respects the visitor's own pause", () => {
        const video = fakeVideo();
        autoplayWhileVisible(video);

        setVisible(true);
        video.pause();
        setVisible(false);
        setVisible(true);

        expect(video.play).toHaveBeenCalledOnce();
    });

    it('does not restart a finished non-looping video', () => {
        const video = fakeVideo();
        autoplayWhileVisible(video);

        setVisible(true);
        video.ended = true;
        video.paused = true;
        setVisible(false);
        setVisible(true);

        expect(video.play).toHaveBeenCalledOnce();
    });

    it('stops observing when stopped', () => {
        const stop = autoplayWhileVisible(fakeVideo());
        stop();
        expect(observerCallback).toBeNull();
    });
});

describe('markUnavailable', () => {
    it('removes controls and shows the notice', () => {
        const wrapper = document.createElement('div');
        const video = fakeVideo();
        markUnavailable(wrapper, video, 'Video kann nicht abgespielt werden');

        expect(video.controls).toBe(false);
        expect(wrapper.classList.contains('scalecommerce-vo-video--unavailable')).toBe(true);
        expect(wrapper.getAttribute('data-vo-unavailable')).toBe('Video kann nicht abgespielt werden');
    });
});

describe('expected playback (#49)', () => {
    it('clears the mark at once when the own play() is rejected (autoplay blocked)', async () => {
        const { markExpectedPlayback, isExpectedPlayback } = await import(
            '../../../src/Resources/app/storefront/src/helper/attach-video-source.js'
        );
        const video = document.createElement('video');
        video.play = () => Promise.reject(new Error('NotAllowedError'));
        let visible;
        window.IntersectionObserver = class {
            constructor(callback) {
                visible = (value) => callback([{ isIntersecting: value }]);
            }

            observe() {}

            disconnect() {}
        };
        autoplayWhileVisible(video);

        visible(true);
        expect(isExpectedPlayback(video)).toBe(true);
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(isExpectedPlayback(video)).toBe(false);
        delete window.IntersectionObserver;
        expect(markExpectedPlayback).toBeTypeOf('function');
    });
});
