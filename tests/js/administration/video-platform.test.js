import { describe, expect, it } from 'vitest';
import { isVideoPlatformUrl } from '../../../src/Resources/app/administration/src/helper/video-platform.js';

describe('isVideoPlatformUrl', () => {
    it.each([
        'https://youtube.com/watch?v=x',
        'https://www.youtube.com/watch?v=x',
        'https://m.youtube.com/watch?v=x',
        'https://youtu.be/x',
        'https://www.youtube-nocookie.com/embed/x',
        'https://WWW.YOUTUBE.COM/watch?v=x',
    ])('detects %s', (url) => {
        expect(isVideoPlatformUrl(url)).toBe(true);
    });

    it.each([
        'https://youtube.com.evil.com/video.mp4',
        'https://notyoutube.com/video.mp4',
        'https://example.com/youtube.com/video.mp4',
        'https://example.com/video.mp4',
        'not a url',
        '',
    ])('ignores %s', (url) => {
        expect(isVideoPlatformUrl(url)).toBe(false);
    });
});
