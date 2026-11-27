import { formatMetaLine } from '../helper/video-meta';
import { isNotFound } from '../helper/api-error';

/**
 * Shared video lookup for the CMS element previews. Consumers define a `videoUuid` computed
 * property; this mixin keeps the matching video record loaded and derives poster and meta line.
 */
export default {
    inject: ['scalecommerceVoApiService'],

    data() {
        return {
            video: null,
            // The configured video was deleted in VideoOptimizer (issue #35).
            videoMissing: false,
        };
    },

    computed: {
        posterUrl() {
            if (!this.video) {
                return null;
            }
            return this.video.poster_url || this.video.thumbnail_url || null;
        },
    },

    watch: {
        videoUuid: {
            immediate: true,
            handler() {
                this.loadVideo();
            },
        },
    },

    methods: {
        async loadVideo() {
            // Only the latest lookup may apply its result: a slow answer for a video that was
            // replaced meanwhile must not flag (or fill in) the new one.
            this._videoRequest = (this._videoRequest ?? 0) + 1;
            const request = this._videoRequest;
            this.videoMissing = false;
            if (!this.videoUuid) {
                this.video = null;
                return;
            }
            try {
                const response = await this.scalecommerceVoApiService.getVideo(
                    this.videoUuid
                );
                if (request !== this._videoRequest) {
                    return;
                }
                this.video = response.data ?? response;
            } catch (error) {
                if (request !== this._videoRequest) {
                    return;
                }
                this.videoMissing = isNotFound(error);
                console.warn(
                    '[VideoOptimizer] failed to load video for preview',
                    error
                );
                this.video = null;
            }
        },

        metaLine() {
            return formatMetaLine(this.video, (key) => this.$tc(key));
        },
    },
};
