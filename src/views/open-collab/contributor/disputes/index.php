@section('logic')
<?php
/**
 * Template: open-collab/contributor/disputes/index.php
 * Variables:
 *   $disputableLedgerEntries — Collection of EarningsLedger models (server-side, for modal dropdown)
 *   $site                    — string
 *   $currentUser             — AuthenticatedUser
 *
 * Dispute history is loaded client-side via GET /api/{site}/open-collab/disputes.
 * The "raise a dispute" modal uses $disputableLedgerEntries rendered into a <select>
 * server-side. The JS layer filters out entries that already have an open dispute
 * once the disputes list has loaded from the API.
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

<!-- Raise dispute modal -->
<div id="dispute-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeDisputeModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:500px;width:92%;
                box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">
            Raise an earnings dispute
        </h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            Tell us what's wrong with this earnings entry. Our team will review it within 2–3 business days.
        </p>
        <input type="hidden" id="dispute-ledger-id">

        <div class="oc-form-group">
            <label class="oc-label" for="dispute-ledger-select">Earnings entry</label>
            <select class="oc-select" id="dispute-ledger-select" onchange="selectLedgerEntry(this)">
                <option value="">Select an earnings entry…</option>
                <?php foreach ($disputableLedgerEntries as $entry):
                    $amount = number_format(abs((int)$entry->amount) / 100, 2);
                    $currency = strtoupper($entry->currency ?? 'GBP');
                    $symbol = $currency === 'GBP' ? '£' : '$';
                    $type = ucfirst($entry->type ?? 'sale');
                    $date = $entry->earned_at?->format('d M Y') ?? '';
                    ?>
                    <option value="<?= (int)$entry->id ?>"
                            data-amount="<?= htmlspecialchars($symbol . $amount) ?>"
                            data-type="<?= htmlspecialchars($type) ?>"
                            data-date="<?= htmlspecialchars($date) ?>">
                        #<?= (int)$entry->id ?> · <?= htmlspecialchars($type) ?>
                        · <?= htmlspecialchars($symbol . $amount) ?> · <?= htmlspecialchars($date) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($disputableLedgerEntries->isEmpty()): ?>
                <div class="oc-help" style="color:var(--amber-dark,#b45309);">
                    No eligible earnings entries found. Entries become disputable once they appear in your earnings
                    ledger.
                </div>
            <?php endif; ?>
        </div>

        <!-- Selected entry summary card -->
        <div id="selected-entry-summary"
             style="display:none;background:var(--cream-dark);border:1px solid var(--border);
                    border-radius:6px;padding:12px 14px;margin-bottom:16px;font-size:.82rem;">
        </div>

        <div class="oc-form-group">
            <label class="oc-label" for="dispute-reason">
                Reason <span style="color:var(--red);">*</span>
            </label>
            <textarea class="oc-textarea" id="dispute-reason" rows="4" style="min-height:100px;"
                      placeholder="Describe the issue clearly — e.g. incorrect amount, missing payment, duplicate entry…"
                      required></textarea>
            <div class="oc-help">Minimum 10 characters. Be specific so we can investigate quickly.</div>
        </div>

        <div id="dispute-modal-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

        <div style="display:flex;gap:10px;">
            <button onclick="closeDisputeModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitDispute()" class="oc-btn oc-btn--primary" style="flex:1;"
                    id="dispute-submit-btn"
                    <?= $disputableLedgerEntries->isEmpty() ? 'disabled' : '' ?>>
                Submit dispute
            </button>
        </div>
    </div>
</div>

<!-- Stats bar (populated from API after disputes load) -->
<div class="oc-stats" style="margin-bottom:24px;">
    <div class="oc-stat oc-stat--accent">
        <div class="oc-stat__label">Open Disputes</div>
        <div class="oc-stat__value" id="stat-open">—</div>
        <div class="oc-stat__sub">Under review</div>
    </div>
    <div class="oc-stat oc-stat--green">
        <div class="oc-stat__label">Resolved</div>
        <div class="oc-stat__value" id="stat-resolved">—</div>
        <div class="oc-stat__sub">Closed in your favour</div>
    </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Rejected</div>
        <div class="oc-stat__value" id="stat-rejected">—</div>
        <div class="oc-stat__sub">No action taken</div>
    </div>
</div>

<!-- Filter bar + raise button -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <button class="filter-pill filter-pill--active" onclick="setFilter('all', this)">All</button>
    <button class="filter-pill" onclick="setFilter('open', this)">Open</button>
    <button class="filter-pill" onclick="setFilter('resolved', this)">Resolved</button>
    <button class="filter-pill" onclick="setFilter('rejected', this)">Rejected</button>
    <?php if (!$disputableLedgerEntries->isEmpty()): ?>
        <button onclick="openDisputeModal()" class="oc-btn oc-btn--primary oc-btn--sm" style="margin-left:auto;">
            <svg viewBox="0 0 20 20" fill="currentColor" width="14" style="margin-right:4px;">
                <path fill-rule="evenodd"
                      d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                      clip-rule="evenodd"/>
            </svg>
            Raise a dispute
        </button>
    <?php endif; ?>
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

<!-- Disputes list (API-driven) -->
<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title" id="list-title">My Disputes</span>
        <span id="list-count"
              style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                     padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
    </div>

    <div id="disputes-loading" style="padding:48px 24px;text-align:center;color:var(--slate);">
        <div class="oc-spinner" style="margin:0 auto 12px;"></div>
        Loading disputes…
    </div>

    <div id="disputes-empty" style="display:none;padding:48px 24px;text-align:center;color:var(--slate);">
        <svg viewBox="0 0 20 20" fill="currentColor" width="32"
             style="opacity:.2;display:block;margin:0 auto 12px;">
            <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"/>
        </svg>
        <div style="font-weight:500;margin-bottom:6px;">No disputes</div>
        <div style="font-size:.85rem;">
            If you believe there's an error in your earnings, you can raise a dispute above.
        </div>
    </div>

    <div id="disputes-error"
         style="display:none;padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">
        Failed to load disputes.
        <button onclick="loadDisputes()" class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-left:8px;">Retry</button>
    </div>

    <div id="disputes-list" style="display:none;flex-direction:column;"></div>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    // IDs of ledger entries that already have an open dispute (populated after load)
    let openDisputeLedgerIds = new Set();
    let allDisputes = [];
    let currentFilter = 'all';

    document.addEventListener('DOMContentLoaded', loadDisputes);

    // ── Filter ────────────────────────────────────────────────────────────────
    function setFilter(status, btn) {
        currentFilter = status;
        document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
        btn.classList.add('filter-pill--active');
        renderDisputes();
    }

    // ── Load disputes from API ────────────────────────────────────────────────
    async function loadDisputes() {
        showState('loading');
        try {
            const res = await fetch(`/api/${SITE}/open-collab/disputes`, {
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });
            if (!res.ok) {
                showState('error');
                return;
            }

            const data = await res.json();
            allDisputes = Array.isArray(data) ? data : (data.data ?? []);

            // Build the set of ledger IDs already under an open dispute
            // so we can grey-out / disable those options in the dropdown.
            openDisputeLedgerIds = new Set(
                allDisputes
                    .filter(d => d.status === 'open')
                    .map(d => d.earnings_ledger_id)
            );
            applyDropdownDisabledState();

            updateStats();
            renderDisputes();
        } catch {
            showState('error');
        }
    }

    /**
     * Disable dropdown options for ledger entries that already have an open dispute.
     * Options remain visible so the contributor can see the entry but can't resubmit.
     */
    function applyDropdownDisabledState() {
        const select = document.getElementById('dispute-ledger-select');
        if (!select) return;

        Array.from(select.options).forEach(opt => {
            if (!opt.value) return; // placeholder
            opt.disabled = openDisputeLedgerIds.has(parseInt(opt.value));
            if (opt.disabled) {
                opt.text = opt.text.replace(' · (open dispute)', '') + ' · (open dispute)';
            }
        });
    }

    function updateStats() {
        document.getElementById('stat-open').textContent = allDisputes.filter(d => d.status === 'open').length;
        document.getElementById('stat-resolved').textContent = allDisputes.filter(d => d.status === 'resolved').length;
        document.getElementById('stat-rejected').textContent = allDisputes.filter(d => d.status === 'rejected').length;
    }

    // ── Render dispute list ───────────────────────────────────────────────────
    function renderDisputes() {
        const filtered = currentFilter === 'all'
            ? allDisputes
            : allDisputes.filter(d => d.status === currentFilter);

        document.getElementById('list-count').textContent = filtered.length;
        document.getElementById('list-title').textContent =
            currentFilter === 'all' ? 'My Disputes' : capitalise(currentFilter) + ' Disputes';

        if (!filtered.length) {
            showState('empty');
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
            }[d.status] ?? {cls: 'oc-badge--draft', label: capitalise(d.status)};

            const createdAt = d.created_at
                ? new Date(d.created_at).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})
                : '';

            const adminNotesHtml = d.admin_notes
                ? `<div style="font-size:.82rem;color:var(--navy);line-height:1.5;
                           background:${d.status === 'resolved' ? '#f0fdf4' : '#fff9f9'};
                           border:1px solid ${d.status === 'resolved' ? '#bbf7d0' : '#fecaca'};
                           border-radius:6px;padding:10px 14px;margin-top:8px;">
                       <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;
                               color:${d.status === 'resolved' ? 'var(--green)' : 'var(--red)'};
                               display:block;margin-bottom:3px;">Admin response</strong>
                       ${escHtml(d.admin_notes)}
                   </div>`
                : (d.status === 'open'
                    ? `<div style="font-size:.78rem;color:var(--slate);font-style:italic;margin-top:4px;">
                           Our team is reviewing this — usually within 2–3 business days.
                       </div>`
                    : '');

            const div = document.createElement('div');
            div.style.cssText = `padding:18px 20px;${!isLast ? 'border-bottom:1px solid var(--border);' : ''}`;
            div.innerHTML = `
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                    <span class="oc-badge ${badge.cls}">${badge.label}</span>
                    <span style="font-size:.72rem;color:var(--slate);font-family:monospace;">
                        Ledger #${d.earnings_ledger_id}
                    </span>
                    <span style="font-size:.72rem;color:var(--slate-light);">${createdAt}</span>
                </div>
                <div style="font-size:.875rem;color:var(--navy);line-height:1.55;margin-bottom:4px;">
                    <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;
                                   color:var(--slate);display:block;margin-bottom:3px;">Your reason</strong>
                    ${escHtml(d.reason ?? '')}
                </div>
                ${adminNotesHtml}`;
            list.appendChild(div);
        });

        showState('list');
    }

    // ── Raise dispute modal ───────────────────────────────────────────────────
    function openDisputeModal() {
        document.getElementById('dispute-ledger-id').value = '';
        document.getElementById('dispute-ledger-select').value = '';
        document.getElementById('dispute-reason').value = '';
        document.getElementById('selected-entry-summary').style.display = 'none';
        document.getElementById('dispute-modal-errors').style.display = 'none';
        document.getElementById('dispute-modal').style.display = 'grid';
    }

    function closeDisputeModal() {
        document.getElementById('dispute-modal').style.display = 'none';
    }

    function selectLedgerEntry(sel) {
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
                <div>
                    <span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Amount</span><br>
                    <strong style="color:var(--navy);">${escHtml(opt.dataset.amount || '—')}</strong>
                </div>
                <div>
                    <span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Type</span><br>
                    <strong style="color:var(--navy);">${escHtml(opt.dataset.type || '—')}</strong>
                </div>
                <div>
                    <span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Date</span><br>
                    <strong style="color:var(--navy);">${escHtml(opt.dataset.date || '—')}</strong>
                </div>
            </div>`;
    }

    async function submitDispute() {
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
            const res = await fetch(`/api/${SITE}/open-collab/disputes`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN()}`,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({earnings_ledger_id: ledgerId, reason}),
            });

            const data = await res.json();

            if (res.ok) {
                closeDisputeModal();
                showToast('✓ Dispute submitted — we\'ll review it shortly');
                await loadDisputes(); // refresh list + re-disable dropdown options
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

    // ── Helpers ───────────────────────────────────────────────────────────────
    function showState(state) {
        document.getElementById('disputes-loading').style.display = state === 'loading' ? 'block' : 'none';
        document.getElementById('disputes-empty').style.display = state === 'empty' ? 'block' : 'none';
        document.getElementById('disputes-error').style.display = state === 'error' ? 'block' : 'none';
        document.getElementById('disputes-list').style.display = state === 'list' ? 'flex' : 'none';
    }

    function capitalise(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
    }

    function escHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function showToast(msg, ok = true) {
        const el = document.getElementById('status-toast');
        el.textContent = msg;
        el.style.background = ok ? 'var(--navy)' : 'var(--red)';
        el.style.opacity = '1';
        setTimeout(() => {
            el.style.opacity = '0';
        }, 2800);
    }
</script>
@endsection