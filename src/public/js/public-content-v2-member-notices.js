(() => {
    'use strict';

    class MemberNoticeStore {
        constructor() {
            this.state = {status: 'idle', viewer: null};
            this.listeners = [];
        }

        subscribe(listener) {
            this.listeners.push(listener);
            listener(this.state);
        }

        update(patch) {
            this.state = {...this.state, ...patch};
            this.listeners.forEach(listener => listener(this.state));
        }
    }

    class MemberNoticeApi {
        constructor(contentUrl) {
            this.contentUrl = contentUrl;
        }

        async get(url) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {Accept: 'application/json'},
            });
            const payload = await response.json();
            if (!response.ok) throw new Error('Unable to load member state.');
            return payload.data;
        }

        async load() {
            const content = await this.get(this.contentUrl);
            return content.links?.viewer_state
                ? this.get(content.links.viewer_state)
                : null;
        }
    }

    class MemberNoticeView {
        render(root, state) {
            root.replaceChildren();
            if (state.status !== 'loaded' || !state.viewer) return;

            const viewer = state.viewer;
            if (viewer.subscription?.required) {
                root.append(this.notice('Subscription required', viewer.subscription.reason, 'is-warning'));
            }
            if (viewer.gift?.claimed) {
                root.append(this.notice('Gift claimed', viewer.gift.message, 'is-success'));
            }
            if (viewer.next_comment_badge) {
                root.append(this.notice('Next badge', viewer.next_comment_badge.name));
            }
        }

        notice(title, message, className = '') {
            const element = document.createElement('div');
            element.className = `public-content-v2-notice ${className}`.trim();
            const strong = document.createElement('strong');
            strong.textContent = `${title}. `;
            element.append(strong, document.createTextNode(message ?? ''));
            return element;
        }
    }

    class MemberNoticeApp {
        constructor(root, api, store, view) {
            this.root = root;
            this.api = api;
            this.store = store;
            this.view = view;
        }

        start() {
            this.store.subscribe(state => this.view.render(this.root, state));
            this.load();
        }

        async load() {
            this.store.update({status: 'loading'});
            try {
                this.store.update({status: 'loaded', viewer: await this.api.load()});
            } catch (error) {
                this.store.update({status: 'error'});
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('public-content-v2-member-notices');
        const contentRoot = document.getElementById('public-content-v2-app');
        if (!root || !contentRoot?.dataset.apiUrl) return;
        new MemberNoticeApp(
            root,
            new MemberNoticeApi(contentRoot.dataset.apiUrl),
            new MemberNoticeStore(),
            new MemberNoticeView(),
        ).start();
    });
})();
