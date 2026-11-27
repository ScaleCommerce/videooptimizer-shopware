import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadComponent } from './support/load-component.js';

describe('video detail playback right after ready', () => {
    let detail;
    beforeAll(async () => {
        detail = await loadComponent(() => import('../../../src/Resources/app/administration/src/component/scalecommerce-vo-video-detail/index.js'));
    });

    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    function context(getPlayback) {
        const ctx = detail.context({
            uuid: 'vid', video: { status: 'ready' }, scalecommerceVoApiService: { getPlayback },
            $nextTick: () => Promise.resolve(), _attachPlayer: vi.fn(),
        });
        return ctx;
    }

    it('retries three times, fresh from the second call, before giving up', async () => {
        const getPlayback = vi.fn().mockResolvedValue({ data: { hls: null, mp4: null } });
        const ctx = context(getPlayback);

        await ctx._loadPlayback();
        expect(ctx.playerError).toBe(false);
        for (let i = 0; i < 3; i += 1) {
            await vi.advanceTimersByTimeAsync(5000);
        }

        expect(getPlayback).toHaveBeenCalledTimes(4);
        expect(getPlayback.mock.calls.map((call) => call[1].fresh)).toEqual([false, true, true, true]);
        expect(ctx.playerError).toBe(true);
    });

    it('plays as soon as the sources are there', async () => {
        const getPlayback = vi.fn()
            .mockResolvedValueOnce({ data: { hls: null, mp4: null } })
            .mockResolvedValueOnce({ data: { hls: 'https://cdn/m.m3u8', mp4: null } });
        const ctx = context(getPlayback);

        await ctx._loadPlayback();
        await vi.advanceTimersByTimeAsync(5000);

        expect(ctx.playback.hls).toBe('https://cdn/m.m3u8');
        expect(ctx._attachPlayer).toHaveBeenCalledOnce();
        expect(ctx.playerError).toBe(false);
    });

    it('retries after a failed lookup too and stops when the dialog closed', async () => {
        const getPlayback = vi.fn().mockRejectedValue(new Error('x'));
        const ctx = context(getPlayback);

        await ctx._loadPlayback();
        ctx._unmounted = true;
        await vi.advanceTimersByTimeAsync(5000);

        expect(getPlayback).toHaveBeenCalledTimes(2);
        expect(ctx.playerError).toBe(false);
    });
});

describe('new library form', () => {
    let list;
    beforeAll(async () => {
        list = await loadComponent(() => import('../../../src/Resources/app/administration/src/module/scalecommerce-vo/page/scalecommerce-vo-list/index.js'));
    });

    const encodings = {
        codecs: [{ key: 'h264', access: 'included', available: true }, { key: 'av1', access: 'addon', available: false }],
        resolutions: [
            { key: '360p', access: 'included', available: true },
            { key: '720p', access: 'included', available: true },
            { key: '4k', access: 'addon', available: true },
        ],
    };

    it('preselects the included, available options', () => {
        const ctx = list.context({ encodings });

        ctx.onNewLibrary();

        expect(ctx.selectedCodecs).toEqual(['h264']);
        expect(ctx.selectedResolutions).toEqual(['360p', '720p']);
    });

    it('preselects once the options arrive when the form was opened first', async () => {
        const ctx = list.context({
            mode: 'create', selectedCodecs: [], selectedResolutions: [],
            scalecommerceVoApiService: { getEncodings: vi.fn().mockResolvedValue({ data: encodings }) },
        });

        await ctx.loadEncodings();

        expect(ctx.selectedResolutions).toEqual(['360p', '720p']);
    });
});
