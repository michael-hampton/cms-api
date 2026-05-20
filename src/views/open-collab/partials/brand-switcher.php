<?php

use App\Framework\Authorization\Auth;
use App\Models\UserSite;

?>
<div class="oc-brand-switcher" id="oc-brand-switcher"
     data-current-slug="<?= htmlspecialchars($site ?? '') ?>"
     data-api-base="/api/sites"
     data-user-id="<?= htmlspecialchars((string)($currentUser->id ?? '')) ?>">

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

        <?php
        $userSites = UserSite::query()
                ->with('site')
                ->where('user_id', Auth::id())
                ->get()
                ->filter(fn($userSite) => $userSite->site !== null)
                ->map(function ($userSite) use ($site) {
                    return [
                            'slug' => $userSite->site->slug,
                            'name' => $userSite->site->name,
                            'active' => $userSite->site->slug === $site,
                    ];
                });

        foreach ($userSites as $brand): ?>

            <li
                    class="oc-brand-switcher__option <?= $brand['active']
                            ? 'oc-brand-switcher__option--active'
                            : '' ?>"
                    role="option"
                    aria-selected="<?= $brand['active'] ? 'true' : 'false' ?>"
                    data-slug="<?= htmlspecialchars($brand['slug']) ?>"
                    data-name="<?= htmlspecialchars($brand['name']) ?>"
            >

                <span class="oc-brand-switcher__option-dot"></span>

                <?= htmlspecialchars($brand['name']) ?>

                <?php if ($brand['active']): ?>
                    <svg
                            class="oc-brand-switcher__option-check"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            width="14"
                            height="14"
                    >
                        <path
                                fill-rule="evenodd"
                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                clip-rule="evenodd"
                        />
                    </svg>
                <?php endif; ?>

            </li>

        <?php endforeach; ?>

    </ul>

    <div class="oc-brand-switcher__overlay" id="oc-switcher-overlay"></div>
</div>

<script>
    (function () {
        'use strict';

        /**
         * BrandSwitcher
         * ─────────────
         * Manages site switching with cross-session persistence.
         *
         * Persistence strategy:
         *   - Selected slug stored in localStorage under a key scoped to the
         *     authenticated user ID so different users on the same browser
         *     never share brand context.
         *   - Key format:  oc_brand_{userId}
         *   - On page load the stored slug is validated against the list of
         *     slugs rendered in the DOM (access check happens server-side at
         *     render time via Site::all()). If the stored slug is no longer
         *     in the DOM list, it is cleared and the current URL slug is used.
         *
         * Single-brand users:
         *   - If only one option exists in the DOM list it is auto-selected
         *     and persisted without showing a prompt.
         */
        class BrandSwitcher {

            constructor(options = {}) {
                this.currentSlug = options.currentSlug || '';
                this.apiBase = options.apiBase || '/api/sites';
                this.token = options.token || localStorage.getItem('oc_token') || '';
                this.userId = options.userId || '';

                this.trigger = document.getElementById('oc-switcher-btn');
                this.dropdown = document.getElementById('oc-switcher-dropdown');
                this.overlay = document.getElementById('oc-switcher-overlay');
                this.label = document.getElementById('oc-switcher-label');
                this.searchInput = document.getElementById('oc-switcher-search');

                // All option elements (excludes search row and no-results placeholder)
                this.options = Array.from(
                    (this.dropdown || document.createElement('ul'))
                        .querySelectorAll('.oc-brand-switcher__option:not(#oc-switcher-no-results)')
                );
                this.noResults = document.getElementById('oc-switcher-no-results');

                this._switching = false;

                if (this.trigger) {
                    this._bindEvents();
                }

                this._handleStartup();
            }

            // ── Persistence helpers ─────────────────────────────────────────

            _storageKey() {
                // Scoped to user so different users on the same browser are isolated
                return this.userId ? `oc_brand_${this.userId}` : null;
            }

            _persistSlug(slug) {
                const key = this._storageKey();
                if (!key) return;
                try {
                    localStorage.setItem(key, slug);
                } catch (_) { /* storage full / private mode — silently ignore */
                }
            }

            _loadPersistedSlug() {
                const key = this._storageKey();
                if (!key) return null;
                try {
                    return localStorage.getItem(key) || null;
                } catch (_) {
                    return null;
                }
            }

            _clearPersistedSlug() {
                const key = this._storageKey();
                if (!key) return;
                try {
                    localStorage.removeItem(key);
                } catch (_) {
                }
            }

            /** All slugs available in the current rendered DOM list. */
            _availableSlugs() {
                return this.options.map(o => o.dataset.slug).filter(Boolean);
            }

            // ── Startup: restore or auto-select ────────────────────────────

            _handleStartup() {
                const available = this._availableSlugs();

                // Single-brand users: auto-select and persist without prompting
                if (available.length === 1) {
                    this._persistSlug(available[0]);
                    // No redirect needed — the server already rendered this site
                    return;
                }

                const persisted = this._loadPersistedSlug();

                if (!persisted) {
                    // First visit — persist the slug the server rendered
                    this._persistSlug(this.currentSlug);
                    return;
                }

                // Validate: persisted slug must still be in the accessible list
                if (!available.includes(persisted)) {
                    // Access revoked or slug renamed — fall back to first available
                    this._clearPersistedSlug();
                    const fallback = available[0] || this.currentSlug;
                    this._persistSlug(fallback);

                    if (fallback && fallback !== this.currentSlug) {
                        window.location.href = this._buildUrl(fallback);
                    }
                    return;
                }

                // Persisted slug differs from current URL slug → redirect to restore
                if (persisted !== this.currentSlug) {
                    window.location.href = this._buildUrl(persisted);
                }
            }

            // ── Public ──────────────────────────────────────────────────────

            open() {
                if (!this.dropdown) return;
                this.dropdown.style.display = 'block';
                this.trigger.setAttribute('aria-expanded', 'true');
                if (this.overlay) this.overlay.style.display = 'block';
                if (this.searchInput) this.searchInput.focus();
            }

            close() {
                if (!this.dropdown) return;
                this.dropdown.style.display = 'none';
                this.trigger.setAttribute('aria-expanded', 'false');
                if (this.overlay) this.overlay.style.display = 'none';
                if (this.searchInput) {
                    this.searchInput.value = '';
                    this._filterSites('');
                }
            }

            toggle() {
                const isOpen = this.dropdown && this.dropdown.style.display !== 'none';
                isOpen ? this.close() : this.open();
            }

            // ── Private ─────────────────────────────────────────────────────

            _bindEvents() {
                this.trigger.addEventListener('click', e => {
                    e.stopPropagation();
                    this.toggle();
                });

                if (this.overlay) {
                    this.overlay.addEventListener('click', () => this.close());
                }

                document.addEventListener('keydown', e => {
                    if (e.key === 'Escape') this.close();
                });

                if (this.dropdown) {
                    this.dropdown.addEventListener('click', e => {
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
                    this.searchInput.addEventListener('input', e => this._filterSites(e.target.value));
                    this.searchInput.addEventListener('click', e => e.stopPropagation());
                }
            }

            _filterSites(query) {
                const filter = query.toLowerCase().trim();
                let hasMatch = false;

                this.options.forEach(opt => {
                    const name = (opt.dataset.name || '').toLowerCase();
                    const isMatch = name.includes(filter);
                    opt.style.display = isMatch ? 'flex' : 'none';
                    if (isMatch) hasMatch = true;
                });

                if (this.noResults) {
                    this.noResults.style.display = hasMatch ? 'none' : 'flex';
                }
            }

            async _switchTo(slug, name) {
                if (this._switching) return;
                this._switching = true;
                this._setLoading(true);

                try {
                    // Persist before navigating so the preference is saved even
                    // if the page unloads before the next DOMContentLoaded fires.
                    this._persistSlug(slug);
                    window.location.href = this._buildUrl(slug);
                } catch (err) {
                    console.error('[BrandSwitcher]', err);
                    this._clearPersistedSlug();   // don't persist a failed switch
                    this._showError(err.message || 'Could not switch site');
                    this._setLoading(false);
                    this._switching = false;
                    this.close();
                }
            }

            /**
             * Replaces the {site} segment in the current URL path.
             * Handles both:
             *   /{slug}/open-collab/...
             *   /{slug}/open-collab/admin/...
             */
            _buildUrl(newSlug) {
                const {pathname, search, hash} = window.location;
                const escaped = this.currentSlug.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                const pattern = new RegExp(`^/${escaped}(/|$)`);

                if (pattern.test(pathname)) {
                    return pathname.replace(pattern, `/${newSlug}$1`) + search + hash;
                }

                // Fallback: replace first path segment
                const parts = pathname.split('/');
                parts[1] = newSlug;
                return parts.join('/') + search + hash;
            }

            _setLoading(on) {
                if (!this.trigger) return;
                this.trigger.disabled = on;
                this.trigger.classList.toggle('oc-brand-switcher__trigger--loading', on);
            }

            _showError(message) {
                const container = document.querySelector('.oc-main') || document.body;
                const alert = document.createElement('div');
                alert.className = 'oc-alert oc-alert--danger';
                alert.style.cssText = 'margin:16px 32px;';
                alert.innerHTML = `
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414
                             1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293
                             1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0
                             00-1.414-1.414L10 8.586 8.707 7.293z"
                          clip-rule="evenodd"/>
                </svg>
                ${this._escape(message)}`;
                container.prepend(alert);
                setTimeout(() => {
                    alert.style.transition = 'opacity .4s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 400);
                }, 4500);
            }

            _escape(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }
        }

        // ── Auto-init ────────────────────────────────────────────────────────────

        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('oc-brand-switcher');
            if (!el) return;

            window.brandSwitcher = new BrandSwitcher({
                currentSlug: el.dataset.currentSlug || '',
                apiBase: el.dataset.apiBase || '/api/sites',
                token: localStorage.getItem('oc_token') || '',
                userId: el.dataset.userId || '',
            });
        });
    }());
</script>