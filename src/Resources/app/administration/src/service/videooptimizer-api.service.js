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

    uploadVideo(libraryId, file, title) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('libraryId', libraryId);
        if (title) {
            formData.append('title', title);
        }
        // getBasicHeaders() defaults Content-Type to application/json; remove it so
        // the browser sets multipart/form-data with the correct boundary, otherwise
        // the server cannot parse the file and rejects the upload.
        const headers = this.getBasicHeaders();
        delete headers['Content-Type'];

        return this.httpClient
            .post(`${this.getApiBasePath()}/videos`, formData, { headers })
            .then((response) => ApiService.handleResponse(response));
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
