import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Hls from '../stubs/hls.js';
import { installPluginBase, stubMedia } from './support.js';

installPluginBase();
const { default: ScalecommerceVoBlocks } = await import(
    '../../../src/Resources/app/storefront/src/scalecommerce-vo-blocks/scalecommerce-vo-blocks.plugin.js'
);

const NATIVE = JSON.stringify({ mp4: 'https://cdn/v.mp4', poster: 'https://cdn/p.jpg', controls: true });

function setup({ action = 'lightbox', mode = 'native' } = {}) {
    document.body.innerHTML = `
        <main id="page">
            <div class="block" data-vo-player-mode="${mode}" data-vo-embed-url="https://player.example/embed/abc?theme=dark"
                 data-vo-title="Demo" data-vo-label-close="Schliessen" data-vo-native-options='${NATIVE}'>
                <button type="button" class="facade" data-vo-action="${action}">Play</button>
            </div>
        </main>`;
    const el = document.querySelector('.block');
    const plugin = new ScalecommerceVoBlocks(el);
    return { el, plugin, facade: el.querySelector('.facade') };
}

const overlay = () => document.querySelector('.scalecommerce-vo-lightbox');
const key = (name, init = {}) => document.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true, ...init }));

beforeEach(() => {
    Hls.reset();
    stubMedia();
});

afterEach(() => {
    // Closes a lightbox a test left open; the module-scoped guard would block the next test.
    if (overlay()) {
        key('Escape');
    }
    document.body.innerHTML = '';
});

describe('player theme (#41)', () => {
    it('applies the theme to the player built from the facade', () => {
        document.body.innerHTML = `<div class="block" data-vo-player-mode="native" data-vo-title="D" data-vo-native-options='${JSON.stringify({ mp4: 'https://cdn/v.mp4', theme: { loopButton: true, mutedStart: true, preload: 'auto', interactive: false, accentColor: '#C72876' } })}'>
            <button type="button" class="facade" data-vo-action="lightbox">Play</button></div>`;
        new ScalecommerceVoBlocks(document.querySelector('.block'));

        document.querySelector('.facade').click();

        const video = document.querySelector('.scalecommerce-vo-lightbox video');
        expect(video.loop).toBe(true);
        expect(video.muted).toBe(true);
        // The theme's "auto" is capped: no video download before play.
        expect(video.preload).toBe('metadata');
        expect(video.parentElement.classList.contains('scalecommerce-vo-video--static')).toBe(true);
        expect(document.querySelector('.scalecommerce-vo-lightbox').style.getPropertyValue('--vo-accent')).toBe('#C72876');
    });
});

describe('subtitle tracks (#42)', () => {
    it('adds the tracks to the player built from the facade and loads them cross-origin', () => {
        const tracks = [
            { src: 'https://cdn/de.vtt?v=2', srclang: 'de', label: 'Deutsch', kind: 'subtitles', default: true },
            { src: 'https://cdn/en.vtt', srclang: 'en', label: 'English', kind: 'captions', default: false },
        ];
        document.body.innerHTML = `<div class="block" data-vo-player-mode="native" data-vo-title="D" data-vo-native-options='${JSON.stringify({ mp4: 'https://cdn/v.mp4', tracks })}'>
            <button type="button" class="facade" data-vo-action="facade">Play</button></div>`;
        new ScalecommerceVoBlocks(document.querySelector('.block'));

        document.querySelector('.facade').click();

        const video = document.querySelector('.block video');
        expect(video.getAttribute('crossorigin')).toBe('anonymous');
        const elements = [...video.querySelectorAll('track')];
        expect(elements.map((track) => [track.kind, track.srclang, track.label, track.getAttribute('src'), track.default])).toEqual([
            ['subtitles', 'de', 'Deutsch', 'https://cdn/de.vtt?v=2', true],
            ['captions', 'en', 'English', 'https://cdn/en.vtt', false],
        ]);
    });

    it('leaves a video without tracks alone', () => {
        document.body.innerHTML = `<div class="block" data-vo-player-mode="native" data-vo-title="D" data-vo-native-options='${JSON.stringify({ mp4: 'https://cdn/v.mp4' })}'>
            <button type="button" class="facade" data-vo-action="facade">Play</button></div>`;
        new ScalecommerceVoBlocks(document.querySelector('.block'));

        document.querySelector('.facade').click();

        const video = document.querySelector('.block video');
        expect(video.hasAttribute('crossorigin')).toBe(false);
        expect(video.querySelector('track')).toBeNull();
    });
});

describe('facade (#34)', () => {
    it('replaces the poster button with the native player and focuses it', () => {
        const { el, facade } = setup({ action: 'facade' });
        const focus = vi.spyOn(HTMLElement.prototype, 'focus');

        facade.click();

        expect(el.querySelector('.facade')).toBeNull();
        const video = el.querySelector('video');
        expect(video).not.toBeNull();
        expect(video.autoplay).toBe(true);
        expect(video.getAttribute('poster')).toBe('https://cdn/p.jpg');
        // jsdom does not make a <video> focusable; the plugin still moves the focus there.
        expect(focus.mock.contexts).toContain(video);
    });

    it('replaces the button with the hosted embed, forced to autoplay muted', () => {
        const { el, facade } = setup({ action: 'facade', mode: 'embed' });

        facade.click();

        const frame = el.querySelector('iframe');
        const url = new URL(frame.src);
        expect(url.searchParams.get('autoplay')).toBe('1');
        expect(url.searchParams.get('muted')).toBe('1');
        expect(url.searchParams.get('theme')).toBe('dark');
        expect(frame.title).toBe('Demo');
        expect(frame.getAttribute('sandbox')).toContain('allow-scripts');
        expect(document.activeElement).toBe(frame);
    });
});

describe('lightbox (#34)', () => {
    it('opens an accessible dialog, makes the page inert and focuses the close button', () => {
        const { facade } = setup();

        facade.click();

        expect(overlay().getAttribute('role')).toBe('dialog');
        expect(overlay().getAttribute('aria-modal')).toBe('true');
        expect(overlay().getAttribute('aria-label')).toBe('Demo');
        expect(document.getElementById('page').hasAttribute('inert')).toBe(true);
        expect(document.body.classList.contains('scalecommerce-vo-lightbox-open')).toBe(true);
        const close = overlay().querySelector('.scalecommerce-vo-lightbox__close');
        expect(close.getAttribute('aria-label')).toBe('Schliessen');
        expect(document.activeElement).toBe(close);
    });

    it('closes with the close button and gives the focus back to the facade', () => {
        const { facade } = setup();
        facade.click();

        overlay().querySelector('.scalecommerce-vo-lightbox__close').click();

        expect(overlay()).toBeNull();
        expect(document.getElementById('page').hasAttribute('inert')).toBe(false);
        expect(document.body.classList.contains('scalecommerce-vo-lightbox-open')).toBe(false);
        expect(document.activeElement).toBe(facade);
    });

    it('closes with Escape and with a click on the backdrop', () => {
        const { facade } = setup();

        facade.click();
        key('Escape');
        expect(overlay()).toBeNull();

        facade.click();
        overlay().click();
        expect(overlay()).toBeNull();
    });

    it('keeps a click inside the stage from closing it', () => {
        const { facade } = setup();
        facade.click();

        overlay().querySelector('.scalecommerce-vo-lightbox__stage').click();

        expect(overlay()).not.toBeNull();
    });

    it('opens only one lightbox at a time', () => {
        const { facade } = setup();
        facade.click();
        facade.click();

        expect(document.querySelectorAll('.scalecommerce-vo-lightbox')).toHaveLength(1);
    });

    it('stops listening for keys once closed', () => {
        const { facade } = setup();
        facade.click();
        const close = overlay().querySelector('.scalecommerce-vo-lightbox__close');
        close.click();
        const focus = vi.spyOn(close, 'focus');

        key('Tab');

        expect(focus).not.toHaveBeenCalled();
    });

    it('traps Tab inside the dialog', () => {
        const { facade } = setup({ mode: 'embed' });
        facade.click();
        const close = overlay().querySelector('.scalecommerce-vo-lightbox__close');
        const frame = overlay().querySelector('iframe');

        // Shift+Tab on the first element wraps to the last, Tab on the last wraps to the first.
        const back = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true });
        document.dispatchEvent(back);
        expect(back.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(frame);

        const forward = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
        document.dispatchEvent(forward);
        expect(forward.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(close);
    });

    it('closes an open lightbox when the plugin is destroyed', () => {
        const { plugin, facade } = setup();
        facade.click();

        plugin.destroy();

        expect(overlay()).toBeNull();
        expect(document.getElementById('page').hasAttribute('inert')).toBe(false);
        // The guard is released: a new lightbox can open.
        facade.click();
        expect(overlay()).not.toBeNull();
    });

    it('releases the player of the lightbox when it closes', () => {
        const { facade } = setup();
        facade.click();
        const video = overlay().querySelector('video');

        key('Escape');

        expect(video.pause).toBeDefined();
        expect(video.hasAttribute('src')).toBe(false);
    });
});

describe('lightbox as a native dialog (#32)', () => {
    let showModal;
    let close;

    // jsdom has no showModal(): this stand-in opens and closes the dialog like a browser does (the
    // close event is dispatched by the browser when Escape closes a modal dialog).
    beforeEach(() => {
        showModal = vi.fn(function open() {
            this.setAttribute('open', '');
        });
        close = vi.fn(function closeDialog() {
            this.removeAttribute('open');
            this.dispatchEvent(new Event('close'));
        });
        window.HTMLDialogElement.prototype.showModal = showModal;
        window.HTMLDialogElement.prototype.close = close;
    });

    afterEach(() => {
        // Closes the dialog while the stand-in still exists (the top-level hook runs after this one).
        if (overlay()) {
            key('Escape');
        }
        delete window.HTMLDialogElement.prototype.showModal;
        delete window.HTMLDialogElement.prototype.close;
    });

    it('opens a <dialog> with showModal() and leaves focus and inert to the browser', () => {
        const { facade } = setup();

        facade.click();

        expect(overlay().tagName).toBe('DIALOG');
        expect(showModal).toHaveBeenCalledTimes(1);
        expect(overlay().hasAttribute('open')).toBe(true);
        expect(overlay().getAttribute('aria-label')).toBe('Demo');
        // The browser provides role and modality; no manual inert or aria-modal.
        expect(overlay().hasAttribute('role')).toBe(false);
        expect(document.getElementById('page').hasAttribute('inert')).toBe(false);
        expect(document.activeElement).toBe(overlay().querySelector('.scalecommerce-vo-lightbox__close'));
    });

    it('closes when the browser closes the dialog on Escape (focus inside the native controls)', () => {
        const { facade } = setup();
        facade.click();
        const dialog = overlay();

        // Escape while the <video> controls or the embed iframe hold the focus never reaches the
        // page as a key event; the browser closes the dialog and fires "close".
        dialog.dispatchEvent(new Event('close'));

        expect(overlay()).toBeNull();
        expect(document.body.classList.contains('scalecommerce-vo-lightbox-open')).toBe(false);
        expect(document.activeElement).toBe(facade);
        // The guard is released.
        facade.click();
        expect(overlay()).not.toBeNull();
    });

    it('closes with the close button, Escape and a backdrop click, and closes the dialog once', () => {
        const { facade } = setup();

        facade.click();
        overlay().querySelector('.scalecommerce-vo-lightbox__close').click();
        expect(overlay()).toBeNull();
        expect(close).toHaveBeenCalledTimes(1);

        facade.click();
        key('Escape');
        expect(overlay()).toBeNull();

        facade.click();
        overlay().click();
        expect(overlay()).toBeNull();
        expect(close).toHaveBeenCalledTimes(3);
    });

    it('does not trap Tab itself while the browser keeps the focus in the modal dialog', () => {
        const { facade } = setup({ mode: 'embed' });
        facade.click();

        const tab = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true });
        document.dispatchEvent(tab);

        expect(tab.defaultPrevented).toBe(false);
    });

    it('closes the dialog when the plugin is destroyed', () => {
        const { plugin, facade } = setup();
        facade.click();

        plugin.destroy();

        expect(overlay()).toBeNull();
        expect(close).toHaveBeenCalledTimes(1);
    });
});
