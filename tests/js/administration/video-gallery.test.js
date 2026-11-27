import { beforeAll, describe, expect, it, vi } from 'vitest';
import { loadComponent } from './support/load-component.js';

let gallery;
beforeAll(async () => {
    gallery = await loadComponent(() => import('../../../src/Resources/app/administration/src/component/scalecommerce-vo-video-gallery/index.js'));
});

const video = (uuid, title = uuid) => ({ uuid, title, status: 'ready' });

function api(overrides = {}) {
    return {
        getVideos: vi.fn(),
        getVideo: vi.fn(),
        updateVideo: vi.fn(() => Promise.resolve({})),
        deleteVideo: vi.fn(() => Promise.resolve()),
        initiateUpload: vi.fn(() => Promise.resolve({ data: { uuid: 'new', key: 'k', uploadId: 'u', partSize: 5, parts: [{ partNumber: 1, url: 'https://s3/1' }] } })),
        uploadParts: vi.fn(),
        completeUpload: vi.fn(() => Promise.resolve({ data: { uuid: 'new' } })),
        ...overrides,
    };
}

describe('upload (#27)', () => {
    it('aborts the running upload when the gallery is unmounted', () => {
        const ctx = gallery.context();
        ctx._uploadAbort = new AbortController();

        gallery.definition.beforeUnmount.call(ctx);

        expect(ctx._uploadAbort.signal.aborted).toBe(true);
        expect(ctx._unmounted).toBe(true);
    });

    it('unmounting without a running upload is harmless', () => {
        const ctx = gallery.context();
        expect(() => gallery.definition.beforeUnmount.call(ctx)).not.toThrow();
    });

    it('creates no video after the gallery was closed during the transfer', async () => {
        const service = api({
            uploadParts: vi.fn((file, parts, size, { signal }) =>
                new Promise((resolve, reject) => {
                    signal.addEventListener('abort', () => reject(new DOMException('aborted', 'AbortError')));
                })
            ),
        });
        const ctx = gallery.context({
            scalecommerceVoApiService: service,
            libraryId: 'lib-1',
            uploadFile: { name: 'a.mp4', size: 3, type: 'video/mp4' },
            _watchProcessing: vi.fn(),
        });

        const upload = gallery.definition.methods.onUpload.call(ctx);
        await vi.waitFor(() => expect(service.uploadParts).toHaveBeenCalled());
        gallery.definition.beforeUnmount.call(ctx);
        await upload;

        expect(service.completeUpload).not.toHaveBeenCalled();
        expect(ctx._watchProcessing).not.toHaveBeenCalled();
        expect(ctx.createNotificationSuccess).not.toHaveBeenCalled();
    });

    it('does not complete an upload that was aborted after the last part', async () => {
        let finishParts;
        const service = api({
            // The transfer ends although the signal was aborted meanwhile (e.g. the last part was in flight).
            uploadParts: vi.fn(() => new Promise((resolve) => {
                finishParts = () => resolve([{ partNumber: 1, etag: 'e' }]);
            })),
        });
        const ctx = gallery.context({
            scalecommerceVoApiService: service,
            libraryId: 'lib-1',
            uploadFile: { name: 'a.mp4', size: 3, type: 'video/mp4' },
            _watchProcessing: vi.fn(),
        });

        const upload = gallery.definition.methods.onUpload.call(ctx);
        await vi.waitFor(() => expect(service.uploadParts).toHaveBeenCalled());
        gallery.definition.beforeUnmount.call(ctx);
        finishParts();
        await upload;

        expect(service.completeUpload).not.toHaveBeenCalled();
        expect(ctx._watchProcessing).not.toHaveBeenCalled();
    });

    it('does not announce or track a finished upload on an unmounted gallery', async () => {
        const service = api({ uploadParts: vi.fn(() => Promise.resolve([{ partNumber: 1, etag: 'e' }])) });
        const ctx = gallery.context({
            scalecommerceVoApiService: service,
            libraryId: 'lib-1',
            uploadFile: { name: 'a.mp4', size: 3, type: 'video/mp4' },
            _watchProcessing: vi.fn(),
            _unmounted: true,
        });

        await gallery.definition.methods.onUpload.call(ctx);

        expect(ctx.createNotificationSuccess).not.toHaveBeenCalled();
        expect(ctx._watchProcessing).not.toHaveBeenCalled();
    });
});

describe('changes keep the loaded pages (#30)', () => {
    const loaded = () => [video('v1'), video('v2'), video('v3')];

    it('renames in place without reloading the first page', async () => {
        const service = api();
        const ctx = gallery.context({ scalecommerceVoApiService: service, videos: loaded(), nextCursor: 'c2', loadVideos: vi.fn() });
        ctx.onRename(ctx.videos[1]);
        ctx.renameTitle = '  New  ';

        await ctx.onConfirmRename();

        expect(service.updateVideo).toHaveBeenCalledWith('v2', { title: 'New' });
        expect(ctx.loadVideos).not.toHaveBeenCalled();
        expect(ctx.videos.map((v) => v.title)).toEqual(['v1', 'New', 'v3']);
        expect(ctx.nextCursor).toBe('c2');
        expect(ctx.$emit).toHaveBeenCalledWith('renamed', 'v2');
    });

    it('removes a deleted video in place', async () => {
        const service = api();
        const ctx = gallery.context({ scalecommerceVoApiService: service, videos: loaded(), nextCursor: 'c2', loadVideos: vi.fn() });
        ctx.confirmDeleteVideo = ctx.videos[0];

        await ctx.onConfirmDelete();

        expect(ctx.loadVideos).not.toHaveBeenCalled();
        expect(ctx.videos.map((v) => v.uuid)).toEqual(['v2', 'v3']);
        expect(ctx.nextCursor).toBe('c2');
        expect(ctx.$emit).toHaveBeenCalledWith('deleted', 'v1');
    });

    it('a failed delete leaves the list alone', async () => {
        const service = api({ deleteVideo: vi.fn(() => Promise.reject(new Error('x'))) });
        const ctx = gallery.context({ scalecommerceVoApiService: service, videos: loaded() });
        ctx.confirmDeleteVideo = ctx.videos[0];

        await ctx.onConfirmDelete();

        expect(ctx.videos).toHaveLength(3);
        expect(ctx.createNotificationError).toHaveBeenCalled();
    });

    it('refreshes only the changed entry after a poster change', async () => {
        const fresh = { uuid: 'v2', title: 'v2', status: 'ready', poster_url: 'https://cdn/new.jpg' };
        const service = api({ getVideo: vi.fn(() => Promise.resolve({ data: fresh })) });
        const ctx = gallery.context({ scalecommerceVoApiService: service, videos: loaded(), detailUuid: 'v2', brokenPosters: { v2: true }, loadVideos: vi.fn() });

        await ctx.onDetailUpdated();

        expect(service.getVideo).toHaveBeenCalledWith('v2');
        expect(ctx.loadVideos).not.toHaveBeenCalled();
        expect(ctx.videos[1]).toEqual(fresh);
        expect(ctx.videos[0].uuid).toBe('v1');
        expect(ctx.brokenPosters).toEqual({});
    });

    it('keeps the list when refreshing the entry fails', async () => {
        const service = api({ getVideo: vi.fn(() => Promise.reject(new Error('x'))) });
        const ctx = gallery.context({ scalecommerceVoApiService: service, videos: loaded(), detailUuid: 'v2' });

        await ctx.onDetailUpdated();

        expect(ctx.videos).toHaveLength(3);
    });
});
