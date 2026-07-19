import template from './scalecommerce-vo-media-split-config.html.twig';

Shopware.Component.register('scalecommerce-vo-media-split-config', {
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
        sideOptions() {
            return [
                { value: 'left', label: this.$tc('scalecommerce-vo.mediaSplit.sideLeft') },
                { value: 'right', label: this.$tc('scalecommerce-vo.mediaSplit.sideRight') },
            ];
        },
        presentationOptions() {
            return [
                { value: 'facade', label: this.$tc('scalecommerce-vo.mediaSplit.presentationFacade') },
                { value: 'lightbox', label: this.$tc('scalecommerce-vo.mediaSplit.presentationLightbox') },
                { value: 'direct', label: this.$tc('scalecommerce-vo.mediaSplit.presentationDirect') },
            ];
        },
        playerModeOptions() {
            return [
                { value: 'native', label: this.$tc('scalecommerce-vo.mediaSplit.playerNative') },
                { value: 'embed', label: this.$tc('scalecommerce-vo.mediaSplit.playerEmbed') },
            ];
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-media-split');
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
        onSideChange(side) {
            this.element.config.side.value = side;
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
        onTextChange(value) {
            this.element.config.text.value = value;
            this.$emit('element-update', this.element);
        },
    },
});
