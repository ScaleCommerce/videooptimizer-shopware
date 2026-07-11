import VideoOptimizerApiService from './service/videooptimizer-api.service';
import './module/vo-media';

Shopware.Service().register('videoOptimizerApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new VideoOptimizerApiService(initContainer.httpClient, container.loginService);
});
