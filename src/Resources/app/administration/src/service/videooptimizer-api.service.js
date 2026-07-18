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
