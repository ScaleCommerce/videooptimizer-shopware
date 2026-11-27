import template from './scalecommerce-vo-video-detail.html.twig';
import './scalecommerce-vo-video-detail.scss';
import {
    parseResolution,
    formatDuration,
    orientationKey,
} from '../../helper/video-meta';

// Mirrors the poster upload proxy's allowlist (VideoOptimizerAdminController).
const POSTER_CONTENT_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const { Component, Mixin } = Shopware;

Component.register('scalecommerce-vo-video-detail', {
    template,

    inject: ['scalecommerceVoApiService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        uuid: { type: String, required: true },
    },

    emits: ['close', 'updated'],

    data() {
        return {
            video: null,
            thumbnails: [],
            frameSrcById: {},
            busy: false,
            statusText: '',
            mediaModalOpen: false,
            cacheBust: 1,
            playback: null,
            playerError: false,
        };
    },

    computed: {
        // Changing the poster needs the VideoOptimizer "editor" permission.
        canUpdate() {
            return this.acl.can('scalecommerce_vo.editor');
        },
        posterSrc() {
            const url = this.video?.poster_url;
            if (!url) {
                return null;
            }
            return (
                url +
                (url.indexOf('?') === -1 ? '?' : '&') +
                `_=${this.cacheBust}`
            );
        },
        canPlay() {
            return !!(
                this.playback &&
                (this.playback.hls || this.playback.mp4)
            );
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
            if (
                this.video.duration !== null &&
                this.video.duration !== undefined
            ) {
                parts.push(formatDuration(this.video.duration));
            }
            const key = orientationKey(this.video.resolution);
            if (key) {
                parts.push(
                    this.$tc(`scalecommerce-vo.detail.orientation_${key}`)
                );
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
        this._unmounted = true;
        this._destroyPlayer();
        Object.values(this.frameSrcById).forEach((src) =>
            URL.revokeObjectURL(src)
        );
    },

    methods: {
        async _load() {
            try {
                const videoResponse =
                    await this.scalecommerceVoApiService.getVideo(this.uuid);
                this.video = videoResponse.data ?? videoResponse;
                this._loadPlayback();
                const thumbsResponse =
                    await this.scalecommerceVoApiService.getThumbnails(
                        this.uuid
                    );
                const thumbs =
                    (thumbsResponse.data ?? thumbsResponse).thumbnails ?? [];
                this.thumbnails = thumbs;
                thumbs.forEach((thumb) => this._loadFrame(thumb.index));
            } catch (error) {
                this.createNotificationError({
                    message: this._errorText(error),
                });
            }
        },

        async _loadPlayback() {
            if (this.video?.status !== 'ready') {
                return;
            }
            try {
                const response =
                    await this.scalecommerceVoApiService.getPlayback(this.uuid);
                if (this._unmounted) {
                    return;
                }
                this.playback = response.data ?? response;
                await this.$nextTick();
                await this._attachPlayer();
            } catch {
                // Playback is a convenience on top of the poster: keep showing the poster and say
                // why there is no player.
                if (!this._unmounted) {
                    this.playerError = true;
                }
            }
        },

        // Same source order as the storefront player: hls.js (MSE), native HLS (Safari), then MP4.
        // hls.js is imported on demand so it only loads when a video is actually opened.
        async _attachPlayer() {
            const el = this.$refs.player;
            if (!el || !this.playback) {
                return;
            }
            const { hls, mp4 } = this.playback;
            const fail = () => {
                this._destroyPlayer();
                this.playerError = true;
            };
            const useMp4 = () => {
                if (!mp4) {
                    fail();
                    return;
                }
                el.addEventListener('error', fail, { once: true });
                el.src = mp4;
            };
            if (hls) {
                const { default: Hls } =
                    await import('hls.js/dist/hls.light.mjs');
                if (this._unmounted) {
                    return;
                }
                if (Hls.isSupported()) {
                    this._hls = new Hls();
                    this._hls.on(Hls.Events.ERROR, (event, data) => {
                        if (data.fatal) {
                            this._destroyPlayer();
                            useMp4();
                        }
                    });
                    this._hls.loadSource(hls);
                    this._hls.attachMedia(el);
                    return;
                }
                if (el.canPlayType('application/vnd.apple.mpegurl')) {
                    el.addEventListener('error', useMp4, { once: true });
                    el.src = hls;
                    return;
                }
            }
            useMp4();
        },

        _destroyPlayer() {
            if (this._hls) {
                this._hls.destroy();
                this._hls = null;
            }
        },

        async _loadFrame(index) {
            try {
                const blob =
                    await this.scalecommerceVoApiService.getThumbnailImage(
                        this.uuid,
                        index
                    );
                const url = URL.createObjectURL(blob);
                if (this._unmounted) {
                    URL.revokeObjectURL(url);
                    return;
                }
                this.frameSrcById = { ...this.frameSrcById, [index]: url };
            } catch (error) {
                // A single frame failing is non-fatal; leave a placeholder.
                console.warn(
                    '[VideoOptimizer] failed to load thumbnail frame',
                    index,
                    error
                );
            }
        },

        // Rejects once the attempt cap is reached so callers don't proceed with stale poster data;
        // the caller's existing catch block shows the error via _errorText (which reads error.message).
        _pollPoster(predicate, attempt = 0) {
            if (this._unmounted) {
                return Promise.resolve();
            }
            if (attempt > 40) {
                return Promise.reject(
                    new Error(this.$tc('scalecommerce-vo.detail.posterTimeout'))
                );
            }
            return new Promise((resolve) => {
                window.setTimeout(async () => {
                    if (this._unmounted) {
                        resolve();
                        return;
                    }
                    try {
                        const response =
                            await this.scalecommerceVoApiService.getVideo(
                                this.uuid
                            );
                        const video = response.data ?? response;
                        if (this._unmounted) {
                            resolve();
                            return;
                        }
                        if (predicate(video, attempt)) {
                            this.video = video;
                            this.cacheBust += 1;
                            resolve();
                            return;
                        }
                    } catch (error) {
                        console.warn(
                            '[VideoOptimizer] poster poll failed, retrying',
                            error
                        );
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
                await this.scalecommerceVoApiService.selectThumbnail(
                    this.uuid,
                    index
                );
                await this._pollPoster(
                    (video) => video.poster?.source === 'thumbnail'
                );
                this.$emit('updated');
            } catch (error) {
                this.createNotificationError({
                    message: this._errorText(error),
                });
            } finally {
                this.busy = false;
                this.statusText = '';
            }
        },

        onFileSelected(event) {
            const file = event.target.files[0];
            // Reset so that choosing the same file again fires "change" again.
            event.target.value = '';
            if (file) {
                this._uploadPoster(file);
            }
        },

        async _uploadPoster(blob) {
            if (!POSTER_CONTENT_TYPES.includes(blob.type)) {
                this.createNotificationError({
                    message: this.$tc(
                        'scalecommerce-vo.detail.posterInvalidType'
                    ),
                });
                return;
            }
            // An earlier custom poster may still report "ready" on the first poll after
            // completing the new upload; wait for "processing" (or a few polls) first.
            const previousStatus = this.video?.poster?.custom_status;
            let sawProcessing = !['ready', 'failed'].includes(previousStatus);
            this.busy = true;
            this.statusText = this.$tc('scalecommerce-vo.detail.uploading');
            try {
                const initResponse =
                    await this.scalecommerceVoApiService.initiatePosterUpload(
                        this.uuid,
                        {
                            contentType: blob.type,
                            fileSize: blob.size,
                        }
                    );
                const init = initResponse.data ?? initResponse;
                await this.scalecommerceVoApiService.uploadPoster(
                    init.uploadUrl,
                    blob
                );
                await this.scalecommerceVoApiService.completePosterUpload(
                    this.uuid,
                    init.key
                );
                this.statusText = this.$tc(
                    'scalecommerce-vo.detail.processing'
                );
                // An uploaded poster becomes the active one by itself once it is processed (API since
                // 2026-11-12), so waiting for "ready" + source "custom" is enough.
                await this._pollPoster((video, attempt) => {
                    const status = video.poster?.custom_status;
                    if (status === 'processing') {
                        sawProcessing = true;
                        return false;
                    }
                    if (status === 'failed') {
                        return sawProcessing || attempt >= 3;
                    }
                    return (
                        status === 'ready' &&
                        (video.poster?.source === 'custom' || attempt >= 5) &&
                        (sawProcessing || attempt >= 3)
                    );
                });
                if (this._unmounted) {
                    return;
                }
                if (this.video.poster?.custom_status === 'failed') {
                    throw new Error(
                        this.$tc('scalecommerce-vo.detail.posterFailed')
                    );
                }
                // Fallback for an API that does not activate the poster by itself.
                if (this.video.poster?.source !== 'custom') {
                    await this.scalecommerceVoApiService.selectPoster(
                        this.uuid,
                        { source: 'custom' }
                    );
                    if (this._unmounted) {
                        return;
                    }
                    await this._pollPoster(
                        (video) => video.poster?.source === 'custom'
                    );
                    if (this._unmounted) {
                        return;
                    }
                }
                this.createNotificationSuccess({
                    message: this.$tc('scalecommerce-vo.detail.posterUpdated'),
                });
                this.$emit('updated');
            } catch (error) {
                this.createNotificationError({
                    message: this._errorText(error),
                });
            } finally {
                this.busy = false;
                this.statusText = '';
            }
        },

        async onRemoveCustomPoster() {
            this.busy = true;
            this.statusText = this.$tc('scalecommerce-vo.detail.applying');
            try {
                await this.scalecommerceVoApiService.deletePoster(this.uuid);
                await this._pollPoster(
                    (video) => video.poster?.source !== 'custom'
                );
                this.$emit('updated');
            } catch (error) {
                this.createNotificationError({
                    message: this._errorText(error),
                });
            } finally {
                this.busy = false;
                this.statusText = '';
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
            let blob;
            try {
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
                blob = await response.blob();
            } catch {
                // HTTP error, or media served from another origin without CORS headers.
                this.createNotificationError({
                    message: this.$tc(
                        'scalecommerce-vo.detail.mediaFetchFailed'
                    ),
                });
                return;
            }
            await this._uploadPoster(blob);
        },

        onClose() {
            this.$emit('close');
        },

        _errorText(error) {
            return (
                error?.response?.data?.errors?.[0]?.detail ??
                error?.message ??
                this.$tc('scalecommerce-vo.detail.genericError')
            );
        },
    },
});
