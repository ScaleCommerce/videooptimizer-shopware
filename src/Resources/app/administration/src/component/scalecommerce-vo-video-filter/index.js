import template from './scalecommerce-vo-video-filter.html.twig';
import './scalecommerce-vo-video-filter.scss';
import { METADATA_KEY_PATTERN } from '../../helper/video-tags';

/**
 * Filters of the video list (issue #37): by tags and by one metadata key/value. Emits `change`
 * with `{ tags: string[], metadata: { [key]: value } }`.
 */
Shopware.Component.register('scalecommerce-vo-video-filter', {
    template,

    inject: ['scalecommerceVoApiService'],

    emits: ['change'],

    data() {
        return {
            tags: [],
            available: [],
            metadataKey: '',
            metadataValue: '',
        };
    },

    computed: {
        metadata() {
            const key = this.metadataKey.trim();
            const value = this.metadataValue.trim();
            return METADATA_KEY_PATTERN.test(key) && value !== ''
                ? { [key]: value }
                : {};
        },
        active() {
            return (
                this.tags.length > 0 || Object.keys(this.metadata).length > 0
            );
        },
        // Nothing to filter by yet: no tags in the organization and no filter in use.
        visible() {
            return this.available.length > 0 || this.active;
        },
    },

    created() {
        this.loadTags();
    },

    beforeUnmount() {
        this._unmounted = true;
        window.clearTimeout(this._timer);
    },

    methods: {
        async loadTags() {
            try {
                const response = await this.scalecommerceVoApiService.getTags();
                if (!this._unmounted) {
                    this.available = response.data ?? response;
                }
            } catch {
                // The filter is a convenience; without tags only the metadata filter remains.
            }
        },

        onTagsChange(tags) {
            this.tags = tags;
            this.emitChange();
        },

        // Typing a metadata filter must not reload the list on every key.
        onMetadataInput() {
            window.clearTimeout(this._timer);
            this._timer = window.setTimeout(() => this.emitChange(), 500);
        },

        emitChange() {
            this.$emit('change', {
                tags: this.tags,
                metadata: this.metadata,
            });
        },

        onClear() {
            this.tags = [];
            this.metadataKey = '';
            this.metadataValue = '';
            this.emitChange();
        },
    },
});
