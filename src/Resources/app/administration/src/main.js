import VideoOptimizerApiService from './service/videooptimizer-api.service';
import './acl';
import './module/vo-media';
import './component/vo-video-picker';
import './module/sw-cms/elements/vo-video';
import './module/sw-cms/blocks/video/vo-video';

Shopware.Service().register('videoOptimizerApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new VideoOptimizerApiService(initContainer.httpClient, container.loginService);
});
