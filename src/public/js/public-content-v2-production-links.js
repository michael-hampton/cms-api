(() => {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('public-content-v2-app');
        if (!root || root.dataset.preview === 'true') return;

        root.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (!link || link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey) {
                return;
            }

            // Stop the preview router in public-content-v2.js from rewriting
            // canonical production URLs to /content-v2/... . The browser's
            // default link navigation still runs because preventDefault is not called.
            event.stopImmediatePropagation();
        }, true);
    });
})();
