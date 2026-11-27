import template from './scalecommerce-vo-video-gallery.html.twig';
import './scalecommerce-vo-video-gallery.scss';
import {
    parseResolution,
    formatDuration,
    orientationKey,
} from '../../helper/video-meta';

// Videos per page in the gallery; further pages load on demand ("Load more videos").
const PAGE_SIZE = 60;

const { Component, Mixin } = Shopware;

Component.register('scalecommerce-vo-video-gallery', {
    template,

    inject: ['scalecommerceVoApiService', 'acl'],

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
            uploadProgress: null,
            ingestUrl: '',
            ingestTitle: '',
            isIngesting: false,
            detailUuid: null,
            confirmDeleteVideo: null,
            renameVideo: null,
            renameTitle: '',
            brokenPosters: {},
            nextCursor: null,
            isLoadingMore: false,
            selectedVideoRecord: null,
        };
    },

    watch: {
        libraryId() {
            // Don't leave the previous library's videos visible and clickable while loading.
            this.videos = [];
            this.loadVideos();
        },
        selectedUuid() {
            this._ensureSelectedVideo();
        },
    },

    computed: {
        canCreate() {
            return this.acl.can('scalecommerce_vo.creator');
        },
        canUpdate() {
            return this.acl.can('scalecommerce_vo.editor');
        },
        canDelete() {
            return this.acl.can('scalecommerce_vo.deleter');
        },

        selectedVideo() {
            if (!this.selectedUuid) {
                return null;
            }
            // The selected video may be on a page that is not loaded yet; see _ensureSelectedVideo().
            return (
                this.videos.find((video) => video.uuid === this.selectedUuid) ??
                (this.selectedVideoRecord?.uuid === this.selectedUuid
                    ? this.selectedVideoRecord
                    : null)
            );
        },

        confirmDeleteVideoTitle() {
            return this.confirmDeleteVideo
                ? this.confirmDeleteVideo.title || this.confirmDeleteVideo.uuid
                : '';
        },

        canIngest() {
            return (
                this.ingestUrl.startsWith('https://') &&
                !!this.libraryId &&
                !this.isUploading &&
                !this.isIngesting
            );
        },
    },

    created() {
        this.loadVideos();
    },

    beforeUnmount() {
        this._unmounted = true;
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
            return [
                this.dimensions(video),
                this.duration(video),
                this.orientationLabel(video),
            ]
                .filter((part) => part !== null)
                .join(' · ');
        },

        // Loads the first page of the library; more pages via loadMoreVideos().
        async loadVideos() {
            this.nextCursor = null;
            if (!this.libraryId) {
                this.videos = [];
                this._ensureSelectedVideo();
                return;
            }
            await this._loadPage(null);
        },

        // Loads the selected video on its own when it is not on a loaded page, so the selection
        // line below the grid still shows it.
        async _ensureSelectedVideo() {
            const uuid = this.selectedUuid;
            if (
                this.mode !== 'select' ||
                !uuid ||
                this.videos.some((video) => video.uuid === uuid) ||
                this.selectedVideoRecord?.uuid === uuid
            ) {
                return;
            }
            try {
                const response =
                    await this.scalecommerceVoApiService.getVideo(uuid);
                if (this.selectedUuid === uuid) {
                    this.selectedVideoRecord = response.data ?? response;
                }
            } catch {
                // Only the selection line is affected; the selection itself stays.
            }
        },

        async loadMoreVideos() {
            if (!this.nextCursor || this.isLoadingMore) {
                return;
            }
            await this._loadPage(this.nextCursor);
        },

        async _loadPage(cursor) {
            // Only the latest request may apply its result: with quick library switches an older,
            // slower response must not overwrite the list of the library selected now.
            const libraryId = this.libraryId;
            const request = (this._loadRequest = (this._loadRequest ?? 0) + 1);
            if (cursor) {
                this.isLoadingMore = true;
            } else {
                this.isLoading = true;
            }
            try {
                const response = await this.scalecommerceVoApiService.getVideos(
                    libraryId,
                    { limit: PAGE_SIZE, cursor }
                );
                if (
                    request !== this._loadRequest ||
                    libraryId !== this.libraryId
                ) {
                    return;
                }
                const items = response.data ?? [];
                if (cursor) {
                    // Skip videos already listed (e.g. moved between pages while paging).
                    const known = new Set(
                        this.videos.map((video) => video.uuid)
                    );
                    this.videos = [
                        ...this.videos,
                        ...items.filter((video) => !known.has(video.uuid)),
                    ];
                } else {
                    this.videos = items;
                }
                const next = response.meta?.has_more
                    ? response.meta.next_cursor
                    : null;
                // A cursor that doesn't move would offer the same page forever.
                this.nextCursor = next && next !== cursor ? next : null;
                this._ensureSelectedVideo();
            } catch (error) {
                if (request !== this._loadRequest) {
                    return;
                }
                this.createNotificationError({
                    message: this._errorText(error),
                });
            } finally {
                if (request === this._loadRequest) {
                    this.isLoading = false;
                    this.isLoadingMore = false;
                }
            }
        },

        // Videos that are not ready (e.g. a failed import) have no usable thumbnail; their URL can
        // redirect to something the browser blocks. Show the placeholder icon instead.
        posterFor(video) {
            if (video.status !== 'ready' || this.brokenPosters[video.uuid]) {
                return null;
            }
            return video.poster_url || video.thumbnail_url || null;
        },

        onPosterError(video) {
            this.brokenPosters = { ...this.brokenPosters, [video.uuid]: true };
        },

        statusLabel(status) {
            const known = [
                'ready',
                'processing',
                'pending',
                'queued',
                'uploading',
                'failed',
            ];
            return known.includes(status)
                ? this.$tc(`scalecommerce-vo.gallery.status.${status}`)
                : status;
        },

        statusVariant(status) {
            if (status === 'ready') return 'success';
            if (status === 'failed') return 'danger';
            return 'warning';
        },

        onSelect(video) {
            if (this.mode === 'manage') {
                if (video.status === 'ready') {
                    this.detailUuid = video.uuid;
                }
                return;
            }
            if (this.mode !== 'select' || video.status !== 'ready') {
                return;
            }
            this.$emit('select', video.uuid);
        },

        onDetailClose() {
            this.detailUuid = null;
        },

        onDetailUpdated() {
            this.loadVideos();
        },

        onFileSelected(event) {
            this.uploadFile = event.target.files[0] ?? null;
        },

        async onUpload() {
            const file = this.uploadFile;
            if (!file || !this.libraryId) {
                return;
            }
            const context = this._selectionContext();
            this.isUploading = true;
            this.uploadProgress = null;
            this._uploadAbort = new AbortController();
            let uuid;
            try {
                uuid = await this._uploadPresigned(
                    this.libraryId,
                    file,
                    this.uploadTitle
                );
            } catch (error) {
                if (this._uploadAbort?.signal.aborted) {
                    this.createNotificationInfo({
                        message: this.$tc(
                            'scalecommerce-vo.gallery.uploadCancelled'
                        ),
                    });
                } else {
                    this.createNotificationError({
                        message: this._errorText(error),
                    });
                }
                return;
            } finally {
                // The file is transferred (or the upload failed): release the upload form right
                // away; processing is tracked in the background and shown on the tile.
                this.isUploading = false;
                this.uploadProgress = null;
                this._uploadAbort = null;
            }
            this.uploadFile = null;
            this.uploadTitle = '';
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
            this.createNotificationSuccess({
                message: this.$tc('scalecommerce-vo.gallery.uploadStarted'),
            });
            this._watchProcessing(uuid, context);
        },

        onCancelUpload() {
            if (this._uploadAbort) {
                this._uploadAbort.abort();
            }
        },

        // What the editor had selected when an upload or import started.
        _selectionContext() {
            return {
                selectedUuid: this.selectedUuid,
                libraryId: this.libraryId,
            };
        },

        // Reloads the list right away (the tile shows "processing") and selects the video once
        // it is ready - unless the editor picked another video or library in the meantime, which
        // must not be overwritten minutes later. Does not block the upload form.
        async _watchProcessing(uuid, context) {
            await this.loadVideos();
            if (!(await this._pollUntilReady(uuid))) {
                return;
            }
            if (
                context &&
                (this.selectedUuid !== context.selectedUuid ||
                    this.libraryId !== context.libraryId)
            ) {
                return;
            }
            this.$emit('upload-complete', uuid);
        },

        async onIngest() {
            if (!this.canIngest) {
                return;
            }
            const context = this._selectionContext();
            this.isIngesting = true;
            try {
                const payload = {
                    library_id: this.libraryId,
                    source_url: this.ingestUrl,
                };
                if (this.ingestTitle) {
                    payload.title = this.ingestTitle;
                }
                const response =
                    await this.scalecommerceVoApiService.ingestVideoUrl(
                        payload
                    );
                const video = response.data ?? response;
                this.createNotificationSuccess({
                    message: this.$tc('scalecommerce-vo.gallery.ingestStarted'),
                });
                this.ingestUrl = '';
                this.ingestTitle = '';
                this._watchProcessing(video.uuid, context);
            } catch (error) {
                this.createNotificationError({
                    message: this._errorText(error),
                });
            } finally {
                this.isIngesting = false;
            }
        },

        // initiate (proxy) -> PUT parts straight to storage -> complete (proxy). Returns the video uuid.
        async _uploadPresigned(libraryId, file, title) {
            const initiateResponse =
                await this.scalecommerceVoApiService.initiateUpload({
                    libraryId,
                    filename: file.name,
                    contentType: file.type || 'application/octet-stream',
                    fileSize: file.size,
                });
            const init = initiateResponse.data ?? initiateResponse;
            this.uploadProgress = { done: 0, total: init.parts.length };
            const parts = await this.scalecommerceVoApiService.uploadParts(
                file,
                init.parts,
                init.partSize,
                {
                    signal: this._uploadAbort?.signal,
                    onProgress: (done, total) => {
                        this.uploadProgress = { done, total };
                    },
                }
            );
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
            const completeResponse =
                await this.scalecommerceVoApiService.completeUpload(payload);
            const completed = completeResponse.data ?? completeResponse;
            return completed.uuid ?? init.uuid;
        },

        // Rejects with an error marked `handled: true` once the failure/timeout notification has
        // already been shown, so callers only need a bare try/catch (no duplicate notification).
        /**
         * Polls until the video is ready (resolves true) or failed, unmounted or timed out
         * (false). Every 5 s during the first minute, then every 15 s, for about 30 minutes in
         * total: large or 4K files can take long, so a timeout is a notice, not an error.
         */
        async _pollUntilReady(uuid) {
            if (!uuid) {
                await this.loadVideos();
                return false;
            }
            for (let attempt = 0; attempt < 128; attempt += 1) {
                await new Promise((resolve) => {
                    window.setTimeout(resolve, attempt < 12 ? 5000 : 15000);
                });
                if (this._unmounted) {
                    return false;
                }
                try {
                    const response =
                        await this.scalecommerceVoApiService.getVideo(uuid);
                    const video = response.data ?? response;
                    if (this._unmounted) {
                        return false;
                    }
                    if (video.status === 'ready') {
                        await this.loadVideos();
                        return true;
                    }
                    if (video.status === 'failed') {
                        await this.loadVideos();
                        let message = this.$tc(
                            'scalecommerce-vo.gallery.processingFailed'
                        );
                        // Upstream text, shown as plain text in the (HTML) notification.
                        const reason =
                            typeof video.error === 'string'
                                ? video.error
                                      .replace(/<[^>]*>/g, '')
                                      .trim()
                                      .slice(0, 200)
                                : '';
                        if (reason) {
                            message = `${message} (${reason})`;
                        }
                        this.createNotificationError({ message });
                        return false;
                    }
                } catch (error) {
                    console.warn(
                        '[VideoOptimizer] polling uploaded video status failed, retrying',
                        error
                    );
                }
            }
            await this.loadVideos();
            this.createNotificationInfo({
                message: this.$tc('scalecommerce-vo.gallery.processingTimeout'),
            });
            return false;
        },

        onRename(video) {
            this.renameVideo = video;
            this.renameTitle = video.title || '';
        },

        onRenameCancel() {
            this.renameVideo = null;
            this.renameTitle = '';
        },

        async onConfirmRename() {
            const video = this.renameVideo;
            const title = this.renameTitle.trim();
            if (!video || !title) {
                return;
            }
            this.onRenameCancel();
            try {
                await this.scalecommerceVoApiService.updateVideo(video.uuid, {
                    title,
                });
                await this.loadVideos();
                this.$emit('renamed', video.uuid);
            } catch (error) {
                this.createNotificationError({
                    message: this._errorText(error),
                });
            }
        },

        onDeleteClick(video) {
            this.confirmDeleteVideo = video;
        },

        async onConfirmDelete() {
            const video = this.confirmDeleteVideo;
            this.confirmDeleteVideo = null;
            if (!video) {
                return;
            }
            try {
                await this.scalecommerceVoApiService.deleteVideo(video.uuid);
                await this.loadVideos();
                this.$emit('deleted', video.uuid);
            } catch (error) {
                this.createNotificationError({
                    message: this._errorText(error),
                });
            }
        },

        _errorText(error) {
            return (
                error?.response?.data?.errors?.[0]?.detail ??
                this.$tc('scalecommerce-vo.gallery.genericError')
            );
        },
    },
});
