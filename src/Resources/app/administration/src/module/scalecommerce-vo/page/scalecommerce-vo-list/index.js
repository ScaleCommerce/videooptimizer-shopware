import template from './scalecommerce-vo-list.html.twig';

const { Component, Mixin } = Shopware;

Component.register('scalecommerce-vo-list', {
    template,

    inject: ['scalecommerceVoApiService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            libraries: [],
            selectedLibraryId: null,
            videos: [],
            uploadTitle: '',
            uploadFile: null,
        };
    },

    computed: {
        libraryOptions() {
            return this.libraries.map((library) => ({ value: library.id, label: library.name }));
        },
    },

    created() {
        this.loadLibraries();
    },

    methods: {
        async loadLibraries() {
            this.isLoading = true;
            try {
                const response = await this.scalecommerceVoApiService.getLibraries();
                this.libraries = response.data ?? response;
                if (this.libraries.length && !this.selectedLibraryId) {
                    this.selectedLibraryId = this.libraries[0].id;
                    await this.loadVideos();
                }
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.isLoading = false;
            }
        },

        async loadVideos() {
            if (!this.selectedLibraryId) {
                return;
            }
            this.isLoading = true;
            try {
                const response = await this.scalecommerceVoApiService.getVideos(this.selectedLibraryId);
                this.videos = response.data ?? response;
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.isLoading = false;
            }
        },

        async onCreateLibrary(name) {
            if (!name) {
                return;
            }
            try {
                await this.scalecommerceVoApiService.createLibrary({ name });
                await this.loadLibraries();
                this.createNotificationSuccess({ message: this.$tc('scalecommerce-vo.list.libraryCreated') });
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        async onDeleteLibrary(id) {
            try {
                await this.scalecommerceVoApiService.deleteLibrary(id);
                this.selectedLibraryId = null;
                await this.loadLibraries();
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        onFileSelected(event) {
            this.uploadFile = event.target.files[0] ?? null;
        },

        async onUpload() {
            if (!this.uploadFile || !this.selectedLibraryId) {
                return;
            }
            this.isLoading = true;
            try {
                const response = await this.scalecommerceVoApiService.uploadVideo(
                    this.selectedLibraryId,
                    this.uploadFile,
                    this.uploadTitle,
                );
                const uploaded = response.data ?? response;
                this.uploadFile = null;
                this.uploadTitle = '';
                this.createNotificationSuccess({ message: this.$tc('scalecommerce-vo.list.uploadStarted') });
                this._pollUntilReady(uploaded.uuid);
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.isLoading = false;
            }
        },

        _pollUntilReady(uuid, attempt = 0) {
            if (!uuid || attempt > 60) {
                this.loadVideos();
                return;
            }
            window.setTimeout(async () => {
                try {
                    const response = await this.scalecommerceVoApiService.getVideo(uuid);
                    const video = response.data ?? response;
                    if (video.status === 'ready') {
                        this.loadVideos();
                        return;
                    }
                } catch (error) {
                    console.warn('[VideoOptimizer] polling video status failed, retrying', error);
                }
                this._pollUntilReady(uuid, attempt + 1);
            }, 5000);
        },

        async onDeleteVideo(uuid) {
            try {
                await this.scalecommerceVoApiService.deleteVideo(uuid);
                await this.loadVideos();
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        async onRenameVideo(uuid, title) {
            if (!title) {
                return;
            }
            try {
                await this.scalecommerceVoApiService.updateVideo(uuid, { title });
                await this.loadVideos();
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        _errorText(error) {
            return error?.response?.data?.errors?.[0]?.detail ?? this.$tc('scalecommerce-vo.list.genericError');
        },
    },
});
