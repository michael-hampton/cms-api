@section('logic')
<?php
/**
 * Template: open-collab/admin/payouts/index.php
 * Variables:
 *   $site        — string
 *   $currentUser — AuthenticatedUser
 *
 * All payout data is loaded client-side via:
 *   GET  /api/{site}/open-collab/admin/payouts          — PayoutController::adminIndex
 *   POST /api/{site}/open-collab/admin/payouts/{id}/approve
 *   POST /api/{site}/open-collab/admin/payouts/{id}/paid
 *   POST /api/{site}/open-collab/admin/payouts/{id}/reject
 */
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<!-- Decline modal -->
<div id="decline-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeDeclineModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:460px;width:92%;
                box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">
            Decline payout
        </h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            Provide a reason — this will be visible to the contributor.
        </p>
        <input type="hidden" id="decline-payout-id">
        <div class="oc-form-group">
            <label class="oc-label" for="decline-reason">Reason <span style="color:var(--red);">*</span></label>
            <textarea class="oc-textarea" id="decline-reason" rows="3" style="min-height:80px;"
                      placeholder="e.g. Missing bank details, incorrect payment method…" required></textarea>
        </div>
        <div id="decline-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button onclick="closeDeclineModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitDecline()" class="oc-btn oc-btn--danger" style="flex:1;" id="decline-confirm-btn">
                Decline payout
            </button>
        </div>
    </div>
</div>

<!-- Mark paid modal -->
<div id="paid-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closePaidModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:460px;width:92%;
                box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">
            Mark as paid
        </h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            Record the payment reference for the audit trail.
        </p>
        <input type="hidden" id="paid-payout-id">
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="paid-reference">Payment reference</label>
            <input class="oc-input" type="text" id="paid-reference"
                   placeholder="e.g. BACS ref, transaction ID…">
        </div>
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="paid-notes">Notes</label>
            <textarea class="oc-textarea" id="paid-notes" rows="2" style="min-height:60px;"
                      placeholder="Any internal notes…"></textarea>
        </div>
        <div id="paid-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button onclick="closePaidModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitMarkPaid()" class="oc-btn oc-btn--primary" style="flex:1;"
                    id="paid-confirm-btn">
                Confirm paid
            </button>
        </div>
    </div>
</div>

<!-- Stats bar (populated from API) -->
<div class="oc-stats" style="margin-bottom:24px;">
    <div class="oc-stat oc-stat--accent">
        <div class="oc-stat__label">Pending Review</div>
        <div class="oc-stat__value" id="stat-pending">—</div>
        <div class="oc-stat__sub">Awaiting approval</div>
    </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Pending Amount</div>
        <div class="oc-stat__value" id="stat-pending-amount">—</div>
        <div class="oc-stat__sub">Total in queue</div>
    </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Total Payouts</div>
        <div class="oc-stat__value" id="stat-total">—</div>
        <div class="oc-stat__sub">All time</div>
    </div>
</div>

<!-- Filter / search bar -->
<div class="oc-card" style="margin-bottom:20px;padding:16px 20px;">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div style="position:relative;flex:1;min-width:200px;">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16"
                 style="position:absolute;left:12px;top:50%;transform:translateY(-50%);
                        color:var(--slate-light);pointer-events:none;">
                <path fill-rule="evenodd"
                      d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                      clip-rule="evenodd"/>
            </svg>
            <input class="oc-input" type="text" id="search-input"
                   placeholder="Search by contributor ID or reference…"
                   style="padding-left:38px;" autocomplete="off">
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button class="filter-pill filter-pill--active" onclick="setFilter('all', this)">All</button>
            <button class="filter-pill" onclick="setFilter('pending', this)">Pending</button>
            <button class="filter-pill" onclick="setFilter('approved', this)">Approved</button>
            <button class="filter-pill" onclick="setFilter('paid', this)">Paid</button>
            <button class="filter-pill" onclick="setFilter('rejected', this)">Rejected</button>
        </div>
        <a href="/<?= htmlspecialchars($site) ?>/open-collab/admin/payouts/scheduled"
           class="oc-btn oc-btn--ghost oc-btn--sm" style="flex-shrink:0;">
            <svg viewBox="0 0 20 20" fill="currentColor" width="13" style="margin-right:4px;">
                <path fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                      clip-rule="evenodd"/>
            </svg>
            View schedule
        </a>
    </div>
</div>

<style>
    .filter-pill {
        padding: 5px 14px;
        border-radius: 20px;
        border: 1.5px solid var(--border);
        background: #fff;
        font-size: .78rem;
        font-weight: 500;
        color: var(--slate);
        cursor: pointer;
        transition: background .15s, color .15s, border-color .15s;
        white-space: nowrap;
    }

    .filter-pill:hover {
        border-color: var(--navy);
        color: var(--navy);
    }

    .filter-pill--active {
        background: var(--navy);
        color: #fff;
        border-color: var(--navy);
    }
</style>

<!-- Pending queue (shown only when pending filter active or all) -->
<div id="pending-card" style="display:none;margin-bottom:24px;border-left:3px solid var(--amber);"
     class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title">Pending Approval</span>
        <span id="pending-card-count"
              style="font-size:.72rem;background:#fef3c7;color:#92400e;
                     padding:2px 8px;border-radius:10px;font-weight:600;">0 pending</span>
    </div>
    <div id="pending-table-wrap" style="overflow-x:auto;">
        <table class="oc-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Contributor</th>
                <th>Amount</th>
                <th>Currency</th>
                <th>Method</th>
                <th>Requested</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody id="pending-tbody"></tbody>
        </table>
    </div>
</div>

<!-- All payouts results card -->
<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title" id="results-title">All Payouts</span>
        <span id="results-count"
              style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                     padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
    </div>

    <div id="payouts-loading" style="padding:48px 24px;text-align:center;color:var(--slate);">
        <div class="oc-spinner" style="margin:0 auto 12px;"></div>
        Loading payouts…
    </div>

    <div id="payouts-empty" style="display:none;padding:48px 24px;text-align:center;color:var(--slate);">
        <div style="font-weight:500;" id="empty-message">No payouts yet</div>
        <div style="font-size:.85rem;margin-top:4px;" id="empty-sub"></div>
    </div>

    <div id="payouts-error"
         style="display:none;padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">
        Failed to load payouts.
        <button onclick="loadPayouts()" class="oc-btn oc-btn--ghost oc-btn--sm"
                style="margin-left:8px;">Retry
        </button>
    </div>

    <div id="payouts-table-wrap" style="display:none;overflow-x:auto;">
        <table class="oc-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Contributor</th>
                <th>Amount</th>
                <th>Currency</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody id="payouts-tbody"></tbody>
        </table>
    </div>
</div>

<div class="oc-card" style="margin-top:24px;">
    <div class="oc-card__header">
        <span class="oc-card__title">Recent Stripe Webhook Events</span>
    </div>
    <div id="stripe-events-loading" style="padding:16px;color:var(--slate);">Loading events…</div>
    <div id="stripe-events-empty" style="display:none;padding:16px;color:var(--slate);">No Stripe webhook events yet.
    </div>
    <div id="stripe-events-wrap" style="display:none;overflow-x:auto;">
        <table class="oc-table">
            <thead>
            <tr>
                <th>Event ID</th>
                <th>Type</th>
                <th>Processed</th>
                <th>Failed</th>
                <th>Error</th>
            </tr>
            </thead>
            <tbody id="stripe-events-tbody"></tbody>
        </table>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    class AdminPayoutsManager {
        #site;
        #token;
        #state = {
            all: [],
            filter: 'all',
            query: '',
            pendingDeclineId: null,
            pendingPaidId: null,
        };
        #debounceTimer = null;

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
        }

        init() {
            document.getElementById('search-input').addEventListener('input', (e) => {
                clearTimeout(this.#debounceTimer);
                this.#debounceTimer = setTimeout(() => {
                    this.#state.query = e.target.value.trim().toLowerCase();
                    this.#render();
                }, 300);
            });
            this.#load();
            this.#loadWebhookEvents();
        }

        setFilter(status, btn) {
            this.#state.filter = status;
            document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
            btn.classList.add('filter-pill--active');
            this.#render();
        }

        async #load() {
            this.#showState('loading');
            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/payouts?per_page=200`, {
                    headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
                });
                if (!res.ok) {
                    this.#showState('error');
                    return;
                }
                const data = await res.json();
                this.#state.all = Array.isArray(data) ? data : (Array.isArray(data.data) ? data.data : []);
                this.#updateStats();
                this.#render();
            } catch {
                this.#showState('error');
            }
        }

        #updateStats() {
            const pending = this.#state.all.filter(p => p.status === 'pending');
            const pendingTotal = pending.reduce((s, p) => s + (p.amount_pence ?? p.amount ?? 0), 0);
            document.getElementById('stat-pending').textContent = pending.length;
            document.getElementById('stat-pending-amount').textContent = `£${(pendingTotal / 100).toFixed(2)}`;
            document.getElementById('stat-total').textContent = this.#state.all.length;
        }

        #render() {
            let filtered = this.#state.filter !== 'all'
                ? this.#state.all.filter(p => p.status === this.#state.filter)
                : [...this.#state.all];

            if (this.#state.query) {
                filtered = filtered.filter(p =>
                    String(p.user_id).includes(this.#state.query) ||
                    (p.reference ?? '').toLowerCase().includes(this.#state.query)
                );
            }

            document.getElementById('results-count').textContent = filtered.length;
            document.getElementById('results-title').textContent =
                this.#state.filter === 'all' ? 'All Payouts' : `${this.#cap(this.#state.filter)} Payouts`;

            const pendingItems = this.#state.all.filter(p => p.status === 'pending');
            const pendingCard = document.getElementById('pending-card');
            if (pendingItems.length > 0 && ['all', 'pending'].includes(this.#state.filter)) {
                pendingCard.style.display = 'block';
                document.getElementById('pending-card-count').textContent = `${pendingItems.length} pending`;
                this.#renderPendingTable(pendingItems);
            } else {
                pendingCard.style.display = 'none';
            }

            if (!filtered.length) {
                this.#showState('empty');
                document.getElementById('empty-message').textContent =
                    this.#state.query ? `No payouts matching "${this.#state.query}"` : 'No payouts yet';
                document.getElementById('empty-sub').textContent =
                    this.#state.filter !== 'all' ? `No ${this.#state.filter} payouts.` : '';
                return;
            }

            this.#renderMainTable(filtered);
            this.#showState('table');
        }

        #renderPendingTable(items) {
            document.getElementById('pending-tbody').innerHTML = items.map(p => {
                const currency = (p.currency ?? 'GBP').toUpperCase();
                const symbol = currency === 'GBP' ? '£' : '$';
                const amount = ((p.amount_pence ?? p.amount ?? 0) / 100).toFixed(2);
                return `<tr id="pending-row-${p.id}">
                <td style="font-family:monospace;font-size:.78rem;color:var(--slate);">PAY-${String(p.id).padStart(6, '0')}</td>
                <td><a href="/admin/contributors/${p.user_id}" style="font-weight:500;color:var(--navy);text-decoration:none;">User #${p.user_id}</a></td>
                <td style="font-weight:600;color:var(--navy);">${symbol}${amount}</td>
                <td style="font-size:.82rem;color:var(--slate);">${this.#esc(currency)}</td>
                <td style="font-size:.82rem;color:var(--slate);">${this.#esc((p.method ?? '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()))}</td>
                <td style="font-size:.78rem;color:var(--slate);">${this.#fmtDate(p.created_at)}</td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <button onclick="manager.approve(${p.id}, this)" class="oc-btn oc-btn--primary oc-btn--sm" id="approve-btn-${p.id}">Approve</button>
                        <button onclick="manager.openDeclineModal(${p.id})" class="oc-btn oc-btn--ghost oc-btn--sm" style="border-color:#fecaca;color:var(--red);">Decline</button>
                    </div>
                </td>
            </tr>`;
            }).join('');
        }

        #renderMainTable(items) {
            document.getElementById('payouts-tbody').innerHTML = items.map(p => {
                const status = p.status ?? 'pending';
                const statusCls = {
                    paid: 'oc-badge--published',
                    approved: 'oc-badge--free',
                    pending: 'oc-badge--waiting-approval',
                    rejected: 'oc-badge--revoked'
                }[status] ?? 'oc-badge--draft';
                const currency = (p.currency ?? 'GBP').toUpperCase();
                const symbol = currency === 'GBP' ? '£' : '$';
                const amount = ((p.amount_pence ?? p.amount ?? 0) / 100).toFixed(2);
                const pdfBtn = ['paid', 'approved'].includes(status)
                    ? `<a href="/api/${this.#esc(this.#site)}/open-collab/admin/payouts/${p.id}/statement" class="oc-btn oc-btn--ghost oc-btn--sm" download>PDF</a>` : '';
                const markPaidBtn = (status === 'approved' && p.method !== 'stripe')
                    ? `<button onclick="manager.openPaidModal(${p.id})" class="oc-btn oc-btn--primary oc-btn--sm">Mark paid</button>` : '';
                const retryBtn = (status === 'failed' && p.method === 'stripe')
                    ? `<button onclick="manager.retry(${p.id}, this)" class="oc-btn oc-btn--ghost oc-btn--sm">Retry</button>` : '';
                const rejectionRow = p.rejection_reason
                    ? `<tr><td colspan="7" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Decline reason:</strong> ${this.#esc(p.rejection_reason)}</td></tr>` : '';
                const failureReason = p.provider_response_json?.error || p.provider_response_json?.reason || '';
                const failureRow = failureReason
                    ? `<tr><td colspan="7" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Stripe failure:</strong> ${this.#esc(failureReason)}</td></tr>` : '';

                return `<tr id="all-row-${p.id}">
                <td style="font-family:monospace;font-size:.78rem;color:var(--slate);">PAY-${String(p.id).padStart(6, '0')}</td>
                <td><a href="/admin/contributors/${p.user_id}" style="font-weight:500;color:var(--navy);text-decoration:none;">User #${p.user_id}</a></td>
                <td style="font-weight:600;">${symbol}${amount}</td>
                <td style="font-size:.82rem;color:var(--slate);">${this.#esc(currency)}</td>
                <td><span class="oc-badge ${statusCls}" id="badge-${p.id}">${this.#cap(status)}</span></td>
                <td style="font-size:.78rem;color:var(--slate);">${this.#fmtDate(p.created_at)}</td>
                <td><div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">${retryBtn}${markPaidBtn}${pdfBtn}</div></td>
            </tr>${rejectionRow}${failureRow}`;
            }).join('');
        }

        async approve(id, btn) {
            if (!confirm('Approve this payout request?')) return;
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div>';
            const res = await fetch(`/api/${this.#site}/open-collab/admin/payouts/${id}/approve`, {
                method: 'POST',
                headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
            });
            const data = await res.json();
            if (res.ok) {
                this.#showToast('✓ Payout approved');
                const p = this.#state.all.find(x => x.id === id);
                if (p) {
                    p.status = 'approved';
                    p.approved_at = new Date().toISOString();
                }
                this.#updateStats();
                this.#render();
            } else {
                this.#showToast(data.error || data.message || 'Approval failed', false);
                btn.disabled = false;
                btn.textContent = 'Approve';
            }
        }

        openDeclineModal(id) {
            this.#state.pendingDeclineId = id;
            document.getElementById('decline-reason').value = '';
            document.getElementById('decline-errors').style.display = 'none';
            document.getElementById('decline-modal').style.display = 'grid';
            document.getElementById('decline-reason').focus();
        }

        closeDeclineModal() {
            this.#state.pendingDeclineId = null;
            document.getElementById('decline-modal').style.display = 'none';
        }

        async submitDecline() {
            const id = this.#state.pendingDeclineId;
            const reason = document.getElementById('decline-reason').value.trim();
            const errBox = document.getElementById('decline-errors');
            const btn = document.getElementById('decline-confirm-btn');
            errBox.style.display = 'none';

            if (!reason) {
                errBox.textContent = 'A reason is required.';
                errBox.style.display = 'block';
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div> Declining…';

            const res = await fetch(`/api/${this.#site}/open-collab/admin/payouts/${id}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${this.#token()}`,
                    Accept: 'application/json'
                },
                body: JSON.stringify({reason}),
            });
            const data = await res.json();
            if (res.ok) {
                this.closeDeclineModal();
                this.#showToast('Payout declined');
                const p = this.#state.all.find(x => x.id === parseInt(id));
                if (p) {
                    p.status = 'rejected';
                    p.rejection_reason = reason;
                }
                this.#updateStats();
                this.#render();
            } else {
                errBox.textContent = data.error || data.message || 'Decline failed.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Decline payout';
            }
        }

        openPaidModal(id) {
            this.#state.pendingPaidId = id;
            document.getElementById('paid-reference').value = '';
            document.getElementById('paid-notes').value = '';
            document.getElementById('paid-errors').style.display = 'none';
            document.getElementById('paid-modal').style.display = 'grid';
            document.getElementById('paid-reference').focus();
        }

        closePaidModal() {
            this.#state.pendingPaidId = null;
            document.getElementById('paid-modal').style.display = 'none';
        }

        async submitMarkPaid() {
            const id = this.#state.pendingPaidId;
            const reference = document.getElementById('paid-reference').value.trim();
            const notes = document.getElementById('paid-notes').value.trim();
            const errBox = document.getElementById('paid-errors');
            const btn = document.getElementById('paid-confirm-btn');
            errBox.style.display = 'none';
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div> Saving…';

            const res = await fetch(`/api/${this.#site}/open-collab/admin/payouts/${id}/paid`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${this.#token()}`,
                    Accept: 'application/json'
                },
                body: JSON.stringify({reference: reference || undefined, notes: notes || undefined}),
            });
            const data = await res.json();
            if (res.ok) {
                this.closePaidModal();
                this.#showToast('✓ Payout marked as paid');
                const p = this.#state.all.find(x => x.id === parseInt(id));
                if (p) {
                    p.status = 'paid';
                    p.reference = reference || p.reference;
                    p.processed_at = new Date().toISOString();
                }
                this.#updateStats();
                this.#render();
            } else {
                errBox.textContent = data.error || data.message || 'Failed.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Confirm paid';
            }
        }

        async retry(id, btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div>';
            const res = await fetch(`/api/${this.#site}/open-collab/admin/payouts/${id}/retry`, {
                method: 'POST',
                headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
            });
            const data = await res.json();
            if (res.ok) {
                this.#showToast('Retry queued');
                const p = this.#state.all.find(x => x.id === id);
                if (p) {
                    p.status = 'approved';
                    p.provider_status = 'retry_queued';
                }
                this.#render();
            } else {
                this.#showToast(data.error || data.message || 'Retry failed', false);
                btn.disabled = false;
                btn.textContent = 'Retry';
            }
        }

        async #loadWebhookEvents() {
            const loading = document.getElementById('stripe-events-loading');
            const empty = document.getElementById('stripe-events-empty');
            const wrap = document.getElementById('stripe-events-wrap');

            const res = await fetch(`/api/${this.#site}/open-collab/admin/stripe-webhooks`, {
                headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
            });

            if (!res.ok) {
                loading.textContent = 'Unable to load Stripe webhook history.';
                return;
            }

            const rows = await res.json();
            const events = Array.isArray(rows?.data) ? rows.data : (Array.isArray(rows) ? rows : []);

            if (events.length === 0) {
                loading.style.display = 'none';
                empty.style.display = 'block';
                return;
            }

            document.getElementById('stripe-events-tbody').innerHTML = events.map((event) => `
                <tr>
                    <td style="font-family:monospace;font-size:.78rem;">${this.#esc(event.stripe_event_id)}</td>
                    <td>${this.#esc(event.type)}</td>
                    <td>${this.#fmtDate(event.processed_at)}</td>
                    <td>${this.#fmtDate(event.failed_at)}</td>
                    <td style="max-width:280px;white-space:normal;">${this.#esc(event.error_message || '')}</td>
                </tr>
            `).join('');

            loading.style.display = 'none';
            wrap.style.display = 'block';
        }

        #showState(state) {
            document.getElementById('payouts-loading').style.display = state === 'loading' ? 'block' : 'none';
            document.getElementById('payouts-empty').style.display = state === 'empty' ? 'block' : 'none';
            document.getElementById('payouts-error').style.display = state === 'error' ? 'block' : 'none';
            document.getElementById('payouts-table-wrap').style.display = state === 'table' ? 'block' : 'none';
        }

        #fmtDate(str) {
            if (!str) return '—';
            return new Date(str).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
        }

        #cap(str) {
            return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
        }

        #esc(str) {
            if (str == null) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        #showToast(msg, ok = true) {
            const el = document.getElementById('status-toast');
            el.textContent = msg;
            el.style.background = ok ? 'var(--navy)' : 'var(--red)';
            el.style.opacity = '1';
            setTimeout(() => {
                el.style.opacity = '0';
            }, 2800);
        }
    }

    const manager = new AdminPayoutsManager(SITE, () => localStorage.getItem('oc_token') || '');
    document.addEventListener('DOMContentLoaded', () => manager.init());
    const submitDecline = () => manager.submitDecline();
    const setFilter = (status, btn) => manager.setFilter(status, btn);
    const submitMarkPaid = () => manager.submitMarkPaid();
</script>
@endsection