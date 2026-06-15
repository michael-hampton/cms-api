(() => {
    'use strict';

    class PublicContentStore {
        #state;
        #listeners = new Set();

        constructor() {
            this.#state = Object.freeze({
                status: 'idle',
                document: null,
                viewer: null,
                comments: {status: 'idle', items: [], stats: {}, error: null},
                mutation: null,
                error: null,
            });
        }

        getState() { return this.#state; }

        subscribe(listener) {
            this.#listeners.add(listener);
            listener(this.#state);
            return () => this.#listeners.delete(listener);
        }

        setState(patch) {
            this.#state = Object.freeze({...this.#state, ...patch});
            this.#listeners.forEach(listener => listener(this.#state));
        }

        setComments(patch) {
            this.setState({comments: {...this.#state.comments, ...patch}});
        }
    }

    class PublicContentApiError extends Error {
        constructor(message, status, payload = null) {
            super(message);
            this.name = 'PublicContentApiError';
            this.status = status;
            this.payload = payload;
        }
    }

    class PublicContentApi {
        constructor(contentUrl, fetchClient = window.fetch.bind(window)) {
            this.contentUrl = contentUrl;
            this.fetchClient = fetchClient;
        }

        async request(url, options = {}) {
            const response = await this.fetchClient(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options.headers ?? {}),
                },
                ...options,
            });

            let payload = null;
            try {
                payload = await response.json();
            } catch (error) {
                throw new PublicContentApiError('The server returned an invalid response.', response.status);
            }

            if (!response.ok) {
                throw new PublicContentApiError(
                    payload.message ?? payload.error ?? 'The request failed.',
                    response.status,
                    payload,
                );
            }

            return payload;
        }

        async getContent() {
            const payload = await this.request(this.contentUrl);
            if (!payload.data) {
                throw new PublicContentApiError('The content response did not contain a document.', 500, payload);
            }
            return payload.data;
        }

        getViewer(url) { return this.request(url).then(payload => payload.data); }
        getComments(url) { return this.request(url).then(payload => payload.data); }
        recordView(url) { return this.request(url, {method: 'POST', body: '{}'}); }
        like(url) { return this.request(url, {method: 'PUT', body: '{}'}).then(payload => payload.data); }
        unlike(url) { return this.request(url, {method: 'DELETE'}).then(payload => payload.data); }
        postComment(url, content) {
            return this.request(url, {method: 'POST', body: JSON.stringify({content})}).then(payload => payload.data);
        }
    }

    class EscapeHtml {
        static value(value) {
            const element = document.createElement('div');
            element.textContent = String(value ?? '');
            return element.innerHTML;
        }
    }

    class PublicContentView {
        render(root, state) {
            if (state.status === 'loading') {
                root.innerHTML = this.loading();
                return;
            }
            if (state.status === 'error') {
                root.innerHTML = this.error(state.error);
                return;
            }
            if (state.status === 'loaded') {
                root.innerHTML = this.document(state);
            }
        }

        loading() {
            return '<div class="public-content-v2-status" role="status"><div class="public-content-v2-spinner"></div><p>Loading content…</p></div>';
        }

        error(error) {
            const message = error?.status === 403 ? 'You do not have access to this content.' : error?.message ?? 'Unable to load this content.';
            return `<div class="public-content-v2-error" role="alert"><h1>Content unavailable</h1><p>${EscapeHtml.value(message)}</p><button class="public-content-v2-retry" data-action="retry">Try again</button></div>`;
        }

        document(state) {
            const document = state.document;
            const mainHtml = document.content?.regions?.main?.rendered_html ?? '';
            const sidebarHtml = document.content?.regions?.sidebar?.rendered_html ?? '';
            const hasSidebar = sidebarHtml.trim() !== '';

            return `
                <article class="public-content-v2-document" data-content-id="${EscapeHtml.value(document.id)}">
                    ${this.header(document)}
                    ${this.engagement(state.viewer, state.mutation)}
                    <div class="public-content-v2-layout ${hasSidebar ? 'has-sidebar' : 'full-width'}">
                        <div class="public-content-v2-main">${mainHtml || this.emptyRegion()}</div>
                        ${hasSidebar ? `<aside class="public-content-v2-sidebar">${sidebarHtml}</aside>` : ''}
                    </div>
                    ${this.authors(document.authors ?? [])}
                    ${this.landingSections(document.landing_sections ?? [])}
                    ${this.comments(state.comments, state.viewer)}
                </article>
            `;
        }

        header(document) {
            const taxonomy = [...(document.taxonomy?.categories ?? []), ...(document.taxonomy?.tags ?? [])];
            return `<header class="public-content-v2-header">
                <h1 class="public-content-v2-title">${EscapeHtml.value(document.title)}</h1>
                ${document.summary ? `<p class="public-content-v2-summary">${EscapeHtml.value(document.summary)}</p>` : ''}
                ${taxonomy.length ? `<div class="public-content-v2-taxonomy">${taxonomy.map(item => `<span class="public-content-v2-chip">${EscapeHtml.value(item.name)}</span>`).join('')}</div>` : ''}
            </header>`;
        }

        engagement(viewer, mutation) {
            if (!viewer) return '<div class="public-content-v2-engagement is-loading">Loading page activity…</div>';
            const disabled = mutation === 'like' ? 'disabled' : '';
            const label = viewer.liked ? 'Unlike' : 'Like';
            return `<section class="public-content-v2-engagement" aria-label="Page activity">
                <button type="button" data-action="toggle-like" ${disabled}>${label}</button>
                <span>${Number(viewer.like_count ?? 0)} likes</span>
                <span>${Number(viewer.view_count ?? 0)} views</span>
                ${!viewer.authenticated ? `<a href="${EscapeHtml.value(viewer.actions?.login ?? '/member/login')}">Sign in to interact</a>` : ''}
            </section>`;
        }

        authors(authors) {
            if (!authors.length) return '';
            return `<section class="public-content-v2-authors"><h2>Authors</h2><div class="public-content-v2-author-list">${authors.map(author => `<article class="public-content-v2-author">${author.image ? `<img src="${EscapeHtml.value(author.image)}" alt="">` : ''}<div><h3>${EscapeHtml.value(author.name)}</h3>${author.bio ? `<p>${EscapeHtml.value(author.bio)}</p>` : ''}</div></article>`).join('')}</div></section>`;
        }

        landingSections(sections) {
            if (!sections.length) return '';
            return `<section class="public-content-v2-landing-sections">${sections.map(section => `<div class="public-content-v2-landing-section"><h2>${EscapeHtml.value(section.category?.name)}</h2><div class="public-content-v2-card-grid">${(section.pages ?? []).map(page => `<article><h3>${EscapeHtml.value(page.title)}</h3>${page.summary ? `<p>${EscapeHtml.value(page.summary)}</p>` : ''}</article>`).join('')}</div></div>`).join('')}</section>`;
        }

        comments(comments, viewer) {
            if (comments.status === 'loading') return '<section class="public-content-v2-comments"><h2>Comments</h2><p>Loading comments…</p></section>';
            const items = comments.items ?? [];
            return `<section class="public-content-v2-comments"><h2>Comments</h2>
                ${items.length ? items.map(comment => `<article class="public-content-v2-comment"><strong>${EscapeHtml.value(comment.name ?? 'Member')}</strong><p>${EscapeHtml.value(comment.content)}</p></article>`).join('') : '<p>No comments yet.</p>'}
                ${viewer?.can_comment ? `<form data-comment-form><label for="public-content-comment">Add a comment</label><textarea id="public-content-comment" name="content" required></textarea><button type="submit">Post comment</button></form>` : '<p>Sign in to comment.</p>'}
                ${comments.error ? `<p class="public-content-v2-inline-error">${EscapeHtml.value(comments.error.message)}</p>` : ''}
            </section>`;
        }

        emptyRegion() { return '<div class="public-content-v2-empty"><p>No rendered content was returned.</p></div>'; }
    }

    class PublicContentApp {
        constructor({root, api, store, view}) {
            this.root = root;
            this.api = api;
            this.store = store;
            this.view = view;
            this.unsubscribe = null;
            this.onClick = this.onClick.bind(this);
            this.onSubmit = this.onSubmit.bind(this);
        }

        start() {
            this.unsubscribe = this.store.subscribe(state => this.view.render(this.root, state));
            this.root.addEventListener('click', this.onClick);
            this.root.addEventListener('submit', this.onSubmit);
            this.load();
        }

        async load() {
            this.store.setState({status: 'loading', error: null});
            try {
                const document = await this.api.getContent();
                this.store.setState({status: 'loaded', document, viewer: null});
                await Promise.allSettled([
                    this.loadViewer(document),
                    this.loadComments(document),
                    this.api.recordView(document.links.view),
                ]);
            } catch (error) {
                this.store.setState({status: 'error', error});
            }
        }

        async loadViewer(document = this.store.getState().document) {
            if (!document?.links?.viewer_state) return;
            const viewer = await this.api.getViewer(document.links.viewer_state);
            this.store.setState({viewer});
        }

        async loadComments(document = this.store.getState().document) {
            if (!document?.links?.comments) return;
            this.store.setComments({status: 'loading', error: null});
            try {
                const result = await this.api.getComments(document.links.comments);
                this.store.setComments({status: 'loaded', items: result.comments ?? [], stats: result.stats ?? {}, error: null});
            } catch (error) {
                this.store.setComments({status: 'error', error});
            }
        }

        async onClick(event) {
            const action = event.target.closest('[data-action]')?.dataset.action;
            if (action === 'retry') return this.load();
            if (action !== 'toggle-like') return;

            const state = this.store.getState();
            if (!state.viewer?.authenticated) {
                window.location.href = state.viewer?.actions?.login ?? '/member/login';
                return;
            }

            this.store.setState({mutation: 'like'});
            try {
                const viewer = state.viewer.liked
                    ? await this.api.unlike(state.document.links.like)
                    : await this.api.like(state.document.links.like);
                this.store.setState({viewer, mutation: null});
            } catch (error) {
                this.store.setState({mutation: null});
                window.alert(error.message);
            }
        }

        async onSubmit(event) {
            const form = event.target.closest('[data-comment-form]');
            if (!form) return;
            event.preventDefault();
            const content = String(new FormData(form).get('content') ?? '').trim();
            if (!content) return;

            const document = this.store.getState().document;
            try {
                await this.api.postComment(document.links.comments, content);
                form.reset();
                await this.loadComments(document);
            } catch (error) {
                this.store.setComments({error});
            }
        }

        destroy() {
            this.unsubscribe?.();
            this.root.removeEventListener('click', this.onClick);
            this.root.removeEventListener('submit', this.onSubmit);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('public-content-v2-app');
        if (!root?.dataset.apiUrl) return;
        const app = new PublicContentApp({root, api: new PublicContentApi(root.dataset.apiUrl), store: new PublicContentStore(), view: new PublicContentView()});
        app.start();
        root.publicContentApp = app;
    });
})();
