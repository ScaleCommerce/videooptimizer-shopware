import template from './scalecommerce-vo-video-list-config.html.twig';
import libraryLoader from '../../../../../mixin/scalecommerce-vo-library-loader';

Shopware.Component.register('scalecommerce-vo-video-list-config', {
    template,
    mixins: ['cms-element', libraryLoader],
    inject: ['scalecommerceVoApiService'],

    data() {
        return {
            availableTags: [],
        };
    },

    computed: {
        selectedLibraryId() {
            return this.element.config.libraryId.value;
        },
        tags() {
            return Array.isArray(this.element.config.tags.value)
                ? this.element.config.tags.value
                : [];
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-video-list');
        this.loadLibraries();
        this.loadTags();
    },

    methods: {
        async loadTags() {
            try {
                const response = await this.scalecommerceVoApiService.getTags();
                this.availableTags = response.data ?? response;
            } catch {
                this.availableTags = [];
            }
        },
        onLibraryChange(libraryId) {
            this.element.config.libraryId.value = libraryId;
            this.$emit('element-update', this.element);
        },
        onTagsChange(tags) {
            this.element.config.tags.value = tags;
            this.$emit('element-update', this.element);
        },
        onDetailPageChange(categoryId) {
            this.element.config.detailCategoryId.value = categoryId || null;
            this.$emit('element-update', this.element);
        },
    },
});
