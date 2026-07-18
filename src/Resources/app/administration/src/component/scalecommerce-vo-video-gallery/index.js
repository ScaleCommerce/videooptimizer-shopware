import template from './scalecommerce-vo-video-gallery.html.twig';
import './scalecommerce-vo-video-gallery.scss';
import { parseResolution, formatDuration, orientationKey } from '../../helper/video-meta';

const { Component, Mixin } = Shopware;

Component.register('scalecommerce-vo-video-gallery', {
    template,

    inject: ['scalecommerceVoApiService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        libraryId: { type: String, required: false, default: null },
        selectedUuid: { type: String, required: false, default: null },
        mode: { type: String, required: false, default: 'select' },
    },

    emits: ['select', 'upload-complete', 'renamed', 'deleted'],

    data() {
        return {
            videos: [],
            isLoading: false,
            uploadTitle: '',
            uploadFile: null,
            isUploading: false,
        };
    },

    watch: {
        libraryId() {
            this.loadVideos();
        },
    },

    computed: {
        selectedVideo() {
            return this.videos.find((video) => video.uuid === this.selectedUuid) ?? null;
        },
    },

    created() {
        this.loadVideos();
    },

    methods: {
        dimensions(video) {
            const parsed = parseResolution(video.resolution);
            return parsed ? `${parsed.width}×${parsed.height}` : null;
        },

        duration(video) {
            if (video.duration === null || video.duration === undefined) {
                return null;
            }
            return formatDuration(video.duration);
        },

        orientationLabel(video) {
            const key = orientationKey(video.resolution);
            if (!key) {
                return null;
            }
            const labels = {
                portrait: 'scalecommerce-vo.gallery.orientationPortrait',
                landscape: 'scalecommerce-vo.gallery.orientationLandscape',
                square: 'scalecommerce-vo.gallery.orientationSquare',
            };
            return this.$tc(labels[key]);
        },

        metaLine(video) {
            return [this.dimensions(video), this.duration(video), this.orientationLabel(video)]
                .filter((part) => part !== null)
                .join(' · ');
        },

        async loadVideos() {
            if (!this.libraryId) {
                this.videos = [];
                return;
            }
            this.isLoading = true;
            try {
                const response = await this.scalecommerceVoApiService.getVideos(this.libraryId);
                this.videos = response.data ?? response;
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.isLoading = false;
            }
        },

        posterFor(video) {
            return video.poster_url || video.thumbnail_url || null;
        },

        statusVariant(status) {
            if (status === 'ready') return 'success';
            if (status === 'failed') return 'danger';
            return 'warning';
        },

        onSelect(video) {
            if (this.mode !== 'select' || video.status !== 'ready') {
                return;
            }
            this.$emit('select', video.uuid);
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
                this.createNotificationSuccess({ message: this.$tc('scalecommerce-vo.gallery.uploadStarted') });
                await this._pollUntilReady(uuid);
                this.$emit('upload-complete', uuid);
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
            const payload = { libraryId, uuid: init.uuid, key: init.key, uploadId: init.uploadId, parts };
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

        async onRename(video) {
            const title = window.prompt(this.$tc('scalecommerce-vo.gallery.renamePrompt'), video.title);
            if (!title) {
                return;
            }
            try {
                await this.scalecommerceVoApiService.updateVideo(video.uuid, { title });
                await this.loadVideos();
                this.$emit('renamed', video.uuid);
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        async onDelete(video) {
            try {
                await this.scalecommerceVoApiService.deleteVideo(video.uuid);
                await this.loadVideos();
                this.$emit('deleted', video.uuid);
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        _errorText(error) {
            return error?.response?.data?.errors?.[0]?.detail ?? this.$tc('scalecommerce-vo.gallery.genericError');
        },
    },
});
