(() => {
    'use strict';

    class PublicContentComponentApi {
        constructor(csrfToken) {
            this.csrfToken = csrfToken;
        }

        async request(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(this.csrfToken ? {'X-CSRF-TOKEN': this.csrfToken} : {}),
                    ...(options.headers ?? {}),
                },
                ...options,
            });

            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.message ?? payload.error ?? 'The request failed.');
            }

            return payload;
        }
    }

    class ComponentAssetManifestLoader {
        constructor() {
            this.styles = new Set([...document.querySelectorAll('link[rel="stylesheet"]')].map(link => link.href));
            this.scripts = new Set([...document.querySelectorAll('script[src]')].map(script => script.src));
        }

        normalize(url) {
            return url ? String(url).replace(/^\/public\//, '/') : url;
        }

        load(component) {
            for (const source of component.assets?.styles ?? []) {
                const url = this.normalize(source);
                const absolute = new URL(url, window.location.origin).href;
                if (this.styles.has(absolute)) continue;

                this.styles.add(absolute);
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = url;
                document.head.append(link);
            }

            if (component.type === 'deals-carousel') return;

            for (const source of component.assets?.scripts ?? []) {
                const url = this.normalize(source);
                const absolute = new URL(url, window.location.origin).href;
                if (this.scripts.has(absolute)) continue;

                this.scripts.add(absolute);
                const script = document.createElement('script');
                script.src = url;
                document.body.append(script);
            }
        }
    }

    class PageActionsComponent {
        constructor(element, component, api) {
            this.button = element.querySelector('#like-button');
            this.component = component;
            this.api = api;
        }

        start() {
            if (!this.button || this.button.dataset.apiHydrated === 'true') return;
            this.button.dataset.apiHydrated = 'true';
            this.button.addEventListener('click', () => this.toggle());
        }

        async toggle() {
            const endpoint = this.component.endpoints?.like;
            if (!endpoint) return;

            const currentlyLiked = this.button.classList.contains('liked');
            this.button.disabled = true;

            try {
                const payload = await this.api.request(endpoint, {
                    method: currentlyLiked ? 'DELETE' : 'PUT',
                    body: currentlyLiked ? undefined : '{}',
                });
                const viewer = payload.data;
                const liked = Boolean(viewer?.liked);
                const icon = this.button.querySelector('.like-icon');
                const text = this.button.querySelector('.like-text');
                const count = this.button.querySelector('#like-count');

                this.button.classList.toggle('liked', liked);
                this.button.setAttribute('aria-pressed', String(liked));

                if (icon) {
                    icon.setAttribute('fill', liked ? 'currentColor' : 'none');
                }

                if (text) {
                    text.textContent = liked ? 'Liked' : 'Like';
                }

                if (count) {
                    count.textContent = String(Number(viewer?.like_count ?? 0));
                }
            } catch (error) {
                if (typeof window.showToast === 'function') {
                    window.showToast(error.message ?? 'Unable to update like.', 'error');
                } else {
                    console.error(error);
                }
            } finally {
                this.button.disabled = false;
            }
        }
    }

    class GuestContributorsCarousel {
        constructor(element) {
            this.root = element.querySelector('.oc-section');
            this.track = this.root?.querySelector('#track') ?? null;
            this.outer = this.root?.querySelector('#trackOuter') ?? null;
            this.prev = this.root?.querySelector('#prevBtn') ?? null;
            this.next = this.root?.querySelector('#nextBtn') ?? null;
            this.dotsHost = this.root?.querySelector('#dots') ?? null;
            this.progress = this.root?.querySelector('#progressFill') ?? null;
            this.count = this.root?.querySelector('#countLabel') ?? null;
            this.cards = this.track ? [...this.track.querySelectorAll('.oc-card')] : [];
            this.current = 0;
            this.timer = null;
        }

        start() {
            if (!this.root || !this.track || !this.outer || !this.cards.length || this.root.dataset.hydrated === 'true') return;
            this.root.dataset.hydrated = 'true';

            this.dots = this.cards.map((_, index) => {
                const dot = document.createElement('button');
                dot.className = 'oc-dot';
                dot.type = 'button';
                dot.setAttribute('aria-label', `Go to slide ${index + 1}`);
                dot.addEventListener('click', () => this.goTo(index));
                this.dotsHost?.append(dot);
                return dot;
            });

            this.prev?.addEventListener('click', () => this.goTo(this.current - 1));
            this.next?.addEventListener('click', () => this.goTo(this.current + 1));
            this.render();
            this.restartAuto();
        }

        goTo(index) {
            this.current = Math.max(0, Math.min(index, this.cards.length - 1));
            this.render();
            this.restartAuto();
        }

        render() {
            this.cards.forEach((card, index) => {
                card.classList.toggle('is-active', index === this.current);
                card.classList.toggle('is-adjacent', index === this.current - 1 || index === this.current + 1);
            });

            let offset = 0;
            for (let index = 0; index < this.current; index++) {
                offset += this.cards[index].offsetWidth + 20;
            }

            this.track.style.transform = `translateX(-${offset}px)`;
            this.dots.forEach((dot, index) => dot.classList.toggle('is-active', index === this.current));

            if (this.prev) this.prev.disabled = this.current === 0;
            if (this.next) this.next.disabled = this.current === this.cards.length - 1;
            if (this.progress) this.progress.style.width = `${((this.current + 1) / this.cards.length) * 100}%`;
            if (this.count) this.count.textContent = `${String(this.current + 1).padStart(2, '0')} / ${String(this.cards.length).padStart(2, '0')}`;
        }

        restartAuto() {
            clearInterval(this.timer);
            this.timer = setInterval(() => {
                this.goTo(this.current < this.cards.length - 1 ? this.current + 1 : 0);
            }, 5200);
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
            this.container = element.querySelector('#comments-container');
            this.count = element.querySelector('#comment-count');
            this.currentPage = 1;
            this.perPage = 10;
            this.pagination = this.ensurePagination();
            this.pageLabel = this.pagination?.querySelector('[data-comments-page]') ?? null;
            this.previous = this.pagination?.querySelector('[data-comments-previous]') ?? null;
            this.next = this.pagination?.querySelector('[data-comments-next]') ?? null;
        }

        ensurePagination() {
            if (!this.container) return null;

            const existing = this.element.querySelector('#comments-pagination');
            if (existing) return existing;

            const pagination = document.createElement('nav');
            pagination.id = 'comments-pagination';
            pagination.className = 'comments-pagination';
            pagination.setAttribute('aria-label', 'Comments pagination');
            pagination.hidden = true;
            pagination.innerHTML = `
                <button type="button" class="comment-page-btn" data-comments-previous>Previous</button>
                <span data-comments-page></span>
                <button type="button" class="comment-page-btn" data-comments-next>Next</button>
            `;
            this.container.insertAdjacentElement('afterend', pagination);
            return pagination;
        }

        start() {
            if (!this.form || this.form.dataset.apiHydrated === 'true') return;
            this.form.dataset.apiHydrated = 'true';
            this.form.addEventListener('submit', event => this.submitComment(event), true);
            this.previous?.addEventListener('click', () => this.load(this.currentPage - 1));
            this.next?.addEventListener('click', () => this.load(this.currentPage + 1));
            this.load(1);
        }

        async load(page = 1) {
            const endpoint = this.component.endpoints?.list;
            if (!endpoint || !this.container) return;

            try {
                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('page', String(page));
                url.searchParams.set('per_page', String(this.perPage));

                const payload = await this.api.request(url.toString());
                const data = payload.data ?? payload;
                const thread = data.thread ?? data.comments ?? [];
                const count = Number(data.count ?? data.stats?.approved ?? thread.length);
                const pagination = data.pagination ?? {
                    current_page: 1,
                    last_page: 1,
                    has_previous: false,
                    has_next: false,
                };

                this.currentPage = Number(pagination.current_page ?? 1);
                this.renderThread(thread);
                this.renderCount(count);
                this.renderPagination(pagination);
            } catch (error) {
                this.container.innerHTML = '';
                const failure = document.createElement('p');
                failure.className = 'comments-error';
                failure.textContent = error.message ?? 'Unable to load comments.';
                this.container.append(failure);
            }
        }

        async submitComment(event) {
            event.preventDefault();
            event.stopImmediatePropagation();

            const endpoint = this.component.endpoints?.create;
            const formData = new FormData(this.form);
            const body = Object.fromEntries(formData.entries());
            body.content = String(body.content ?? '').trim();
            if (!endpoint || !body.content) return;

            this.submit.disabled = true;

            try {
                const payload = await this.api.request(endpoint, {
                    method: 'POST',
                    body: JSON.stringify(body),
                });
                const responseData = payload.data ?? payload;
                const status = responseData.status ?? responseData.comment?.status;
                const approved = status === 'approved';

                this.message.textContent = responseData.message
                    ?? (approved ? 'Your comment has been posted.' : 'Your comment has been submitted for review.');
                this.message.className = `form-message ${approved ? 'success' : 'pending'}`;
                this.message.style.display = 'block';
                this.form.reset();

                await this.load(1);
            } catch (error) {
                this.message.textContent = error.message ?? 'Unable to submit comment.';
                this.message.className = 'form-message error';
                this.message.style.display = 'block';
            } finally {
                this.submit.disabled = false;
            }
        }

        renderThread(thread) {
            this.container.innerHTML = '';

            if (!thread.length) {
                const empty = document.createElement('div');
                empty.className = 'no-comments';
                empty.innerHTML = '<h3>No comments yet</h3><p>Be the first to share your thoughts!</p>';
                this.container.append(empty);
                return;
            }

            thread.forEach(comment => this.container.append(this.createComment(comment)));
        }

        createComment(comment) {
            const article = document.createElement('article');
            article.className = 'comment-card';
            article.dataset.commentId = String(comment.id ?? '');

            const name = String(comment.name ?? 'Member');
            const avatar = document.createElement('div');
            avatar.className = 'comment-avatar';
            const circle = document.createElement('div');
            circle.className = 'avatar-circle';
            circle.textContent = name.trim().charAt(0).toUpperCase() || 'M';
            avatar.append(circle);

            const body = document.createElement('div');
            body.className = 'comment-body';
            const meta = document.createElement('div');
            meta.className = 'comment-meta';
            const author = document.createElement('h4');
            author.className = 'comment-author';
            author.textContent = name;
            const time = document.createElement('time');
            time.className = 'comment-date';
            time.dateTime = String(comment.created_at ?? '');
            time.textContent = this.formatDate(comment.created_at);
            const content = document.createElement('div');
            content.className = 'comment-content';
            content.textContent = String(comment.content ?? '');

            meta.append(author, time);
            body.append(meta, content);
            article.append(avatar, body);
            return article;
        }

        formatDate(value) {
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
        }

        renderCount(count) {
            if (this.count) this.count.textContent = String(count);
        }

        renderPagination(pagination) {
            if (!this.pagination) return;
            const lastPage = Number(pagination.last_page ?? 1);
            this.pagination.hidden = lastPage <= 1;
            if (this.pageLabel) this.pageLabel.textContent = `Page ${this.currentPage} of ${lastPage}`;
            if (this.previous) this.previous.disabled = !pagination.has_previous;
            if (this.next) this.next.disabled = !pagination.has_next;
        }
    }

    class NewsletterComponent {
        constructor(element) {
            this.element = element;
            this.siteId = element.dataset.siteId;
            this.siteSlug = element.dataset.siteSlug;
            this.storageKey = element.dataset.storageKey;
            this.subscribed = element.dataset.subscribed === 'true';
        }

        start() {
            if (this.element.dataset.apiHydrated === 'true') return;
            this.element.dataset.apiHydrated = 'true';

            if (this.subscribed) {
                return;
            }

            this.element.querySelectorAll('[data-nl-open]').forEach(button => {
                button.addEventListener('click', () => this.openModal());
            });

            this.element.querySelectorAll('[data-nl-close]').forEach(button => {
                button.addEventListener('click', () => this.closeModal());
            });

            const modal = document.getElementById('nl-modal-' + this.siteId);
            if (modal) {
                modal.addEventListener('click', event => {
                    if (event.target === modal) {
                        this.closeModal();
                    }
                });
            }

            this.element.querySelectorAll('[data-nl-form]').forEach(form => {
                form.addEventListener('submit', event => this.handleSubmit(event));
            });

            if (modal) {
                modal.querySelectorAll('[data-nl-form]').forEach(form => {
                    form.addEventListener('submit', event => this.handleSubmit(event));
                });
                modal.querySelectorAll('[data-nl-close]').forEach(button => {
                    button.addEventListener('click', () => this.closeModal());
                });
            }
        }

        openModal() {
            const modal = document.getElementById('nl-modal-' + this.siteId);
            if (!modal) return;
            modal.removeAttribute('hidden');
            modal.querySelector('input[type="email"]')?.focus();
        }

        closeModal() {
            const modal = document.getElementById('nl-modal-' + this.siteId);
            if (modal) {
                modal.setAttribute('hidden', '');
            }
            try {
                if (this.storageKey) localStorage.setItem(this.storageKey, '1');
            } catch (e) {}
        }

        handleSubmit(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const source = form.dataset.nlForm || 'inline';
            const success = document.getElementById('nl-' + source + '-success-' + this.siteId);
            const error = document.getElementById('nl-' + source + '-error-' + this.siteId);
            const email = form.querySelector('input[name="email"]');
            const newsletterConsent = form.querySelector('input[name="newsletter_consent"]');
            const offersConsent = form.querySelector('input[name="offers_consent"]');

            if (!form.reportValidity()) return;

            if (error) {
                error.setAttribute('hidden', '');
                error.textContent = '';
            }

            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;

            fetch('/' + this.siteSlug + '/newsletter/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    email: email.value.trim(),
                    site_id: Number(this.siteId),
                    newsletter_consent: !!(newsletterConsent && newsletterConsent.checked),
                    offers_consent: !!(offersConsent && offersConsent.checked)
                })
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json().catch(() => ({})).then(body => {
                            throw new Error(body.message || 'We could not complete your subscription. Please try again.');
                        });
                    }
                    return response.json();
                })
                .then(() => {
                    form.setAttribute('hidden', '');
                    if (success) success.removeAttribute('hidden');
                    try {
                        if (this.storageKey) localStorage.setItem(this.storageKey, '1');
                    } catch (e) {}
                })
                .catch(requestError => {
                    if (error) {
                        error.textContent = requestError.message || 'We could not complete your subscription. Please try again.';
                        error.removeAttribute('hidden');
                    }
                })
                .finally(() => {
                    if (button) button.disabled = false;
                });
        }
    }

    class ComponentHydratorRegistry {
        constructor(api) {
            this.api = api;
            this.assets = new ComponentAssetManifestLoader();

            this.registerIslands();
        }

        registerIslands() {
            if (!window.PublicIslands) {
                return;
            }

            window.PublicIslands.register('page-actions', {
                hydrate: (element, props) => new PageActionsComponent(element, this.componentFromProps(element, props), this.api).start(),
            });

            window.PublicIslands.register('comments', {
                hydrate: (element, props) => new CommentsComponent(element, this.componentFromProps(element, props), this.api).start(),
            });

            window.PublicIslands.register('newsletter-signup-widget', {
                hydrate: (element, props) => new NewsletterComponent(element, this.componentFromProps(element, props), this.api).start(),
            });

            window.PublicIslands.register('guest-contributors', {
                hydrate: (element) => new GuestContributorsCarousel(element).start(),
            });
        }

        componentFromProps(element, props = {}) {
            return {
                id: element.dataset.componentId,
                type: element.dataset.componentType ?? element.dataset.component ?? element.dataset.island,
                endpoints: props.endpoints ?? {},
            };
        }

        hydrate(element, component) {
            if (component.stateful && window.PublicIslands) {
                window.PublicIslands.scan(element);
                return;
            }

            this.assets.load(component);
        }
    }

    const root = document.getElementById('public-content-v2-app');
    const registry = new ComponentHydratorRegistry(
        new PublicContentComponentApi(root?.dataset.csrfToken ?? ''),
    );

    document.addEventListener('public-content:component-mounted', event => {
        registry.hydrate(event.detail.element, event.detail.component);
    });

    document.addEventListener('public-content:document-composed', event => {
        event.detail.root.querySelector('[data-region="header"]')?.classList.add('page-header');
    });
})();
