import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let VideoOptimizerApiService;

beforeAll(async () => {
    // Minimal stand-in for Shopware's ApiService, which the service reads from the global at import.
    globalThis.Shopware = {
        Classes: {
            ApiService: class {
                constructor(httpClient, loginService, apiEndpoint) {
                    this.httpClient = httpClient;
                    this.apiEndpoint = apiEndpoint;
                }

                getApiBasePath() {
                    return `/api/${this.apiEndpoint}`;
                }

                getBasicHeaders() {
                    return { Authorization: 'Bearer test' };
                }

                static handleResponse(response) {
                    return response.data;
                }
            },
        },
    };
    ({ default: VideoOptimizerApiService } = await import(
        '../../../src/Resources/app/administration/src/service/videooptimizer-api.service.js'
    ));
});

const response = ({ ok = true, status = 200, etag = '"e"' } = {}) => ({
    ok,
    status,
    headers: { get: (name) => (name === 'ETag' ? etag : null) },
});

describe('VideoOptimizerApiService', () => {
    let service;
    let httpClient;

    beforeEach(() => {
        httpClient = { get: vi.fn(() => Promise.resolve({ data: { data: [] } })) };
        service = new VideoOptimizerApiService(httpClient, {});
    });

    afterEach(() => {
        vi.useRealTimers();
        delete globalThis.fetch;
    });

    it('requests one page of a library when limit/cursor are given', async () => {
        await service.getVideos('lib-1', { limit: 60, cursor: 'c2' });
        expect(httpClient.get).toHaveBeenCalledWith(
            '/api/_action/scalecommerce-vo/libraries/lib-1/videos',
            { headers: { Authorization: 'Bearer test' }, params: { limit: 60, cursor: 'c2' } }
        );
    });

    it('requests the full list without paging options', async () => {
        await service.getVideos('lib-1');
        expect(httpClient.get.mock.calls[0][1].params).toEqual({});
    });

    it('sends force only when a forced reprocess is requested', async () => {
        httpClient.post = vi.fn(() => Promise.resolve({ data: { data: { queued: 2 } } }));
        await service.reprocessLibrary('lib-1');
        await service.reprocessLibrary('lib-1', { force: true });

        expect(httpClient.post.mock.calls[0][1]).toEqual({});
        expect(httpClient.post.mock.calls[1][1]).toEqual({ force: true });
    });

    it('uploads parts in order, reports progress and returns the ETags', async () => {
        globalThis.fetch = vi.fn(() => Promise.resolve(response()));
        const progress = [];
        const parts = [1, 2, 3].map((partNumber) => ({ partNumber, url: `https://storage/${partNumber}` }));

        const etags = await service.uploadParts(new Blob(['abcdef']), parts, 2, {
            onProgress: (done, total) => progress.push(`${done}/${total}`),
        });

        expect(etags).toEqual(parts.map(({ partNumber }) => ({ partNumber, etag: '"e"' })));
        expect(progress).toEqual(['1/3', '2/3', '3/3']);
        expect(globalThis.fetch.mock.calls.map(([url]) => url)).toEqual(parts.map(({ url }) => url));
    });

    it('retries a failing part with backoff', async () => {
        vi.useFakeTimers();
        globalThis.fetch = vi
            .fn()
            .mockRejectedValueOnce(new TypeError('network'))
            .mockResolvedValueOnce(response({ ok: false, status: 503 }))
            .mockResolvedValueOnce(response());

        const upload = service.uploadParts(new Blob(['ab']), [{ partNumber: 1, url: 'https://storage/1' }], 2);
        await vi.runAllTimersAsync();

        await expect(upload).resolves.toEqual([{ partNumber: 1, etag: '"e"' }]);
        expect(globalThis.fetch).toHaveBeenCalledTimes(3);
    });

    it('gives up after three attempts', async () => {
        vi.useFakeTimers();
        globalThis.fetch = vi.fn(() => Promise.resolve(response({ ok: false, status: 500 })));

        const upload = service.uploadParts(new Blob(['ab']), [{ partNumber: 1, url: 'https://storage/1' }], 2);
        const result = expect(upload).rejects.toThrow('Part upload failed (500)');
        await vi.runAllTimersAsync();

        await result;
        expect(globalThis.fetch).toHaveBeenCalledTimes(3);
    });

    it('does not retry a missing ETag (bucket CORS configuration)', async () => {
        globalThis.fetch = vi.fn(() => Promise.resolve(response({ etag: null })));

        await expect(
            service.uploadParts(new Blob(['ab']), [{ partNumber: 1, url: 'https://storage/1' }], 2)
        ).rejects.toThrow('Missing ETag');
        expect(globalThis.fetch).toHaveBeenCalledOnce();
    });

    it('does not retry after the upload was cancelled', async () => {
        const controller = new AbortController();
        globalThis.fetch = vi.fn(() => {
            controller.abort();
            return Promise.reject(new DOMException('aborted', 'AbortError'));
        });

        await expect(
            service.uploadParts(new Blob(['ab']), [{ partNumber: 1, url: 'https://storage/1' }], 2, {
                signal: controller.signal,
            })
        ).rejects.toThrow('aborted');
        expect(globalThis.fetch).toHaveBeenCalledOnce();
    });
});

describe('subtitle calls (#36)', () => {
    let service;
    let httpClient;

    beforeEach(() => {
        httpClient = {
            get: vi.fn(() => Promise.resolve({ data: { data: [] } })),
            post: vi.fn(() => Promise.resolve({ data: { data: {} } })),
            delete: vi.fn(() => Promise.resolve({ data: {} })),
        };
        service = new VideoOptimizerApiService(httpClient, {});
    });

    it('encodes the language in the path', async () => {
        await service.deleteSubtitle('vid', 'de/../x');
        expect(httpClient.delete.mock.calls[0][0]).toBe('/api/_action/scalecommerce-vo/videos/vid/subtitles/de%2F..%2Fx');
        await service.deleteSubtitleLanguage('en-US');
        expect(httpClient.delete.mock.calls[1][0]).toBe('/api/_action/scalecommerce-vo/subtitle-languages/en-US');
    });

    it('uploads a subtitle as multipart form data', async () => {
        const file = new File(['WEBVTT'], 'de.vtt');
        await service.uploadSubtitle('vid', { file, language: 'de', kind: 'captions', isDefault: true });

        const [url, form] = httpClient.post.mock.calls[0];
        expect(url).toBe('/api/_action/scalecommerce-vo/videos/vid/subtitles');
        expect(form.get('language')).toBe('de');
        expect(form.get('kind')).toBe('captions');
        expect(form.get('is_default')).toBe('true');
        expect(form.get('file').name).toBe('de.vtt');
    });

    it('does not force a JSON Content-Type on the multipart body', async () => {
        await service.uploadSubtitle('vid', { file: new File(['x'], 'a.vtt'), language: 'de' });

        const [, form, config] = httpClient.post.mock.calls[0];
        expect(form).toBeInstanceOf(FormData);
        expect(config.headers['Content-Type']).toBe('multipart/form-data');
        expect(config.headers['Content-Type']).not.toMatch(/json/);
    });

    it('sends kind and default flag only when set', async () => {
        await service.uploadSubtitle('vid', { file: new File(['x'], 'a.srt'), language: 'de', kind: null, isDefault: null });

        const form = httpClient.post.mock.calls[0][1];
        expect(form.has('kind')).toBe(false);
        expect(form.has('is_default')).toBe(false);
    });

    it('downloads a subtitle as blob', async () => {
        httpClient.get.mockResolvedValue({ data: 'blob' });
        expect(await service.downloadSubtitle('vid', 'de')).toBe('blob');
        expect(httpClient.get.mock.calls[0][1].responseType).toBe('blob');
    });
});

describe('tag and metadata calls (#37)', () => {
    it('sends the filters as query parameters', async () => {
        const httpClient = { get: vi.fn(() => Promise.resolve({ data: { data: [] } })) };
        const service = new VideoOptimizerApiService(httpClient, {});

        await service.getVideos('lib', { limit: 60, tags: ['a', 'b'], metadata: { cms_id: '42' } });
        await service.getTags();

        expect(httpClient.get.mock.calls[0][1].params).toEqual({ limit: 60, tags: 'a,b', 'metadata[cms_id]': '42' });
        expect(httpClient.get.mock.calls[1][0]).toBe('/api/_action/scalecommerce-vo/tags');
    });
});

describe('savings call (#38)', () => {
    it('sends the period only as a pair and the scope by id', async () => {
        const httpClient = { get: vi.fn(() => Promise.resolve({ data: { data: {} } })) };
        const service = new VideoOptimizerApiService(httpClient, {});

        await service.getSavings({ from: '2026-10-01', to: '2026-10-31', libraryId: 'lib' });
        await service.getSavings({ from: '2026-10-01' });

        expect(httpClient.get.mock.calls[0][1].params).toEqual({ from: '2026-10-01', to: '2026-10-31', libraryId: 'lib' });
        expect(httpClient.get.mock.calls[1][1].params).toEqual({});
    });
});

describe('webhook info call (#39)', () => {
    it('reads the receiver info through the proxy', async () => {
        const httpClient = { get: vi.fn(() => Promise.resolve({ data: { data: { url: 'u' } } })) };
        const service = new VideoOptimizerApiService(httpClient, {});

        expect(await service.getWebhookInfo()).toEqual({ data: { url: 'u' } });
        expect(httpClient.get.mock.calls[0][0]).toBe('/api/_action/scalecommerce-vo/webhook');
    });
});

describe('playback call', () => {
    it('asks for a fresh embed only on request', async () => {
        const httpClient = { get: vi.fn(() => Promise.resolve({ data: { data: {} } })) };
        const service = new VideoOptimizerApiService(httpClient, {});

        await service.getPlayback('vid');
        await service.getPlayback('vid', { fresh: true });

        expect(httpClient.get.mock.calls[0][1].params).toEqual({});
        expect(httpClient.get.mock.calls[1][1].params).toEqual({ fresh: 1 });
    });
});
