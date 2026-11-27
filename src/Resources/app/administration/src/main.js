import VideoOptimizerApiService from './service/videooptimizer-api.service';
import './acl';
import './module/scalecommerce-vo';
import './component/scalecommerce-vo-video-gallery';
import './component/scalecommerce-vo-video-detail';
import './component/scalecommerce-vo-preview-surface';
import './component/scalecommerce-vo-api-notice';
import './component/scalecommerce-vo-tag-picker';
import './component/scalecommerce-vo-video-tags';
import './component/scalecommerce-vo-video-filter';
import './component/scalecommerce-vo-savings';
import './component/scalecommerce-vo-webhook';
import './component/scalecommerce-vo-subtitles';
import './component/scalecommerce-vo-subtitle-languages';
import './module/sw-cms/elements/scalecommerce-vo-video';
import './module/sw-cms/blocks/video/scalecommerce-vo-video';
import './module/sw-cms/elements/scalecommerce-vo-media-split';
import './module/sw-cms/blocks/video/scalecommerce-vo-media-split';
import './module/sw-cms/elements/scalecommerce-vo-background-hero';
import './module/sw-cms/blocks/video/scalecommerce-vo-background-hero';
import './module/sw-cms/elements/scalecommerce-vo-spotlight';
import './module/sw-cms/blocks/video/scalecommerce-vo-spotlight';
import './module/sw-cms/elements/scalecommerce-vo-video-grid';
import './module/sw-cms/blocks/video/scalecommerce-vo-video-grid';

Shopware.Service().register('scalecommerceVoApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new VideoOptimizerApiService(
        initContainer.httpClient,
        container.loginService
    );
});
