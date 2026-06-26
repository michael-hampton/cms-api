@section('logic')
<?php
/**
 * Template: open-collab/contributor/disputes/index.php
 *
 * The page is now an orchestrator for configurable surface sections.
 * Default surface: disputes.index
 */
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<div data-open-collab-surface="<?= htmlspecialchars($surface ?? 'disputes.index') ?>">
    <?php foreach (($sections ?? []) as $section): ?>
        <section data-section-key="<?= htmlspecialchars($section->key()) ?>">
            <?php switch ($section->key()):
                case 'disputes.stats': ?>
                    @include('open-collab.sections.disputes.stats')
                    <?php break;
                case 'disputes.table': ?>
                    @include('open-collab.sections.disputes.table')
                    <?php break;
            endswitch; ?>
        </section>
    <?php endforeach; ?>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    class ContributorDisputesManager {
        #site;
        #token;
        #state = {
            all: [],
            filter: 'all',
            openLedgerIds: new Set(),
        };

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
        }

        init() {
            this.#load();
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
                const res = await fetch(`/api/${this.#site}/open-collab/disputes`, {
                    headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
                });
                if (!res.ok) {
                    this.#showState('error');
                    return;
                }
                const data = await res.json();
                this.#state.all = Array.isArray(data) ? data : (data.data ?? []);
                this.#state.openLedgerIds = new Set(
                    this.#state.all.filter(d => d.status === 'open').map(d => d.earnings_ledger_id)
                );
                this.#applyDropdownDisabledState();
                this.#updateStats();
                this.#render();
            } catch {
                this.#showState('error');
            }
        }

        #applyDropdownDisabledState() {
            const select = document.getElementById('dispute-ledger-select');
            if (!select) return;
            Array.from(select.options).forEach(opt => {
                if (!opt.value) return;
                opt.disabled = this.#state.openLedgerIds.has(parseInt(opt.value));
                if (opt.disabled && !opt.text.includes('(open dispute)')) {
                    opt.text += ' · (open dispute)';
                }
            });
        }

        #updateStats() {
            document.getElementById('stat-open').textContent = this.#state.all.filter(d => d.status === 'open').length;
            document.getElementById('stat-resolved').textContent = this.#state.all.filter(d => d.status === 'resolved').length;
            document.getElementById('stat-rejected').textContent = this.#state.all.filter(d => d.status === 'rejected').length;
        }

        #render() {
            const filtered = this.#state.filter === 'all'
                ? this.#state.all
                : this.#state.all.filter(d => d.status === this.#state.filter);

            document.getElementById('list-count').textContent = filtered.length;
            document.getElementById('list-title').textContent =
                this.#state.filter === 'all' ? 'My Disputes' : `${this.#cap(this.#state.filter)} Disputes`;

            if (!filtered.length) {
                this.#showState('empty');
                return;
            }

            const list = document.getElementById('disputes-list');
            list.innerHTML = '';
            filtered.forEach((d, i) => {
                const isLast = i === filtered.length - 1;
                const badge = {
                    open: {cls: 'oc-badge--waiting-approval', label: 'Under review'},
                    resolved: {cls: 'oc-badge--published', label: 'Resolved'},
                    rejected: {cls: 'oc-badge--revoked', label: 'Rejected'},
                }[d.status] ?? {cls: 'oc-badge--draft', label: this.#cap(d.status)};
                const createdAt = d.created_at
                    ? new Date(d.created_at).toLocaleDateString('en-GB', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric'
                    })
                    : '';
                const adminNotesHtml = d.admin_notes
                    ? `<div style="font-size:.82rem;color:var(--navy);line-height:1.5;background:${d.status === 'resolved' ? '#f0fdf4' : '#fff9f9'};border:1px solid ${d.status === 'resolved' ? '#bbf7d0' : '#fecaca'};border-radius:6px;padding:10px 14px;margin-top:8px;">
                       <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:${d.status === 'resolved' ? 'var(--green)' : 'var(--red)'};display:block;margin-bottom:3px;">Admin response</strong>
                       ${this.#esc(d.admin_notes)}
                   </div>`
                    : (d.status === 'open' ? '<div style="font-size:.78rem;color:var(--slate);font-style:italic;margin-top:4px;">Our team is reviewing this — usually within 2–3 business days.</div>' : '');

                const div = document.createElement('div');
                div.style.cssText = `padding:18px 20px;${!isLast ? 'border-bottom:1px solid var(--border);' : ''}`;
                div.innerHTML = `
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                    <span class="oc-badge ${badge.cls}">${badge.label}</span>
                    <span style="font-size:.72rem;color:var(--slate);font-family:monospace;">Ledger #${d.earnings_ledger_id}</span>
                    <span style="font-size:.72rem;color:var(--slate-light);">${createdAt}</span>
                </div>
                <div style="font-size:.875rem;color:var(--navy);line-height:1.55;margin-bottom:4px;">
                    <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--slate);display:block;margin-bottom:3px;">Your reason</strong>
                    ${this.#esc(d.reason ?? '')}
                </div>
                ${adminNotesHtml}`;
                list.appendChild(div);
            });
            this.#showState('list');
        }

        openDisputeModal() {
            document.getElementById('dispute-ledger-id').value = '';
            document.getElementById('dispute-ledger-select').value = '';
            document.getElementById('dispute-reason').value = '';
            document.getElementById('selected-entry-summary').style.display = 'none';
            document.getElementById('dispute-modal-errors').style.display = 'none';
            document.getElementById('dispute-modal').style.display = 'grid';
        }

        closeDisputeModal() {
            document.getElementById('dispute-modal').style.display = 'none';
        }

        selectLedgerEntry(sel) {
            const opt = sel.options[sel.selectedIndex];
            const id = opt.value;
            document.getElementById('dispute-ledger-id').value = id;
            const summary = document.getElementById('selected-entry-summary');
            if (!id) {
                summary.style.display = 'none';
                return;
            }
            summary.style.display = 'block';
            summary.innerHTML = `
            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                <div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Amount</span><br><strong style="color:var(--navy);">${this.#esc(opt.dataset.amount || '—')}</strong></div>
                <div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Type</span><br><strong style="color:var(--navy);">${this.#esc(opt.dataset.type || '—')}</strong></div>
                <div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Date</span><br><strong style="color:var(--navy);">${this.#esc(opt.dataset.date || '—')}</strong></div>
            </div>`;
        }

        async submitDispute() {
            const ledgerId = parseInt(document.getElementById('dispute-ledger-id').value);
            const reason = document.getElementById('dispute-reason').value.trim();
            const errBox = document.getElementById('dispute-modal-errors');
            const btn = document.getElementById('dispute-submit-btn');
            errBox.style.display = 'none';

            if (!ledgerId || ledgerId <= 0) {
                errBox.textContent = 'Please select an earnings entry to dispute.';
                errBox.style.display = 'block';
                return;
            }
            if (reason.length < 10) {
                errBox.textContent = 'Please provide a reason of at least 10 characters.';
                errBox.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div> Submitting…';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/disputes`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json'
                    },
                    body: JSON.stringify({earnings_ledger_id: ledgerId, reason}),
                });
                const data = await res.json();
                if (res.ok) {
                    this.closeDisputeModal();
                    this.#showToast("✓ Dispute submitted — we'll review it shortly");
                    await this.#load();
                } else {
                    errBox.textContent = data.error || data.message || 'Submission failed.';
                    errBox.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Submit dispute';
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Submit dispute';
            }
        }

        #showState(state) {
            document.getElementById('disputes-loading').style.display = state === 'loading' ? 'block' : 'none';
            document.getElementById('disputes-empty').style.display = state === 'empty' ? 'block' : 'none';
            document.getElementById('disputes-error').style.display = state === 'error' ? 'block' : 'none';
            document.getElementById('disputes-list').style.display = state === 'list' ? 'flex' : 'none';
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

    const manager = new ContributorDisputesManager(SITE, () => localStorage.getItem('oc_token') || '');
    document.addEventListener('DOMContentLoaded', () => manager.init());
    const setFilter = (status, btn) => manager.setFilter(status, btn);
    const openDisputeModal = () => manager.openDisputeModal();
    const closeDisputeModal = () => manager.closeDisputeModal();
    const submitDispute = () => manager.submitDispute();
    const selectLedgerEntry = (sel) => manager.selectLedgerEntry(sel);
</script>
@endsection
