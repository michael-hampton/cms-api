(() => {
    'use strict';

    const rewriteAuthorLinks = root => {
        const path = window.location.pathname;
        const match = path.match(/^\/([^/]+)\/content-v2(?:\/|$)/);
        if (!match) return;

        const site = match[1];

        root.querySelectorAll(`a.author-profile-link[href^="/${site}/authors/"]`).forEach(link => {
            const url = new URL(link.href, window.location.origin);
            link.href = `/${site}/content-v2${url.pathname.slice(site.length + 1)}${url.search}${url.hash}`;
        });
    };

    document.addEventListener('public-content:component-mounted', event => {
        if (event.detail.component.type === 'authors') {
            rewriteAuthorLinks(event.detail.element);
        }
    });

    document.addEventListener('public-content:document-composed', event => {
        rewriteAuthorLinks(event.detail.root);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => rewriteAuthorLinks(document));
    } else {
        rewriteAuthorLinks(document);
    }
})();
