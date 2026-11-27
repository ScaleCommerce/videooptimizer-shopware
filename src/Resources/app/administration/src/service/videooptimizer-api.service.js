const { ApiService } = Shopware.Classes;

export default class VideoOptimizerApiService extends ApiService {
    constructor(
        httpClient,
        loginService,
        apiEndpoint = '_action/scalecommerce-vo'
    ) {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'scalecommerceVoApiService';
    }

    getLibraries() {
        return this.httpClient
            .get(`${this.getApiBasePath()}/libraries`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    getEncodings() {
        return this.httpClient
            .get(`${this.getApiBasePath()}/encodings`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    createLibrary(payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/libraries`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    updateLibrary(id, payload) {
        return this.httpClient
            .patch(
                `${this.getApiBasePath()}/libraries/${encodeURIComponent(id)}`,
                payload,
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    deleteLibrary(id) {
        return this.httpClient
            .delete(
                `${this.getApiBasePath()}/libraries/${encodeURIComponent(id)}`,
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    reprocessLibrary(id) {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/libraries/${encodeURIComponent(id)}/reprocess`,
                {},
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    /**
     * With `limit` (and `cursor`), one page is returned as { data, meta: { next_cursor, has_more } };
     * without, the full list.
     */
    getVideos(libraryId, { limit, cursor } = {}) {
        const params = {};
        if (limit) {
            params.limit = limit;
        }
        if (cursor) {
            params.cursor = cursor;
        }
        return this.httpClient
            .get(
                `${this.getApiBasePath()}/libraries/${encodeURIComponent(libraryId)}/videos`,
                { headers: this.getBasicHeaders(), params }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    getVideo(uuid) {
        return this.httpClient
            .get(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}`,
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    getPlayback(uuid) {
        return this.httpClient
            .get(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}/playback`,
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    getAllVideos(libraryId) {
        const query = libraryId
            ? `?libraryId=${encodeURIComponent(libraryId)}`
            : '';
        return this.httpClient
            .get(`${this.getApiBasePath()}/videos${query}`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    getThumbnails(uuid) {
        return this.httpClient
            .get(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}/thumbnails`,
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    // Fetches a frame image as a blob via our proxy (the frame URLs are public per the current
    // OpenAPI spec, but the admin SPA should not fetch cross-origin images with unknown CORS);
    // callers create an object URL for the <img>.
    getThumbnailImage(uuid, index) {
        return this.httpClient
            .get(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}/thumbnails/${encodeURIComponent(index)}`,
                {
                    headers: this.getBasicHeaders(),
                    responseType: 'blob',
                }
            )
            .then((response) => response.data);
    }

    selectThumbnail(uuid, index) {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}/thumbnail`,
                { thumbnailIndex: index },
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    initiatePosterUpload(uuid, payload) {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}/poster/initiate`,
                payload,
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    // Single presigned PUT straight to storage (poster is not multipart). No auth header.
    uploadPoster(uploadUrl, blob) {
        return fetch(uploadUrl, {
            method: 'PUT',
            body: blob,
            headers: { 'Content-Type': blob.type },
        }).then((response) => {
            if (!response.ok) {
                throw new Error(`Poster upload failed (${response.status})`);
            }
        });
    }

    completePosterUpload(uuid, key) {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}/poster/complete`,
                { key },
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    selectPoster(uuid, payload) {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}/poster/select`,
                payload,
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    deletePoster(uuid) {
        return this.httpClient
            .delete(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}/poster`,
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    initiateUpload(payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/videos/upload/initiate`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    completeUpload(payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/videos/upload/complete`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    ingestVideoUrl(payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/videos/ingest`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    // PUTs each file part directly to its presigned storage URL and collects the ETags the
    // complete step needs. Cross-origin, so no auth header; the bucket must expose ETag via CORS.
    /**
     * Uploads the parts one after another straight to storage (presigned URLs, no auth header).
     * Each part is retried up to three times with backoff; `signal` cancels the upload and
     * `onProgress(done, total)` reports finished parts. The API has no endpoint to abort a
     * multipart upload; storage removes incomplete uploads via its lifecycle rule.
     */
    async uploadParts(file, parts, partSize, { signal, onProgress } = {}) {
        const etags = [];
        for (const [index, part] of parts.entries()) {
            const start = (part.partNumber - 1) * partSize;
            const blob = file.slice(start, start + partSize);
            const etag = await this._putPart(part.url, blob, signal);
            etags.push({ partNumber: part.partNumber, etag });
            if (onProgress) {
                onProgress(index + 1, parts.length);
            }
        }
        return etags;
    }

    async _putPart(url, blob, signal, attempts = 3) {
        for (let attempt = 1; ; attempt += 1) {
            try {
                const response = await fetch(url, {
                    method: 'PUT',
                    body: blob,
                    signal,
                });
                if (!response.ok) {
                    throw new Error(`Part upload failed (${response.status})`);
                }
                const etag = response.headers.get('ETag');
                if (!etag) {
                    const error = new Error(
                        'Missing ETag on uploaded part - check bucket CORS (expose ETag).'
                    );
                    // A configuration problem; retrying won't help.
                    error.noRetry = true;
                    throw error;
                }
                return etag;
            } catch (error) {
                if (signal?.aborted || error.noRetry || attempt >= attempts) {
                    throw error;
                }
                await new Promise((resolve) => {
                    window.setTimeout(resolve, 1000 * 2 ** (attempt - 1));
                });
            }
        }
    }

    updateVideo(uuid, payload) {
        return this.httpClient
            .patch(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}`,
                payload,
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    deleteVideo(uuid) {
        return this.httpClient
            .delete(
                `${this.getApiBasePath()}/videos/${encodeURIComponent(uuid)}`,
                { headers: this.getBasicHeaders() }
            )
            .then((response) => ApiService.handleResponse(response));
    }
}
