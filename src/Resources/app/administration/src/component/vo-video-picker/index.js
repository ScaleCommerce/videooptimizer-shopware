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
        _emit() {
            this.$emit('update:value', { libraryId: this.libraryId, videoUuid: this.videoUuid });
        },
        _errorText(error) {
            return error?.response?.data?.errors?.[0]?.detail ?? this.$tc('vo-media.list.genericError');
        },
    },
});
