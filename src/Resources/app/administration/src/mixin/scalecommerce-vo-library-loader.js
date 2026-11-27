/**
 * Loads the VideoOptimizer libraries for the CMS element configurations and keeps the error, so
 * the configuration can say why the selection is empty (scalecommerce-vo-api-notice) instead of
 * silently showing nothing (issue #11). Plain-object mixin, like scalecommerce-vo-element-video.
 */
export default {
    inject: ['scalecommerceVoApiService'],

    data() {
        return {
            libraries: [],
            librariesError: null,
        };
    },

    computed: {
        libraryOptions() {
            return this.libraries.map((library) => ({
                value: library.id,
                label: library.name,
            }));
        },
    },

    methods: {
        async loadLibraries() {
            this.librariesError = null;
            try {
                const response =
                    await this.scalecommerceVoApiService.getLibraries();
                this.libraries = response.data ?? response;
            } catch (error) {
                this.libraries = [];
                this.librariesError = error;
            }
        },
    },
};
