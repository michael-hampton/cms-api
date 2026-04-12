@section('logic')
<?php
/**
 * Template: open-collab/admin/disputes/index.php
 * Variables:
 *   $disputes    — Collection of EarningsDispute models (open)
 *   $site        — string
 *   $currentUser — AuthenticatedUser
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

<?php if ($disputes->isEmpty()): ?>
    <div class="oc-card" style="padding:64px 24px;text-align:center;">
        <svg viewBox="0 0 20 20" fill="currentColor" width="36"
             style="opacity:.15;display:block;margin:0 auto 16px;color:var(--green);">
            <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"/>
        </svg>
        <div style="font-size:1.05rem;font-weight:600;color:var(--navy);">No open disputes</div>
        <div style="font-size:.875rem;color:var(--slate);margin-top:4px;">
            All earnings disputes have been resolved.
        </div>
    </div>
<?php else: ?>

    <div class="oc-card" style="overflow:hidden;">
        <div class="oc-card__header">
            <span class="oc-card__title">Open Earnings Disputes</span>
            <span style="font-size:.72rem;background:#fef3c7;color:#92400e;
                         padding:2px 8px;border-radius:10px;font-weight:600;">
                <?= $disputes->count() ?> open
            </span>
        </div>

        <div style="display:flex;flex-direction:column;">
            <?php foreach ($disputes as $i => $dispute):
                $dArr = is_array($dispute) ? $dispute : (method_exists($dispute, 'toArray') ? $dispute->toArray() : (array)$dispute);
                $isLast = $i === $disputes->count() - 1;
                ?>
                <div id="dispute-row-<?= (int)$dArr['id'] ?>"
                     style="padding:18px 20px;<?= !$isLast ? 'border-bottom:1px solid var(--border);' : '' ?>">

                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
                                <a href="/admin/contributors/<?= (int)$dArr['user_id'] ?>"
                                   style="font-weight:600;color:var(--navy);text-decoration:none;">
                                    User #<?= (int)$dArr['user_id'] ?>
                                </a>
                                <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                                             border-radius:10px;padding:2px 8px;font-family:monospace;">
                                    Ledger #<?= (int)$dArr['earnings_ledger_id'] ?>
                                </span>
                                <span class="oc-badge oc-badge--waiting-approval" style="font-size:.65rem;">Open</span>
                            </div>
                            <div style="font-size:.875rem;color:var(--navy);line-height:1.55;margin-bottom:6px;
                                        background:var(--cream-dark);border:1px solid var(--border);border-radius:6px;
                                        padding:10px 14px;">
                                <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;
                                               color:var(--slate);display:block;margin-bottom:4px;">
                                    Contributor's reason
                                </strong>
                                <?= htmlspecialchars($dArr['reason'] ?? '') ?>
                            </div>
                            <div style="font-size:.75rem;color:var(--slate-light);">
                                Raised <?= !empty($dArr['created_at']) ? $dArr['created_at']->format('d M Y, H:i') : '' ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;flex-shrink:0;">
                            <button onclick="openResolveModal(<?= (int)$dArr['id'] ?>)"
                                    class="oc-btn oc-btn--primary oc-btn--sm">
                                <svg viewBox="0 0 20 20" fill="currentColor" width="13">
                                    <path fill-rule="evenodd"
                                          d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                          clip-rule="evenodd"/>
                                </svg>
                                Resolve
                            </button>
                            <button onclick="openRejectModal(<?= (int)$dArr['id'] ?>)"
                                    class="oc-btn oc-btn--ghost oc-btn--sm"
                                    style="border-color:#fecaca;color:var(--red);">
                                Reject
                            </button>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    // Show adjustment reason field when adjustment amount is filled
    document.getElementById('resolve-adjustment')?.addEventListener('input', function () {
        const group = document.getElementById('resolve-reason-group');
        group.style.display = this.value ? 'block' : 'none';
    });

    // ── Resolve modal ─────────────────────────────────────────
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

        // Convert pounds to pence — strict parsing
        let adjustmentAmount = null;
        if (adjRaw !== '') {
            const parsed = parseFloat(adjRaw);
            if (isNaN(parsed)) {
                errBox.textContent = 'Adjustment amount must be a valid number.';
                errBox.style.display = 'block';
                return;
            }
            adjustmentAmount = Math.round(parsed * 100); // pence
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
            removeDisputeRow(id);
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
            removeDisputeRow(id);
        } else {
            errBox.textContent = data.error || data.message || 'Reject failed.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Reject dispute';
        }
    }

    function removeDisputeRow(id) {
        const row = document.getElementById('dispute-row-' + id);
        if (!row) return;
        row.style.opacity = '.4';
        row.style.pointerEvents = 'none';
        setTimeout(() => row.remove(), 1000);
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