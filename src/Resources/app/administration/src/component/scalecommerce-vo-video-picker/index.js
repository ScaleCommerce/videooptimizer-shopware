import template from './scalecommerce-vo-video-picker.html.twig';

const { Component, Mixin } = Shopware;

Component.register('scalecommerce-vo-video-picker', {
    template,

    inject: ['scalecommerceVoApiService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        value: {
            type: Object,
            required: false,
            default: () => ({ libraryId: null, videoUuid: null }),
        },
    },

    data() {
        return {
            libraries: [],
            videos: [],
            libraryId: this.value.libraryId,
            videoUuid: this.value.videoUuid,
            uploadFile: null,
            uploadTitle: '',
            isUploading: false,
        };
    },

    computed: {
        libraryOptions() {
            return this.libraries.map((library) => ({ value: library.id, label: library.name }));
        },
        videoOptions() {
            return this.videos.map((video) => ({ value: video.uuid, label: video.title || video.uuid }));
        },
    },

    watch: {
        value: {
            handler(newValue) {
                const next = newValue || {};
                const libraryChanged = next.libraryId !== this.libraryId;
                this.libraryId = next.libraryId ?? null;
                this.videoUuid = next.videoUuid ?? null;
                if (libraryChanged) {
                    this.loadVideos();
                }
            },
        },
    },

    created() {
        this.loadLibraries();
    },

    methods: {
        async loadLibraries() {
            try {
                const response = await this.scalecommerceVoApiService.getLibraries();
                this.libraries = response.data ?? response;
                if (this.libraryId) {
                    await this.loadVideos();
                }
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },
        async loadVideos() {
            if (!this.libraryId) {
                this.videos = [];
                return;
            }
            try {
                const response = await this.scalecommerceVoApiService.getVideos(this.libraryId);
                this.videos = response.data ?? response;
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },
        onLibraryChange(value) {
            this.libraryId = value;
            this.videoUuid = null;
            this.loadVideos();
            this._emit();
        },
        onVideoChange(value) {
            this.videoUuid = value;
            this._emit();
        },
        onFileSelected(event) {
            this.uploadFile = event.target.files[0] ?? null;
        },
        async onUpload() {
            const file = this.uploadFile;
            if (!file || !this.libraryId) {
                return;
            }
            this.isUploading = true;
            try {
                const uuid = await this._uploadPresigned(this.libraryId, file, this.uploadTitle);
                this.uploadFile = null;
                this.uploadTitle = '';
                this.createNotificationSuccess({ message: this.$tc('scalecommerce-vo.list.uploadStarted') });
                await this._pollUntilReady(uuid);
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.isUploading = false;
            }
        },

        // initiate (proxy) -> PUT parts straight to storage -> complete (proxy). Returns the video uuid.
        async _uploadPresigned(libraryId, file, title) {
            const initiateResponse = await this.scalecommerceVoApiService.initiateUpload({
                libraryId,
                filename: file.name,
                contentType: file.type || 'application/octet-stream',
                fileSize: file.size,
            });
            const init = initiateResponse.data ?? initiateResponse;
            const parts = await this.scalecommerceVoApiService.uploadParts(file, init.parts, init.partSize);
            const payload = {
                libraryId,
                uuid: init.uuid,
                key: init.key,
                uploadId: init.uploadId,
                parts,
            };
            if (title) {
                payload.title = title;
            }
            const completeResponse = await this.scalecommerceVoApiService.completeUpload(payload);
            const completed = completeResponse.data ?? completeResponse;
            return completed.uuid ?? init.uuid;
        },
        _pollUntilReady(uuid, attempt = 0) {
            if (!uuid || attempt > 60) {
                return this.loadVideos();
            }
            return new Promise((resolve) => {
                window.setTimeout(async () => {
                    try {
                        const response = await this.scalecommerceVoApiService.getVideo(uuid);
                        const video = response.data ?? response;
                        if (video.status === 'ready') {
                            await this.loadVideos();
                            // Auto-select the freshly uploaded video.
                            this.videoUuid = uuid;
                            this._emit();
                            resolve();
                            return;
                        }
                    } catch (error) {
                        console.warn('[VideoOptimizer] polling uploaded video status failed, retrying', error);
                    }
                    resolve(this._pollUntilReady(uuid, attempt + 1));
                }, 5000);
            });
        },
        _emit() {
            this.$emit('update:value', { libraryId: this.libraryId, videoUuid: this.videoUuid });
        },
        _errorText(error) {
            return error?.response?.data?.errors?.[0]?.detail ?? this.$tc('scalecommerce-vo.list.genericError');
        },
    },
});
