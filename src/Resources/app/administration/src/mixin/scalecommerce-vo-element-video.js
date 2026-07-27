import { formatMetaLine } from '../helper/video-meta';

/**
 * Shared video lookup for the CMS element previews. Consumers define a `videoUuid` computed
 * property; this mixin keeps the matching video record loaded and derives poster and meta line.
 */
export default {
    inject: ['scalecommerceVoApiService'],

    data() {
        return {
            video: null,
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
            if (!this.videoUuid) {
                this.video = null;
                return;
            }
            try {
                const response = await this.scalecommerceVoApiService.getVideo(this.videoUuid);
                this.video = response.data ?? response;
            } catch (error) {
                console.warn('[VideoOptimizer] failed to load video for preview', error);
                this.video = null;
            }
        },

        metaLine() {
            return formatMetaLine(this.video, (key) => this.$tc(key));
        },
    },
};
