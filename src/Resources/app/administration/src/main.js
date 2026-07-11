import VideoOptimizerApiService from './service/videooptimizer-api.service';
import './acl';
import './module/scale-video-optimizer';
import './component/scale-video-optimizer-video-picker';
import './module/sw-cms/elements/scale-video-optimizer-video';
import './module/sw-cms/blocks/video/scale-video-optimizer-video';

Shopware.Service().register('scaleVideoOptimizerApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new VideoOptimizerApiService(initContainer.httpClient, container.loginService);
});
