import template from './scalecommerce-vo-list.html.twig';

const { Component, Mixin } = Shopware;

Component.register('scalecommerce-vo-list', {
    template,

    inject: ['scalecommerceVoApiService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            libraries: [],
            selectedLibraryId: null,
        };
    },

    computed: {
        libraryOptions() {
            return this.libraries.map((library) => ({ value: library.id, label: library.name }));
        },
    },

    created() {
        this.loadLibraries();
    },

    methods: {
        async loadLibraries() {
            this.isLoading = true;
            try {
                const response = await this.scalecommerceVoApiService.getLibraries();
                this.libraries = response.data ?? response;
                if (this.libraries.length && !this.selectedLibraryId) {
                    this.selectedLibraryId = this.libraries[0].id;
                }
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.isLoading = false;
            }
        },

        async onCreateLibrary(name) {
            if (!name) {
                return;
            }
            try {
                await this.scalecommerceVoApiService.createLibrary({ name });
                await this.loadLibraries();
                this.createNotificationSuccess({ message: this.$tc('scalecommerce-vo.list.libraryCreated') });
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        async onDeleteLibrary(id) {
            try {
                await this.scalecommerceVoApiService.deleteLibrary(id);
                this.selectedLibraryId = null;
                await this.loadLibraries();
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        _errorText(error) {
            return error?.response?.data?.errors?.[0]?.detail ?? this.$tc('scalecommerce-vo.list.genericError');
        },
    },
});
