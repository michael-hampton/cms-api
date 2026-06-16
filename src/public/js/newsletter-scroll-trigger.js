(() => {
    'use strict';

    const observed = new WeakSet();

    const open = teaser => {
        if (!teaser) return;

        const siteId = teaser.dataset.siteId;
        const modal = document.getElementById(`nl-modal-${siteId}`);
        if (!modal || !modal.hasAttribute('hidden')) return;

        modal.removeAttribute('hidden');
        window.setTimeout(() => modal.querySelector('input[type="email"]')?.focus(), 50);
    };

    const observe = teaser => {
        if (!teaser || observed.has(teaser)) return;
        observed.add(teaser);

        teaser.addEventListener('mouseenter', () => open(teaser));
        teaser.addEventListener('pointerenter', () => open(teaser));

        if (!('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver(entries => {
            for (const entry of entries) {
                if (!entry.isIntersecting || entry.intersectionRatio < 0.25) continue;
                open(teaser);
                observer.disconnect();
                break;
            }
        }, {
            threshold: [0.25],
            rootMargin: '0px 0px -5% 0px',
        });

        observer.observe(teaser);
    };

    const hydrate = root => {
        if (root?.matches?.('.nl-teaser[data-site-id]')) observe(root);
        root?.querySelectorAll?.('.nl-teaser[data-site-id]').forEach(observe);
    };

    document.addEventListener('mouseover', event => {
        const teaser = event.target.closest?.('.nl-teaser[data-site-id]');
        if (teaser) open(teaser);
    });

    document.addEventListener('public-content:component-mounted', event => {
        if (event.detail.component.type === 'newsletter-signup-widget') {
            hydrate(event.detail.element);
        }
    });

    document.addEventListener('public-content:document-composed', event => hydrate(event.detail.root));

    new MutationObserver(() => hydrate(document)).observe(document.documentElement, {
        childList: true,
        subtree: true,
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => hydrate(document));
    } else {
        hydrate(document);
    }
})();
