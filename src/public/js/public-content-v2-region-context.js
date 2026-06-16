(() => {
    'use strict';

    const hydrate = root => {
        root.querySelectorAll?.('[data-region-select]').forEach(select => {
            if (select.dataset.hydrated === 'true') return;
            select.dataset.hydrated = 'true';

            select.addEventListener('change', () => {
                const target = select.value;
                if (!target) return;
                window.location.assign(target);
            });
        });
    };

    document.addEventListener('public-content:component-mounted', event => {
        if (event.detail.component.type === 'region-context') {
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
