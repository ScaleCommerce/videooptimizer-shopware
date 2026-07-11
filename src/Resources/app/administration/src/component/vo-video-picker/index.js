import template from './vo-video-picker.html.twig';

const { Component } = Shopware;

Component.register('vo-video-picker', {
    template,

    inject: ['videoOptimizerApiService'],

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

    created() {
        this.loadLibraries();
    },

    methods: {
        async loadLibraries() {
            const response = await this.videoOptimizerApiService.getLibraries();
            this.libraries = response.data ?? response;
            if (this.libraryId) {
                await this.loadVideos();
            }
        },
        async loadVideos() {
            if (!this.libraryId) {
                this.videos = [];
                return;
            }
            const response = await this.videoOptimizerApiService.getVideos(this.libraryId);
            this.videos = response.data ?? response;
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
    },
});
