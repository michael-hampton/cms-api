(() => {
    'use strict';

    class DirectoryStore {
        #state = Object.freeze({status: 'idle', document: null, error: null});
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

    class DirectoryApi {
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
                throw new Error(payload.message ?? 'Unable to load this directory.');
            }
            return payload.data;
        }
    }

    class EscapeHtml {
        static value(value) {
            const node = document.createElement('div');
            node.textContent = String(value ?? '');
            return node.innerHTML;
        }
    }

    class DirectoryView {
        render(root, state) {
            if (state.status === 'loading') {
                root.innerHTML = '<div class="public-directory-status"><div class="public-directory-spinner"></div><p>Loading…</p></div>';
                return;
            }
            if (state.status === 'error') {
                root.innerHTML = `<div class="public-directory-error"><h1>Page unavailable</h1><p>${EscapeHtml.value(state.error?.message)}</p><button type="button" data-action="retry">Try again</button></div>`;
                return;
            }
            if (state.status === 'loaded') {
                root.innerHTML = state.document.entity
                    ? this.detail(state.document)
                    : this.index(state.document);
            }
        }

        index(document) {
            const entities = document.entities ?? [];
            return `<section class="directory-page">
                <header class="directory-hero"><p class="directory-eyebrow">Explore</p><h1>${EscapeHtml.value(document.title)}</h1><p>Browse all published ${EscapeHtml.value(document.type)} profiles.</p></header>
                <div class="directory-grid">${entities.map(entity => this.entityCard(entity)).join('')}</div>
                ${entities.length ? '' : '<div class="directory-empty"><h2>Nothing here yet</h2><p>Check back soon.</p></div>'}
                ${this.pagination(document.pagination)}
            </section>`;
        }

        detail(document) {
            const entity = document.entity;
            const meta = entity.meta ?? {};
            const pages = document.pages ?? [];
            return `<section class="directory-page">
                <header class="directory-hero directory-hero--detail">
                    ${entity.image ? `<img class="directory-avatar" src="${EscapeHtml.value(entity.image)}" alt="${EscapeHtml.value(entity.name)}">` : entity.icon ? `<div class="directory-icon">${entity.icon}</div>` : ''}
                    <p class="directory-eyebrow">${EscapeHtml.value(entity.type)}</p>
                    <h1>${entity.type === 'tag' ? '#' : ''}${EscapeHtml.value(entity.name)}</h1>
                    ${entity.description ? `<p>${EscapeHtml.value(entity.description)}</p>` : ''}
                    <div class="directory-stats"><span><strong>${Number(document.stats?.page_count ?? 0)}</strong> articles</span>${Number(document.stats?.related_count ?? 0) ? `<span><strong>${Number(document.stats.related_count)}</strong> subcategories</span>` : ''}</div>
                </header>
                ${this.authorDetails(entity.type, meta)}
                ${this.related(document.related ?? [])}
                <section class="directory-results">
                    <div class="directory-section-heading"><h2>Latest articles</h2><span>${Number(document.stats?.page_count ?? 0)} results</span></div>
                    <div class="directory-page-grid">${pages.map(page => this.pageCard(page)).join('')}</div>
                    ${pages.length ? '' : '<div class="directory-empty"><h2>No articles yet</h2><p>There is no published content for this page yet.</p></div>'}
                    ${this.pagination(document.pagination)}
                </section>
            </section>`;
        }

        pagination(pagination) {
            if (!pagination || pagination.last_page <= 1) return '';

            const current = Number(pagination.current_page);
            const last = Number(pagination.last_page);
            const start = Math.max(1, current - 2);
            const end = Math.min(last, current + 2);
            const buttons = [];

            for (let page = start; page <= end; page++) {
                buttons.push(`<button type="button" data-action="page" data-page="${page}" class="${page === current ? 'active' : ''}" aria-current="${page === current ? 'page' : 'false'}">${page}</button>`);
            }

            return `<nav class="public-directory-pagination" aria-label="Directory pagination">
                <button type="button" data-action="previous-page" ${current <= 1 ? 'disabled' : ''}>Previous</button>
                ${buttons.join('')}
                <button type="button" data-action="next-page" ${current >= last ? 'disabled' : ''}>Next</button>
            </nav>`;
        }

        entityCard(entity) {
            return `<a class="directory-card" href="${EscapeHtml.value(entity.url)}">${entity.image ? `<img src="${EscapeHtml.value(entity.image)}" alt="">` : `<div class="directory-card__mark">${entity.icon ?? (entity.type === 'tag' ? '#' : EscapeHtml.value(entity.name).slice(0, 1))}</div>`}<div><span>${EscapeHtml.value(entity.type)}</span><h2>${EscapeHtml.value(entity.name)}</h2>${entity.description ? `<p>${EscapeHtml.value(entity.description)}</p>` : ''}</div></a>`;
        }

        pageCard(page) {
            return `<article class="directory-article-card">${page.image ? `<a href="${EscapeHtml.value(page.url)}"><img src="${EscapeHtml.value(page.image)}" alt="${EscapeHtml.value(page.title)}"></a>` : ''}<div class="directory-article-card__body"><div class="directory-taxonomy">${(page.categories ?? []).map(item => `<a href="${EscapeHtml.value(item.url)}">${EscapeHtml.value(item.name)}</a>`).join('')}${(page.tags ?? []).map(item => `<a href="${EscapeHtml.value(item.url)}">#${EscapeHtml.value(item.name)}</a>`).join('')}</div><h3><a href="${EscapeHtml.value(page.url)}">${EscapeHtml.value(page.title)}</a></h3>${page.summary ? `<p>${EscapeHtml.value(page.summary)}</p>` : ''}${(page.authors ?? []).length ? `<div class="directory-authors">By ${(page.authors ?? []).map(item => `<a href="${EscapeHtml.value(item.url)}">${EscapeHtml.value(item.name)}</a>`).join(', ')}</div>` : ''}</div></article>`;
        }

        related(items) {
            if (!items.length) return '';
            return `<section class="directory-related"><div class="directory-section-heading"><h2>Subcategories</h2></div><div class="directory-chip-list">${items.map(item => `<a href="${EscapeHtml.value(item.url)}">${item.icon ?? ''}${EscapeHtml.value(item.name)}</a>`).join('')}</div></section>`;
        }

        authorDetails(type, meta) {
            if (type !== 'author') return '';
            const groups = [
                ['Expertise', meta.expertise],
                ['Location', Array.isArray(meta.location) ? meta.location.join(', ') : meta.location],
                ['Education', Array.isArray(meta.education) ? meta.education.join(', ') : meta.education],
                ['Awards', Array.isArray(meta.awards) ? meta.awards.join(', ') : meta.awards],
                ['Experience', meta.years_of_experience ? `${meta.years_of_experience} years` : null],
            ].filter(([, value]) => value);
            if (!groups.length && !meta.website && !meta.twitter && !meta.linkedin && !meta.facebook) return '';
            return `<section class="directory-profile-grid">${groups.map(([label, value]) => `<article><span>${EscapeHtml.value(label)}</span><p>${EscapeHtml.value(value)}</p></article>`).join('')}<article><span>Connect</span><div class="directory-links">${meta.website ? `<a href="${EscapeHtml.value(meta.website)}" rel="noopener" target="_blank">Website</a>` : ''}${meta.twitter ? `<a href="https://twitter.com/${EscapeHtml.value(meta.twitter)}" rel="noopener" target="_blank">Twitter</a>` : ''}${meta.linkedin ? `<a href="${EscapeHtml.value(meta.linkedin)}" rel="noopener" target="_blank">LinkedIn</a>` : ''}${meta.facebook ? `<a href="${EscapeHtml.value(meta.facebook)}" rel="noopener" target="_blank">Facebook</a>` : ''}</div></article></section>`;
        }
    }

    class DirectoryApp {
        constructor(root, api, store, view) {
            this.root = root;
            this.api = api;
            this.store = store;
            this.view = view;
            this.siteSlug = root.dataset.site;
            this.preview = root.dataset.preview === 'true';
            this.perPage = Math.max(1, Number(root.dataset.perPage || 12));
            this.currentPage = Math.max(1, Number(new URLSearchParams(window.location.search).get('page') || 1));
            this.document = null;
        }

        start() {
            this.store.subscribe(state => this.view.render(this.root, state));
            this.root.addEventListener('click', event => this.onClick(event));
            this.load();
        }

        onClick(event) {
            if (event.target.closest('[data-action="retry"]')) {
                this.load();
                return;
            }

            const pageButton = event.target.closest('[data-page]');
            if (pageButton) {
                this.goToPage(Number(pageButton.dataset.page));
                return;
            }

            if (event.target.closest('[data-action="previous-page"]')) {
                this.goToPage(this.currentPage - 1);
                return;
            }

            if (event.target.closest('[data-action="next-page"]')) {
                this.goToPage(this.currentPage + 1);
                return;
            }

            if (!this.preview) return;

            const link = event.target.closest('a[href]');
            if (!link || link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey) return;

            const url = new URL(link.href, window.location.origin);
            if (url.origin !== window.location.origin) return;

            const target = this.toPreviewPath(url.pathname);
            if (!target || target === url.pathname) return;

            event.preventDefault();
            window.location.assign(`${target}${url.search}${url.hash}`);
        }

        goToPage(page) {
            if (!this.document) return;

            const total = this.items().length;
            const last = Math.max(1, Math.ceil(total / this.perPage));
            this.currentPage = Math.max(1, Math.min(last, page));

            const params = new URLSearchParams(window.location.search);
            if (this.currentPage > 1) params.set('page', String(this.currentPage));
            else params.delete('page');
            const query = params.toString();
            window.history.pushState({}, '', `${window.location.pathname}${query ? '?' + query : ''}${window.location.hash}`);

            this.publish();
            this.root.scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        items() {
            if (!this.document) return [];
            return this.document.entity ? (this.document.pages ?? []) : (this.document.entities ?? []);
        }

        publish() {
            const items = this.items();
            const total = items.length;
            const lastPage = Math.max(1, Math.ceil(total / this.perPage));
            this.currentPage = Math.min(this.currentPage, lastPage);
            const offset = (this.currentPage - 1) * this.perPage;
            const sliced = items.slice(offset, offset + this.perPage);
            const document = {
                ...this.document,
                pagination: {
                    current_page: this.currentPage,
                    per_page: this.perPage,
                    total,
                    last_page: lastPage,
                },
            };

            if (document.entity) document.pages = sliced;
            else document.entities = sliced;

            this.store.setState({status: 'loaded', document, error: null});
        }

        toPreviewPath(pathname) {
            const segments = pathname.split('/').filter(Boolean);
            if (segments[0] !== this.siteSlug) return null;
            if (segments.length === 1) return `/${this.siteSlug}/content-v2`;
            if (segments[1] === 'content-v2') return pathname;
            if (['authors', 'categories', 'tags'].includes(segments[1])) {
                return `/${this.siteSlug}/content-v2/${segments.slice(1).join('/')}`;
            }
            if (segments.length === 2) {
                return `/${this.siteSlug}/content-v2/${encodeURIComponent(segments[1])}`;
            }
            return null;
        }

        async load() {
            this.store.setState({status: 'loading', error: null});
            try {
                this.document = await this.api.load();
                this.publish();
            } catch (error) {
                this.store.setState({status: 'error', error});
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('public-directory-app');
        if (!root?.dataset.apiUrl) return;
        new DirectoryApp(root, new DirectoryApi(root.dataset.apiUrl), new DirectoryStore(), new DirectoryView()).start();
    });
})();
