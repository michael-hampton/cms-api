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

        async load(filters) {
            const query = this.buildQuery(filters);
            const target = query ? `${this.url}${this.url.includes('?') ? '&' : '?'}${query}` : this.url;
            const response = await this.fetchClient(target, {
                credentials: 'same-origin',
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            });
            const payload = await response.json();
            if (!response.ok || !payload.data) throw new Error(payload.message ?? 'Unable to load this directory.');
            return payload.data;
        }

        buildQuery(filters) {
            const params = new URLSearchParams();
            if (filters.q) params.set('q', filters.q);
            if (filters.sort) params.set('sort', filters.sort);
            if (filters.page > 1) params.set('page', String(filters.page));
            if (filters.perPage) params.set('per_page', String(filters.perPage));
            Object.entries(filters.facets ?? {}).forEach(([key, values]) => {
                (values ?? []).forEach(value => params.append(`facet[${key}][]`, value));
            });
            return params.toString();
        }
    }

    class EscapeHtml {
        static value(value) {
            const node = document.createElement('div');
            node.textContent = String(value ?? '');
            return node.innerHTML;
        }
    }

    const SORT_LABELS = {
        name_asc: 'Name A–Z',
        name_desc: 'Name Z–A',
        newest: 'Newest',
        oldest: 'Oldest',
        most_articles: 'Most articles',
        title_asc: 'Title A–Z',
        title_desc: 'Title Z–A',
        most_viewed: 'Most viewed',
        most_commented: 'Most commented',
    };

    class DirectoryView {
        render(root, state) {
            if (state.status === 'loading' && !state.document) {
                root.innerHTML = '<div class="public-directory-status"><div class="public-directory-spinner"></div><p>Loading…</p></div>';
                return;
            }
            if (state.status === 'error') {
                root.innerHTML = `<div class="public-directory-error"><h1>Page unavailable</h1><p>${EscapeHtml.value(state.error?.message)}</p><button type="button" data-action="retry">Try again</button></div>`;
                return;
            }

            if (!state.document) return;

            // Refactored routing logic to handle page collections without an entity
            let markup = '';
            if (state.document.entity) {
                markup = this.detail(state.document);
            } else if (state.document.pages) {
                markup = this.collectionView(state.document);
            } else {
                markup = this.index(state.document);
            }

            root.innerHTML = state.status === 'loading' ? `<div class="public-directory-app--refreshing">${markup}</div>` : markup;
        }

        toolbar(query, placeholder, total, noun, sort, perPage) {
            return `<div class="directory-toolbar">${this.search(query, placeholder, total, noun)}<div class="directory-toolbar__controls">${this.sortSelect(sort)}${this.perPageSelect(perPage)}</div></div>`;
        }

        search(query, placeholder, total, noun) {
            return `<div class="directory-search" role="search"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg><input type="search" data-directory-search value="${EscapeHtml.value(query)}" placeholder="${EscapeHtml.value(placeholder)}" autocomplete="off" aria-label="${EscapeHtml.value(placeholder)}">${query ? '<button type="button" data-action="clear-search">Clear</button>' : ''}</div><div class="directory-search-summary" aria-live="polite">${query ? `${total} result${total === 1 ? '' : 's'} for “${EscapeHtml.value(query)}”` : `${total} ${EscapeHtml.value(noun)}`}</div>`;
        }

        sortSelect(sort) {
            if (!sort?.options?.length) return '';
            return `<label class="directory-sort"><span>Sort by</span><select data-directory-sort>${sort.options.map(option => `<option value="${EscapeHtml.value(option)}" ${option === sort.current ? 'selected' : ''}>${EscapeHtml.value(SORT_LABELS[option] ?? option)}</option>`).join('')}</select></label>`;
        }

        perPageSelect(perPage) {
            if (!perPage?.options?.length) return '';
            return `<label class="directory-per-page"><span>Per page</span><select data-directory-per-page>${perPage.options.map(option => `<option value="${option}" ${Number(option) === Number(perPage.current) ? 'selected' : ''}>${option}</option>`).join('')}</select></label>`;
        }

        facets(groups, activeCount) {
            const visible = (groups ?? []).filter(group => group.options?.length);
            if (!visible.length) return '';
            return `<aside class="directory-facets"><div class="directory-facets__header"><h2>Filter</h2>${activeCount ? '<button type="button" data-action="clear-facets">Clear all</button>' : ''}</div>${visible.map(group => this.facetGroup(group)).join('')}</aside>`;
        }

        facetGroup(group) {
            return `<div class="directory-facet-group"><h3>${EscapeHtml.value(group.label)}</h3><ul>${group.options.map(option => `<li><label><input type="checkbox" data-facet-key="${EscapeHtml.value(group.key)}" data-facet-value="${EscapeHtml.value(option.value)}" ${option.selected ? 'checked' : ''}><span>${EscapeHtml.value(option.label)}</span><span class="directory-facet-count">${Number(option.count)}</span></label></li>`).join('')}</ul></div>`;
        }

        activeFacetCount(groups) {
            return (groups ?? []).reduce((total, group) => total + (group.options ?? []).filter(option => option.selected).length, 0);
        }

        index(document) {
            const entities = document.entities ?? [];
            const query = document.search?.query ?? '';
            const total = Number(document.pagination?.total ?? entities.length);
            const type = String(document.type ?? 'directory');
            const label = type === 'author' ? 'authors' : type === 'tag' ? 'tags' : type === 'category' ? 'categories' : type;
            return `<section class="directory-page"><header class="directory-hero"><p class="directory-eyebrow">Explore</p><h1>${EscapeHtml.value(document.title)}</h1><p>Browse all published ${EscapeHtml.value(label)}.</p></header>${this.toolbar(query, `Search ${label}…`, total, label, document.sort, document.per_page)}<div class="directory-page-grid">${entities.map(entity => this.entityCard(entity)).join('')}</div>${entities.length ? '' : `<div class="directory-empty"><h2>No matches found</h2><p>Try a different search term.</p>${query ? '<button type="button" data-action="clear-search">Clear search</button>' : ''}</div>`}${this.pagination(document.pagination)}</section>`;
        }

        detail(document) {
            const entity = document.entity;
            const meta = entity.meta ?? {};
            const pages = document.pages ?? [];
            const query = document.search?.query ?? '';
            const total = Number(document.pagination?.total ?? pages.length);
            const config = document.page_card ?? {};
            const facetGroups = document.facets ?? [];
            const activeFacets = this.activeFacetCount(facetGroups);
            return `<section class="directory-page"><header class="directory-hero directory-hero--detail">${entity.image ? `<img class="directory-avatar" src="${EscapeHtml.value(entity.image)}" alt="${EscapeHtml.value(entity.name)}">` : entity.icon ? `<div class="directory-icon">${entity.icon}</div>` : ''}<p class="directory-eyebrow">${EscapeHtml.value(entity.type)}</p><h1>${entity.type === 'tag' ? '#' : ''}${EscapeHtml.value(entity.name)}</h1>${entity.description ? `<p>${EscapeHtml.value(entity.description)}</p>` : ''}<div class="directory-stats"><span><strong>${Number(document.stats?.page_count ?? 0)}</strong> articles</span>${Number(document.stats?.related_count ?? 0) ? `<span><strong>${Number(document.stats.related_count)}</strong> subcategories</span>` : ''}</div></header>${this.authorDetails(entity.type, meta)}${this.related(document.related ?? [])}<section class="directory-results"><div class="directory-section-heading"><h2>Latest articles</h2><span>${total} results</span></div>${this.toolbar(query, 'Search articles…', total, 'articles', document.sort, document.per_page)}<div class="directory-results__layout">${this.facets(facetGroups, activeFacets)}<div class="directory-results__main">${pages.length ? `<div class="directory-page-grid">${pages.map(page => this.pageCard(page, config)).join('')}</div>` : `<div class="directory-empty"><h2>No matches found</h2><p>Try a different search term or filter.</p>${query || activeFacets ? '<button type="button" data-action="clear-search">Clear search</button>' : ''}</div>`}${this.pagination(document.pagination)}</div></div></section></section>`;
        }

        // New method to handle non-entity content grids (e.g., Reviews & Buying Guides)
        collectionView(document) {
            const pages = document.pages ?? [];
            const query = document.search?.query ?? '';
            const total = Number(document.pagination?.total ?? pages.length);
            const type = String(document.type ?? 'articles');
            const label = type === 'review' ? 'reviews' : type === 'buying-guide' ? 'buying guides' : type;
            const config = document.page_card ?? {};
            const facetGroups = document.facets ?? [];
            const activeFacets = this.activeFacetCount(facetGroups);

            return `<section class="directory-page"><header class="directory-hero"><p class="directory-eyebrow">Explore</p><h1>${EscapeHtml.value(document.title)}</h1>${document.description ? `<p>${EscapeHtml.value(document.description)}</p>` : `<p>Browse all published ${EscapeHtml.value(label)}.</p>`}</header><section class="directory-results"><div class="directory-section-heading"><h2>Latest ${EscapeHtml.value(label)}</h2><span>${total} results</span></div>${this.toolbar(query, `Search ${label}…`, total, label, document.sort, document.per_page)}<div class="directory-results__layout">${this.facets(facetGroups, activeFacets)}<div class="directory-results__main">${pages.length ? `<div class="directory-page-grid">${pages.map(page => this.pageCard(page, config)).join('')}</div>` : `<div class="directory-empty"><h2>No matches found</h2><p>Try a different search term or filter.</p>${query || activeFacets ? '<button type="button" data-action="clear-search">Clear search</button>' : ''}</div>`}${this.pagination(document.pagination)}</div></div></section></section>`;
        }

        pagination(pagination) {
            if (!pagination || pagination.last_page <= 1) return '';
            const current = Number(pagination.current_page);
            const last = Number(pagination.last_page);
            const buttons = [];
            for (let page = Math.max(1, current - 2); page <= Math.min(last, current + 2); page++) buttons.push(`<button type="button" data-action="page" data-page="${page}" class="${page === current ? 'active' : ''}" aria-current="${page === current ? 'page' : 'false'}">${page}</button>`);
            return `<nav class="public-directory-pagination" aria-label="Directory pagination"><button type="button" data-action="previous-page" ${current <= 1 ? 'disabled' : ''}>Previous</button>${buttons.join('')}<button type="button" data-action="next-page" ${current >= last ? 'disabled' : ''}>Next</button></nav>`;
        }

        entityCard(entity) {
            const url = EscapeHtml.value(entity.url);
            const name = EscapeHtml.value(entity.name);
            const prefixedName = `${entity.type === 'tag' ? '#' : ''}${name}`;
            const media = entity.image
                ? `<a class="directory-page-card__image directory-entity-card__image" href="${url}"><img src="${EscapeHtml.value(entity.image)}" alt="${name}" loading="lazy" decoding="async"></a>`
                : `<a class="directory-entity-card__mark" href="${url}" aria-label="View ${name}">${entity.icon ?? (entity.type === 'tag' ? '#' : name.slice(0, 1))}</a>`;
            const taxonomy = entity.type === 'tag' || entity.type === 'category'
                ? `<div class="directory-taxonomy"><a href="${url}">${prefixedName}</a></div>`
                : `<span class="directory-entity-card__type">${EscapeHtml.value(entity.type)}</span>`;
            const summary = entity.description ? `<p class="directory-page-card__summary">${EscapeHtml.value(entity.description)}</p>` : '';
            return `<article class="directory-page-card directory-entity-card">${media}<div class="directory-page-card__body">${taxonomy}<h3><a href="${url}">${prefixedName}</a></h3>${summary}<a class="directory-page-card__read-more" href="${url}">View ${EscapeHtml.value(entity.type)} <span aria-hidden="true">→</span></a></div></article>`;
        }

        pageCard(page, config) {
            const image = config.show_image !== false && page.image ? `<a class="directory-page-card__image" href="${EscapeHtml.value(page.url)}"><img src="${EscapeHtml.value(page.image.url)}" alt="${EscapeHtml.value(page.image.alt || page.title)}"${page.image.width ? ` width="${Number(page.image.width)}"` : ''}${page.image.height ? ` height="${Number(page.image.height)}"` : ''} loading="lazy" decoding="async"></a>` : '';
            const categories = config.show_categories !== false ? this.taxonomy(page.categories, Number(config.category_limit ?? 2), false) : '';
            const tags = config.show_tags !== false ? this.taxonomy(page.tags, Number(config.tag_limit ?? 3), true) : '';
            const summary = config.show_summary !== false && page.summary ? `<p class="directory-page-card__summary">${EscapeHtml.value(this.truncate(page.summary, Number(config.summary_length ?? 150)))}</p>` : '';
            const authors = config.show_authors !== false ? this.authors(page.authors, Number(config.author_limit ?? 3)) : '';
            const date = config.show_published_date !== false && page.published_at ? `<time datetime="${EscapeHtml.value(page.published_at)}">${EscapeHtml.value(this.formatDate(page.published_at))}</time>` : '';
            return `<article class="directory-page-card">${image}<div class="directory-page-card__body">${categories}<h3><a href="${EscapeHtml.value(page.url)}">${EscapeHtml.value(page.title)}</a></h3>${summary}<div class="directory-page-card__meta">${authors}${date}</div>${tags}<a class="directory-page-card__read-more" href="${EscapeHtml.value(page.url)}">Read more <span aria-hidden="true">→</span></a></div></article>`;
        }

        taxonomy(items, limit, prefixed) {
            const values = (items ?? []).slice(0, Math.max(0, limit));
            if (!values.length) return '';
            return `<div class="directory-taxonomy">${values.map(item => `<a href="${EscapeHtml.value(item.url)}">${prefixed ? '#' : ''}${EscapeHtml.value(item.name)}</a>`).join('')}</div>`;
        }

        authors(items, limit) {
            const values = (items ?? []).slice(0, Math.max(0, limit));
            if (!values.length) return '';
            return `<span class="directory-authors">By ${values.map(item => `<a href="${EscapeHtml.value(item.url)}">${EscapeHtml.value(item.name)}</a>`).join(', ')}</span>`;
        }

        truncate(value, length) {
            const text = String(value ?? '');
            return text.length > length ? `${text.slice(0, length).trimEnd()}…` : text;
        }

        formatDate(value) {
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat(undefined, {day: 'numeric', month: 'short', year: 'numeric'}).format(date);
        }

        related(items) {
            if (!items.length) return '';
            return `<section class="directory-related"><div class="directory-section-heading"><h2>Subcategories</h2></div><div class="directory-chip-list">${items.map(item => `<a href="${EscapeHtml.value(item.url)}">${item.icon ?? ''}${EscapeHtml.value(item.name)}</a>`).join('')}</div></section>`;
        }

        authorDetails(type, meta) {
            if (type !== 'author') return '';
            const groups = [['Expertise', meta.expertise], ['Location', Array.isArray(meta.location) ? meta.location.join(', ') : meta.location], ['Education', Array.isArray(meta.education) ? meta.education.join(', ') : meta.education], ['Awards', Array.isArray(meta.awards) ? meta.awards.join(', ') : meta.awards], ['Experience', meta.years_of_experience ? `${meta.years_of_experience} years` : null]].filter(([, value]) => value);
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
            const params = new URLSearchParams(window.location.search);
            this.filters = {
                q: String(params.get('q') || '').trim(),
                sort: String(params.get('sort') || ''),
                page: Math.max(1, Number(params.get('page') || 1)),
                perPage: Number(params.get('per_page') || 0) || null,
                facets: this.readFacetsFromUrl(params),
            };
            this.document = null;
            this.searchDebounce = null;
        }

        readFacetsFromUrl(params) {
            const facets = {};
            for (const key of params.keys()) {
                const match = key.match(/^facet\[(.+)]\[]$/);
                if (!match) continue;
                facets[match[1]] = params.getAll(key);
            }
            return facets;
        }

        start() {
            this.store.subscribe(state => this.view.render(this.root, state));
            this.root.addEventListener('click', event => this.onClick(event));
            this.root.addEventListener('input', event => this.onInput(event));
            this.root.addEventListener('change', event => this.onChange(event));
            this.load();
        }

        onInput(event) {
            if (!event.target.matches('[data-directory-search]')) return;
            const value = event.target.value.trim();
            clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => {
                this.filters.q = value;
                this.filters.page = 1;
                this.load(true);
            }, 350);
        }

        onChange(event) {
            if (event.target.matches('[data-directory-sort]')) {
                this.filters.sort = event.target.value;
                this.filters.page = 1;
                this.load();
                return;
            }
            if (event.target.matches('[data-directory-per-page]')) {
                this.filters.perPage = Number(event.target.value);
                this.filters.page = 1;
                this.load();
                return;
            }
            if (event.target.matches('[data-facet-key]')) {
                const key = event.target.dataset.facetKey;
                const value = event.target.dataset.facetValue;
                const current = new Set(this.filters.facets[key] ?? []);
                event.target.checked ? current.add(value) : current.delete(value);
                this.filters.facets[key] = Array.from(current);
                if (!this.filters.facets[key].length) delete this.filters.facets[key];
                this.filters.page = 1;
                this.load();
            }
        }

        onClick(event) {
            if (event.target.closest('[data-action="retry"]')) return void this.load();
            if (event.target.closest('[data-action="clear-search"]')) {
                this.filters.q = '';
                this.filters.page = 1;
                this.load(true);
                return;
            }
            if (event.target.closest('[data-action="clear-facets"]')) {
                this.filters.facets = {};
                this.filters.page = 1;
                this.load();
                return;
            }
            const pageButton = event.target.closest('[data-page]');
            if (pageButton) return void this.goToPage(Number(pageButton.dataset.page));
            if (event.target.closest('[data-action="previous-page"]')) return void this.goToPage(this.filters.page - 1);
            if (event.target.closest('[data-action="next-page"]')) return void this.goToPage(this.filters.page + 1);
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
            const last = Math.max(1, Number(this.document?.pagination?.last_page ?? 1));
            this.filters.page = Math.max(1, Math.min(last, page));
            this.load();
            this.root.scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        updateUrl() {
            const params = new URLSearchParams();
            if (this.filters.q) params.set('q', this.filters.q);
            if (this.filters.sort) params.set('sort', this.filters.sort);
            if (this.filters.page > 1) params.set('page', String(this.filters.page));
            if (this.filters.perPage) params.set('per_page', String(this.filters.perPage));
            Object.entries(this.filters.facets).forEach(([key, values]) => {
                (values ?? []).forEach(value => params.append(`facet[${key}][]`, value));
            });
            const query = params.toString();
            window.history.replaceState({}, '', `${window.location.pathname}${query ? '?' + query : ''}${window.location.hash}`);
        }

        async load(restoreSearchFocus = false) {
            this.store.setState({status: 'loading', document: this.document, error: null});
            try {
                this.document = await this.api.load(this.filters);
                if (!this.filters.sort && this.document.sort?.current) this.filters.sort = this.document.sort.current;
                if (!this.filters.perPage && this.document.per_page?.current) this.filters.perPage = this.document.per_page.current;
                this.updateUrl();
                this.store.setState({status: 'loaded', document: this.document, error: null});
                if (restoreSearchFocus) window.requestAnimationFrame(() => {
                    const input = this.root.querySelector('[data-directory-search]');
                    if (!input) return;
                    input.focus();
                    input.setSelectionRange(input.value.length, input.value.length);
                });
            } catch (error) {
                this.store.setState({status: 'error', error});
            }
        }

        toPreviewPath(pathname) {
            const segments = pathname.split('/').filter(Boolean);
            if (segments[0] !== this.siteSlug) return null;
            if (segments.length === 1) return `/${this.siteSlug}/content-v2`;
            if (segments[1] === 'content-v2') return pathname;
            // Updated to handle review and buying-guide routing slugs if needed
            if (['authors', 'categories', 'tags', 'reviews', 'buying-guides'].includes(segments[1])) return `/${this.siteSlug}/content-v2/${segments.slice(1).join('/')}`;
            if (segments.length === 2) return `/${this.siteSlug}/content-v2/${encodeURIComponent(segments[1])}`;
            return null;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('public-directory-app');
        if (!root?.dataset.apiUrl) return;
        new DirectoryApp(root, new DirectoryApi(root.dataset.apiUrl), new DirectoryStore(), new DirectoryView()).start();
    });
})();