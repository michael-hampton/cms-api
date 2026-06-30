/**
 * oc-api-client.js
 * Thin fetch wrapper with GET caching + bearer auth, shared by every widget.
 * Must be loaded after oc-shared.js and before any widget file.
 */
(() => {
    class OpenCollabApiClient {
        constructor(token) {
            this.token = token; // () => string
            this.cache = new Map();
        }

        /** Drop any cached GET response for this URL (used after a mutation). */
        bust(url) {
            this.cache.delete(url);
        }

        async fetchJson(url, options = {}) {
            const method = String(options.method ?? 'GET').toUpperCase();
            const cacheKey = method === 'GET' && !options.body ? url : null;

            if (cacheKey && this.cache.has(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            const request = fetch(url, {
                ...options,
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${this.token()}`,
                    ...(options.headers ?? {}),
                },
            }).then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            });

            if (cacheKey) this.cache.set(cacheKey, request);

            try {
                return await request;
            } catch (e) {
                if (cacheKey) this.cache.delete(cacheKey);
                throw e;
            }
        }

        /** Convenience wrapper for mutating JSON POST/PUT/etc requests. */
        async sendJson(url, body, method = 'POST') {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    Authorization: `Bearer ${this.token()}`,
                },
                body: JSON.stringify(body),
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(payload.error || payload.message || 'Request failed.');
            return payload;
        }
    }

    window.OpenCollabApiClient = OpenCollabApiClient;
})();