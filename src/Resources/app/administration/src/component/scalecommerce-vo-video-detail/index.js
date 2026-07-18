import template from './scalecommerce-vo-video-detail.html.twig';
import './scalecommerce-vo-video-detail.scss';
import { parseResolution, formatDuration, orientationKey } from '../../helper/video-meta';

const { Component, Mixin } = Shopware;

Component.register('scalecommerce-vo-video-detail', {
    template,

    inject: ['scalecommerceVoApiService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        uuid: { type: String, required: true },
    },

    data() {
        return {
            video: null,
            thumbnails: [],
            frameSrcById: {},
            busy: false,
            statusText: '',
            mediaModalOpen: false,
            cacheBust: 1,
        };
    },

    computed: {
        posterSrc() {
            const url = this.video?.poster_url;
            if (!url) {
                return null;
            }
            return url + (url.indexOf('?') === -1 ? '?' : '&') + `_=${this.cacheBust}`;
        },
        hasCustomPoster() {
            return this.video?.poster?.source === 'custom';
        },
        metaLine() {
            if (!this.video) {
                return null;
            }
            const parts = [];
            const res = parseResolution(this.video.resolution);
            if (res) {
                parts.push(`${res.width}×${res.height}`);
            }
            if (this.video.duration !== null && this.video.duration !== undefined) {
                parts.push(formatDuration(this.video.duration));
            }
            const key = orientationKey(this.video.resolution);
            if (key) {
                parts.push(this.$tc(`scalecommerce-vo.detail.orientation_${key}`));
            }
            if (this.video.status) {
                parts.push(this.video.status);
            }
            return parts.join(' · ');
        },
    },

    created() {
        this._load();
    },

    beforeUnmount() {
        Object.values(this.frameSrcById).forEach((src) => URL.revokeObjectURL(src));
    },

    methods: {
        async _load() {
            try {
                const videoResponse = await this.scalecommerceVoApiService.getVideo(this.uuid);
                this.video = videoResponse.data ?? videoResponse;
                const thumbsResponse = await this.scalecommerceVoApiService.getThumbnails(this.uuid);
                const thumbs = (thumbsResponse.data ?? thumbsResponse).thumbnails ?? [];
                this.thumbnails = thumbs;
                thumbs.forEach((thumb) => this._loadFrame(thumb.index));
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        async _loadFrame(index) {
            try {
                const blob = await this.scalecommerceVoApiService.getThumbnailImage(this.uuid, index);
                this.frameSrcById = { ...this.frameSrcById, [index]: URL.createObjectURL(blob) };
            } catch (error) {
                // A single frame failing is non-fatal; leave a placeholder.
                console.warn('[VideoOptimizer] failed to load thumbnail frame', index, error);
            }
        },

        _pollPoster(predicate, attempt = 0) {
            if (attempt > 40) {
                return Promise.resolve();
            }
            return new Promise((resolve) => {
                window.setTimeout(async () => {
                    try {
                        const response = await this.scalecommerceVoApiService.getVideo(this.uuid);
                        const video = response.data ?? response;
                        if (predicate(video)) {
                            this.video = video;
                            this.cacheBust += 1;
                            resolve();
                            return;
                        }
                    } catch (error) {
                        console.warn('[VideoOptimizer] poster poll failed, retrying', error);
                    }
                    resolve(this._pollPoster(predicate, attempt + 1));
                }, 2000);
            });
        },

        async onPickFrame(index) {
            if (this.busy) {
                return;
            }
            this.busy = true;
            this.statusText = this.$tc('scalecommerce-vo.detail.applying');
            try {
                await this.scalecommerceVoApiService.selectThumbnail(this.uuid, index);
                await this._pollPoster((video) => video.poster?.source === 'thumbnail');
                this.$emit('updated');
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.busy = false;
                this.statusText = '';
            }
        },

        onFileSelected(event) {
            const file = event.target.files[0];
            if (file) {
                this._uploadPoster(file);
            }
        },

        async _uploadPoster(blob) {
            this.busy = true;
            this.statusText = this.$tc('scalecommerce-vo.detail.uploading');
            try {
                const initResponse = await this.scalecommerceVoApiService.initiatePosterUpload(this.uuid, {
                    contentType: blob.type,
                    fileSize: blob.size,
                });
                const init = initResponse.data ?? initResponse;
                await this.scalecommerceVoApiService.uploadPoster(init.uploadUrl, blob);
                await this.scalecommerceVoApiService.completePosterUpload(this.uuid, init.key);
                this.statusText = this.$tc('scalecommerce-vo.detail.processing');
                await this._pollPoster((video) => ['ready', 'failed'].includes(video.poster?.custom_status));
                if (this.video.poster?.custom_status === 'failed') {
                    throw new Error(this.$tc('scalecommerce-vo.detail.posterFailed'));
                }
                await this.scalecommerceVoApiService.selectPoster(this.uuid, { source: 'custom' });
                await this._pollPoster((video) => video.poster?.source === 'custom');
                this.createNotificationSuccess({ message: this.$tc('scalecommerce-vo.detail.posterUpdated') });
                this.$emit('updated');
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.busy = false;
                this.statusText = '';
            }
        },

        async onRemoveCustomPoster() {
            this.busy = true;
            try {
                await this.scalecommerceVoApiService.deletePoster(this.uuid);
                await this._pollPoster((video) => video.poster?.source !== 'custom');
                this.$emit('updated');
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.busy = false;
            }
        },

        onMediaModalClose() {
            this.mediaModalOpen = false;
        },

        async onMediaSelected(selection) {
            this.mediaModalOpen = false;
            const item = Array.isArray(selection) ? selection[0] : selection;
            const url = item?.url ?? item?.media?.url;
            if (!url) {
                return;
            }
            try {
                const blob = await fetch(url).then((response) => response.blob());
                await this._uploadPoster(blob);
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        onClose() {
            this.$emit('close');
        },

        _errorText(error) {
            return error?.response?.data?.errors?.[0]?.detail ?? this.$tc('scalecommerce-vo.detail.genericError');
        },
    },
});
