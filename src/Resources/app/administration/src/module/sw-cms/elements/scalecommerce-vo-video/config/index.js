import template from './scalecommerce-vo-cms-element-config.html.twig';
import libraryLoader from '../../../../../mixin/scalecommerce-vo-library-loader';
import layout from '../../../../../mixin/scalecommerce-vo-layout';

Shopware.Component.register('scalecommerce-vo-cms-element-config', {
    template,
    mixins: ['cms-element', libraryLoader, layout],
    inject: ['scalecommerceVoApiService'],

    computed: {
        selectedLibraryId() {
            return this.element.config.libraryId.value;
        },
        selectedVideoUuid() {
            return this.element.config.videoUuid.value;
        },
        playerModeOptions() {
            return [
                {
                    value: 'native',
                    label: this.$tc('scalecommerce-vo.cms.playerModeNative'),
                },
                {
                    value: 'embed',
                    label: this.$tc('scalecommerce-vo.cms.playerModeEmbed'),
                },
            ];
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-video');
        this.loadLibraries();
    },

    methods: {
        onLibraryChange(libraryId) {
            this.element.config.libraryId.value = libraryId;
            this.element.config.videoUuid.value = null;
            this.$emit('element-update', this.element);
        },
        onVideoSelect(uuid) {
            this.element.config.videoUuid.value = uuid;
            this.$emit('element-update', this.element);
        },
        onFormatChange(format) {
            this.element.config.format.value = format;
            this.$emit('element-update', this.element);
        },
        onPlayerModeChange(mode) {
            this.element.config.playerMode.value = mode;
            this.$emit('element-update', this.element);
        },
    },
});
