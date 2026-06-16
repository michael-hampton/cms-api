(() => {
    'use strict';

    class PublicContentPreviewLinkNormalizer {
        constructor(root) {
            this.root = root;
            this.siteSlug = root.dataset.site;
            this.onDocumentClick = this.onDocumentClick.bind(this);
        }

        start() {
            this.normalize(document);

            document.addEventListener('public-content:document-composed', event => {
                this.normalize(event.detail.root);
            });

            // Header search results are rendered dynamically outside the V2 root.
            // Intercept at document level so preview navigation remains in V2.
            document.addEventListener('click', this.onDocumentClick, true);

            const observer = new MutationObserver(records => {
                records.forEach(record => {
                    record.addedNodes.forEach(node => {
                        if (!(node instanceof Element)) return;
                        this.normalize(node);
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true,
            });
        }

        onDocumentClick(event) {
            const link = event.target.closest?.('a[href]');
            if (!link || link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const previewUrl = this.toPreviewUrl(link.href);
            if (!previewUrl || previewUrl.href === link.href) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            window.location.assign(previewUrl.href);
        }

        normalize(container) {
            if (!this.siteSlug) return;

            const links = container.matches?.('a[href]')
                ? [container]
                : [...container.querySelectorAll?.('a[href]') ?? []];

            links.forEach(link => {
                const previewUrl = this.toPreviewUrl(link.href);
                if (previewUrl) {
                    link.href = previewUrl.href;
                }
            });
        }

        toPreviewUrl(href) {
            if (!this.siteSlug) return null;

            const url = new URL(href, window.location.origin);
            if (url.origin !== window.location.origin) return null;

            const segments = url.pathname.split('/').filter(Boolean);
            if (segments[0] !== this.siteSlug) return null;
            if (segments[1] === 'content-v2') return null;

            if (segments.length === 1) {
                url.pathname = `/${this.siteSlug}/content-v2`;
                return url;
            }

            const aliases = {
                author: 'authors',
                category: 'categories',
                tag: 'tags',
            };

            const directory = aliases[segments[1]] ?? segments[1];
            if (['authors', 'categories', 'tags'].includes(directory)) {
                const suffix = segments.slice(2).map(encodeURIComponent).join('/');
                url.pathname = `/${this.siteSlug}/content-v2/${directory}${suffix ? `/${suffix}` : ''}`;
                return url;
            }

            const reserved = new Set([
                'member',
                'open-collab',
                'shop',
                'cart',
                'checkout',
                'search',
                'api',
                'assets',
                'images',
                'subscription-confirmation',
            ]);

            // Canonical content pages are /{site}/{slug}. Search results use
            // this shape, so convert only that shape and leave application routes alone.
            if (segments.length === 2 && !reserved.has(segments[1])) {
                url.pathname = `/${this.siteSlug}/content-v2/${encodeURIComponent(segments[1])}`;
                return url;
            }

            return null;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('public-content-v2-app');
        if (!root || root.dataset.preview !== 'true') return;

        new PublicContentPreviewLinkNormalizer(root).start();
    });
})();
