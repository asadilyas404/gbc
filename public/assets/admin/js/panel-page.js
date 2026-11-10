(() => {
    if (window.PanelPage) return;
    const pages = new Map();
    const ownedHandlers = new WeakSet();
    const nativeTimeout = window.setTimeout.bind(window);
    const nativeInterval = window.setInterval.bind(window);
    let rendering = false;
    let readyQueue = [];

    function create(name) {
        if (pages.has(name)) pages.get(name).dispose();
        const body = document.body;
        const cleanups = [];
        const timeouts = new Set();
        const intervals = new Set();
        const jq = (...args) => window.jQuery(...args);
        const namespace = '.panelPage_' + name;
        let active = true;
        const page = {
            mount(callback) {
                if (!active) return;
                const jquery = window.jQuery;
                const targets = [document, window];
                const handlers = target => Object.values(jquery?._data?.(target, 'events') || {}).flat();
                const previousHandlers = new Set(targets.flatMap(handlers));
                const restore = [];
                targets.forEach(target => {
                    if (typeof target.addEventListener !== 'function') return;
                    const descriptor = Object.getOwnPropertyDescriptor(target, 'addEventListener');
                    const original = target.addEventListener;
                    target.addEventListener = function (event, listener, options) {
                        original.call(this, event, listener, options);
                        // jQuery owns its central dispatcher; remove its individual handlers below.
                        if (listener !== jquery?._data?.(this, 'handle')) {
                            page.cleanup(() => this.removeEventListener(event, listener, options));
                        }
                    };
                    restore.push(() => {
                        if (descriptor) Object.defineProperty(target, 'addEventListener', descriptor);
                        else delete target.addEventListener;
                    });
                });
                try {
                    return callback();
                } finally {
                    restore.reverse().forEach(callback => callback());
                    targets.forEach(target => handlers(target).forEach(handle => {
                        if (previousHandlers.has(handle) || ownedHandlers.has(handle)) return;
                        ownedHandlers.add(handle);
                        const original = handle.handler;
                        const mounted = function (...args) { return page.mount(() => original.apply(this, args)); };
                        mounted.guid = original.guid;
                        handle.handler = mounted;
                        page.cleanup(() => jquery(target).off(handle.origType + (handle.namespace ? '.' + handle.namespace : ''), handle.selector, mounted));
                    }));
                }
            },
            globals(bindings) {
                Object.entries(bindings).forEach(([key, binding]) => {
                    const previous = Object.getOwnPropertyDescriptor(window, key);
                    if (previous && !previous.configurable) return;
                    Object.defineProperty(window, key, {...binding, configurable: true});
                    page.cleanup(() => {
                        if (Object.getOwnPropertyDescriptor(window, key)?.get !== binding.get) return;
                        if (previous) Object.defineProperty(window, key, previous);
                        else delete window[key];
                    });
                });
            },
            expose(exports) {
                Object.entries(exports).forEach(([key, value]) => {
                    const previous = Object.getOwnPropertyDescriptor(window, key);
                    if (previous && !previous.configurable) return;
                    Object.defineProperty(window, key, {value, writable: true, configurable: true});
                    page.cleanup(() => {
                        if (Object.getOwnPropertyDescriptor(window, key)?.value !== value) return;
                        if (previous) Object.defineProperty(window, key, previous);
                        else delete window[key];
                    });
                });
            },
            cleanup(callback) {
                if (typeof callback !== 'function') return;
                if (active) cleanups.push(callback);
                else callback();
            },
            ready(callback) {
                const run = () => {
                    if (active && document.body === body) page.mount(() => callback.call(document, window.jQuery));
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', run, {once: true});
                    page.cleanup(() => document.removeEventListener('DOMContentLoaded', run));
                }
                else if (rendering) readyQueue.push(run);
                else page.setTimeout(run, 0);
            },
            on(events, ...args) {
                return page.onTarget(document, events, ...args);
            },
            onTarget(target, events, ...args) {
                if (!active) return;
                if (events === 'ready' || (target === window && events === 'load')) return page.ready(args[args.length - 1]);
                jq(target).on(events.split(/\s+/).map(event => event + namespace).join(' '), ...args);
                page.cleanup(() => jq(target).off(namespace));
            },
            onceTarget(target, events, ...args) {
                if (!active) return;
                jq(target).one(events.split(/\s+/).map(event => event + namespace).join(' '), ...args);
                page.cleanup(() => jq(target).off(namespace));
            },
            listen(target, event, callback, options) {
                if (!active) return;
                if ((target === document && event === 'DOMContentLoaded')
                    || (target === window && event === 'load')) return page.ready(() => callback.call(target, new Event(event)));
                target.addEventListener(event, callback, options);
                page.cleanup(() => target.removeEventListener(event, callback, options));
            },
            setTimeout(callback, delay, ...args) {
                if (!active) return null;
                const id = nativeTimeout(() => {
                    timeouts.delete(id);
                    if (active) page.mount(() => callback(...args));
                }, delay);
                timeouts.add(id);
                return id;
            },
            setInterval(callback, delay) {
                if (!active) return null;
                const id = nativeInterval(() => { if (active) page.mount(callback); }, delay);
                intervals.add(id);
                return id;
            },
            resource(resource, method) {
                page.cleanup(() => resource[method]());
                return resource;
            },
            request(request) {
                if (request && typeof request.abort === 'function') page.cleanup(() => request.abort());
                return request;
            },
            dispose() {
                if (!active) return;
                active = false;
                if (window.jQuery) jq(document).off(namespace);
                timeouts.forEach(id => window.clearTimeout(id));
                intervals.forEach(id => window.clearInterval(id));
                cleanups.reverse().forEach(callback => {
                    try { callback(); } catch (error) { console.error('Could not clean up page:', name, error); }
                });
                pages.delete(name);
            }
        };
        pages.set(name, page);
        return page;
    }

    // Dispose on rendering, not on visit: failed/canceled requests leave the current page usable.
    document.addEventListener('turbo:before-render', () => {
        Array.from(pages.values()).reverse().forEach(page => page.dispose());
        if (window.jQuery) {
            const jq = window.jQuery;
            // These plugins keep document/window listeners or popups outside their owner.
            if (jq.fn.select2) jq('select.select2-hidden-accessible').each(function () {
                if (jq(this).data('select2')) jq(this).select2('destroy');
            });
            if (jq.fn.daterangepicker || jq.fn.tooltip || jq.fn.popover) jq('input, button, a, [data-toggle]').each(function () {
                const element = jq(this);
                if (element.data('daterangepicker')) element.data('daterangepicker').remove();
                if (element.data('bs.tooltip')) element.tooltip('dispose');
                if (element.data('bs.popover')) element.popover('dispose');
            });
            if (jq.fn.owlCarousel) jq('.owl-loaded').trigger('destroy.owl.carousel');
            if (jq.fn.dataTable?.isDataTable) jq('table').each(function () {
                if (jq.fn.dataTable.isDataTable(this)) jq(this).DataTable().destroy();
            });
            if (window.jQuery.fn.modal) window.jQuery('.modal.show, .modal.in').modal('hide');
            window.jQuery('.modal-backdrop').remove();
        }
    });
    window.PanelPage = {
        create,
        startRender() { rendering = true; },
        finishRender() {
            rendering = false;
            const callbacks = readyQueue;
            readyQueue = [];
            callbacks.forEach(callback => {
                try { callback(); } catch (error) { console.error('Could not initialize page:', error); }
            });
        },
    };
})();
