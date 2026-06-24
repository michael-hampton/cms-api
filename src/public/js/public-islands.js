(function (window, document) {
    'use strict';

    const registry = {};
    const hydrated = new WeakSet();
    const scheduled = new WeakSet();

    function parseProps(root) {
        const raw = root.getAttribute('data-props');

        if (!raw) {
            return {};
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            console.warn('Invalid public island props', root, error);
            return {};
        }
    }

    function islandDefinition(root) {
        const type = root?.getAttribute('data-island');
        return type ? registry[type] : null;
    }

    function hydrateRoot(root) {
        if (!root || hydrated.has(root)) {
            return;
        }

        const definition = islandDefinition(root);

        if (!definition || typeof definition.hydrate !== 'function') {
            return;
        }

        definition.hydrate(root, parseProps(root));
        hydrated.add(root);
    }

    function visibleTarget(root) {
        if (root.getClientRects().length > 0) {
            return root;
        }

        return [...root.children].find(child => child.getClientRects().length > 0) ?? root;
    }

    function hydrateWhenVisible(root) {
        let observer = null;
        const events = ['click', 'focusin', 'pointerenter'];
        const hydrate = () => {
            events.forEach((event) => root.removeEventListener(event, hydrate));
            if (observer) {
                observer.disconnect();
            }
            hydrateRoot(root);
        };

        events.forEach((event) => root.addEventListener(event, hydrate, { once: true }));

        if (!('IntersectionObserver' in window)) {
            hydrateRoot(root);
            return;
        }

        const target = visibleTarget(root);
        observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                hydrate();
            });
        }, {
            rootMargin: '120px',
        });

        observer.observe(target);
    }

    function hydrateWhenIdle(root) {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(() => hydrateRoot(root));
            return;
        }

        window.setTimeout(() => hydrateRoot(root), 1);
    }

    function hydrateOnInteraction(root) {
        const events = ['click', 'focusin', 'pointerenter'];
        const hydrate = () => {
            events.forEach((event) => root.removeEventListener(event, hydrate));
            hydrateRoot(root);
        };

        events.forEach((event) => root.addEventListener(event, hydrate, { once: true }));
    }

    function schedule(root) {
        if (!root || scheduled.has(root) || root.getAttribute('data-stateful') !== 'true') {
            return;
        }

        const definition = islandDefinition(root);
        if (!definition || typeof definition.hydrate !== 'function') {
            return;
        }

        scheduled.add(root);

        switch (root.getAttribute('data-hydration') || 'load') {
            case 'none':
                return;
            case 'visible':
                hydrateWhenVisible(root);
                return;
            case 'idle':
                hydrateWhenIdle(root);
                return;
            case 'interaction':
                hydrateOnInteraction(root);
                return;
            case 'load':
            default:
                hydrateRoot(root);
        }
    }

    window.PublicIslands = {
        register(type, definition) {
            registry[type] = definition;
            this.scan();
        },

        hydrate: hydrateRoot,

        scan(root = document) {
            if (root.matches?.('[data-island]')) {
                schedule(root);
            }

            root.querySelectorAll?.('[data-island]').forEach(schedule);
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.PublicIslands.scan());
    } else {
        window.PublicIslands.scan();
    }
})(window, document);
