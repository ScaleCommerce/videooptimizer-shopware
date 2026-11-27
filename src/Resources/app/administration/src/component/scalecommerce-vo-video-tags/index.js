import template from './scalecommerce-vo-video-tags.html.twig';
import './scalecommerce-vo-video-tags.scss';
import { apiErrorText } from '../../helper/api-error';
import {
    MAX_METADATA_KEYS,
    metadataPatch,
    metadataProblem,
    metadataRows,
    sameTags,
} from '../../helper/video-tags';

const { Component, Mixin } = Shopware;

/**
 * Tags and metadata (key/value, e.g. CMS ids) of one video (issue #37), saved with one PATCH.
 */
Component.register('scalecommerce-vo-video-tags', {
    template,

    inject: ['scalecommerceVoApiService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        video: { type: Object, required: true },
    },

    emits: ['saved'],

    data() {
        return {
            tags: [],
            rows: [],
            available: [],
            isSaving: false,
        };
    },

    computed: {
        canUpdate() {
            return this.acl.can('scalecommerce_vo.editor');
        },
        problem() {
            return metadataProblem(this.rows);
        },
        patch() {
            return metadataPatch(this.video.metadata, this.rows);
        },
        tagsChanged() {
            return !sameTags(this.tags, this.video.tags ?? []);
        },
        canSave() {
            return (
                this.canUpdate &&
                !this.isSaving &&
                !this.problem &&
                (this.tagsChanged || this.patch !== null)
            );
        },
        canAddRow() {
            return this.rows.length < MAX_METADATA_KEYS;
        },
    },

    watch: {
        'video.uuid'() {
            this.reset();
        },
    },

    created() {
        this.reset();
        this.loadTags();
    },

    beforeUnmount() {
        this._unmounted = true;
    },

    methods: {
        reset() {
            this.tags = [...(this.video.tags ?? [])];
            this.rows = metadataRows(this.video.metadata);
        },

        async loadTags() {
            try {
                const response = await this.scalecommerceVoApiService.getTags();
                if (!this._unmounted) {
                    this.available = response.data ?? response;
                }
            } catch {
                // Suggestions only; new tags can still be typed.
            }
        },

        onAddRow() {
            if (this.canAddRow) {
                this.rows = [...this.rows, { key: '', value: '' }];
            }
        },

        onRemoveRow(index) {
            this.rows = this.rows.filter((row, i) => i !== index);
        },

        async onSave() {
            if (!this.canSave) {
                return;
            }
            const payload = {};
            if (this.tagsChanged) {
                payload.tags = this.tags;
            }
            if (this.patch !== null) {
                payload.metadata = this.patch;
            }
            this.isSaving = true;
            try {
                const response =
                    await this.scalecommerceVoApiService.updateVideo(
                        this.video.uuid,
                        payload
                    );
                if (this._unmounted) {
                    return;
                }
                this.createNotificationSuccess({
                    message: this.$tc('scalecommerce-vo.tags.saved'),
                });
                this.$emit('saved', response.data ?? response);
                this.loadTags();
            } catch (error) {
                this.createNotificationError({
                    message: apiErrorText(
                        error,
                        this.$tc('scalecommerce-vo.detail.genericError')
                    ),
                });
            } finally {
                this.isSaving = false;
            }
        },
    },
});
