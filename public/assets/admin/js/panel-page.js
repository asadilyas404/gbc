(() => {
    if (window.PanelPage) return;
    const pages = new Map();

    function create(name) {
        if (pages.has(name)) pages.get(name).dispose();
        const body = document.body;
        const cleanups = [];
        const timeouts = new Set();
        const intervals = new Set();
        const jq = window.jQuery;
        const namespace = '.panelPage_' + name;
        let active = true;
        const page = {
            expose(exports) {
                Object.assign(window, exports);
                page.cleanup(() => {
                    Object.entries(exports).forEach(([key, value]) => {
                        if (window[key] === value) delete window[key];
                    });
                });
            },
            cleanup(callback) {
                if (typeof callback !== 'function') return;
                if (active) cleanups.push(callback);
                else callback();
            },
            ready(callback) {
                const run = () => { if (active && document.body === body) callback(); };
                if (document.readyState === 'loading') page.listen(document, 'DOMContentLoaded', run, {once: true});
                else page.setTimeout(run, 0);
            },
            on(events, ...args) {
                if (!active) return;
                jq(document).on(events.split(/\s+/).map(event => event + namespace).join(' '), ...args);
            },
            listen(target, event, callback, options) {
                if (!active) return;
                target.addEventListener(event, callback, options);
                page.cleanup(() => target.removeEventListener(event, callback, options));
            },
            setTimeout(callback, delay, ...args) {
                if (!active) return null;
                const id = window.setTimeout(() => {
                    timeouts.delete(id);
                    if (active) callback(...args);
                }, delay);
                timeouts.add(id);
                return id;
            },
            setInterval(callback, delay) {
                if (!active) return null;
                const id = window.setInterval(() => { if (active) callback(); }, delay);
                intervals.add(id);
                return id;
            },
            dispose() {
                if (!active) return;
                active = false;
                if (jq) jq(document).off(namespace);
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
        pages.forEach(page => page.dispose());
        if (window.jQuery) {
            if (window.jQuery.fn.modal) window.jQuery('.modal.show, .modal.in').modal('hide');
            window.jQuery('.modal-backdrop').remove();
        }
    });
    window.PanelPage = {create};
})();
