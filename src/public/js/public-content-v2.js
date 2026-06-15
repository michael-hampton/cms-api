(() => {
    'use strict';

    class PublicContentStore {
        #state;
        #listeners = new Set();

        constructor(initialState = {}) {
            this.#state = Object.freeze({
                status: 'idle',
                document: null,
                error: null,
                ...initialState,
            });
        }

        getState() {
            return this.#state;
        }

        subscribe(listener) {
            this.#listeners.add(listener);
            listener(this.#state);

            return () => this.#listeners.delete(listener);
        }

        setState(patch) {
            this.#state = Object.freeze({
                ...this.#state,
                ...patch,
            });

            this.#listeners.forEach(listener => listener(this.#state));
        }

        setLoading() {
            this.setState({status: 'loading', error: null});
        }

        setDocument(document) {
            this.setState({status: 'loaded', document, error: null});
        }

        setError(error) {
            this.setState({status: 'error', error});
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
        constructor(url, fetchClient = window.fetch.bind(window)) {
            this.url = url;
            this.fetchClient = fetchClient;
        }

        async getContent() {
            const response = await this.fetchClient(this.url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            let payload = null;

            try {
                payload = await response.json();
            } catch (error) {
                throw new PublicContentApiError(
                    'The content API returned an invalid response.',
                    response.status,
                );
            }

            if (!response.ok) {
                throw new PublicContentApiError(
                    payload.message ?? payload.error ?? 'Unable to load this content.',
                    response.status,
                    payload,
                );
            }

            if (!payload.data) {
                throw new PublicContentApiError(
                    'The content API response did not contain a document.',
                    response.status,
                    payload,
                );
            }

            return payload.data;
        }
    }

    class EscapeHtml {
        static value(value) {
            const element = document.createElement('div');
            element.textContent = String(value ?? '');

            return element.innerHTML;
        }
    }

    class HeadingBlockRenderer {
        render(block) {
            const level = Math.min(6, Math.max(1, Number(block.data?.level ?? 2)));
            const text = EscapeHtml.value(block.data?.text ?? block.data?.title ?? '');

            return `<h${level}>${text}</h${level}>`;
        }
    }

    class ParagraphBlockRenderer {
        render(block) {
            const html = block.data?.html ?? block.data?.content ?? block.data?.text ?? '';

            if (typeof html !== 'string') {
                return '';
            }

            return `<div class="public-content-v2-paragraph">${html}</div>`;
        }
    }

    class ImageBlockRenderer {
        render(block) {
            const image = block.data?.image ?? block.data ?? {};
            const src = image.src ?? image.url ?? block.data?.imageUrl ?? '';

            if (!src) {
                return '';
            }

            const alt = EscapeHtml.value(image.alt ?? block.data?.alt ?? '');
            const caption = image.caption ?? block.data?.caption ?? '';

            return `
                <figure class="public-content-v2-image">
                    <img src="${EscapeHtml.value(src)}" alt="${alt}" loading="lazy">
                    ${caption ? `<figcaption>${EscapeHtml.value(caption)}</figcaption>` : ''}
                </figure>
            `;
        }
    }

    class HtmlFallbackBlockRenderer {
        render(block) {
            if (typeof block.rendered_html === 'string' && block.rendered_html.trim() !== '') {
                return `<div class="public-content-v2-fallback">${block.rendered_html}</div>`;
            }

            return `
                <div class="public-content-v2-unsupported">
                    Unsupported block: ${EscapeHtml.value(block.type ?? 'unknown')}
                </div>
            `;
        }
    }

    class BlockRendererRegistry {
        #renderers = new Map();

        constructor(fallbackRenderer = new HtmlFallbackBlockRenderer()) {
            this.fallbackRenderer = fallbackRenderer;
            this.register('heading', new HeadingBlockRenderer());
            this.register('paragraph', new ParagraphBlockRenderer());
            this.register('text', new ParagraphBlockRenderer());
            this.register('image', new ImageBlockRenderer());
        }

        register(type, renderer) {
            this.#renderers.set(type, renderer);

            return this;
        }

        render(block) {
            const renderer = this.#renderers.get(block.type) ?? this.fallbackRenderer;

            return `
                <section
                    class="public-content-v2-block public-content-v2-block--${EscapeHtml.value(block.type ?? 'unknown')}"
                    data-block-id="${EscapeHtml.value(block.id ?? '')}"
                    data-block-type="${EscapeHtml.value(block.type ?? 'unknown')}"
                >
                    ${renderer.render(block)}
                </section>
            `;
        }
    }

    class PublicContentView {
        constructor(blockRegistry) {
            this.blockRegistry = blockRegistry;
        }

        render(root, state) {
            switch (state.status) {
                case 'loading':
                    root.innerHTML = this.loading();
                    return;
                case 'loaded':
                    root.innerHTML = this.document(state.document);
                    return;
                case 'error':
                    root.innerHTML = this.error(state.error);
                    return;
                default:
                    return;
            }
        }

        loading() {
            return `
                <div class="public-content-v2-status" role="status" aria-live="polite">
                    <div class="public-content-v2-spinner" aria-hidden="true"></div>
                    <p>Loading content…</p>
                </div>
            `;
        }

        error(error) {
            const message = error?.status === 403
                ? 'You do not have access to this content.'
                : error?.message ?? 'Unable to load this content.';

            return `
                <div class="public-content-v2-error" role="alert">
                    <h1>Content unavailable</h1>
                    <p>${EscapeHtml.value(message)}</p>
                    <button class="public-content-v2-retry" type="button" data-action="retry">
                        Try again
                    </button>
                </div>
            `;
        }

        document(document) {
            const main = document.content?.regions?.main ?? {blocks: []};
            const sidebar = document.content?.regions?.sidebar ?? {blocks: []};
            const hasSidebar = Array.isArray(sidebar.blocks) && sidebar.blocks.length > 0;

            return `
                <article class="public-content-v2-document" data-content-id="${EscapeHtml.value(document.id)}">
                    ${this.header(document)}
                    <div class="public-content-v2-layout ${hasSidebar ? 'has-sidebar' : 'full-width'}">
                        <div class="public-content-v2-main">
                            ${this.region(main)}
                        </div>
                        ${hasSidebar ? `
                            <aside class="public-content-v2-sidebar">
                                ${this.region(sidebar)}
                            </aside>
                        ` : ''}
                    </div>
                </article>
            `;
        }

        header(document) {
            const categories = document.taxonomy?.categories ?? [];
            const tags = document.taxonomy?.tags ?? [];
            const taxonomy = [...categories, ...tags];

            return `
                <header class="public-content-v2-header">
                    <h1 class="public-content-v2-title">${EscapeHtml.value(document.title)}</h1>
                    ${document.summary ? `
                        <p class="public-content-v2-summary">${EscapeHtml.value(document.summary)}</p>
                    ` : ''}
                    ${taxonomy.length ? `
                        <div class="public-content-v2-taxonomy" aria-label="Content taxonomy">
                            ${taxonomy.map(item => `
                                <span class="public-content-v2-chip">${EscapeHtml.value(item.name)}</span>
                            `).join('')}
                        </div>
                    ` : ''}
                </header>
            `;
        }

        region(region) {
            if (!Array.isArray(region.blocks) || region.blocks.length === 0) {
                return '<div class="public-content-v2-empty"><p>No content blocks were returned.</p></div>';
            }

            return region.blocks
                .map(block => this.blockRegistry.render(block))
                .join('');
        }
    }

    class PublicContentApp {
        constructor({root, api, store, view}) {
            this.root = root;
            this.api = api;
            this.store = store;
            this.view = view;
            this.unsubscribe = null;
            this.onClick = this.onClick.bind(this);
        }

        start() {
            this.unsubscribe = this.store.subscribe(
                state => this.view.render(this.root, state),
            );
            this.root.addEventListener('click', this.onClick);
            this.load();
        }

        async load() {
            this.store.setLoading();

            try {
                this.store.setDocument(await this.api.getContent());
            } catch (error) {
                this.store.setError(error);
            }
        }

        onClick(event) {
            const action = event.target.closest('[data-action]')?.dataset.action;

            if (action === 'retry') {
                this.load();
            }
        }

        destroy() {
            this.unsubscribe?.();
            this.root.removeEventListener('click', this.onClick);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('public-content-v2-app');

        if (!root || !root.dataset.apiUrl) {
            return;
        }

        const app = new PublicContentApp({
            root,
            api: new PublicContentApi(root.dataset.apiUrl),
            store: new PublicContentStore(),
            view: new PublicContentView(new BlockRendererRegistry()),
        });

        app.start();
        root.publicContentApp = app;
    });
})();
