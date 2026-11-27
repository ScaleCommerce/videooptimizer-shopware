import { beforeAll, describe, expect, it, vi } from 'vitest';
import { loadComponent } from './support/load-component.js';
import {
    isSubtitleFileName,
    languageOptions,
    subtitleFileProblem,
} from '../../../src/Resources/app/administration/src/helper/subtitles.js';
import {
    apiErrorText,
    subtitleLanguageInUse,
} from '../../../src/Resources/app/administration/src/helper/api-error.js';

describe('subtitle helpers (#36)', () => {
    it.each([['de.vtt', true], ['EN.SRT', true], ['de.txt', false], ['vtt', false], [undefined, false]])('%s is a subtitle file: %s', (name, expected) => {
        expect(isSubtitleFileName(name)).toBe(expected);
    });

    it('rejects wrong types and files over 2 MB', () => {
        expect(subtitleFileProblem({ name: 'a.vtt', size: 10 })).toBeNull();
        expect(subtitleFileProblem({ name: 'a.mp4', size: 10 })).toBe('type');
        expect(subtitleFileProblem({ name: 'a.srt', size: 2 * 1024 * 1024 + 1 })).toBe('size');
    });

    it('flags the languages a video already has a subtitle in', () => {
        const options = languageOptions([{ code: 'de', label: 'Deutsch' }, { code: 'en', label: 'English' }], [{ language: 'de' }]);
        expect(options).toEqual([
            { value: 'de', label: 'Deutsch (de)', replaces: true },
            { value: 'en', label: 'English (en)', replaces: false },
        ]);
    });

    it('reads the video count of a language in use', () => {
        const error = (code, meta) => ({ response: { data: { errors: [{ code, meta }] } } });
        expect(subtitleLanguageInUse(error('SCALECOMMERCE_VO__SUBTITLE_LANGUAGE_IN_USE', { videos: 3 }))).toBe(3);
        expect(subtitleLanguageInUse(error('SCALECOMMERCE_VO__SUBTITLE_LANGUAGE_IN_USE'))).toBe(0);
        expect(subtitleLanguageInUse(error('OTHER'))).toBeNull();
        expect(subtitleLanguageInUse(new Error('x'))).toBeNull();
    });

    it('prefers the server detail for error texts', () => {
        expect(apiErrorText({ response: { data: { errors: [{ detail: 'Nope' }] } }, message: 'm' }, 'f')).toBe('Nope');
        expect(apiErrorText(new Error('m'), 'f')).toBe('m');
        expect(apiErrorText({}, 'f')).toBe('f');
    });
});

describe('scalecommerce-vo-subtitles (#36)', () => {
    let subtitles;
    beforeAll(async () => {
        subtitles = await loadComponent(() => import('../../../src/Resources/app/administration/src/component/scalecommerce-vo-subtitles/index.js'));
    });

    function context(service, overrides = {}) {
        return subtitles.context({ uuid: 'vid-1', scalecommerceVoApiService: service, ...overrides });
    }

    it('loads subtitles and languages and preselects the first language', async () => {
        const service = {
            getSubtitles: vi.fn().mockResolvedValue({ data: [{ language: 'de' }] }),
            getSubtitleLanguages: vi.fn().mockResolvedValue({ data: [{ code: 'de', label: 'Deutsch' }, { code: 'en', label: 'English' }] }),
        };
        const ctx = context(service);

        await ctx.load();

        expect(ctx.subtitles).toEqual([{ language: 'de' }]);
        expect(ctx.language).toBe('de');
        expect(ctx.isLoading).toBe(false);
    });

    it('shows the load error instead of throwing', async () => {
        const service = { getSubtitles: vi.fn().mockRejectedValue({ response: { data: { errors: [{ detail: 'Down' }] } } }), getSubtitleLanguages: vi.fn().mockResolvedValue({ data: [] }) };
        const ctx = context(service);

        await ctx.load();

        expect(ctx.loadError).toBe('Down');
    });

    it('refuses an invalid file with a notification', () => {
        const ctx = context({});
        const input = { files: [{ name: 'a.exe', size: 1 }], value: 'x' };

        ctx.onFileSelected({ target: input });

        expect(ctx.file).toBeNull();
        expect(ctx.createNotificationError).toHaveBeenCalledWith({ message: 'scalecommerce-vo.subtitles.fileInvalid_type' });
        expect(input.value).toBe('');
    });

    it('uploads with language, kind and default flag, then reloads', async () => {
        const service = { uploadSubtitle: vi.fn().mockResolvedValue({ data: { skipped_cues: 2 } }) };
        const file = { name: 'de.vtt', size: 5 };
        const ctx = context(service, { file, language: 'de', kind: 'captions', isDefault: true, canUpload: true, load: vi.fn() });

        await ctx.onUpload();

        expect(service.uploadSubtitle).toHaveBeenCalledWith('vid-1', { file, language: 'de', kind: 'captions', isDefault: true });
        expect(ctx.createNotificationSuccess).toHaveBeenCalledOnce();
        expect(ctx.load).toHaveBeenCalledOnce();
        expect(ctx.file).toBeNull();
        expect(ctx.isUploading).toBe(false);
    });

    it('leaves kind and default flag out unless they were chosen', async () => {
        const service = { uploadSubtitle: vi.fn().mockResolvedValue({ data: {} }) };
        const file = { name: 'de.srt', size: 5 };
        const ctx = context(service, { file, language: 'de', kind: null, isDefault: false, canUpload: true, load: vi.fn() });

        await ctx.onUpload();

        expect(service.uploadSubtitle).toHaveBeenCalledWith('vid-1', { file, language: 'de', kind: null, isDefault: null });
    });

    it('reports a failed upload and re-enables the form', async () => {
        const service = { uploadSubtitle: vi.fn().mockRejectedValue({ response: { data: { errors: [{ detail: 'No valid cues' }] } } }) };
        const ctx = context(service, { file: { name: 'a.vtt' }, language: 'de', canUpload: true, load: vi.fn() });

        await ctx.onUpload();

        expect(ctx.createNotificationError).toHaveBeenCalledWith({ message: 'No valid cues' });
        expect(ctx.uploadError).toBe('No valid cues');
        expect(ctx.file).toEqual({ name: 'a.vtt' });
        expect(ctx.isUploading).toBe(false);
    });

    it('deletes the confirmed subtitle and reloads', async () => {
        const service = { deleteSubtitle: vi.fn().mockResolvedValue({}) };
        const ctx = context(service, { confirmDelete: { language: 'de' }, load: vi.fn() });

        await ctx.onConfirmDelete();

        expect(service.deleteSubtitle).toHaveBeenCalledWith('vid-1', 'de');
        expect(ctx.confirmDelete).toBeNull();
        expect(ctx.load).toHaveBeenCalledOnce();
    });
});

describe('scalecommerce-vo-subtitle-languages (#36)', () => {
    let languages;
    beforeAll(async () => {
        languages = await loadComponent(() => import('../../../src/Resources/app/administration/src/component/scalecommerce-vo-subtitle-languages/index.js'));
    });

    it('adds a language with an optional label and reloads', async () => {
        const service = { createSubtitleLanguage: vi.fn().mockResolvedValue({}) };
        const ctx = languages.context({ scalecommerceVoApiService: service, code: ' en-US ', label: '', canAdd: true, load: vi.fn() });

        await ctx.onAdd();

        expect(service.createSubtitleLanguage).toHaveBeenCalledWith({ code: 'en-US' });
        expect(ctx.code).toBe('');
        expect(ctx.load).toHaveBeenCalledOnce();
    });

    it('explains how many videos still use a language that cannot be deleted', async () => {
        const conflict = { response: { status: 409, data: { errors: [{ code: 'SCALECOMMERCE_VO__SUBTITLE_LANGUAGE_IN_USE', meta: { videos: 3 } }] } } };
        const service = { deleteSubtitleLanguage: vi.fn().mockRejectedValue(conflict) };
        const ctx = languages.context({ scalecommerceVoApiService: service, confirmDelete: { code: 'de' }, load: vi.fn() });
        ctx.$tc = (key, count, values) => `${key}|${count}|${JSON.stringify(values)}`;

        await ctx.onConfirmDelete();

        expect(ctx.createNotificationError).toHaveBeenCalledWith({ message: 'scalecommerce-vo.subtitleLanguages.inUse|3|{"count":3,"language":"de"}' });
        expect(ctx.load).not.toHaveBeenCalled();
    });
});
