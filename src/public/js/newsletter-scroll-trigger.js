(() => {
    'use strict';

    const observed = new WeakSet();

    const sessionKey = siteId => `newsletter_auto_opened_${siteId}`;

    const hasAutoOpened = siteId => {
        try {
            return window.sessionStorage.getItem(sessionKey(siteId)) === '1';
        } catch (error) {
            return false;
        }
    };

    const markAutoOpened = siteId => {
        try {
            window.sessionStorage.setItem(sessionKey(siteId), '1');
        } catch (error) {
            // The in-memory dataset guard still prevents repeated opening on this render.
        }
    };

    const openAutomatically = teaser => {
        if (!teaser || teaser.dataset.autoOpened === 'true') return;

        const siteId = teaser.dataset.siteId;
        if (!siteId || hasAutoOpened(siteId)) return;

        const modal = document.getElementById(`nl-modal-${siteId}`);
        if (!modal || !modal.hasAttribute('hidden')) return;

        teaser.dataset.autoOpened = 'true';
        markAutoOpened(siteId);
        modal.removeAttribute('hidden');

        window.setTimeout(() => {
            modal.querySelector('input[type="email"]')?.focus();
        }, 50);
    };

    const observe = teaser => {
        if (!teaser || observed.has(teaser)) return;
        observed.add(teaser);

        if (!('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver(entries => {
            for (const entry of entries) {
                if (!entry.isIntersecting || entry.intersectionRatio < 0.25) continue;
                openAutomatically(teaser);
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

    document.addEventListener('public-content:component-mounted', event => {
        if (event.detail.component.type === 'newsletter-signup-widget') {
            hydrate(event.detail.element);
        }
    });

    document.addEventListener('public-content:document-composed', event => {
        hydrate(event.detail.root);
    });

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
