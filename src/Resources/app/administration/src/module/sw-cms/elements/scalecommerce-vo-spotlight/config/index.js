import template from './scalecommerce-vo-spotlight-config.html.twig';
import libraryLoader from '../../../../../mixin/scalecommerce-vo-library-loader';

Shopware.Component.register('scalecommerce-vo-spotlight-config', {
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
        presentationOptions() {
            return [
                {
                    value: 'facade',
                    label: this.$tc(
                        'scalecommerce-vo.spotlight.presentationFacade'
                    ),
                },
                {
                    value: 'lightbox',
                    label: this.$tc(
                        'scalecommerce-vo.spotlight.presentationLightbox'
                    ),
                },
                {
                    value: 'direct',
                    label: this.$tc(
                        'scalecommerce-vo.spotlight.presentationDirect'
                    ),
                },
            ];
        },
        playerModeOptions() {
            return [
                {
                    value: 'native',
                    label: this.$tc('scalecommerce-vo.spotlight.playerNative'),
                },
                {
                    value: 'embed',
                    label: this.$tc('scalecommerce-vo.spotlight.playerEmbed'),
                },
            ];
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-spotlight');
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
        onPresentationChange(presentation) {
            this.element.config.presentation.value = presentation;
            this.$emit('element-update', this.element);
        },
        onPlayerModeChange(mode) {
            this.element.config.playerMode.value = mode;
            this.$emit('element-update', this.element);
        },
    },
});
