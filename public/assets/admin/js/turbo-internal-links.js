(() => {
    if (window.turboInternalLinksInitialized) return;
    window.turboInternalLinksInitialized = true;

    function markInternalNavigationLinks(root) {
        const links = root instanceof HTMLAnchorElement ? [root] : [];
        if (root.querySelectorAll) links.push(...root.querySelectorAll('a[href]'));
        links.forEach(link => {
            if (link.closest('[data-turbo="false"]') || link.hasAttribute('data-turbo')
                || link.hasAttribute('download') || link.hasAttribute('target')
                || link.hasAttribute('onclick') || link.hasAttribute('data-toggle')
                || link.hasAttribute('data-bs-toggle') || link.relList.contains('external')
                || link.getAttribute('role') === 'button') return;
            const href = link.getAttribute('href').trim();
            if (!href || href.startsWith('#')) return;
            const url = new URL(href, window.location.href);
            if (['http:', 'https:'].includes(url.protocol)
                && url.origin === window.location.origin && !url.hash) {
                link.setAttribute('data-turbo', 'true');
            }
        });
        const forms = root instanceof HTMLFormElement ? [root] : [];
        if (root.querySelectorAll) forms.push(...root.querySelectorAll('form'));
        forms.forEach(form => {
            if (form.method.toLowerCase() !== 'get' || form.hasAttribute('target')
                || form.hasAttribute('data-turbo') || form.closest('[data-turbo="false"]')) return;
            const url = new URL(form.action, window.location.href);
            if (url.origin === window.location.origin) form.setAttribute('data-turbo', 'true');
        });
    }
    markInternalNavigationLinks(document);
    document.addEventListener('turbo:load', () => markInternalNavigationLinks(document));
    new MutationObserver(mutations => mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
            if (node instanceof Element) markInternalNavigationLinks(node);
        });
    })).observe(document.documentElement, {childList: true, subtree: true});
})();
