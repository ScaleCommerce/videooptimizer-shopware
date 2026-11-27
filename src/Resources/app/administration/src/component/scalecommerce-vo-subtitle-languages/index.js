import template from './scalecommerce-vo-subtitle-languages.html.twig';
import './scalecommerce-vo-subtitle-languages.scss';
import { apiErrorText, subtitleLanguageInUse } from '../../helper/api-error';

const { Component, Mixin } = Shopware;

/**
 * The organization's subtitle languages (issue #36): a subtitle can only be uploaded in one of
 * them. Deleting a language that videos still use is refused by the API (409); the count of
 * those videos is shown.
 */
Component.register('scalecommerce-vo-subtitle-languages', {
    template,

    inject: ['scalecommerceVoApiService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            languages: [],
            isLoading: false,
            error: null,
            code: '',
            label: '',
            isSaving: false,
            confirmDelete: null,
        };
    },

    computed: {
        canUpdate() {
            return this.acl.can('scalecommerce_vo.editor');
        },
        canDelete() {
            return this.acl.can('scalecommerce_vo.deleter');
        },
        canAdd() {
            return this.code.trim() !== '' && !this.isSaving;
        },
    },

    created() {
        this.load();
    },

    beforeUnmount() {
        this._unmounted = true;
    },

    methods: {
        async load() {
            this.isLoading = true;
            this.error = null;
            try {
                const response =
                    await this.scalecommerceVoApiService.getSubtitleLanguages();
                if (!this._unmounted) {
                    this.languages = response.data ?? response;
                }
            } catch (error) {
                if (!this._unmounted) {
                    this.languages = [];
                    this.error = error;
                }
            } finally {
                if (!this._unmounted) {
                    this.isLoading = false;
                }
            }
        },

        async onAdd() {
            if (!this.canAdd) {
                return;
            }
            this.isSaving = true;
            try {
                const payload = { code: this.code.trim() };
                if (this.label.trim() !== '') {
                    payload.label = this.label.trim();
                }
                await this.scalecommerceVoApiService.createSubtitleLanguage(
                    payload
                );
                this.code = '';
                this.label = '';
                await this.load();
            } catch (error) {
                this.createNotificationError({
                    message: apiErrorText(
                        error,
                        this.$tc('scalecommerce-vo.list.genericError')
                    ),
                });
            } finally {
                this.isSaving = false;
            }
        },

        async onConfirmDelete() {
            const language = this.confirmDelete;
            this.confirmDelete = null;
            if (!language) {
                return;
            }
            try {
                await this.scalecommerceVoApiService.deleteSubtitleLanguage(
                    language.code
                );
                await this.load();
            } catch (error) {
                const videos = subtitleLanguageInUse(error);
                this.createNotificationError({
                    message:
                        videos === null
                            ? apiErrorText(
                                  error,
                                  this.$tc('scalecommerce-vo.list.genericError')
                              )
                            : this.$tc(
                                  'scalecommerce-vo.subtitleLanguages.inUse',
                                  videos,
                                  { count: videos, language: language.code }
                              ),
                });
            }
        },
    },
});
