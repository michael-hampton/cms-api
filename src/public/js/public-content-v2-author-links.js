(() => {
    'use strict';

    const rewriteAuthorLinks = root => {
        const app = document.getElementById('public-content-v2-app');
        const site = app?.dataset.site;

        if (!site) return;

        root.querySelectorAll(`a.author-profile-link[href^="/${site}/authors/"]`).forEach(link => {
            const url = new URL(link.href, window.location.origin);
            const authorPath = url.pathname.slice(`/${site}`.length);

            link.href = `/${site}/content-v2${authorPath}${url.search}${url.hash}`;
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
