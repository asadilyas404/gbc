(() => {
    if (window.panelNavigationInitialized) return;
    window.panelNavigationInitialized = true;
    const loadedLibraries = new Set();

    function libraryKey(script) {
        const url = new URL(script.src, window.location.href);
        return url.hostname === 'maps.googleapis.com' && url.pathname === '/maps/api/js'
            ? 'google-maps' : url.href;
    }

    function isPageScript(script) {
        return script.hasAttribute('data-panel-reload') || /\/js\/(view-pages\/|custom\.js)/.test(script.src);
    }

    function rememberLibraries() {
        document.querySelectorAll('script[src]').forEach(script => {
            if (!isPageScript(script)) loadedLibraries.add(libraryKey(script));
        });
    }

    async function activate(script) {
        if (!script.isConnected) return;
        const originalType = script.getAttribute('data-panel-script-type');
        const key = script.src ? libraryKey(script) : null;
        if (key && !isPageScript(script) && loadedLibraries.has(key)) {
            // Google Maps is shared, but each new page supplies its own map callback.
            if (key === 'google-maps') {
                const url = new URL(script.src);
                const libraries = (url.searchParams.get('libraries') || '').split(',').filter(Boolean);
                if (window.google?.maps?.importLibrary) {
                    await Promise.all(libraries.map(name => window.google.maps.importLibrary(name)));
                }
                const callback = url.searchParams.get('callback');
                const init = callback && callback.split('.').reduce((value, name) => value?.[name], window);
                if (typeof init === 'function') init();
            }
            return;
        }
        const active = document.createElement('script');
        for (const {name, value} of Array.from(script.attributes)) {
            if (name !== 'type' && name !== 'data-panel-script-type') active.setAttribute(name, value);
        }
        if (originalType) active.type = originalType;
        active.async = false;
        active.textContent = script.textContent;
        const completion = active.src || active.type === 'module' ? new Promise((resolve, reject) => {
            active.addEventListener('load', resolve, {once: true});
            active.addEventListener('error', () => reject(new Error('Could not load ' + active.src)), {once: true});
        }) : Promise.resolve();
        script.replaceWith(active);
        await completion;
        if (key && !isPageScript(active)) loadedLibraries.add(key);
    }

    document.addEventListener('turbo:before-render', event => {
        const render = event.detail.render;
        event.detail.newBody.querySelectorAll('script').forEach(script => {
            const type = script.getAttribute('type') || '';
            if (!['', 'text/javascript', 'application/javascript', 'module'].includes(type)) return;
            script.setAttribute('data-panel-script-type', type);
            script.type = 'application/x-panel-script';
        });
        event.detail.render = async (currentBody, newBody) => {
            window.PanelPage.startRender();
            try {
                await render(currentBody, newBody);
                const scripts = Array.from(document.body.querySelectorAll('script[type="application/x-panel-script"]'));
                const callbacks = [];
                for (const script of scripts) {
                    if (script.src && libraryKey(script) === 'google-maps'
                        && new URL(script.src).searchParams.has('callback')) callbacks.push(script);
                    else await activate(script);
                }
                // Callback APIs must load after the page has exported their callbacks.
                for (const script of callbacks) await activate(script);
            } finally {
                window.PanelPage.finishRender();
            }
        };
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', rememberLibraries, {once: true});
    else rememberLibraries();
})();
