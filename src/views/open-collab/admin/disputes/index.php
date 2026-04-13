@section('logic')
<?php
/**
 * Template: open-collab/admin/disputes/index.php
 * Variables:
 *   $site        — string
 *   $currentUser — AuthenticatedUser
 *
 * Data is loaded via JS/API so no server-side $disputes variable is needed.
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

<!-- Resolve modal -->
<div id="resolve-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeResolveModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:500px;width:92%;
                box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">
            Resolve dispute
        </h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            Add notes and optionally apply a ledger adjustment (positive = credit, negative = debit).
        </p>
        <input type="hidden" id="resolve-dispute-id">
        <div class="oc-form-group">
            <label class="oc-label" for="resolve-notes">Admin notes <span style="color:var(--red);">*</span></label>
            <textarea class="oc-textarea" id="resolve-notes" rows="3" style="min-height:80px;"
                      placeholder="Explain your resolution decision…" required></textarea>
        </div>
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="resolve-adjustment">
                Adjustment amount (£)
                <span style="font-size:.72rem;color:var(--slate);font-weight:400;margin-left:4px;">
                    — positive = credit, negative = debit
                </span>
            </label>
            <input class="oc-input" type="number" id="resolve-adjustment" step="0.01"
                   placeholder="e.g. 5.00 or -2.50 — leave blank for no adjustment">
        </div>
        <div class="oc-form-group" id="resolve-reason-group" style="display:none;">
            <label class="oc-label" for="resolve-adjustment-reason">Adjustment reason</label>
            <input class="oc-input" type="text" id="resolve-adjustment-reason"
                   placeholder="Reason for the ledger adjustment…">
        </div>
        <div id="resolve-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button onclick="closeResolveModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitResolve()" class="oc-btn oc-btn--primary" style="flex:1;" id="resolve-confirm-btn">
                Resolve dispute
            </button>
        </div>
    </div>
</div>

<!-- Reject modal -->
<div id="reject-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeRejectModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:440px;width:92%;
                box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">
            Reject dispute
        </h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            Explain why this dispute is being rejected.
        </p>
        <input type="hidden" id="reject-dispute-id">
        <div class="oc-form-group">
            <label class="oc-label" for="reject-notes">Admin notes <span style="color:var(--red);">*</span></label>
            <textarea class="oc-textarea" id="reject-notes" rows="3" style="min-height:80px;"
                      placeholder="Explain why this dispute has been rejected…" required></textarea>
        </div>
        <div id="reject-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button onclick="closeRejectModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitReject()" class="oc-btn oc-btn--danger" style="flex:1;" id="reject-confirm-btn">
                Reject dispute
            </button>
        </div>
    </div>
</div>

<!-- Filter bar -->
<div class="oc-card" style="margin-bottom:20px;padding:16px 20px;">
    <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <div style="position:relative;flex:1;min-width:200px;">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16"
                 style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--slate-light);pointer-events:none;">
                <path fill-rule="evenodd"
                      d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                      clip-rule="evenodd"/>
            </svg>
            <input class="oc-input" type="text" id="search-input"
                   placeholder="Search by user ID or ledger ID…"
                   style="padding-left:38px;"
                   autocomplete="off">
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap;">
            <button class="filter-pill filter-pill--active" data-status="all" onclick="setStatusFilter('all', this)">
                All
            </button>
            <button class="filter-pill" data-status="open" onclick="setStatusFilter('open', this)">Open</button>
            <button class="filter-pill" data-status="resolved" onclick="setStatusFilter('resolved', this)">Resolved
            </button>
            <button class="filter-pill" data-status="rejected" onclick="setStatusFilter('rejected', this)">Rejected
            </button>
        </div>
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

<!-- Results card -->
<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title" id="results-title">All Disputes</span>
        <span id="results-count"
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
        <div style="font-weight:500;margin-bottom:6px;" id="empty-message">No disputes found</div>
        <div style="font-size:.85rem;" id="empty-sub">All earnings disputes will appear here.</div>
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

    let allDisputes = [];
    let currentStatus = 'all';
    let currentQuery = '';
    let debounceTimer = null;

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('search-input').addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentQuery = this.value.trim().toLowerCase();
                renderDisputes();
            }, 300);
        });

        loadDisputes();
    });

    function setStatusFilter(status, btn) {
        currentStatus = status;
        document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
        btn.classList.add('filter-pill--active');
        renderDisputes();
    }

    async function loadDisputes() {
        showState('loading');
        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/disputes`, {
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });
            if (!res.ok) {
                showState('error');
                return;
            }
            const data = await res.json();
            allDisputes = Array.isArray(data) ? data : (data.data ?? []);
            renderDisputes();
        } catch {
            showState('error');
        }
    }

    function renderDisputes() {
        let filtered = allDisputes;

        if (currentStatus !== 'all') {
            filtered = filtered.filter(d => d.status === currentStatus);
        }

        if (currentQuery) {
            filtered = filtered.filter(d =>
                String(d.user_id).includes(currentQuery) ||
                String(d.earnings_ledger_id).includes(currentQuery) ||
                (d.reason ?? '').toLowerCase().includes(currentQuery)
            );
        }

        document.getElementById('results-count').textContent = filtered.length;
        document.getElementById('results-title').textContent =
            currentStatus === 'all' ? 'All Disputes' : `${capitalise(currentStatus)} Disputes`;

        if (!filtered.length) {
            showState('empty');
            document.getElementById('empty-message').textContent =
                currentQuery ? `No disputes matching "${currentQuery}"` : 'No disputes found';
            document.getElementById('empty-sub').textContent =
                currentStatus !== 'all' ? `No ${currentStatus} disputes.` : 'Earnings disputes will appear here.';
            return;
        }

        const list = document.getElementById('disputes-list');
        list.innerHTML = '';

        filtered.forEach((d, i) => {
            const isLast = i === filtered.length - 1;
            const statusBadge = {
                open: 'oc-badge--waiting-approval',
                resolved: 'oc-badge--published',
                rejected: 'oc-badge--revoked',
            }[d.status] ?? 'oc-badge--draft';

            const createdAt = d.created_at
                ? new Date(d.created_at).toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                })
                : '';

            const actionsHtml = d.status === 'open'
                ? `<button onclick="openResolveModal(${d.id})" class="oc-btn oc-btn--primary oc-btn--sm">
                       <svg viewBox="0 0 20 20" fill="currentColor" width="13">
                           <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                       </svg>
                       Resolve
                   </button>
                   <button onclick="openRejectModal(${d.id})" class="oc-btn oc-btn--ghost oc-btn--sm" style="border-color:#fecaca;color:var(--red);">
                       Reject
                   </button>`
                : `<span class="oc-badge ${statusBadge}" style="font-size:.65rem;">${capitalise(d.status)}</span>`;

            const adminNotesHtml = d.admin_notes
                ? `<div style="margin-top:8px;padding:8px 12px;font-size:.78rem;
                               background:${d.status === 'resolved' ? '#f0fdf4' : '#fff9f9'};
                               border:1px solid ${d.status === 'resolved' ? '#bbf7d0' : '#fecaca'};
                               border-radius:6px;color:var(--navy);">
                       <strong style="color:${d.status === 'resolved' ? 'var(--green)' : 'var(--red)'};">Admin response:</strong>
                       ${escHtml(d.admin_notes)}
                   </div>`
                : '';

            const div = document.createElement('div');
            div.id = `dispute-row-${d.id}`;
            div.style.cssText = `padding:18px 20px;${!isLast ? 'border-bottom:1px solid var(--border);' : ''}`;
            div.innerHTML = `
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
                            <a href="/${escHtml(SITE)}/open-collab/admin/contributors/${d.user_id}"
                               style="font-weight:600;color:var(--navy);text-decoration:none;">
                                User #${d.user_id}
                            </a>
                            <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                                         border-radius:10px;padding:2px 8px;font-family:monospace;">
                                Ledger #${d.earnings_ledger_id}
                            </span>
                            <span class="oc-badge ${statusBadge}" style="font-size:.65rem;">${capitalise(d.status)}</span>
                        </div>
                        <div style="font-size:.875rem;color:var(--navy);line-height:1.55;margin-bottom:4px;
                                    background:var(--cream-dark);border:1px solid var(--border);border-radius:6px;
                                    padding:10px 14px;">
                            <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;
                                           color:var(--slate);display:block;margin-bottom:4px;">
                                Contributor's reason
                            </strong>
                            ${escHtml(d.reason ?? '')}
                        </div>
                        ${adminNotesHtml}
                        <div style="font-size:.75rem;color:var(--slate-light);margin-top:4px;">${createdAt}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;flex-shrink:0;">
                        ${actionsHtml}
                    </div>
                </div>`;
            list.appendChild(div);
        });

        showState('list');
    }

    // ── Resolve modal ─────────────────────────────────────────
    document.getElementById('resolve-adjustment')?.addEventListener('input', function () {
        document.getElementById('resolve-reason-group').style.display = this.value ? 'block' : 'none';
    });

    function openResolveModal(id) {
        document.getElementById('resolve-dispute-id').value = id;
        document.getElementById('resolve-notes').value = '';
        document.getElementById('resolve-adjustment').value = '';
        document.getElementById('resolve-adjustment-reason').value = '';
        document.getElementById('resolve-reason-group').style.display = 'none';
        document.getElementById('resolve-errors').style.display = 'none';
        document.getElementById('resolve-modal').style.display = 'grid';
        document.getElementById('resolve-notes').focus();
    }

    function closeResolveModal() {
        document.getElementById('resolve-modal').style.display = 'none';
    }

    async function submitResolve() {
        const id = document.getElementById('resolve-dispute-id').value;
        const notes = document.getElementById('resolve-notes').value.trim();
        const adjRaw = document.getElementById('resolve-adjustment').value.trim();
        const adjReason = document.getElementById('resolve-adjustment-reason').value.trim();
        const errBox = document.getElementById('resolve-errors');
        const btn = document.getElementById('resolve-confirm-btn');
        errBox.style.display = 'none';

        if (!notes) {
            errBox.textContent = 'Admin notes are required.';
            errBox.style.display = 'block';
            return;
        }

        let adjustmentAmount = null;
        if (adjRaw !== '') {
            const parsed = parseFloat(adjRaw);
            if (isNaN(parsed)) {
                errBox.textContent = 'Adjustment amount must be a valid number.';
                errBox.style.display = 'block';
                return;
            }
            adjustmentAmount = Math.round(parsed * 100);
        }

        if (adjustmentAmount !== null && !adjReason) {
            errBox.textContent = 'An adjustment reason is required when applying an adjustment.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Resolving…';

        const payload = {admin_notes: notes};
        if (adjustmentAmount !== null) {
            payload.adjustment_amount = adjustmentAmount;
            payload.adjustment_reason = adjReason;
        }

        const res = await fetch(`/api/${SITE}/open-collab/admin/disputes/${id}/resolve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (res.ok) {
            closeResolveModal();
            showToast('✓ Dispute resolved');
            // Update in local cache and re-render
            const dispute = allDisputes.find(d => d.id === parseInt(id));
            if (dispute) {
                dispute.status = 'resolved';
                dispute.admin_notes = notes;
            }
            renderDisputes();
        } else {
            errBox.textContent = data.error || data.message || 'Resolve failed.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Resolve dispute';
        }
    }

    // ── Reject modal ──────────────────────────────────────────
    function openRejectModal(id) {
        document.getElementById('reject-dispute-id').value = id;
        document.getElementById('reject-notes').value = '';
        document.getElementById('reject-errors').style.display = 'none';
        document.getElementById('reject-modal').style.display = 'grid';
        document.getElementById('reject-notes').focus();
    }

    function closeRejectModal() {
        document.getElementById('reject-modal').style.display = 'none';
    }

    async function submitReject() {
        const id = document.getElementById('reject-dispute-id').value;
        const notes = document.getElementById('reject-notes').value.trim();
        const errBox = document.getElementById('reject-errors');
        const btn = document.getElementById('reject-confirm-btn');
        errBox.style.display = 'none';

        if (!notes) {
            errBox.textContent = 'Admin notes are required.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Rejecting…';

        const res = await fetch(`/api/${SITE}/open-collab/admin/disputes/${id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
                'Accept': 'application/json',
            },
            body: JSON.stringify({admin_notes: notes}),
        });
        const data = await res.json();

        if (res.ok) {
            closeRejectModal();
            showToast('Dispute rejected');
            const dispute = allDisputes.find(d => d.id === parseInt(id));
            if (dispute) {
                dispute.status = 'rejected';
                dispute.admin_notes = notes;
            }
            renderDisputes();
        } else {
            errBox.textContent = data.error || data.message || 'Reject failed.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Reject dispute';
        }
    }

    // ── Helpers ───────────────────────────────────────────────
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
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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