(() => {
    'use strict';

    class SubscriptionAccountApiClient {
        async get(url) {
            return this.request(url);
        }

        async post(url, payload = {}) {
            return this.request(url, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        }

        async request(url, options = {}) {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    ...(options.headers || {}),
                },
            });

            let payload = {};

            try {
                payload = await response.json();
            } catch {
                payload = {};
            }

            const result = payload.data ?? payload;

            if (!response.ok || result.success === false) {
                throw new Error(result.message || 'The request could not be completed.');
            }

            return result;
        }
    }

    class SubscriptionAccountState {
        constructor() {
            this.subscription = null;
            this.listeners = new Set();
        }

        get current() {
            return this.subscription;
        }

        setSubscription(subscription) {
            this.subscription = subscription;

            for (const listener of this.listeners) {
                listener(subscription);
            }
        }

        subscribe(listener) {
            this.listeners.add(listener);

            return () => this.listeners.delete(listener);
        }
    }

    window.SubscriptionAccount = Object.freeze({
        api: new SubscriptionAccountApiClient(),
        state: new SubscriptionAccountState(),
    });
})();
