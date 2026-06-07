@section('logic')
<?php
/**
 * Template: open-collab/payouts/index.php
 * Variables:
 *   $site        — string (site slug)
 *   $currentUser — AuthenticatedUser
 *
 * Balance and payout history are loaded client-side via
 * PayoutController::balance and PayoutController::index.
 */

$pageTitle = 'Payouts';
$activeNav = 'earnings';
$breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/contributor/dashboard'],
        ['label' => 'Payouts'],
];
$pageClass = 'oc-page--wide';
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<!-- Stats bar (populated from API) -->
<div class="oc-stats" style="animation:fadeSlideIn .4s ease;margin-bottom:24px;">
    <div class="oc-stat oc-stat--accent">
        <div class="oc-stat__label">Available to Withdraw</div>
        <div class="oc-stat__value" id="stat-balance">—</div>
        <div class="oc-stat__sub">Settled minus deductions</div>
    </div>

    <div class="oc-stat">
        <div class="oc-stat__label">Estimated</div>
        <div class="oc-stat__value" id="stat-estimated">—</div>
        <div class="oc-stat__sub">Visible, not payable yet</div>
    </div>

    <div class="oc-stat">
        <div class="oc-stat__label">Confirmed</div>
        <div class="oc-stat__value" id="stat-confirmed">—</div>
        <div class="oc-stat__sub">Approved, not settled</div>
    </div>

    <div class="oc-stat oc-stat--green">
        <div class="oc-stat__label">Withdrawn</div>
        <div class="oc-stat__value" id="stat-withdrawn">—</div>
        <div class="oc-stat__sub">Paid out</div>
    </div>

    <div class="oc-stat">
        <div class="oc-stat__label">Deductions</div>
        <div class="oc-stat__value" id="stat-liabilities">—</div>
        <div class="oc-stat__sub">Open liabilities</div>
    </div>

    <div class="oc-stat">
        <div class="oc-stat__label">Pending Payouts</div>
        <div class="oc-stat__value" id="stat-in-flight">—</div>
        <div class="oc-stat__sub">Pending or approved</div>
    </div>
</div>

<div class="oc-grid-sidebar" style="align-items:start;">

    <!-- Payout history table -->
    <div class="oc-card" style="animation:fadeSlideIn .45s ease;">
        <div class="oc-card__header">
            <span class="oc-card__title" id="list-title">Payout History</span>
            <span id="list-count"
                  style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                         padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
        </div>

        <!-- Filter pills -->
        <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;">
            <button class="filter-pill filter-pill--active" onclick="setFilter('all', this)">All</button>
            <button class="filter-pill" onclick="setFilter('pending', this)">Pending</button>
            <button class="filter-pill" onclick="setFilter('approved', this)">Approved</button>
            <button class="filter-pill" onclick="setFilter('paid', this)">Paid</button>
            <button class="filter-pill" onclick="setFilter('rejected', this)">Rejected</button>
        </div>

        <style>
            .filter-pill {
                padding: 4px 12px;
                border-radius: 20px;
                border: 1.5px solid var(--border);
                background: #fff;
                font-size: .75rem;
                font-weight: 500;
                color: var(--slate);
                cursor: pointer;
                transition: background .15s, color .15s, border-color .15s;
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

        <div id="payouts-loading" style="padding:48px 24px;text-align:center;color:var(--slate);">
            <div class="oc-spinner" style="margin:0 auto 12px;"></div>
            Loading payouts…
        </div>

        <div id="payouts-empty" style="display:none;padding:48px 24px;text-align:center;color:var(--slate);">
            <svg viewBox="0 0 20 20" fill="currentColor" width="32"
                 style="opacity:.2;display:block;margin:0 auto 12px;">
                <path fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                      clip-rule="evenodd"/>
            </svg>
            <div style="font-weight:500;margin-bottom:6px;" id="empty-message">No payout requests yet</div>
            <div style="font-size:.85rem;" id="empty-sub">
                Once your balance reaches £50.00, you can request a payout.
            </div>
        </div>

        <div id="payouts-error"
             style="display:none;padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">
            Failed to load payouts.
            <button onclick="loadAll()" class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-left:8px;">Retry</button>
        </div>

        <div id="payouts-table-wrap" style="display:none;overflow-x:auto;">
            <table class="oc-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="payouts-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- Request payout sidebar -->
    <div style="position:sticky;top:calc(var(--header-h) + 20px);">
        <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.95rem;">Request Payout</span>
            </div>
            <div class="oc-card__body">
                <div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:var(--radius);
                            padding:16px;margin-bottom:16px;text-align:center;" id="balance-card">
                    <div style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
                                color:var(--slate);margin-bottom:4px;">Available now
                    </div>
                    <div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy);"
                         id="balance-display">—
                    </div>
                </div>

                <div id="payout-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

                <div id="payout-form-wrap">
                    <div class="oc-form-group">
                        <label class="oc-label" for="payout-method">Payout method</label>
                        <select class="oc-select" id="payout-method">
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="paypal">PayPal</option>
                            <option value="other">Other</option>
                        </select>
                        <div class="oc-help">Your payout details are configured in
                            <a href="/contributor/settings#payment">Settings</a>.
                        </div>
                    </div>
                    <button onclick="requestPayout()" class="oc-btn oc-btn--amber oc-btn--block" id="payout-btn"
                            disabled>
                        Request payout
                    </button>
                </div>

                <div id="below-minimum-note" style="display:none;padding:16px;text-align:center;
                            border:1.5px dashed var(--border);border-radius:var(--radius);">
                    <div style="font-size:.85rem;font-weight:500;margin-bottom:4px;color:var(--navy);">
                        Minimum not reached
                    </div>
                    <div style="font-size:.78rem;color:var(--slate);">
                        You need at least <strong>£50.00</strong> to request a payout.
                    </div>
                </div>

                <div style="font-size:.72rem;color:var(--slate);line-height:1.6;padding-top:12px;
                            border-top:1px solid var(--border);margin-top:16px;">
                    Payouts are processed manually by our team, typically within 2–5 business days after approval.
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    class ContributorPayoutsManager {
        #site;
        #token;
        #state = {
            all: [],
            filter: 'all',
            balancePence: 0,
        };

        static #MIN_PENCE = 5000;

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
        }

        init() {
            Promise.all([this.#loadBalance(), this.#loadPayouts()]);
        }

        setFilter(status, btn) {
            this.#state.filter = status;
            document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
            btn.classList.add('filter-pill--active');
            this.#render();
        }

        async #loadBalance() {
            try {
                const res = await fetch(`/api/${this.#site}/open-collab/payouts/balance`, {
                    headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
                });
                const data = await res.json();
                const payload = data?.data ?? data ?? {};

                const balancePence =
                    payload.available_to_withdraw ??
                    payload.available_balance ??
                    payload.balance_pence ??
                    0;

                this.#state.balancePence = balancePence;

                const fmt = (pence) => `£${((Number(pence || 0)) / 100).toFixed(2)}`;

                document.getElementById('stat-balance').textContent = fmt(balancePence);
                document.getElementById('balance-display').textContent = fmt(balancePence);

                document.getElementById('stat-estimated').textContent = fmt(payload.estimated_balance);
                document.getElementById('stat-confirmed').textContent = fmt(payload.confirmed_balance);
                document.getElementById('stat-withdrawn').textContent = fmt(payload.withdrawn_balance);
                document.getElementById('stat-liabilities').textContent = fmt(payload.open_liabilities);
                document.getElementById('stat-in-flight').textContent = fmt(payload.in_flight_payouts);

                const btn = document.getElementById('payout-btn');
                btn.textContent = `Request ${fmt(balancePence)}`;

                if (this.#state.balancePence >= ContributorPayoutsManager.#MIN_PENCE) {
                    btn.disabled = false;
                    document.getElementById('below-minimum-note').style.display = 'none';
                    document.getElementById('payout-form-wrap').style.display = 'block';
                } else {
                    btn.disabled = true;
                    document.getElementById('below-minimum-note').style.display = 'block';
                    document.getElementById('payout-form-wrap').style.display = 'none';
                }
            } catch {
                document.getElementById('balance-display').textContent = '—';
            }
        }

        async #loadPayouts() {
            this.#showState('loading');
            try {
                const res = await fetch(`/api/${this.#site}/open-collab/payouts`, {
                    headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
                });
                if (!res.ok) {
                    this.#showState('error');
                    return;
                }
                const data = await res.json();
                this.#state.all = Array.isArray(data) ? data : (data.data ?? []);
                document.getElementById('stat-total').textContent = this.#state.all.length;
                this.#render();
            } catch {
                this.#showState('error');
            }
        }

        #render() {
            const filtered = this.#state.filter === 'all'
                ? this.#state.all
                : this.#state.all.filter(p => p.status === this.#state.filter);

            document.getElementById('list-count').textContent = filtered.length;
            document.getElementById('list-title').textContent =
                this.#state.filter === 'all' ? 'Payout History' : `${this.#cap(this.#state.filter)} Payouts`;

            if (!filtered.length) {
                this.#showState('empty');
                document.getElementById('empty-message').textContent =
                    this.#state.filter !== 'all' ? `No ${this.#state.filter} payouts` : 'No payout requests yet';
                document.getElementById('empty-sub').textContent =
                    this.#state.filter !== 'all' ? '' : 'Once your balance reaches £50.00, you can request a payout.';
                return;
            }

            document.getElementById('payouts-tbody').innerHTML = filtered.map(p => {
                const statusClass = {
                    paid: 'oc-badge--published',
                    approved: 'oc-badge--free',
                    pending: 'oc-badge--waiting-approval',
                    rejected: 'oc-badge--revoked'
                }[p.status] ?? 'oc-badge--draft';
                const currency = (p.currency ?? 'GBP').toUpperCase();
                const symbol = currency === 'GBP' ? '£' : '$';
                const amount = ((p.amount_pence ?? p.amount ?? 0) / 100).toFixed(2);
                const createdAt = p.created_at
                    ? new Date(p.created_at).toLocaleDateString('en-GB', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric'
                    })
                    : '—';
                const downloadBtn = ['paid', 'approved'].includes(p.status)
                    ? `<a href="/api/${this.#esc(this.#site)}/open-collab/payouts/${p.id}/statement" class="oc-btn oc-btn--ghost oc-btn--sm" download title="Download PDF">PDF</a>`
                    : '<span style="font-size:.75rem;color:var(--slate);">—</span>';
                const rejectionRow = p.rejection_reason
                    ? `<tr><td colspan="6" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Rejection reason:</strong> ${this.#esc(p.rejection_reason)}</td></tr>` : '';

                return `<tr>
                <td style="white-space:nowrap;color:var(--slate);">${createdAt}</td>
                <td style="font-weight:600;font-family:var(--font-display);font-size:1rem;color:var(--navy);">${symbol}${amount}</td>
                <td style="color:var(--slate);font-size:.85rem;">${this.#esc((p.method ?? '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()))}</td>
                <td><span class="oc-badge ${statusClass}">${this.#cap(p.status)}</span></td>
                <td style="color:var(--slate);font-size:.82rem;font-family:monospace;">${this.#esc(p.reference ?? '—')}</td>
                <td style="text-align:right;">${downloadBtn}</td>
            </tr>${rejectionRow}`;
            }).join('');

            this.#showState('table');
        }

        async requestPayout() {
            const btn = document.getElementById('payout-btn');
            const errBox = document.getElementById('payout-errors');
            const method = document.getElementById('payout-method').value;
            errBox.style.display = 'none';
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div> Submitting…';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/payouts`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        Authorization: `Bearer ${this.#token()}`
                    },
                    body: JSON.stringify({method}),
                });
                const data = await res.json();
                if (res.ok) {
                    this.#showToast('✓ Payout request submitted. Our team will process it shortly.');
                    this.init();
                } else {
                    errBox.textContent = data.message || data.error || 'Request failed. Please try again.';
                    errBox.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Request payout';
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Request payout';
            }
        }

        #showState(state) {
            document.getElementById('payouts-loading').style.display = state === 'loading' ? 'block' : 'none';
            document.getElementById('payouts-empty').style.display = state === 'empty' ? 'block' : 'none';
            document.getElementById('payouts-error').style.display = state === 'error' ? 'block' : 'none';
            document.getElementById('payouts-table-wrap').style.display = state === 'table' ? 'block' : 'none';
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

    const manager = new ContributorPayoutsManager(SITE, () => localStorage.getItem('oc_token') || '');
    document.addEventListener('DOMContentLoaded', () => manager.init());
    const setFilter = (status, btn) => manager.setFilter(status, btn);
    const requestPayout = () => manager.requestPayout();
</script>
@endsection