import { beforeAll, describe, expect, it, vi } from 'vitest';
import { loadComponent } from './support/load-component.js';
import elementVideo from '../../../src/Resources/app/administration/src/mixin/scalecommerce-vo-element-video.js';
import { isNotFound } from '../../../src/Resources/app/administration/src/helper/api-error.js';

const notFound = { response: { status: 404, data: { errors: [{ status: '404' }] } } };
const unavailable = { response: { status: 502, data: { errors: [{ status: '502' }] } } };

describe('videos that no longer exist (#35)', () => {
    it('recognizes a 404 only', () => {
        expect(isNotFound(notFound)).toBe(true);
        expect(isNotFound(unavailable)).toBe(false);
        expect(isNotFound(new Error('x'))).toBe(false);
        expect(isNotFound(undefined)).toBe(false);
    });

    describe('editor preview of the single-video elements', () => {
        function preview(getVideo, videoUuid = 'gone') {
            const ctx = { videoUuid, video: null, videoMissing: false, scalecommerceVoApiService: { getVideo } };
            ctx.loadVideo = elementVideo.methods.loadVideo.bind(ctx);
            return ctx;
        }

        it('flags a deleted video', async () => {
            const ctx = preview(vi.fn().mockRejectedValue(notFound));
            vi.spyOn(console, 'warn').mockImplementation(() => {});

            await ctx.loadVideo();

            expect(ctx.videoMissing).toBe(true);
            expect(ctx.video).toBeNull();
        });

        it('does not call a video missing when the API is merely unavailable', async () => {
            const ctx = preview(vi.fn().mockRejectedValue(unavailable));
            vi.spyOn(console, 'warn').mockImplementation(() => {});

            await ctx.loadVideo();

            expect(ctx.videoMissing).toBe(false);
        });

        it('clears the flag when another video is configured or the selection is removed', async () => {
            const ctx = preview(vi.fn().mockResolvedValue({ data: { uuid: 'ok' } }));
            ctx.videoMissing = true;

            await ctx.loadVideo();
            expect(ctx.videoMissing).toBe(false);

            ctx.videoMissing = true;
            ctx.videoUuid = null;
            await ctx.loadVideo();
            expect(ctx.videoMissing).toBe(false);
        });
    });

    it('ignores a slow answer for a video that was replaced meanwhile', async () => {
        let rejectOld;
        const getVideo = vi.fn((uuid) => (uuid === 'old' ? new Promise((_, reject) => { rejectOld = reject; }) : Promise.resolve({ data: { uuid } })));
        const ctx = { videoUuid: 'old', video: null, videoMissing: false, scalecommerceVoApiService: { getVideo } };
        ctx.loadVideo = elementVideo.methods.loadVideo.bind(ctx);
        vi.spyOn(console, 'warn').mockImplementation(() => {});

        const first = ctx.loadVideo();
        ctx.videoUuid = 'new';
        await ctx.loadVideo();
        rejectOld(notFound);
        await first;

        expect(ctx.video).toEqual({ uuid: 'new' });
        expect(ctx.videoMissing).toBe(false);
    });

    describe('selection in the gallery', () => {
        let gallery;
        beforeAll(async () => {
            gallery = await loadComponent(() => import('../../../src/Resources/app/administration/src/component/scalecommerce-vo-video-gallery/index.js'));
        });

        function ctx(getVideo) {
            return gallery.context({ mode: 'select', selectedUuid: 'gone', videos: [], selectedVideoRecord: null, scalecommerceVoApiService: { getVideo } });
        }

        it('flags the selected video when it was deleted', async () => {
            const context = ctx(vi.fn().mockRejectedValue(notFound));

            await context._ensureSelectedVideo();

            expect(context.selectedMissing).toBe(true);
        });

        it('does not flag it on other errors', async () => {
            const context = ctx(vi.fn().mockRejectedValue(unavailable));

            await context._ensureSelectedVideo();

            expect(context.selectedMissing).toBe(false);
        });
    });

    describe('video grid preview', () => {
        let grid;
        beforeAll(async () => {
            grid = await loadComponent(() => import('../../../src/Resources/app/administration/src/module/sw-cms/elements/scalecommerce-vo-video-grid/component/index.js'));
        });

        it('marks only the deleted items', async () => {
            vi.spyOn(console, 'warn').mockImplementation(() => {});
            const getVideo = vi.fn((uuid) => (uuid === 'gone' ? Promise.reject(notFound) : Promise.resolve({ data: { uuid } })));
            const context = grid.context({ items: [{ video: 'gone' }, { video: 'ok' }], scalecommerceVoApiService: { getVideo } });

            await context.loadVideos();

            expect(context.missing).toEqual({ gone: true });
            expect(context.videos.gone).toBeNull();
            expect(context.videos.ok).toEqual({ uuid: 'ok' });
        });
    });
});
