import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { loadComponent } from './support/load-component.js';

let detail;
beforeAll(async () => {
    detail = await loadComponent(() => import('../../../src/Resources/app/administration/src/component/scalecommerce-vo-video-detail/index.js'));
});

afterEach(() => {
    vi.unstubAllGlobals();
});

function mediaHost(type) {
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ ok: true, blob: () => Promise.resolve(new Blob(['x'], { type })) })));
}

describe('poster from the media library (#30)', () => {
    it('uses the media entity type when the host answers with a generic Content-Type', async () => {
        mediaHost('application/octet-stream');
        const ctx = detail.context({ _uploadPoster: vi.fn() });

        await ctx.onMediaSelected([{ url: 'https://shop/media/poster', mimeType: 'image/jpeg' }]);

        expect(ctx._uploadPoster).toHaveBeenCalledOnce();
        expect(ctx._uploadPoster.mock.calls[0][0].type).toBe('image/jpeg');
    });

    it('falls back to the file extension', async () => {
        mediaHost('');
        const ctx = detail.context({ _uploadPoster: vi.fn() });

        await ctx.onMediaSelected({ media: { url: 'https://shop/media/ab/poster.webp?x=1' } });

        expect(ctx._uploadPoster.mock.calls[0][0].type).toBe('image/webp');
    });

    it('keeps the fetched blob when its type is already right', async () => {
        mediaHost('image/png');
        const ctx = detail.context({ _uploadPoster: vi.fn() });

        await ctx.onMediaSelected([{ url: 'https://shop/media/poster.png', mimeType: 'image/png' }]);

        expect(ctx._uploadPoster.mock.calls[0][0].type).toBe('image/png');
    });

    it('still rejects an unsupported image', async () => {
        const ctx = detail.context();
        await ctx._uploadPoster(new Blob(['x'], { type: 'image/gif' }));

        expect(ctx.createNotificationError).toHaveBeenCalledOnce();
    });
});
