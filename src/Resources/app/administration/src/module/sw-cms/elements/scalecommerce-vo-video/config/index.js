import template from './scalecommerce-vo-cms-element-config.html.twig';

Shopware.Component.register('scalecommerce-vo-cms-element-config', {
    template,
    mixins: ['cms-element'],
    inject: ['scalecommerceVoApiService'],

    data() {
        return {
            libraries: [],
        };
    },

    computed: {
        libraryOptions() {
            return this.libraries.map((library) => ({ value: library.id, label: library.name }));
        },
        selectedLibraryId() {
            return this.element.config.libraryId.value;
        },
        selectedVideoUuid() {
            return this.element.config.videoUuid.value;
        },
        playerModeOptions() {
            return [
                { value: 'native', label: this.$tc('scalecommerce-vo.cms.playerModeNative') },
                { value: 'embed', label: this.$tc('scalecommerce-vo.cms.playerModeEmbed') },
            ];
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-video');
        this.loadLibraries();
    },

    methods: {
        async loadLibraries() {
            try {
                const response = await this.scalecommerceVoApiService.getLibraries();
                this.libraries = response.data ?? response;
            } catch (error) {
                this.libraries = [];
            }
        },
        onLibraryChange(libraryId) {
            this.element.config.libraryId.value = libraryId;
            this.element.config.videoUuid.value = null;
            this.$emit('element-update', this.element);
        },
        onVideoSelect(uuid) {
            this.element.config.videoUuid.value = uuid;
            this.$emit('element-update', this.element);
        },
        onPlayerModeChange(mode) {
            this.element.config.playerMode.value = mode;
            this.$emit('element-update', this.element);
        },
    },
});
