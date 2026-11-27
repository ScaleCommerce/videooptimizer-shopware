import template from './scalecommerce-vo-subtitles.html.twig';
import './scalecommerce-vo-subtitles.scss';
import { apiErrorText } from '../../helper/api-error';
import { languageOptions, subtitleFileProblem } from '../../helper/subtitles';

const { Component, Mixin } = Shopware;

/**
 * Subtitles of one video (issue #36): list, upload (.vtt/.srt, replaces the language's subtitle),
 * download and delete. The languages are the organization's subtitle languages, managed in the
 * module's "Subtitle languages" card.
 */
Component.register('scalecommerce-vo-subtitles', {
    template,

    inject: ['scalecommerceVoApiService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        uuid: { type: String, required: true },
    },

    data() {
        return {
            subtitles: [],
            languages: [],
            isLoading: false,
            isUploading: false,
            language: null,
            kind: null,
            isDefault: false,
            file: null,
            confirmDelete: null,
            loadError: null,
            uploadError: null,
        };
    },

    computed: {
        canUpdate() {
            return this.acl.can('scalecommerce_vo.editor');
        },
        options() {
            return languageOptions(this.languages, this.subtitles);
        },
        replaces() {
            return this.options.some(
                (option) => option.value === this.language && option.replaces
            );
        },
        kindOptions() {
            return [
                {
                    value: 'subtitles',
                    label: this.$tc('scalecommerce-vo.subtitles.kindSubtitles'),
                },
                {
                    value: 'captions',
                    label: this.$tc('scalecommerce-vo.subtitles.kindCaptions'),
                },
            ];
        },
        canUpload() {
            return !!this.file && !!this.language && !this.isUploading;
        },
    },

    watch: {
        uuid() {
            this.load();
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
            const uuid = this.uuid;
            this.isLoading = true;
            this.loadError = null;
            try {
                const [subtitles, languages] = await Promise.all([
                    this.scalecommerceVoApiService.getSubtitles(uuid),
                    this.scalecommerceVoApiService.getSubtitleLanguages(),
                ]);
                if (this._unmounted || uuid !== this.uuid) {
                    return;
                }
                this.subtitles = subtitles.data ?? subtitles;
                this.languages = languages.data ?? languages;
                if (
                    !this.language ||
                    !this.languages.some((l) => l.code === this.language)
                ) {
                    this.language = this.languages[0]?.code ?? null;
                }
            } catch (error) {
                if (!this._unmounted) {
                    this.loadError = apiErrorText(error, '');
                }
            } finally {
                if (!this._unmounted) {
                    this.isLoading = false;
                }
            }
        },

        languageLabel(code) {
            return this.languages.find((l) => l.code === code)?.label ?? code;
        },

        onFileSelected(event) {
            const file = event.target.files[0] ?? null;
            // Reset so that choosing the same file again fires "change" again.
            event.target.value = '';
            const problem = file ? subtitleFileProblem(file) : null;
            if (problem) {
                this.file = null;
                this.createNotificationError({
                    message: this.$tc(
                        `scalecommerce-vo.subtitles.fileInvalid_${problem}`
                    ),
                });
                return;
            }
            this.file = file;
            this.uploadError = null;
        },

        async onUpload() {
            if (!this.canUpload) {
                return;
            }
            this.isUploading = true;
            this.uploadError = null;
            try {
                const response =
                    await this.scalecommerceVoApiService.uploadSubtitle(
                        this.uuid,
                        {
                            file: this.file,
                            language: this.language,
                            kind: this.kind,
                            // Only when ticked: the API keeps the existing flag otherwise.
                            isDefault: this.isDefault ? true : null,
                        }
                    );
                const skipped = (response.data ?? response).skipped_cues ?? 0;
                this.createNotificationSuccess({
                    message:
                        skipped > 0
                            ? this.$tc(
                                  'scalecommerce-vo.subtitles.uploadedSkipped',
                                  skipped,
                                  { count: skipped }
                              )
                            : this.$tc('scalecommerce-vo.subtitles.uploaded'),
                });
                this.file = null;
                this.isDefault = false;
                await this.load();
            } catch (error) {
                // Notification and a message that stays next to the form; the file stays selected
                // so the upload can be retried.
                this.uploadError = apiErrorText(
                    error,
                    this.$tc('scalecommerce-vo.detail.genericError')
                );
                this.createNotificationError({ message: this.uploadError });
            } finally {
                this.isUploading = false;
            }
        },

        async onDownload(subtitle) {
            try {
                const blob =
                    await this.scalecommerceVoApiService.downloadSubtitle(
                        this.uuid,
                        subtitle.language
                    );
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `${this.uuid}-${subtitle.language}.vtt`;
                link.click();
                window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            } catch (error) {
                this.createNotificationError({
                    message: apiErrorText(
                        error,
                        this.$tc('scalecommerce-vo.detail.genericError')
                    ),
                });
            }
        },

        async onConfirmDelete() {
            const subtitle = this.confirmDelete;
            this.confirmDelete = null;
            if (!subtitle) {
                return;
            }
            try {
                await this.scalecommerceVoApiService.deleteSubtitle(
                    this.uuid,
                    subtitle.language
                );
                await this.load();
            } catch (error) {
                this.createNotificationError({
                    message: apiErrorText(
                        error,
                        this.$tc('scalecommerce-vo.detail.genericError')
                    ),
                });
            }
        },
    },
});
