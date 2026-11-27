// Minimal hls.js stand-in: records calls and lets tests emit events.
export default class Hls {
    static supported = true;

    static instances = [];

    static Events = { ERROR: 'hlsError', MANIFEST_PARSED: 'hlsManifestParsed' };

    static isSupported() {
        return Hls.supported;
    }

    constructor(config = {}) {
        this.config = config;
        this.handlers = {};
        this.calls = [];
        this.destroyed = false;
        Hls.instances.push(this);
    }

    on(event, handler) {
        (this.handlers[event] ??= []).push(handler);
    }

    emit(event, data = {}) {
        (this.handlers[event] ?? []).forEach((handler) => handler(event, data));
    }

    loadSource(url) {
        this.calls.push(['loadSource', url]);
    }

    attachMedia(media) {
        this.calls.push(['attachMedia', media]);
    }

    startLoad() {
        this.calls.push(['startLoad']);
    }

    destroy() {
        this.destroyed = true;
    }

    static reset() {
        Hls.supported = true;
        Hls.instances = [];
    }
}
