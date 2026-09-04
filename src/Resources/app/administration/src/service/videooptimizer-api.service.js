const { ApiService } = Shopware.Classes;

export default class VideoOptimizerApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/scalecommerce-vo') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'scalecommerceVoApiService';
    }

    getLibraries() {
        return this.httpClient
            .get(`${this.getApiBasePath()}/libraries`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    getEncodings() {
        return this.httpClient
            .get(`${this.getApiBasePath()}/encodings`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    createLibrary(payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/libraries`, payload, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    updateLibrary(id, payload) {
        return this.httpClient
            .patch(`${this.getApiBasePath()}/libraries/${id}`, payload, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    deleteLibrary(id) {
        return this.httpClient
            .delete(`${this.getApiBasePath()}/libraries/${id}`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    getVideos(libraryId) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/libraries/${libraryId}/videos`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    getVideo(uuid) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/videos/${uuid}`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    getAllVideos(libraryId) {
        const query = libraryId ? `?libraryId=${encodeURIComponent(libraryId)}` : '';
        return this.httpClient
            .get(`${this.getApiBasePath()}/videos${query}`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    getThumbnails(uuid) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/videos/${uuid}/thumbnails`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    // Fetches a frame image as a blob via our proxy (the frame URLs are public per the current
    // OpenAPI spec, but the admin SPA should not fetch cross-origin images with unknown CORS);
    // callers create an object URL for the <img>.
    getThumbnailImage(uuid, index) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/videos/${uuid}/thumbnails/${index}`, {
                headers: this.getBasicHeaders(),
                responseType: 'blob',
            })
            .then((response) => response.data);
    }

    selectThumbnail(uuid, index) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/videos/${uuid}/thumbnail`, { thumbnailIndex: index }, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    initiatePosterUpload(uuid, payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/videos/${uuid}/poster/initiate`, payload, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    // Single presigned PUT straight to storage (poster is not multipart). No auth header.
    uploadPoster(uploadUrl, blob) {
        return fetch(uploadUrl, { method: 'PUT', body: blob, headers: { 'Content-Type': blob.type } }).then((response) => {
            if (!response.ok) {
                throw new Error(`Poster upload failed (${response.status})`);
            }
        });
    }

    completePosterUpload(uuid, key) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/videos/${uuid}/poster/complete`, { key }, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    selectPoster(uuid, payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/videos/${uuid}/poster/select`, payload, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    deletePoster(uuid) {
        return this.httpClient
            .delete(`${this.getApiBasePath()}/videos/${uuid}/poster`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    initiateUpload(payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/videos/upload/initiate`, payload, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    completeUpload(payload) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/videos/upload/complete`, payload, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    // PUTs each file part directly to its presigned storage URL and collects the ETags the
    // complete step needs. Cross-origin, so no auth header; the bucket must expose ETag via CORS.
    uploadParts(file, parts, partSize) {
        return parts.reduce(
            (chain, part) => chain.then((etags) => {
                const start = (part.partNumber - 1) * partSize;
                const blob = file.slice(start, start + partSize);
                return fetch(part.url, { method: 'PUT', body: blob }).then((response) => {
                    if (!response.ok) {
                        throw new Error(`Part upload failed (${response.status})`);
                    }
                    const etag = response.headers.get('ETag');
                    if (!etag) {
                        throw new Error('Missing ETag on uploaded part - check bucket CORS (expose ETag).');
                    }
                    etags.push({ partNumber: part.partNumber, etag });
                    return etags;
                });
            }),
            Promise.resolve([]),
        );
    }

    updateVideo(uuid, payload) {
        return this.httpClient
            .patch(`${this.getApiBasePath()}/videos/${uuid}`, payload, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    deleteVideo(uuid) {
        return this.httpClient
            .delete(`${this.getApiBasePath()}/videos/${uuid}`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }
}
