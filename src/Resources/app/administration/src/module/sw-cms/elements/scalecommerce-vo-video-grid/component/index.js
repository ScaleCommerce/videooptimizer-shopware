import template from './scalecommerce-vo-video-grid-component.html.twig';
import './scalecommerce-vo-video-grid-component.scss';
import { formatMetaLine } from '../../../../../helper/video-meta';

const PLACEHOLDER_TILE_COUNT = 3;

Shopware.Component.register('scalecommerce-vo-video-grid-component', {
    template,
    mixins: ['cms-element'],

    inject: ['scalecommerceVoApiService'],

    data() {
        return {
            videos: {}, // uuid -> video record (or null when the lookup failed)
        };
    },

    computed: {
        items() {
            return (this.element.config?.items?.value ?? []).filter((item) => item.video);
        },
        headline() {
            return this.element.config?.headline?.value || '';
        },
        intro() {
            return this.element.config?.intro?.value || '';
        },
        presentation() {
            return this.element.config?.presentation?.value || 'lightbox';
        },
        playerMode() {
            return this.element.config?.playerMode?.value || 'native';
        },
        showControls() {
            return this.element.config?.showControls?.value ?? true;
        },
        tiles() {
            if (this.items.length === 0) {
                return Array.from({ length: PLACEHOLDER_TILE_COUNT }, () => ({
                    uuid: null,
                    label: '',
                    posterUrl: null,
                    status: null,
                    meta: '',
                    title: null,
                }));
            }

            return this.items.map((item) => {
                const video = this.videos[item.video] ?? null;
                return {
                    uuid: item.video,
                    label: item.label || '',
                    posterUrl: video ? (video.poster_url || video.thumbnail_url || null) : null,
                    status: video?.status ?? null,
                    meta: formatMetaLine(video, (key) => this.$tc(key)),
                    title: video ? (video.title || video.uuid) : null,
                };
            });
        },
        itemUuids() {
            return this.items.map((item) => item.video).join(',');
        },
    },

    watch: {
        itemUuids: {
            immediate: true,
            handler() {
                this.loadVideos();
            },
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-video-grid');
    },

    methods: {
        async loadVideos() {
            // Key presence, not truthiness: a lookup that failed stores null and must not be retried
            // on every config change.
            const uuids = this.items
                .map((item) => item.video)
                .filter((uuid) => uuid && !(uuid in this.videos));

            await Promise.all(uuids.map(async (uuid) => {
                try {
                    const response = await this.scalecommerceVoApiService.getVideo(uuid);
                    this.videos = { ...this.videos, [uuid]: response.data ?? response };
                } catch (error) {
                    console.warn('[VideoOptimizer] failed to load video for preview', error);
                    this.videos = { ...this.videos, [uuid]: null };
                }
            }));
        },
    },
});
