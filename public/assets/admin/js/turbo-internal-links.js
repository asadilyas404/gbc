(() => {
    function markInternalNavigationLinks(root) {
        const links = [];

        if (root instanceof HTMLAnchorElement) {
            links.push(root);
        }

        if (root.querySelectorAll) {
            links.push(...root.querySelectorAll('a[href]'));
        }

        links.forEach((link) => {
            if (link.hasAttribute('data-turbo')
                || link.hasAttribute('download')
                || link.hasAttribute('target')
                || link.hasAttribute('onclick')
                || link.hasAttribute('data-toggle')
                || link.hasAttribute('data-bs-toggle')
                || link.relList.contains('external')
                || link.getAttribute('role') === 'button') {
                return;
            }

            try {
                const href = link.getAttribute('href').trim();
                if (!href) {
                    return;
                }

                const url = new URL(href, window.location.href);
                if (url.origin === window.location.origin && url.hash === '') {
                    link.setAttribute('data-turbo', 'true');
                }
            } catch (error) {
                console.warn('Could not inspect a navigation link for Turbo.', error);
            }
        });
    }

    markInternalNavigationLinks(document);

    document.addEventListener('turbo:load', () => markInternalNavigationLinks(document));

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element) {
                    markInternalNavigationLinks(node);
                }
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
