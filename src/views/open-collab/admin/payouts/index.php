@section('logic')
<?php
/**
 * Template: open-collab/admin/payouts/index.php
 * Variables:
 *   $pendingPayouts — Collection of Payout models (status = pending)
 *   $allPayouts     — array of Payout models (all statuses, newest first)
 *   $site           — string
 *   $currentUser    — AuthenticatedUser
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
            <input class="oc-input" type="text" id="paid-reference" placeholder="e.g. BACS ref, transaction ID…">
        </div>
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="paid-notes">Notes</label>
            <textarea class="oc-textarea" id="paid-notes" rows="2" style="min-height:60px;"
                      placeholder="Any internal notes…"></textarea>
        </div>
        <div id="paid-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button onclick="closePaidModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitMarkPaid()" class="oc-btn oc-btn--primary" style="flex:1;" id="paid-confirm-btn">
                Confirm paid
            </button>
        </div>
    </div>
</div>

<!-- Detail modal -->
<div id="detail-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeDetailModal()">
    <div style="background:#fff;border-radius:12px;max-width:560px;width:94%;max-height:80vh;
                display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <span style="font-weight:700;color:var(--navy);" id="detail-title">Payout Details</span>
            <button onclick="closeDetailModal()"
                    style="background:none;border:none;cursor:pointer;color:var(--slate);font-size:1.2rem;">✕
            </button>
        </div>
        <div id="detail-body" style="padding:24px;overflow-y:auto;flex:1;"></div>
        <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;"
             id="detail-actions"></div>
    </div>
</div>

<!-- Stats bar -->
<div class="oc-stats" style="margin-bottom:24px;">
    <?php
    $pendingCount = count($pendingPayouts);
    $pendingTotal = 0;
    foreach ($pendingPayouts as $p) {
        $pendingTotal += (int)($p->amount ?? 0);
    }
    ?>
    <div class="oc-stat oc-stat--accent">
        <div class="oc-stat__label">Pending Review</div>
        <div class="oc-stat__value"><?= $pendingCount ?></div>
        <div class="oc-stat__sub">Awaiting approval</div>
    </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Pending Amount</div>
        <div class="oc-stat__value">£<?= number_format($pendingTotal / 100, 2) ?></div>
        <div class="oc-stat__sub">Total in queue</div>
    </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Total Payouts</div>
        <div class="oc-stat__value"><?= count($allPayouts) ?></div>
        <div class="oc-stat__sub">All time</div>
    </div>
</div>

<!-- Quick actions: pending queue -->
<?php if ($pendingCount > 0): ?>
    <div class="oc-card" style="margin-bottom:24px;border-left:3px solid var(--amber);">
        <div class="oc-card__header">
            <span class="oc-card__title">Pending Approval</span>
            <span style="font-size:.72rem;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-weight:600;">
            <?= $pendingCount ?> pending
        </span>
        </div>
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
            <tbody>
            <?php foreach ($pendingPayouts as $payout): ?>
                <tr id="pending-row-<?= (int)$payout->id ?>">
                    <td style="font-family:monospace;font-size:.78rem;color:var(--slate);">
                        PAY-<?= str_pad($payout->id, 6, '0', STR_PAD_LEFT) ?>
                    </td>
                    <td>
                        <a href="/admin/contributors/<?= (int)$payout->user_id ?>"
                           style="font-weight:500;color:var(--navy);text-decoration:none;">
                            User #<?= (int)$payout->user_id ?>
                        </a>
                    </td>
                    <td style="font-weight:600;color:var(--navy);">
                        £<?= number_format((int)$payout->amount / 100, 2) ?>
                    </td>
                    <td style="font-size:.82rem;color:var(--slate);">
                        <?= htmlspecialchars(strtoupper($payout->currency ?? 'GBP')) ?>
                    </td>
                    <td style="font-size:.82rem;color:var(--slate);">
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $payout->method ?? ''))) ?>
                    </td>
                    <td style="font-size:.78rem;color:var(--slate);">
                        <?= $payout->created_at ? date('d M Y', strtotime($payout->created_at)) : '–' ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button onclick="approvePayout(<?= (int)$payout->id ?>, this)"
                                    class="oc-btn oc-btn--primary oc-btn--sm"
                                    id="approve-btn-<?= (int)$payout->id ?>">
                                Approve
                            </button>
                            <button onclick="openDeclineModal(<?= (int)$payout->id ?>)"
                                    class="oc-btn oc-btn--ghost oc-btn--sm"
                                    style="border-color:#fecaca;color:var(--red);">
                                Decline
                            </button>
                            <button onclick="viewDetail(<?= (int)$payout->id ?>)"
                                    class="oc-btn oc-btn--ghost oc-btn--sm">
                                Details
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- All payouts table -->
<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title">All Payouts</span>
        <div style="display:flex;gap:8px;align-items:center;">
            <a href="/<?= $site ?>/open-collab/admin/payouts/scheduled" class="oc-btn oc-btn--ghost oc-btn--sm">
                <svg viewBox="0 0 20 20" fill="currentColor" width="13" style="margin-right:4px;">
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                          clip-rule="evenodd"/>
                </svg>
                View schedule
            </a>
            <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">
                <?= count($allPayouts) ?>
            </span>
        </div>
    </div>

    <?php if (empty($allPayouts)): ?>
        <div style="padding:48px 24px;text-align:center;color:var(--slate);">
            <svg viewBox="0 0 20 20" fill="currentColor" width="32"
                 style="opacity:.2;display:block;margin:0 auto 12px;">
                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                <path fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                      clip-rule="evenodd"/>
            </svg>
            <div style="font-weight:500;">No payouts yet</div>
        </div>
    <?php else: ?>
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
            <tbody>
            <?php foreach ($allPayouts as $payout):
                $pArr = is_object($payout) ? $payout : (object)$payout;
                $status = $pArr->status ?? 'pending';
                $statusClass = match ($status) {
                    'paid' => 'oc-badge--published',
                    'approved' => 'oc-badge--free',
                    'pending' => 'oc-badge--waiting-approval',
                    'rejected' => 'oc-badge--revoked',
                    default => 'oc-badge--draft',
                };
                ?>
                <tr id="all-row-<?= (int)$pArr->id ?>">
                    <td style="font-family:monospace;font-size:.78rem;color:var(--slate);">
                        PAY-<?= str_pad($pArr->id, 6, '0', STR_PAD_LEFT) ?>
                    </td>
                    <td>
                        <a href="/admin/contributors/<?= (int)$pArr->user_id ?>"
                           style="font-weight:500;color:var(--navy);text-decoration:none;">
                            User #<?= (int)$pArr->user_id ?>
                        </a>
                    </td>
                    <td style="font-weight:600;">
                        <?= strtoupper($pArr->currency ?? 'GBP') === 'GBP' ? '£' : '$' ?><?= number_format((int)$pArr->amount / 100, 2) ?>
                    </td>
                    <td style="font-size:.82rem;color:var(--slate);">
                        <?= htmlspecialchars(strtoupper($pArr->currency ?? 'GBP')) ?>
                    </td>
                    <td>
                        <span class="oc-badge <?= $statusClass ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </td>
                    <td style="font-size:.78rem;color:var(--slate);">
                        <?= $pArr->created_at ? date('d M Y', strtotime($pArr->created_at)) : '–' ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button onclick="viewDetail(<?= (int)$pArr->id ?>)"
                                    class="oc-btn oc-btn--ghost oc-btn--sm">Details
                            </button>
                            <?php if ($status === 'approved'): ?>
                                <button onclick="openPaidModal(<?= (int)$pArr->id ?>)"
                                        class="oc-btn oc-btn--primary oc-btn--sm">
                                    Mark paid
                                </button>
                            <?php endif; ?>
                            <?php if (in_array($status, ['paid', 'approved'])): ?>
                                <a href="/api/<?= htmlspecialchars($site) ?>/open-collab/admin/payouts/<?= (int)$pArr->id ?>/statement"
                                   class="oc-btn oc-btn--ghost oc-btn--sm" download>
                                    <svg viewBox="0 0 20 20" fill="currentColor" width="12" style="margin-right:3px;">
                                        <path fill-rule="evenodd"
                                              d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                    PDF
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php if (!empty($pArr->rejection_reason)): ?>
                <tr>
                    <td colspan="7" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;">
                        <strong>Decline reason:</strong> <?= htmlspecialchars($pArr->rejection_reason) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    // ── Approve ───────────────────────────────────────────────
    async function approvePayout(id, btn) {
        if (!confirm('Approve this payout request?')) return;
        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div>';

        const res = await fetch(`/api/${SITE}/open-collab/admin/payouts/${id}/approve`, {
            method: 'POST',
            headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
        });
        const data = await res.json();

        if (res.ok) {
            showToast('✓ Payout approved');
            // Update badge in both rows
            updateRowStatus(id, 'Approved', 'oc-badge--free');
            document.getElementById('pending-row-' + id)?.remove();
        } else {
            showToast(data.error || data.message || 'Approval failed', false);
            btn.disabled = false;
            btn.textContent = 'Approve';
        }
    }

    // ── Decline modal ─────────────────────────────────────────
    function openDeclineModal(id) {
        document.getElementById('decline-payout-id').value = id;
        document.getElementById('decline-reason').value = '';
        document.getElementById('decline-errors').style.display = 'none';
        document.getElementById('decline-modal').style.display = 'grid';
        document.getElementById('decline-reason').focus();
    }

    function closeDeclineModal() {
        document.getElementById('decline-modal').style.display = 'none';
    }

    async function submitDecline() {
        const id = document.getElementById('decline-payout-id').value;
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

        const res = await fetch(`/api/${SITE}/open-collab/admin/payouts/${id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
                'Accept': 'application/json',
            },
            body: JSON.stringify({reason}),
        });
        const data = await res.json();

        if (res.ok) {
            closeDeclineModal();
            showToast('Payout declined');
            updateRowStatus(id, 'Rejected', 'oc-badge--revoked');
            document.getElementById('pending-row-' + id)?.remove();
        } else {
            errBox.textContent = data.error || data.message || 'Decline failed.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Decline payout';
        }
    }

    // ── Mark paid modal ───────────────────────────────────────
    function openPaidModal(id) {
        document.getElementById('paid-payout-id').value = id;
        document.getElementById('paid-reference').value = '';
        document.getElementById('paid-notes').value = '';
        document.getElementById('paid-errors').style.display = 'none';
        document.getElementById('paid-modal').style.display = 'grid';
        document.getElementById('paid-reference').focus();
    }

    function closePaidModal() {
        document.getElementById('paid-modal').style.display = 'none';
    }

    async function submitMarkPaid() {
        const id = document.getElementById('paid-payout-id').value;
        const reference = document.getElementById('paid-reference').value.trim();
        const notes = document.getElementById('paid-notes').value.trim();
        const errBox = document.getElementById('paid-errors');
        const btn = document.getElementById('paid-confirm-btn');
        errBox.style.display = 'none';

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Saving…';

        const res = await fetch(`/api/${SITE}/open-collab/admin/payouts/${id}/paid`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
                'Accept': 'application/json',
            },
            body: JSON.stringify({reference: reference || undefined, notes: notes || undefined}),
        });
        const data = await res.json();

        if (res.ok) {
            closePaidModal();
            showToast('✓ Payout marked as paid');
            updateRowStatus(id, 'Paid', 'oc-badge--published');
        } else {
            errBox.textContent = data.error || data.message || 'Failed.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Confirm paid';
        }
    }

    // ── Detail modal ──────────────────────────────────────────
    async function viewDetail(id) {
        document.getElementById('detail-title').textContent = 'Payout PAY-' + String(id).padStart(6, '0');
        document.getElementById('detail-body').innerHTML = '<div class="oc-spinner" style="margin:20px auto;"></div>';
        document.getElementById('detail-actions').innerHTML = '';
        document.getElementById('detail-modal').style.display = 'grid';

        // Fetch from the admin list endpoint (reuse existing data via a search)
        // Since there's no single-payout admin endpoint, we fetch all and filter
        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/payouts?per_page=200`, {
                headers: {'Authorization': `Bearer ${TOKEN()}`},
            });
            const data = await res.json();
            const items = data?.data ?? data ?? [];
            const payout = items.find(p => (p.id || p) === id || p.id === id);

            if (!payout) {
                document.getElementById('detail-body').innerHTML = '<p style="color:var(--slate);">Payout not found.</p>';
                return;
            }

            const fmtDate = str => str ? new Date(str).toLocaleDateString('en-GB', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            }) : '–';
            const currency = (payout.currency || 'GBP').toUpperCase();
            const symbol = currency === 'GBP' ? '£' : '$';
            const amount = ((payout.amount_pence || payout.amount || 0) / 100).toFixed(2);

            document.getElementById('detail-body').innerHTML = `
                <dl style="display:grid;grid-template-columns:140px 1fr;gap:10px 16px;font-size:.875rem;">
                    <dt style="color:var(--slate);font-weight:500;">Payout ID</dt>
                    <dd style="font-family:monospace;">PAY-${String(id).padStart(6, '0')}</dd>
                    <dt style="color:var(--slate);font-weight:500;">Contributor</dt>
                    <dd>User #${payout.user_id}</dd>
                    <dt style="color:var(--slate);font-weight:500;">Amount</dt>
                    <dd style="font-weight:700;font-size:1.05rem;">${symbol}${amount}</dd>
                    <dt style="color:var(--slate);font-weight:500;">Currency</dt>
                    <dd>${currency}</dd>
                    <dt style="color:var(--slate);font-weight:500;">Method</dt>
                    <dd>${(payout.method || '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</dd>
                    <dt style="color:var(--slate);font-weight:500;">Status</dt>
                    <dd><span class="oc-badge oc-badge--${getStatusClass(payout.status)}">${(payout.status || '').charAt(0).toUpperCase() + (payout.status || '').slice(1)}</span></dd>
                    <dt style="color:var(--slate);font-weight:500;">Requested</dt>
                    <dd>${fmtDate(payout.created_at)}</dd>
                    ${payout.approved_at ? `<dt style="color:var(--slate);font-weight:500;">Approved</dt><dd>${fmtDate(payout.approved_at)}</dd>` : ''}
                    ${payout.processed_at ? `<dt style="color:var(--slate);font-weight:500;">Processed</dt><dd>${fmtDate(payout.processed_at)}</dd>` : ''}
                    ${payout.reference ? `<dt style="color:var(--slate);font-weight:500;">Reference</dt><dd style="font-family:monospace;">${payout.reference}</dd>` : ''}
                    ${payout.notes ? `<dt style="color:var(--slate);font-weight:500;">Notes</dt><dd>${payout.notes}</dd>` : ''}
                    ${payout.rejection_reason ? `<dt style="color:var(--slate);font-weight:500;">Decline reason</dt><dd style="color:var(--red);">${payout.rejection_reason}</dd>` : ''}
                </dl>`;

            const actionsEl = document.getElementById('detail-actions');
            if ((payout.status === 'paid' || payout.status === 'approved') && payout.id) {
                actionsEl.innerHTML += `<a href="/api/${SITE}/open-collab/admin/payouts/${payout.id}/statement" class="oc-btn oc-btn--ghost oc-btn--sm" download>Download statement PDF</a>`;
            }
            actionsEl.innerHTML += `<button onclick="closeDetailModal()" class="oc-btn oc-btn--primary oc-btn--sm">Close</button>`;
        } catch {
            document.getElementById('detail-body').innerHTML = '<p style="color:var(--red);">Failed to load details.</p>';
        }
    }

    function closeDetailModal() {
        document.getElementById('detail-modal').style.display = 'none';
    }

    // ── Helpers ───────────────────────────────────────────────
    function getStatusClass(status) {
        return {
            paid: 'published',
            approved: 'free',
            pending: 'waiting-approval',
            rejected: 'revoked'
        }[status] || 'draft';
    }

    function updateRowStatus(id, label, cls) {
        const row = document.getElementById('all-row-' + id);
        if (!row) return;
        const badge = row.querySelector('.oc-badge');
        if (badge) {
            badge.className = 'oc-badge ' + cls;
            badge.textContent = label;
        }
    }

    function showToast(msg, ok = true) {
        const el = document.getElementById('status-toast');
        el.textContent = msg;
        el.style.background = ok ? 'var(--navy)' : 'var(--red)';
        el.style.opacity = '1';
        setTimeout(() => el.style.opacity = '0', 2800);
    }
</script>
@endsection