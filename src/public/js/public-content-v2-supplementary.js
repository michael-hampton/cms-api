(() => {
    'use strict';

    class SupplementaryStore {
        #state = Object.freeze({status: 'idle', widgets: null, error: null});
        #listeners = new Set();

        subscribe(listener) {
            this.#listeners.add(listener);
            listener(this.#state);
            return () => this.#listeners.delete(listener);
        }

        setState(patch) {
            this.#state = Object.freeze({...this.#state, ...patch});
            this.#listeners.forEach(listener => listener(this.#state));
        }
    }

    class SupplementaryApi {
        constructor(url, fetchClient = window.fetch.bind(window)) {
            this.url = url;
            this.fetchClient = fetchClient;
        }

        async load() {
            const response = await this.fetchClient(this.url, {
                credentials: 'same-origin',
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            });
            const payload = await response.json();
            if (!response.ok || !payload.data) {
                throw new Error(payload.message ?? 'Unable to load page widgets.');
            }
            return payload.data.widgets ?? {};
        }
    }

    class HtmlEscape {
        static value(value) {
            const element = document.createElement('div');
            element.textContent = String(value ?? '');
            return element.innerHTML;
        }
    }

    class SupplementaryView {
        render(root, state) {
            if (state.status === 'loading') {
                root.innerHTML = '<p class="public-content-v2-widget-status">Loading recommendations…</p>';
                return;
            }
            if (state.status === 'error') {
                root.innerHTML = '';
                return;
            }
            if (state.status === 'loaded') {
                root.innerHTML = this.widgets(state.widgets ?? {});
            }
        }

        widgets(widgets) {
            return [
                this.activityFeed(widgets.activity_feed ?? []),
                this.trending(widgets.trending ?? []),
                this.products(widgets.products ?? []),
                this.newsletter(widgets.newsletter ?? null),
                this.deals(widgets.deals ?? []),
                this.contributors(widgets.guest_contributors ?? []),
            ].join('');
        }

        activityFeed(items) {
            if (!items.length) return '';
            return `<section class="public-content-v2-widget"><h2>Activity Feed</h2><div class="public-content-v2-list">${items.map(item => `<article><a href="${HtmlEscape.value(item.url)}"><strong>${HtmlEscape.value(item.title)}</strong></a>${item.category ? `<span>${HtmlEscape.value(item.category)}</span>` : ''}<small>${Number(item.comment_count ?? 0)} comments</small></article>`).join('')}</div></section>`;
        }

        trending(items) {
            if (!items.length) return '';
            return `<section class="public-content-v2-widget"><h2>Trending Now</h2><div class="public-content-v2-card-grid">${items.map((item, index) => `<article>${item.image ? `<img src="${HtmlEscape.value(item.image)}" alt="">` : ''}<span>#${index + 1}</span><h3><a href="${HtmlEscape.value(item.url)}">${HtmlEscape.value(item.title)}</a></h3><small>${Number(item.like_count ?? 0)} likes · ${Number(item.comment_count ?? 0)} comments</small></article>`).join('')}</div></section>`;
        }

        products(items) {
            if (!items.length) return '';
            return `<section class="public-content-v2-widget"><h2>Products</h2><div class="public-content-v2-card-grid">${items.map(item => `<article>${item.image ? `<img src="${HtmlEscape.value(item.image)}" alt="">` : ''}<h3>${HtmlEscape.value(item.name)}</h3>${item.sale_price ?? item.price ? `<p>${HtmlEscape.value(item.sale_price ?? item.price)}</p>` : ''}</article>`).join('')}</div></section>`;
        }

        deals(items) {
            if (!items.length) return '';
            return `<section class="public-content-v2-widget"><h2>Today’s Deals</h2><div class="public-content-v2-card-grid">${items.map(item => `<article><h3>${HtmlEscape.value(item.title ?? item.name ?? 'Deal')}</h3>${item.description ? `<p>${HtmlEscape.value(item.description)}</p>` : ''}</article>`).join('')}</div></section>`;
        }

        contributors(items) {
            if (!items.length) return '';
            return `<section class="public-content-v2-widget"><h2>Guest Contributors</h2><div class="public-content-v2-card-grid">${items.map(item => `<article>${item.image ? `<img src="${HtmlEscape.value(item.image)}" alt="">` : ''}<h3>${HtmlEscape.value(item.name)}</h3>${item.bio ? `<p>${HtmlEscape.value(item.bio)}</p>` : ''}</article>`).join('')}</div></section>`;
        }

        newsletter(config) {
            if (!config?.enabled) return '';
            return '<section class="public-content-v2-widget public-content-v2-newsletter"><h2>Stay informed</h2><p>Get the latest stories delivered to your inbox.</p><button type="button" data-action="open-newsletter">Sign up</button></section>';
        }
    }

    class SupplementaryApp {
        constructor(root, api, store, view) {
            this.root = root;
            this.api = api;
            this.store = store;
            this.view = view;
            this.onClick = this.onClick.bind(this);
        }

        start() {
            this.unsubscribe = this.store.subscribe(state => this.view.render(this.root, state));
            this.root.addEventListener('click', this.onClick);
            this.load();
        }

        async load() {
            this.store.setState({status: 'loading', error: null});
            try {
                this.store.setState({status: 'loaded', widgets: await this.api.load()});
            } catch (error) {
                this.store.setState({status: 'error', error});
            }
        }

        onClick(event) {
            if (event.target.closest('[data-action="open-newsletter"]')) {
                document.dispatchEvent(new CustomEvent('newsletter:open'));
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('public-content-v2-supplementary');
        const contentRoot = document.getElementById('public-content-v2-app');
        if (!root || !contentRoot?.dataset.apiUrl) return;
        new SupplementaryApp(
            root,
            new SupplementaryApi(contentRoot.dataset.apiUrl),
            new SupplementaryStore(),
            new SupplementaryView(),
        ).start();
    });
})();
