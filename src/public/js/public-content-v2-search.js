(() => {
    'use strict';

    const root = document.getElementById('public-content-v2-app');
    if (!root || root.dataset.preview !== 'true') return;

    const siteSlug = root.dataset.site;
    if (!siteSlug) return;

    const toPreviewUrl = href => {
        const url = new URL(href, window.location.origin);
        if (url.origin !== window.location.origin) return null;

        const segments = url.pathname.split('/').filter(Boolean);
        if (segments[0] !== siteSlug) return null;
        if (segments[1] === 'content-v2') return url;

        const slug = segments.at(-1);
        if (!slug) return null;

        url.pathname = `/${siteSlug}/content-v2/${encodeURIComponent(slug)}`;
        return url;
    };

    document.addEventListener('click', event => {
        const card = event.target.closest?.('.search-result-card');
        if (!card) return;

        // Shop/deal results must continue to use their normal product routes.
        if (card.closest('#shopResultsGrid')) return;

        // Locked cards intentionally open the subscription modal.
        if (card.classList.contains('search-result-locked')) return;

        const readMore = card.querySelector('a.result-read-more[href]');
        if (!readMore) return;

        const previewUrl = toPreviewUrl(readMore.href);
        if (!previewUrl) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.assign(previewUrl.href);
    }, true);

    const observer = new MutationObserver(records => {
        records.forEach(record => {
            record.addedNodes.forEach(node => {
                if (!(node instanceof Element)) return;

                const cards = node.matches('.search-result-card')
                    ? [node]
                    : [...node.querySelectorAll('.search-result-card')];

                cards.forEach(card => {
                    if (card.closest('#shopResultsGrid') || card.classList.contains('search-result-locked')) {
                        return;
                    }

                    const readMore = card.querySelector('a.result-read-more[href]');
                    if (!readMore) return;

                    const previewUrl = toPreviewUrl(readMore.href);
                    if (previewUrl) {
                        readMore.href = previewUrl.href;
                    }
                });
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
})();
