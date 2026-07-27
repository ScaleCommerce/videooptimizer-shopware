import template from './scalecommerce-vo-preview-surface.html.twig';
import './scalecommerce-vo-preview-surface.scss';

/**
 * Editor counterpart to the storefront surface macro: renders the poster still of a
 * VideoOptimizer video plus the affordance matching the configured presentation mode.
 * Layout around it is owned by the element components; the default slot is laid over the poster.
 */
Shopware.Component.register('scalecommerce-vo-preview-surface', {
    template,

    props: {
        posterUrl: { type: String, required: false, default: null },
        aspect: { type: String, required: false, default: '16 / 9' },
        minHeight: { type: Number, required: false, default: null },
        presentation: { type: String, required: false, default: 'facade' },
        playerMode: { type: String, required: false, default: 'native' },
        controls: { type: Boolean, required: false, default: true },
        status: { type: String, required: false, default: null },
        meta: { type: String, required: false, default: '' },
        hasVideo: { type: Boolean, required: false, default: false },
        emptyLabel: { type: String, required: false, default: null },
        tooltip: { type: String, required: false, default: null },
    },

    computed: {
        surfaceStyle() {
            if (this.minHeight) {
                return { minHeight: `${this.minHeight}px` };
            }
            return { aspectRatio: this.aspect };
        },
        statusVariant() {
            if (this.status === 'ready') {
                return 'ready';
            }
            if (this.status === 'failed') {
                return 'failed';
            }
            return 'pending';
        },
        emptyText() {
            return this.emptyLabel || this.$tc('scalecommerce-vo.cms.selectVideo');
        },
        showPlay() {
            return this.hasVideo && (this.presentation === 'facade' || this.presentation === 'lightbox');
        },
        showControlsBar() {
            return this.hasVideo
                && this.presentation === 'direct'
                && this.playerMode === 'native'
                && this.controls;
        },
        showExpand() {
            return this.hasVideo && this.presentation === 'lightbox';
        },
        showEmbed() {
            return this.hasVideo && this.playerMode === 'embed';
        },
    },
});
