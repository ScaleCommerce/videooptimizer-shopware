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
