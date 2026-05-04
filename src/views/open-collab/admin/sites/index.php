@extends('open-collab/layouts/admin')

@section('content')

<div class="oc-page__header">
    <div>
        <h1 class="oc-page__title">Sites</h1>
        <p class="oc-page__subtitle">All sites running on this installation.</p>
    </div>
</div>

<div class="oc-sites-grid" id="sites-grid">

    <?php if (empty($sites)): ?>

        <div class="oc-sites-empty">
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                      d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083 9zM10 2a8 8 0 100 16A8 8 0 0010 2zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.559-.499-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.559.499.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.498-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0C6.412 13.97 6.118 12.546 6.03 11H4.083a6.004 6.004 0 002.783 4.118z"
                      clip-rule="evenodd"/>
            </svg>
            <p class="oc-sites-empty__title">No sites yet</p>
            <p class="oc-sites-empty__body">Sites will appear here once created.</p>
        </div>

    <?php else: ?>

        <?php foreach ($sites as $s): ?>
            <div class="oc-site-card" data-site-id="<?= (int)$s['id'] ?>">

                <div class="oc-site-card__header">

                    <div class="oc-site-card__identity">
                        <div class="oc-site-card__avatar">
                            <?= htmlspecialchars(strtoupper(substr($s['name'], 0, 1))) ?>
                        </div>
                        <div>
                            <div class="oc-site-card__name"><?= htmlspecialchars($s['name']) ?></div>
                            <div class="oc-site-card__slug">/<?= htmlspecialchars($s['slug']) ?></div>
                        </div>
                    </div>

                    <?php if ($s['is_active']): ?>
                        <span class="oc-pill oc-pill--active">
                            <span class="oc-pill__dot"></span>
                            Active
                        </span>
                    <?php else: ?>
                        <span class="oc-pill oc-pill--inactive">
                            <span class="oc-pill__dot"></span>
                            Inactive
                        </span>
                    <?php endif; ?>

                </div><!-- /header -->

                <div class="oc-site-card__body">
                    <div class="oc-site-card__meta">

                        <div class="oc-site-card__meta-row">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083 9zM10 2a8 8 0 100 16A8 8 0 0010 2zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.559-.499-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.559.499.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.498-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0C6.412 13.97 6.118 12.546 6.03 11H4.083a6.004 6.004 0 002.783 4.118z"
                                      clip-rule="evenodd"/>
                            </svg>
                            <?php if (!empty($s['domain'])): ?>
                                <span class="oc-site-card__meta-value">
                                    <?= htmlspecialchars($s['domain']) ?>
                                </span>
                            <?php elseif (!empty($s['subdomain'])): ?>
                                <span class="oc-site-card__meta-value">
                                    <?= htmlspecialchars($s['subdomain']) ?>.<span
                                            style="opacity:.6"><?= htmlspecialchars(config('app.base_domain', 'example.com')) ?></span>
                                </span>
                            <?php else: ?>
                                <span class="oc-site-card__meta-value oc-site-card__meta-value--empty">
                                    No domain configured
                                </span>
                            <?php endif; ?>
                        </div>

                    </div>
                </div><!-- /body -->

                <div class="oc-site-card__footer">
                    <a
                            href="/<?= htmlspecialchars($s['slug']) ?>/open-collab/admin/sites/settings"
                            class="oc-btn oc-btn--ghost oc-btn--sm">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
                            <path fill-rule="evenodd"
                                  d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                                  clip-rule="evenodd"/>
                        </svg>
                        Settings
                    </a>

                    <button
                            type="button"
                            class="oc-btn oc-btn--ghost oc-btn--sm js-toggle-status"
                            data-site-id="<?= (int)$s['id'] ?>"
                            data-active="<?= $s['is_active'] ? '1' : '0' ?>">
                        <?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                </div>

            </div><!-- /card -->
        <?php endforeach; ?>

    <?php endif; ?>

</div><!-- /grid -->

@endsection

@section('scripts')
<script>
    /**
     * SiteIndexPage
     * ─────────────
     * Handles inline activate / deactivate toggling on the card grid.
     * No full page reload — updates the pill and button text optimistically,
     * rolls back on API failure.
     */
    class SiteIndexPage {

        constructor() {
            this.token = localStorage.getItem('oc_token') || '';
            this._bindEvents();
        }

        _bindEvents() {
            document.getElementById('sites-grid')
                ?.addEventListener('click', (e) => {
                    const btn = e.target.closest('.js-toggle-status');
                    if (btn) this._toggleStatus(btn);
                });
        }

        async _toggleStatus(btn) {
            if (btn.disabled) return;

            const siteId = parseInt(btn.dataset.siteId, 10);
            const isActive = btn.dataset.active === '1';
            const newState = !isActive;

            // Optimistic UI
            btn.disabled = true;
            this._applyCardState(btn, newState);

            try {
                const res = await fetch(`/api/sites/${siteId}/toggle-status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(this.token ? {Authorization: `Bearer ${this.token}`} : {}),
                    },
                    body: JSON.stringify({is_active: newState}),
                });

                if (!res.ok) {
                    throw new Error(`Server error (${res.status})`);
                }

            } catch (err) {
                // Rollback
                this._applyCardState(btn, isActive);
                this._showError(`Could not update site status: ${err.message}`);
            } finally {
                btn.disabled = false;
            }
        }

        /**
         * Update pill + button text in the card without re-rendering.
         *
         * @param {HTMLElement} btn
         * @param {boolean} active
         */
        _applyCardState(btn, active) {
            btn.dataset.active = active ? '1' : '0';
            btn.textContent = active ? 'Deactivate' : 'Activate';

            const card = btn.closest('.oc-site-card');
            if (!card) return;

            const pill = card.querySelector('.oc-pill');
            if (!pill) return;

            pill.className = `oc-pill ${active ? 'oc-pill--active' : 'oc-pill--inactive'}`;
            pill.innerHTML = `<span class="oc-pill__dot"></span>${active ? 'Active' : 'Inactive'}`;
        }

        _showError(message) {
            const container = document.querySelector('.oc-main') || document.body;
            const alert = document.createElement('div');
            alert.className = 'oc-alert oc-alert--danger';
            alert.style.cssText = 'margin:16px 32px 0;';
            alert.innerHTML = `
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                          clip-rule="evenodd"/>
                </svg>
                ${this._escape(message)}
            `;

            const main = document.getElementById('main-content');
            main ? main.prepend(alert) : container.prepend(alert);

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

    new SiteIndexPage();
</script>
@endsection