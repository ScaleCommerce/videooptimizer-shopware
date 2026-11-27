import template from './scalecommerce-vo-tag-picker.html.twig';
import './scalecommerce-vo-tag-picker.scss';
import { addTag, removeTag } from '../../helper/video-tags';

/**
 * Chips of the chosen tags with a select for the organization's existing tags and, with
 * `allow-create`, a field for new ones (issue #37). `v-model` is the list of tag names.
 */
Shopware.Component.register('scalecommerce-vo-tag-picker', {
    template,

    props: {
        modelValue: { type: Array, required: true },
        // [{ name, video_count }] from GET tags
        available: { type: Array, required: false, default: () => [] },
        allowCreate: { type: Boolean, required: false, default: false },
        disabled: { type: Boolean, required: false, default: false },
        label: { type: String, required: false, default: '' },
    },

    emits: ['update:modelValue'],

    data() {
        return {
            newTag: '',
        };
    },

    computed: {
        options() {
            const chosen = new Set(
                this.modelValue.map((tag) => tag.toLowerCase())
            );
            return this.available
                .filter((tag) => !chosen.has(tag.name.toLowerCase()))
                .map((tag) => ({
                    value: tag.name,
                    label: `${tag.name} (${tag.video_count})`,
                }));
        },
    },

    methods: {
        onPick(name) {
            if (name) {
                this.$emit('update:modelValue', addTag(this.modelValue, name));
            }
        },

        onAddNew() {
            const next = addTag(this.modelValue, this.newTag);
            if (next !== this.modelValue) {
                this.$emit('update:modelValue', next);
                this.newTag = '';
            }
        },

        onRemove(name) {
            this.$emit('update:modelValue', removeTag(this.modelValue, name));
        },
    },
});
