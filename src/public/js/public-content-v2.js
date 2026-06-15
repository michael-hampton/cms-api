(() => {
    'use strict';

    class PublicContentStore {
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

            let payload;
            try {
                payload = await response.json();
            } catch (error) {
                throw new PublicContentApiError('The server returned invalid JSON.', response.status);
            }

            if (!response.ok) {
                throw new PublicContentApiError(
                    payload.message ?? payload.error ?? 'Unable to load this content.',
                    response.status,
                    payload,
                );
            }

            return payload;
        }

        async getDocument() {
            const payload = await this.request(this.url);
            if (!payload.data) {
                throw new PublicContentApiError('The content response did not contain a document.', 500, payload);
            }
            return payload.data;
        }

        recordView(url) {
            return url
                ? this.request(url, {method: 'POST', body: '{}'})
                : Promise.resolve();
        }
    }

    class ComponentAssetLoader {
        #loadedScripts = new Set();
        #loadedStyles = new Set();

        async prepare(html) {
            const template = document.createElement('template');
            template.innerHTML = html ?? '';

            for (const link of [...template.content.querySelectorAll('link[rel="stylesheet"]')]) {
                await this.loadStyle(link.href);
                link.remove();
            }

            const scripts = [...template.content.querySelectorAll('script')];
            scripts.forEach(script => script.remove());

            return {
                fragment: template.content.cloneNode(true),
                scripts,
            };
        }

        executeAfterMount(scripts) {
            if (!scripts.length) return;

            window.setTimeout(async () => {
                for (const script of scripts) {
                    if (script.src) {
                        await this.loadScript(script.src);
                        continue;
                    }

                    const executable = document.createElement('script');
                    executable.textContent = script.textContent;
                    document.body.append(executable);
                    executable.remove();
                }
            }, 0);
        }

        loadStyle(url) {
            if (!url || this.#loadedStyles.has(url)) return Promise.resolve();
            this.#loadedStyles.add(url);

            return new Promise(resolve => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = url;
                link.onload = resolve;
                link.onerror = resolve;
                document.head.append(link);
            });
        }

        loadScript(url) {
            if (!url || this.#loadedScripts.has(url)) return Promise.resolve();
            this.#loadedScripts.add(url);

            return new Promise(resolve => {
                const script = document.createElement('script');
                script.src = url;
                script.onload = resolve;
                script.onerror = resolve;
                document.body.append(script);
            });
        }
    }

    class ComponentHydrator {
        hydrate(element, component) {
            element.dataset.component = component.type;
            element.dataset.componentId = component.id;
            element.dataset.stateful = component.stateful ? 'true' : 'false';
            element.dataset.endpoints = JSON.stringify(component.endpoints ?? {});

            document.dispatchEvent(new CustomEvent('public-content:component-mounted', {
                detail: {element, component},
            }));
        }
    }

    class PublicContentComposer {
        constructor(assetLoader, hydrator) {
            this.assetLoader = assetLoader;
            this.hydrator = hydrator;
            this.pendingScripts = [];
        }

        async render(root, documentData) {
            const rendered = documentData.content?.regions ?? {};
            const components = documentData.content?.components ?? {};
            const sidebarHtml = rendered.sidebar?.rendered_html ?? '';

            this.pendingScripts = [];
            root.replaceChildren();
            root.className = 'public-content-v2-app';

            root.append(await this.region('notices', components.notices ?? []));

            const article = document.createElement('article');
            article.className = 'public-content-v2-document';
            article.dataset.contentId = documentData.id;
            article.append(await this.region('header', components.header ?? []));

            const layout = document.createElement('div');
            layout.className = `page-layout ${sidebarHtml.trim() ? 'has-sidebar' : 'full-width'}`;

            const main = document.createElement('div');
            main.className = `main-content ${sidebarHtml.trim() ? 'with-sidebar' : 'full-width'}`;
            main.innerHTML = rendered.main?.rendered_html ?? '';
            main.append(await this.region('after-content', components['after-content'] ?? []));
            layout.append(main);

            if (sidebarHtml.trim()) {
                const sidebar = document.createElement('aside');
                sidebar.className = 'sidebar';
                sidebar.innerHTML = sidebarHtml;
                layout.append(sidebar);
            }

            article.append(layout);
            article.append(await this.region('below-content', components['below-content'] ?? []));
            root.append(article);
            root.append(await this.region('modals', components.modals ?? []));

            document.dispatchEvent(new CustomEvent('public-content:document-composed', {
                detail: {root, document: documentData},
            }));

            for (const scripts of this.pendingScripts) {
                this.assetLoader.executeAfterMount(scripts);
            }
        }

        async region(name, components) {
            const region = document.createElement('div');
            region.className = `public-content-region public-content-region--${name}`;
            region.dataset.region = name;

            for (const component of components) {
                const element = document.createElement('div');
                element.className = `public-content-component public-content-component--${component.type}`;

                const prepared = await this.assetLoader.prepare(component.html);
                element.append(prepared.fragment);
                region.append(element);

                this.hydrator.hydrate(element, component);

                if (component.type !== 'guest-contributors' && prepared.scripts.length) {
                    this.pendingScripts.push(prepared.scripts);
                }
            }

            return region;
        }
    }

    class PublicContentView {
        constructor(composer) {
            this.composer = composer;
        }

        async render(root, state) {
            if (state.status === 'loading') {
                root.innerHTML = '<div class="public-content-v2-status" role="status"><div class="public-content-v2-spinner"></div><p>Loading content…</p></div>';
                return;
            }

            if (state.status === 'error') {
                const message = state.error?.status === 403
                    ? 'You do not have access to this content.'
                    : state.error?.message ?? 'Unable to load this content.';
                root.innerHTML = '<div class="public-content-v2-error" role="alert"><h1>Content unavailable</h1><p></p><button type="button" data-action="retry">Try again</button></div>';
                root.querySelector('p').textContent = message;
                return;
            }

            if (state.status === 'loaded') {
                await this.composer.render(root, state.document);
            }
        }
    }

    class PublicContentApp {
        constructor(root, api, store, view) {
            this.root = root;
            this.api = api;
            this.store = store;
            this.view = view;
            this.siteSlug = root.dataset.site;
            this.onClick = this.onClick.bind(this);
        }

        start() {
            this.store.subscribe(state => this.view.render(this.root, state));
            this.root.addEventListener('click', this.onClick);
            this.load();
        }

        async load() {
            this.store.setState({status: 'loading', error: null});

            try {
                const documentData = await this.api.getDocument();
                this.store.setState({status: 'loaded', document: documentData});
                this.api.recordView(documentData.links?.view).catch(() => undefined);
            } catch (error) {
                this.store.setState({status: 'error', error});
            }
        }

        onClick(event) {
            if (event.target.closest('[data-action="retry"]')) {
                this.load();
                return;
            }

            const link = event.target.closest('a[href]');
            if (!link || link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey) {
                return;
            }

            const url = new URL(link.href, window.location.origin);
            if (url.origin !== window.location.origin) {
                return;
            }

            const canonicalPath = url.pathname
                .replace(/\/category\//, '/categories/')
                .replace(/\/tag\//, '/tags/')
                .replace(/\/author\//, '/authors/');

            const previewPath = this.toPreviewPath(canonicalPath);
            const targetPath = previewPath ?? canonicalPath;

            if (targetPath !== url.pathname) {
                event.preventDefault();
                window.location.assign(`${targetPath}${url.search}${url.hash}`);
            }
        }

        toPreviewPath(pathname) {
            if (!this.siteSlug) return null;

            const segments = pathname.split('/').filter(Boolean);
            if (segments[0] !== this.siteSlug) return null;

            if (segments.length === 1) {
                return `/${this.siteSlug}/content-v2`;
            }

            const first = segments[1];
            const reserved = new Set([
                'content-v2',
                'authors',
                'categories',
                'tags',
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

            if (reserved.has(first) || segments.length !== 2) {
                return null;
            }

            return `/${this.siteSlug}/content-v2/${encodeURIComponent(first)}`;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('public-content-v2-app');
        if (!root?.dataset.apiUrl) return;

        const app = new PublicContentApp(
            root,
            new PublicContentApi(root.dataset.apiUrl),
            new PublicContentStore(),
            new PublicContentView(
                new PublicContentComposer(
                    new ComponentAssetLoader(),
                    new ComponentHydrator(),
                ),
            ),
        );

        app.start();
        root.publicContentApp = app;
    });
})();
