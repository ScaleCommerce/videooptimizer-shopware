import template from './vo-video-picker.html.twig';

const { Component, Mixin } = Shopware;

Component.register('vo-video-picker', {
    template,

    inject: ['videoOptimizerApiService'],

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
                const response = await this.videoOptimizerApiService.getLibraries();
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
                const response = await this.videoOptimizerApiService.getVideos(this.libraryId);
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
            if (!this.uploadFile || !this.libraryId) {
                return;
            }
            this.isUploading = true;
            try {
                const response = await this.videoOptimizerApiService.uploadVideo(
                    this.libraryId,
                    this.uploadFile,
                    this.uploadTitle,
                );
                const uploaded = response.data ?? response;
                this.uploadFile = null;
                this.uploadTitle = '';
                this.createNotificationSuccess({ message: this.$tc('vo-media.list.uploadStarted') });
                await this._pollUntilReady(uploaded.uuid);
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.isUploading = false;
            }
        },
        _pollUntilReady(uuid, attempt = 0) {
            if (!uuid || attempt > 60) {
                return this.loadVideos();
            }
            return new Promise((resolve) => {
                window.setTimeout(async () => {
                    try {
                        const response = await this.videoOptimizerApiService.getVideo(uuid);
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
            return error?.response?.data?.errors?.[0]?.detail ?? this.$tc('vo-media.list.genericError');
        },
    },
});
