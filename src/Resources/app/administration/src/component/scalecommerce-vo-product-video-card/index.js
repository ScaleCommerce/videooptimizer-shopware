import template from './scalecommerce-vo-product-video-card.html.twig';
import libraryLoader from '../../mixin/scalecommerce-vo-library-loader';
import {
    FIELD,
    FIELD_LIBRARY,
    productVideo,
    withProductVideo,
} from '../../helper/product-video';

/**
 * Card of the product detail page to choose the product's video with the same gallery selection as the
 * CMS elements. The choice is stored in the product's custom fields (no entity, no migration) and
 * shown by the storefront as a slide of the product image gallery; variants inherit the main product's.
 */
Shopware.Component.register('scalecommerce-vo-product-video-card', {
    template,
    mixins: [libraryLoader],
    inject: ['scalecommerceVoApiService', 'acl'],

    props: {
        product: { type: Object, required: true },
        parentProduct: { type: Object, required: false, default: null },
    },

    computed: {
        own() {
            return productVideo(this.product);
        },
        inherited() {
            return productVideo(this.parentProduct);
        },
        isInherited() {
            return !this.own.uuid && !!this.inherited.uuid && !!this.product.parentId;
        },
        shown() {
            return this.isInherited ? this.inherited : this.own;
        },
        canEdit() {
            return this.acl.can('product.editor');
        },
        selectedLibraryId() {
            return this.own.libraryId || this.inherited.libraryId || null;
        },
    },

    created() {
        this.loadLibraries();
    },

    methods: {
        update(values) {
            this.product.customFields = withProductVideo(
                this.product.customFields,
                values
            );
        },
        onLibraryChange(libraryId) {
            this.update({ uuid: null, libraryId });
        },
        onVideoSelect(uuid) {
            this.update({ uuid, libraryId: this.selectedLibraryId });
        },
        onRemove() {
            this.update({ uuid: null, libraryId: null });
        },
    },
});

export { FIELD, FIELD_LIBRARY };
