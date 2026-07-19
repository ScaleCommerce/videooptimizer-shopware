import VideoOptimizerApiService from './service/videooptimizer-api.service';
import './acl';
import './module/scalecommerce-vo';
import './component/scalecommerce-vo-video-gallery';
import './component/scalecommerce-vo-video-detail';
import './module/sw-cms/elements/scalecommerce-vo-video';
import './module/sw-cms/blocks/video/scalecommerce-vo-video';
import './module/sw-cms/elements/scalecommerce-vo-media-split';
import './module/sw-cms/blocks/video/scalecommerce-vo-media-split';
import './module/sw-cms/elements/scalecommerce-vo-background-hero';
import './module/sw-cms/blocks/video/scalecommerce-vo-background-hero';

Shopware.Service().register('scalecommerceVoApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new VideoOptimizerApiService(initContainer.httpClient, container.loginService);
});
