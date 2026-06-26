@section('logic')
<?php
/**
 * Template: open-collab/payouts/index.php
 *
 * The page is now an orchestrator for configurable surface sections.
 * Default surface: payouts.index
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

<div data-open-collab-surface="<?= htmlspecialchars($surface ?? 'payouts.index') ?>">
    <?php foreach (($sections ?? []) as $section): ?>
        <section data-section-key="<?= htmlspecialchars($section->key()) ?>">
            <?php switch ($section->key()):
                case 'payouts.stats': ?>
                    @include('open-collab.sections.payouts.stats')
                    <?php break;
                case 'payouts.history_table': ?>
                    @include('open-collab.sections.payouts.history-table')
                    <?php break;
            endswitch; ?>
        </section>
    <?php endforeach; ?>
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
                    headers: {
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json'
                    },
                });

                if (!res.ok) {
                    this.#showState('error');
                    return;
                }

                const data = await res.json();

                this.#state.all = Array.isArray(data)
                    ? data
                    : (data.data ?? []);

                this.#render();
            } catch (e) {
                console.log('e', e);
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
