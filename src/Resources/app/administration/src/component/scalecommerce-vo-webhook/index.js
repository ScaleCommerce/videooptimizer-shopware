import template from './scalecommerce-vo-webhook.html.twig';
import './scalecommerce-vo-webhook.scss';

const { Component, Mixin } = Shopware;

/**
 * The webhook receiver of the plugin (issue #39): the URL to enter in VideoOptimizer, whether the
 * secret is configured and when the last delivery arrived. The secret itself is set in the plugin
 * configuration and never shown here.
 */
Component.register('scalecommerce-vo-webhook', {
    template,

    inject: ['scalecommerceVoApiService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            info: null,
            error: null,
        };
    },

    computed: {
        lastDeliveryText() {
            const last = this.info?.lastDelivery;
            if (!last) {
                return this.$tc('scalecommerce-vo.webhook.noDelivery');
            }
            return this.$tc('scalecommerce-vo.webhook.lastDelivery', 0, {
                event: last.event,
                time: new Date(last.time * 1000).toLocaleString(),
            });
        },
        canOpenConfig() {
            return this.acl.can('system.plugin_maintain');
        },
        configRoute() {
            return {
                name: 'sw.extension.config',
                params: { namespace: 'ScaleVideoOptimizer' },
            };
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
            try {
                const response =
                    await this.scalecommerceVoApiService.getWebhookInfo();
                if (!this._unmounted) {
                    this.info = response.data ?? response;
                }
            } catch (error) {
                if (!this._unmounted) {
                    this.error = error;
                }
            }
        },

        async onCopy() {
            try {
                await navigator.clipboard.writeText(this.info.url);
                this.createNotificationSuccess({
                    message: this.$tc('scalecommerce-vo.webhook.copied'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$tc('scalecommerce-vo.webhook.copyFailed'),
                });
            }
        },
    },
});
