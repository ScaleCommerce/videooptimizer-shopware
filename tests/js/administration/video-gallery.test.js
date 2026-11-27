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
