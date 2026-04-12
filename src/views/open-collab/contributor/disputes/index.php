@section('logic')
<?php
/**
 * Template: open-collab/contributor/disputes/index.php
 * Variables:
 *   $disputes                — Collection of EarningsDispute models (this contributor)
 *   $disputableLedgerEntries — Collection of EarningsLedger entries that can be disputed
 *   $site                    — string
 *   $currentUser             — AuthenticatedUser
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
                <?php foreach ($disputableLedgerEntries as $eArr):
                    $amount = number_format(abs((int)$eArr->amount) / 100, 2);
                    $currency = strtoupper($eArr->currency ?? 'GBP');
                    $symbol = $currency === 'GBP' ? '£' : '$';
                    $type = ucfirst($eArr->type ?? 'sale');
                    $date = $eArr->earned_at?->format('d M Y') ?? '';
                    ?>
                    <option value="<?= (int)$eArr->id ?>"
                            data-amount="<?= $symbol . $amount ?>"
                            data-type="<?= htmlspecialchars($type) ?>"
                            data-date="<?= htmlspecialchars($date) ?>">
                        #<?= (int)$eArr->id ?> · <?= $type ?> · <?= $symbol . $amount ?> · <?= $date ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Selected entry summary -->
        <div id="selected-entry-summary" style="display:none;background:var(--cream-dark);border:1px solid var(--border);
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
            <button onclick="submitDispute()" class="oc-btn oc-btn--primary" style="flex:1;" id="dispute-submit-btn">
                Submit dispute
            </button>
        </div>
    </div>
</div>

<!-- Summary stats -->
<?php
$openCount = $disputes->filter(fn($d) => ($d->status ?? '') === 'open')->count();
$resolvedCount = $disputes->filter(fn($d) => ($d->status ?? '') === 'resolved')->count();
$rejectedCount = $disputes->filter(fn($d) => ($d->status ?? '') === 'rejected')->count();
?>
<div class="oc-stats" style="margin-bottom:24px;">
    <div class="oc-stat oc-stat--accent">
        <div class="oc-stat__label">Open Disputes</div>
        <div class="oc-stat__value"><?= $openCount ?></div>
        <div class="oc-stat__sub">Under review</div>
    </div>
    <div class="oc-stat oc-stat--green">
        <div class="oc-stat__label">Resolved</div>
        <div class="oc-stat__value"><?= $resolvedCount ?></div>
        <div class="oc-stat__sub">Closed in your favour</div>
    </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Rejected</div>
        <div class="oc-stat__value"><?= $rejectedCount ?></div>
        <div class="oc-stat__sub">No action taken</div>
    </div>
</div>

<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title">My Disputes</span>
        <?php if (!$disputableLedgerEntries->isEmpty()): ?>
            <button onclick="openDisputeModal()" class="oc-btn oc-btn--primary oc-btn--sm">
                <svg viewBox="0 0 20 20" fill="currentColor" width="14" style="margin-right:4px;">
                    <path fill-rule="evenodd"
                          d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                          clip-rule="evenodd"/>
                </svg>
                Raise a dispute
            </button>
        <?php endif; ?>
    </div>

    <?php if ($disputes->isEmpty()): ?>
        <div style="padding:48px 24px;text-align:center;color:var(--slate);">
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
    <?php else: ?>
        <div style="display:flex;flex-direction:column;">
            <?php foreach ($disputes as $i => $dispute):
                $dArr = is_array($dispute) ? $dispute : (method_exists($dispute, 'toArray') ? $dispute->toArray() : (array)$dispute);
                $status = $dArr['status'] ?? 'open';
                $isLast = $i === $disputes->count() - 1;

                $statusBadge = match ($status) {
                    'open' => ['cls' => 'oc-badge--waiting-approval', 'label' => 'Under review'],
                    'resolved' => ['cls' => 'oc-badge--published', 'label' => 'Resolved'],
                    'rejected' => ['cls' => 'oc-badge--revoked', 'label' => 'Rejected'],
                    default => ['cls' => 'oc-badge--draft', 'label' => ucfirst($status)],
                };
                ?>
                <div style="padding:18px 20px;<?= !$isLast ? 'border-bottom:1px solid var(--border);' : '' ?>">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                                <span class="oc-badge <?= $statusBadge['cls'] ?>">
                                    <?= $statusBadge['label'] ?>
                                </span>
                                <span style="font-size:.72rem;color:var(--slate);font-family:monospace;">
                                    Ledger #<?= (int)$dArr['earnings_ledger_id'] ?>
                                </span>
                                <span style="font-size:.72rem;color:var(--slate-light);">
                                    <?= !empty($dArr['created_at']) ? $dArr['created_at']->format('d M Y') : '' ?>
                                </span>
                            </div>

                            <div style="font-size:.875rem;color:var(--navy);line-height:1.55;margin-bottom:8px;">
                                <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;
                                               color:var(--slate);display:block;margin-bottom:3px;">Your reason</strong>
                                <?= htmlspecialchars($dArr['reason'] ?? '') ?>
                            </div>

                            <?php if (!empty($dArr['admin_notes'])): ?>
                                <div style="font-size:.82rem;color:var(--navy);line-height:1.5;
                                        background:<?= $status === 'resolved' ? '#f0fdf4' : '#fff9f9' ?>;
                                        border:1px solid <?= $status === 'resolved' ? '#bbf7d0' : '#fecaca' ?>;
                                        border-radius:6px;padding:10px 14px;">
                                    <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;
                                            color:<?= $status === 'resolved' ? 'var(--green)' : 'var(--red)' ?>;
                                            display:block;margin-bottom:3px;">
                                        Admin response
                                    </strong>
                                    <?= htmlspecialchars($dArr['admin_notes']) ?>
                                </div>
                            <?php elseif ($status === 'open'): ?>
                                <div style="font-size:.78rem;color:var(--slate);font-style:italic;">
                                    Our team is reviewing this — usually within 2–3 business days.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Dispute eligibility notice -->
<?php if ($disputableLedgerEntries->isEmpty() && $disputes->isEmpty()): ?>
    <div class="oc-card" style="margin-top:20px;padding:24px;">
        <div style="font-size:.875rem;color:var(--slate);line-height:1.65;">
            <strong style="color:var(--navy);">No disputable entries.</strong>
            Disputes can be raised against earnings ledger entries.
            Once you have earnings recorded, any incorrect entries can be disputed here.
        </div>
    </div>
<?php endif; ?>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

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
                <div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Amount</span><br>
                    <strong style="color:var(--navy);">${opt.dataset.amount || '—'}</strong></div>
                <div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Type</span><br>
                    <strong style="color:var(--navy);">${opt.dataset.type || '—'}</strong></div>
                <div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Date</span><br>
                    <strong style="color:var(--navy);">${opt.dataset.date || '—'}</strong></div>
            </div>`;
    }

    async function submitDispute() {
        const ledgerId = document.getElementById('dispute-ledger-id').value;
        const reason = document.getElementById('dispute-reason').value.trim();
        const errBox = document.getElementById('dispute-modal-errors');
        const btn = document.getElementById('dispute-submit-btn');
        errBox.style.display = 'none';

        if (!ledgerId) {
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
                body: JSON.stringify({
                    earnings_ledger_id: parseInt(ledgerId),
                    reason,
                }),
            });

            const data = await res.json();

            if (res.ok) {
                closeDisputeModal();
                showToast('✓ Dispute submitted — we\'ll review it shortly');
                setTimeout(() => window.location.reload(), 1200);
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

    function showToast(msg, ok = true) {
        const el = document.getElementById('status-toast');
        el.textContent = msg;
        el.style.background = ok ? 'var(--navy)' : 'var(--red)';
        el.style.opacity = '1';
        setTimeout(() => el.style.opacity = '0', 2800);
    }
</script>
@endsection