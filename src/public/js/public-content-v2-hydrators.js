(() => {
    'use strict';

    class PublicContentComponentApi {
        async request(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options.headers ?? {}),
                },
                ...options,
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message ?? 'The request failed.');
            }

            return payload;
        }
    }

    class ComponentAssetManifestLoader {
        constructor() {
            this.styles = new Set(
                [...document.querySelectorAll('link[rel="stylesheet"]')]
                    .map(link => link.href),
            );
            this.scripts = new Set(
                [...document.querySelectorAll('script[src]')]
                    .map(script => script.src),
            );
        }

        load(component) {
            for (const url of component.assets?.styles ?? []) {
                const absolute = new URL(url, window.location.origin).href;
                if (this.styles.has(absolute)) continue;
                this.styles.add(absolute);

                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = url;
                document.head.append(link);
            }

            for (const url of component.assets?.scripts ?? []) {
                const absolute = new URL(url, window.location.origin).href;
                if (this.scripts.has(absolute)) continue;
                this.scripts.add(absolute);

                const script = document.createElement('script');
                script.src = url;
                document.body.append(script);
            }
        }
    }

    class CommentsComponent {
        constructor(element, component, api) {
            this.element = element;
            this.component = component;
            this.api = api;
            this.form = element.querySelector('#comment-form');
            this.message = element.querySelector('#form-message');
            this.submit = this.form?.querySelector('.btn-submit') ?? null;
        }

        start() {
            if (!this.form || this.form.dataset.apiHydrated === 'true') {
                return;
            }

            this.form.dataset.apiHydrated = 'true';
            this.form.addEventListener('submit', event => this.submitComment(event), true);
        }

        async submitComment(event) {
            event.preventDefault();
            event.stopImmediatePropagation();

            const endpoint = this.component.endpoints?.create;
            const content = String(new FormData(this.form).get('content') ?? '').trim();

            if (!endpoint || !content) {
                return;
            }

            this.setLoading(true);
            this.hideMessage();

            try {
                const payload = await this.api.request(endpoint, {
                    method: 'POST',
                    body: JSON.stringify({content}),
                });

                this.showMessage(
                    payload.data?.status === 'approved'
                        ? 'Your comment has been posted.'
                        : 'Your comment has been submitted for review.',
                    payload.data?.status === 'approved' ? 'success' : 'pending',
                );

                this.form.reset();
                const counter = this.element.querySelector('#char-count');
                if (counter) counter.textContent = '0';

                window.setTimeout(() => {
                    document.getElementById('public-content-v2-app')?.publicContentApp?.load();
                }, 500);
            } catch (error) {
                this.showMessage(error.message ?? 'An error occurred. Please try again.', 'error');
            } finally {
                this.setLoading(false);
            }
        }

        setLoading(loading) {
            if (!this.submit) return;
            this.submit.disabled = loading;
            this.submit.classList.toggle('loading', loading);
        }

        hideMessage() {
            if (this.message) this.message.style.display = 'none';
        }

        showMessage(message, type) {
            if (!this.message) return;
            this.message.textContent = message;
            this.message.className = `form-message ${type}`;
            this.message.style.display = 'block';
        }
    }

    class NewsletterComponent {
        constructor(element) {
            this.element = element;
        }

        start() {
            this.element.addEventListener('click', event => {
                if (event.target.closest('button, [data-newsletter-trigger]')) {
                    document.dispatchEvent(new CustomEvent('newsletter:open'));
                }
            });
        }
    }

    class ComponentHydratorRegistry {
        constructor(api) {
            this.api = api;
            this.assets = new ComponentAssetManifestLoader();
            this.factories = new Map([
                ['comments', (element, component) => new CommentsComponent(element, component, this.api)],
                ['newsletter-signup-widget', element => new NewsletterComponent(element)],
            ]);
        }

        hydrate(element, component) {
            this.assets.load(component);

            const factory = this.factories.get(component.type);
            if (!factory) return;
            factory(element, component).start();
        }
    }

    const registry = new ComponentHydratorRegistry(new PublicContentComponentApi());

    document.addEventListener('public-content:component-mounted', event => {
        registry.hydrate(event.detail.element, event.detail.component);
    });

    document.addEventListener('public-content:document-composed', event => {
        event.detail.root
            .querySelector('[data-region="header"]')
            ?.classList.add('page-header');
    });
})();
