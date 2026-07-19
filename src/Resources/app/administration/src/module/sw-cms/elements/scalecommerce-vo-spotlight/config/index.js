import template from './scalecommerce-vo-spotlight-config.html.twig';

Shopware.Component.register('scalecommerce-vo-spotlight-config', {
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
            return this.element.config.video.value;
        },
        presentationOptions() {
            return [
                { value: 'facade', label: this.$tc('scalecommerce-vo.spotlight.presentationFacade') },
                { value: 'lightbox', label: this.$tc('scalecommerce-vo.spotlight.presentationLightbox') },
                { value: 'direct', label: this.$tc('scalecommerce-vo.spotlight.presentationDirect') },
            ];
        },
        playerModeOptions() {
            return [
                { value: 'native', label: this.$tc('scalecommerce-vo.spotlight.playerNative') },
                { value: 'embed', label: this.$tc('scalecommerce-vo.spotlight.playerEmbed') },
            ];
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-spotlight');
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
