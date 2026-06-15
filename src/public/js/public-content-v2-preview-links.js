(() => {
    'use strict';

    class PublicContentPreviewLinkNormalizer {
        constructor(root) {
            this.root = root;
            this.siteSlug = root.dataset.site;
        }

        start() {
            this.normalize(this.root);

            document.addEventListener('public-content:document-composed', event => {
                this.normalize(event.detail.root);
            });
        }

        normalize(container) {
            if (!this.siteSlug) return;

            container.querySelectorAll('a[href]').forEach(link => {
                const url = new URL(link.href, window.location.origin);
                if (url.origin !== window.location.origin) return;

                const segments = url.pathname.split('/').filter(Boolean);
                if (segments[0] !== this.siteSlug) return;
                if (segments[1] === 'content-v2') return;

                if (!['authors', 'categories', 'tags', 'author', 'category', 'tag'].includes(segments[1])) {
                    return;
                }

                const plural = {
                    author: 'authors',
                    category: 'categories',
                    tag: 'tags',
                }[segments[1]] ?? segments[1];

                const suffix = segments.slice(2).map(encodeURIComponent).join('/');
                const path = `/${this.siteSlug}/content-v2/${plural}${suffix ? `/${suffix}` : ''}`;
                link.href = `${path}${url.search}${url.hash}`;
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('public-content-v2-app');
        if (!root) return;

        new PublicContentPreviewLinkNormalizer(root).start();
    });
})();
