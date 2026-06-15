(() => {
    'use strict';

    const root = document.getElementById('public-content-v2-app');
    if (!root || root.dataset.preview !== 'true') return;

    const siteSlug = root.dataset.site;
    const originalCreateResultCard = window.createResultCard;

    if (typeof originalCreateResultCard !== 'function' || !siteSlug) return;

    const toPreviewUrl = page => {
        const raw = page?.url || `/${page?.slug ?? ''}`;
        const url = new URL(raw, window.location.origin);
        const segments = url.pathname.split('/').filter(Boolean);
        const slug = page?.slug || segments.at(-1);

        if (!slug) return null;

        url.pathname = `/${siteSlug}/content-v2/${encodeURIComponent(slug)}`;
        return url.href;
    };

    window.createResultCard = function createPreviewResultCard(page, isShop = false) {
        const card = originalCreateResultCard(page, isShop);

        if (isShop || page?.page_type === 'deal' || page?.page_type === 'product' || !page?.can_view) {
            return card;
        }

        const previewUrl = toPreviewUrl(page);
        if (!previewUrl) return card;

        card.onclick = event => {
            if (event.target.closest('a, button')) return;
            window.location.assign(previewUrl);
        };

        card.querySelectorAll('a.result-read-more').forEach(link => {
            link.href = previewUrl;
        });

        return card;
    };
})();
