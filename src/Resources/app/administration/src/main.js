import VideoOptimizerApiService from './service/videooptimizer-api.service';
import './acl';
import './module/scalecommerce-vo';
import './component/scalecommerce-vo-video-picker';
import './module/sw-cms/elements/scalecommerce-vo-video';
import './module/sw-cms/blocks/video/scalecommerce-vo-video';

Shopware.Service().register('scalecommerceVoApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new VideoOptimizerApiService(initContainer.httpClient, container.loginService);
});
