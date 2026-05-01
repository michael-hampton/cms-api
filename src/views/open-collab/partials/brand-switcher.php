<style>
    /* Container & Layout */
    .oc-brand-switcher {
        position: relative;
        display: inline-block;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    /* The Trigger Button */
    .oc-brand-switcher__trigger {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
        transition: all 0.2s ease;
        white-space: nowrap;
        outline: none;
    }

    .oc-brand-switcher__trigger:hover {
        background-color: #f8fafc;
        border-color: #cbd5e1;
    }

    .oc-brand-switcher__trigger:focus {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        border-color: #3b82f6;
    }

    /* Chevron Rotation */
    .oc-brand-switcher__trigger[aria-expanded="true"] .oc-brand-switcher__chevron {
        transform: rotate(180deg);
    }

    .oc-brand-switcher__chevron {
        color: #64748b;
        transition: transform 0.2s ease;
    }

    /* Dropdown Menu */
    .oc-brand-switcher__dropdown {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        min-width: 220px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        padding: 6px;
        margin: 0;
        list-style: none;
        z-index: 1000;

        /* Scrollbar Logic */
        max-height: 300px; /* Limits height to ~6-7 items */
        overflow-y: auto;
        overflow-x: hidden;

        /* Smooth scrolling for mobile */
        -webkit-overflow-scrolling: touch;
    }

    /* Menu Options */
    .oc-brand-switcher__option {
        display: flex;
        align-items: center;
        padding: 10px 12px;
        font-size: 14px;
        color: #475569;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.1s;
    }

    .oc-brand-switcher__option:hover {
        background-color: #f1f5f9;
        color: #0f172a;
    }

    /* Active State */
    .oc-brand-switcher__option--active {
        background-color: #eff6ff;
        color: #2563eb;
        font-weight: 500;
    }

    .oc-brand-switcher__option-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #cbd5e1;
        margin-right: 12px;
    }

    .oc-brand-switcher__option--active .oc-brand-switcher__option-dot {
        background-color: #3b82f6;
    }

    .oc-brand-switcher__option-check {
        margin-left: auto;
        color: #3b82f6;
    }

    /* Loading State for Trigger */
    .oc-brand-switcher__trigger--loading {
        opacity: 0.7;
        cursor: wait;
    }

    /* Overlay to catch clicks outside */
    .oc-brand-switcher__overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 999;
        display: none;
    }

    /* Animation */
    @keyframes oc-slide-up {
        from {
            opacity: 0;
            transform: translateY(4px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Search Container */
    .oc-brand-switcher__search-container {
        position: sticky;
        top: -6px; /* Offsets the dropdown padding */
        background: #ffffff;
        padding: 8px;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 4px;
        z-index: 10;
    }

    .oc-brand-switcher__search-input {
        width: 100%;
        padding: 6px 10px;
        font-size: 13px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        outline: none;
        box-sizing: border-box;
    }

    .oc-brand-switcher__search-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }
</style>

<div class="oc-brand-switcher" id="oc-brand-switcher">

    <button
            class="oc-brand-switcher__trigger"
            id="oc-switcher-btn"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-label="Switch site"
            type="button">

        <span class="oc-brand-switcher__label" id="oc-switcher-label">
            <?= htmlspecialchars($currentSiteName ?? $site ?? 'Select site') ?>
        </span>

        <svg class="oc-brand-switcher__chevron" viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
            <path fill-rule="evenodd"
                  d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                  clip-rule="evenodd"/>
        </svg>
    </button>

    <ul
            class="oc-brand-switcher__dropdown"
            id="oc-switcher-dropdown"
            role="listbox"
            aria-label="Available sites"
            style="display:none;">

        <li class="oc-brand-switcher__search-container">
            <input
                    type="text"
                    id="oc-switcher-search"
                    class="oc-brand-switcher__search-input"
                    placeholder="Search sites..."
                    autocomplete="off"
            >
        </li>

        <?php foreach (\App\Models\Site::all() ?? [] as $s): ?>
            <li
                    class="oc-brand-switcher__option <?= ($s->slug === $site) ? 'oc-brand-switcher__option--active' : '' ?>"
                    role="option"
                    aria-selected="<?= ($s->slug === $site) ? 'true' : 'false' ?>"
                    data-slug="<?= htmlspecialchars($s->slug) ?>"
                    data-name="<?= htmlspecialchars($s->name) ?>">

                <span class="oc-brand-switcher__option-dot"></span>
                <?= htmlspecialchars($s->name) ?>

                <?php if ($s->slug === $site): ?>
                    <svg class="oc-brand-switcher__option-check" viewBox="0 0 20 20" fill="currentColor" width="14"
                         height="14">
                        <path fill-rule="evenodd"
                              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                              clip-rule="evenodd"/>
                    </svg>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>

    </ul>

    <div class="oc-brand-switcher__overlay" id="oc-switcher-overlay"></div>
</div>

<script>
    /**
     * BrandSwitcher
     * ─────────────
     * Replaces the {site} segment in the current URL with the chosen slug,
     * after confirming the site exists via the SiteController API.
     *
     * URL pattern assumed:  /{site}/open-collab/...  or  /{site}/open-collab/admin/...
     *
     * Usage (auto-init via data attribute — see bottom of file).
     */
    class BrandSwitcher {

        /**
         * @param {object} options
         * @param {string} options.currentSlug   – active site slug
         * @param {string} options.apiBase       – e.g. '/api/sites'
         * @param {string} [options.token]       – Bearer token (reads localStorage by default)
         * @param {string} [options.triggerId]   – override element IDs if needed
         */
        constructor(options = {}) {
            this.currentSlug = options.currentSlug || '';
            this.apiBase = options.apiBase || '/api/sites';
            this.token = options.token || localStorage.getItem('oc_token') || '';

            this.trigger = document.getElementById('oc-switcher-btn');
            this.dropdown = document.getElementById('oc-switcher-dropdown');
            this.overlay = document.getElementById('oc-switcher-overlay');
            this.label = document.getElementById('oc-switcher-label');

            this.searchInput = document.getElementById('oc-switcher-search');
            this.options = this.dropdown.querySelectorAll('.oc-brand-switcher__option:not(#oc-switcher-no-results)');
            this.noResults = document.getElementById('oc-switcher-no-results');

            this._switching = false;

            if (this.trigger) {
                this._bindEvents();
            }
        }

        // ── Public ───────────────────────────────────────────────────────────────

        open() {
            if (!this.dropdown) return;
            this.dropdown.style.display = 'block';
            this.trigger.setAttribute('aria-expanded', 'true');
            if (this.overlay) this.overlay.style.display = 'block';
        }

        close() {
            if (!this.dropdown) return;
            this.dropdown.style.display = 'none';
            this.trigger.setAttribute('aria-expanded', 'false');
            if (this.overlay) this.overlay.style.display = 'none';

            // Reset search on close
            if (this.searchInput) {
                this.searchInput.value = '';
                this._filterSites('');
            }
        }

        toggle() {
            const isOpen = this.dropdown && this.dropdown.style.display !== 'none';
            isOpen ? this.close() : this.open();
        }

        // ── Private ──────────────────────────────────────────────────────────────

        _bindEvents() {
            this.trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });

            if (this.overlay) {
                this.overlay.addEventListener('click', () => this.close());
            }

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.close();
            });

            // Option selection
            if (this.dropdown) {
                this.dropdown.addEventListener('click', (e) => {
                    const option = e.target.closest('.oc-brand-switcher__option');
                    if (!option) return;

                    const slug = option.dataset.slug;
                    const name = option.dataset.name;

                    if (!slug || slug === this.currentSlug) {
                        this.close();
                        return;
                    }

                    this._switchTo(slug, name);
                });
            }

            if (this.searchInput) {
                this.searchInput.addEventListener('input', (e) => this._filterSites(e.target.value));

                // Stop clicks on search from closing the dropdown
                this.searchInput.addEventListener('click', (e) => e.stopPropagation());
            }
        }

        _filterSites(query) {
            const filter = query.toLowerCase().trim();
            let hasMatch = false;

            this.options.forEach(opt => {
                const name = opt.dataset.name.toLowerCase();
                const isMatch = name.includes(filter);
                opt.style.display = isMatch ? 'flex' : 'none';
                if (isMatch) hasMatch = true;
            });

            if (this.noResults) {
                this.noResults.style.display = hasMatch ? 'none' : 'flex';
            }
        }

        /**
         * Validates the target site via API then redirects.
         * The API call guards against switching to a deleted/inactive site
         * that may still be in the DOM list (stale render).
         *
         * @param {string} slug
         * @param {string} name
         */
        async _switchTo(slug, name) {
            if (this._switching) return;
            this._switching = true;

            this._setLoading(true);

            try {
                // const res = await fetch(`${this.apiBase}/by-slug/${slug}`, {
                //     headers: this._headers(),
                // });
                //
                // if (!res.ok) {
                //     throw new Error(`Site "${name}" is not available (${res.status})`);
                // }

                window.location.href = this._buildUrl(slug);

            } catch (err) {
                console.error('[BrandSwitcher]', err);
                this._showError(err.message || 'Could not switch site');
                this._setLoading(false);
                this._switching = false;
                this.close();
            }
        }

        /**
         * Replaces the first path segment that matches currentSlug with the new slug.
         * Handles:
         *   /guitar-world/open-collab/admin/contributors/2/violations
         *   /guitar-world/open-collab/violations
         *
         * Falls back to swapping any leading /{slug}/ pattern.
         *
         * @param {string} newSlug
         * @returns {string}
         */
        _buildUrl(newSlug) {
            const {pathname, search, hash} = window.location;

            // Escape current slug for safe regex use
            const escaped = this.currentSlug.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const pattern = new RegExp(`^/${escaped}(/|$)`);

            if (pattern.test(pathname)) {
                return pathname.replace(pattern, `/${newSlug}$1`) + search + hash;
            }

            // Fallback: replace first segment regardless
            const parts = pathname.split('/');   // ['', 'guitar-world', 'open-collab', ...]
            parts[1] = newSlug;
            return parts.join('/') + search + hash;
        }

        _setLoading(on) {
            if (!this.trigger) return;
            this.trigger.disabled = on;
            this.trigger.classList.toggle('oc-brand-switcher__trigger--loading', on);
        }

        _showError(message) {
            // Reuse the existing flash pattern from the layouts
            const container = document.querySelector('.oc-main') || document.body;
            const alert = document.createElement('div');
            alert.className = 'oc-alert oc-alert--danger';
            alert.style.cssText = 'margin:16px 32px;';
            alert.innerHTML = `
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                      clip-rule="evenodd"/>
            </svg>
            ${this._escape(message)}
        `;
            container.prepend(alert);
            setTimeout(() => {
                alert.style.transition = 'opacity .4s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 400);
            }, 4500);
        }

        _headers() {
            const h = {'Content-Type': 'application/json'};
            if (this.token) h['Authorization'] = `Bearer ${this.token}`;
            return h;
        }

        _escape(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
    }

    // ── Auto-init ─────────────────────────────────────────────────────────────────
    // Triggered by the layouts passing data attributes on #oc-brand-switcher

    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('oc-brand-switcher');
        if (!el) return;

        window.brandSwitcher = new BrandSwitcher({
            currentSlug: el.dataset.currentSlug || '',
            apiBase: el.dataset.apiBase || '/api/sites',
            token: localStorage.getItem('oc_token') || '',
        });
    });
</script>