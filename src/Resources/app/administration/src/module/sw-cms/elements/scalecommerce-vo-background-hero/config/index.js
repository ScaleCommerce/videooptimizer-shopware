import template from './scalecommerce-vo-background-hero-config.html.twig';
import libraryLoader from '../../../../../mixin/scalecommerce-vo-library-loader';

Shopware.Component.register('scalecommerce-vo-background-hero-config', {
    template,
    mixins: ['cms-element', libraryLoader],
    inject: ['scalecommerceVoApiService'],

    computed: {
        selectedLibraryId() {
            return this.element.config.libraryId.value;
        },
        selectedVideoUuid() {
            return this.element.config.video.value;
        },
        overlayOptions() {
            return [
                {
                    value: 'gradient',
                    label: this.$tc(
                        'scalecommerce-vo.backgroundHero.overlayGradient'
                    ),
                },
                {
                    value: 'dark',
                    label: this.$tc(
                        'scalecommerce-vo.backgroundHero.overlayDark'
                    ),
                },
                {
                    value: 'none',
                    label: this.$tc(
                        'scalecommerce-vo.backgroundHero.overlayNone'
                    ),
                },
            ];
        },
        heightOptions() {
            return [
                {
                    value: 'full',
                    label: this.$tc(
                        'scalecommerce-vo.backgroundHero.heightFull'
                    ),
                },
                {
                    value: 'large',
                    label: this.$tc(
                        'scalecommerce-vo.backgroundHero.heightLarge'
                    ),
                },
                {
                    value: 'medium',
                    label: this.$tc(
                        'scalecommerce-vo.backgroundHero.heightMedium'
                    ),
                },
            ];
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-background-hero');
        this.loadLibraries();
    },

    methods: {
        onLibraryChange(libraryId) {
            this.element.config.libraryId.value = libraryId;
            this.element.config.video.value = null;
            this.$emit('element-update', this.element);
        },
        onVideoSelect(uuid) {
            this.element.config.video.value = uuid;
            this.$emit('element-update', this.element);
        },
        onOverlayChange(overlay) {
            this.element.config.overlay.value = overlay;
            this.$emit('element-update', this.element);
        },
        onHeightChange(height) {
            this.element.config.height.value = height;
            this.$emit('element-update', this.element);
        },
    },
});
