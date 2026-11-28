import template from './sw-product-detail-base.html.twig';

// Adds the VideoOptimizer card below the media card of the product detail page.
Shopware.Component.override('sw-product-detail-base', {
    template,
});
