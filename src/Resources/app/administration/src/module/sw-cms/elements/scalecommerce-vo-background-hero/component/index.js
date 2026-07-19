import template from './scalecommerce-vo-background-hero-component.html.twig';
import './scalecommerce-vo-background-hero-component.scss';
import { parseResolution, formatDuration, orientationKey } from '../../../../../helper/video-meta';

Shopware.Component.register('scalecommerce-vo-background-hero-component', {
    template,
    mixins: ['cms-element'],

    inject: ['scalecommerceVoApiService'],

    data() {
        return {
            video: null,
        };
    },

    computed: {
        videoUuid() {
            return this.element.config?.video?.value ?? null;
        },
        posterUrl() {
            if (!this.video) return null;
            return this.video.poster_url || this.video.thumbnail_url || null;
        },
        headline() {
            return this.element.config?.headline?.value || this.video?.title || this.video?.uuid || '';
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

    created() {
        this.initElementConfig('scalecommerce-vo-background-hero');
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
        statusVariant(status) {
            if (status === 'ready') return 'success';
            if (status === 'failed') return 'danger';
            return 'warning';
        },

        metaLine() {
            if (!this.video) {
                return '';
            }
            const parsed = parseResolution(this.video.resolution);
            const dimensions = parsed ? `${parsed.width}×${parsed.height}` : null;
            const duration = this.video.duration === null || this.video.duration === undefined
                ? null
                : formatDuration(this.video.duration);
            const key = orientationKey(this.video.resolution);
            const labels = {
                portrait: 'scalecommerce-vo.gallery.orientationPortrait',
                landscape: 'scalecommerce-vo.gallery.orientationLandscape',
                square: 'scalecommerce-vo.gallery.orientationSquare',
            };
            const orientation = key ? this.$tc(labels[key]) : null;

            return [dimensions, duration, orientation].filter((part) => part !== null).join(' · ');
        },
    },
});
