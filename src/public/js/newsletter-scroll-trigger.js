(() => {
    'use strict';

    const observed = new WeakSet();

    const isDismissed = teaser => {
        const key = teaser.dataset.storageKey;
        if (!key) return false;

        try {
            return window.localStorage.getItem(key) === '1';
        } catch (error) {
            return false;
        }
    };

    const open = teaser => {
        if (teaser.dataset.scrollOpened === 'true' || isDismissed(teaser)) return;

        const siteId = teaser.dataset.siteId;
        const modal = document.getElementById(`nl-modal-${siteId}`);
        if (!modal) return;

        teaser.dataset.scrollOpened = 'true';
        modal.removeAttribute('hidden');

        const input = modal.querySelector('input[type="email"]');
        window.setTimeout(() => input?.focus(), 50);
    };

    const observe = teaser => {
        if (!teaser || observed.has(teaser)) return;
        observed.add(teaser);

        if (!('IntersectionObserver' in window)) {
            return;
        }

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting || entry.intersectionRatio < 0.35) return;
                open(teaser);
                observer.disconnect();
            });
        }, {
            threshold: [0.35],
            rootMargin: '0px 0px -10% 0px',
        });

        observer.observe(teaser);
    };

    const hydrate = root => {
        if (root?.matches?.('.nl-teaser')) observe(root);
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => hydrate(document));
    } else {
        hydrate(document);
    }
})();
