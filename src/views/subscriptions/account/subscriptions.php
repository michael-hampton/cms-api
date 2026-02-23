<?php
/**
 * View: account/subscriptions.php
 *
 * Variables from ShopAccountController::subscriptions():
 *   $member      – authenticated member
 *   $grouped     – ['active' => ['print' => [], 'digital' => []], 'expired' => [...]]
 *   $summary     – ['total', 'active', 'expired', 'cancelled']
 *   $active_tab  – 'subscriptions'
 */
?>
<style>
    /* ── Subscription grid ───────────────────────────────────────── */
    .sub-grid {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    /* ── Section labels ──────────────────────────────────────────── */
    .section-label {
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--ink-muted);
        margin: 28px 0 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-label:first-child {
        margin-top: 0;
    }
    .section-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border-soft);
    }

    /* ── Cancellation modal styles ───────────────────────────────── */
    .cancel-step {
        display: none;
    }

    .cancel-step.active {
        display: block;
    }

    .benefit-list {
        list-style: none;
        margin: 14px 0 18px;
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    .benefit-list li {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        color: var(--ink-soft);
    }

    .benefit-list__icon {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: var(--red-light);
        color: var(--red);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
        font-weight: 700;
    }

    .reason-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin: 14px 0;
    }

    .reason-radio {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 14px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: var(--transition);
        font-size: 14px;
        color: var(--ink-soft);
    }

    .reason-radio:hover {
        border-color: var(--ink-muted);
        color: var(--ink);
    }
    .reason-radio.selected {
        border-color: var(--ink);
        background: var(--paper);
        color: var(--ink);
        font-weight: 500;
    }
    .reason-radio input[type="radio"] {
        accent-color: var(--ink);
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }

    .reason-other-textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        font-family: var(--font-body);
        font-size: 14px;
        color: var(--ink);
        resize: vertical;
        min-height: 80px;
        outline: none;
        margin-top: 8px;
        transition: var(--transition);
    }

    .reason-other-textarea:focus {
        border-color: var(--ink);
    }

    .confirm-danger-box {
        background: var(--red-light);
        border: 1px solid #f5a5a0;
        border-radius: var(--radius-sm);
        padding: 16px;
        margin: 14px 0;
        font-size: 13px;
        color: var(--red);
        line-height: 1.65;
    }

    /* Retention offer cards */
    .retention-cards {
        display: flex;
        flex-direction: column;
        gap: 9px;
        margin-top: 16px;
    }

    .retention-card {
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 13px 16px;
        display: flex;
        align-items: center;
        gap: 13px;
        cursor: pointer;
        transition: var(--transition);
        background: var(--white);
        text-align: left;
        width: 100%;
        font-family: var(--font-body);
    }
    .retention-card:hover {
        border-color: var(--green);
        background: var(--green-light);
    }

    .retention-card__icon {
        font-size: 20px;
        flex-shrink: 0;
    }

    .retention-card__title {
        font-weight: 600;
        font-size: 14px;
        color: var(--ink);
        margin-bottom: 2px;
    }

    .retention-card__sub {
        font-size: 12px;
        color: var(--ink-muted);
    }
</style>

<?php $page_title = 'Subscriptions'; ?>

@include('subscriptions/account/_layout')

<main class="page-content">

    <div class="page-heading">
        <div class="page-heading__eyebrow">Account</div>
        <h1 class="page-heading__title">Subscriptions</h1>
        <p class="page-heading__sub">
            <?= $summary['active'] ?? 0 ?> active
            · <?= ($summary['expired'] ?? 0) + ($summary['cancelled'] ?? 0) ?> expired or cancelled
        </p>
    </div>

    <?php
    $allGroups = [
            'active' => array_merge($grouped['active']['print'] ?? [], $grouped['active']['digital'] ?? []),
            'expired' => array_merge($grouped['expired']['print'] ?? [], $grouped['expired']['digital'] ?? []),
    ];
    $hasAny = !empty($allGroups['active']) || !empty($allGroups['expired']);
    ?>

    <?php if (!$hasAny): ?>
        <div class="card">
            <div class="card__body">
                <div class="empty-state">
                    <div class="empty-state__icon">📭</div>
                    <div class="empty-state__title">No subscriptions yet</div>
                    <div class="empty-state__sub">Browse our publications and start your first subscription.</div>
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions" class="btn btn--primary">Browse
                        publications</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="sub-grid">
            <?php if (!empty($allGroups['active'])): ?>
                <div class="section-label">Active</div>
                <?php foreach ($allGroups['active'] as $sub): ?>
                    @include('subscriptions/account/_subscription_card', ['sub' => $sub])
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($allGroups['expired'])): ?>
                <div class="section-label">Expired &amp; Cancelled</div>
                <?php foreach ($allGroups['expired'] as $sub): ?>
                    @include('subscriptions/account/_subscription_card', ['sub' => $sub])
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <!-- ── Cancellation modal (3-step) ───────────────────────────────── -->
    <div class="modal-overlay" id="cancel-modal" role="dialog" aria-modal="true" aria-labelledby="cancel-modal-title">
        <div class="modal">

            <div class="modal__header">
                <div>
                    <h2 class="modal__title" id="cancel-modal-title">Cancel Subscription</h2>
                    <div id="step-indicator" style="margin-top:10px;"></div>
                </div>
                <button class="modal__close" onclick="closeCancelModal()" aria-label="Close">×</button>
            </div>

            <div class="modal__body">

                <!-- Step 1: Loss aversion + retention offers -->
                <div class="cancel-step active" id="cancel-step-1">
                    <p style="font-size:14px; color:var(--ink-soft); margin-bottom:4px; line-height:1.6;">
                        You're about to cancel <strong id="cancel-plan-name">your subscription</strong>.
                        You'll keep access until <strong id="cancel-end-date">—</strong>.
                    </p>
                    <p style="font-size:13px; color:var(--ink-muted); margin-bottom:2px;">After cancelling you'll
                        lose:</p>
                    <ul class="benefit-list">
                        <li>
                            <div class="benefit-list__icon">✕</div>
                            Access to all future issues
                        </li>
                        <li>
                            <div class="benefit-list__icon">✕</div>
                            Member pricing on renewals
                        </li>
                        <li>
                            <div class="benefit-list__icon">✕</div>
                            Digital archive access
                        </li>
                    </ul>
                    <p style="font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.09em; color:var(--ink-muted); margin-bottom:8px;">
                        Or consider instead</p>
                    <div class="retention-cards">
                        <button class="retention-card" onclick="closeModalWithAction('pause')">
                            <span class="retention-card__icon">⏸️</span>
                            <div>
                                <div class="retention-card__title">Pause instead</div>
                                <div class="retention-card__sub">Take a break and resume any time</div>
                            </div>
                        </button>
                        <button class="retention-card" onclick="closeModalWithAction('switch')">
                            <span class="retention-card__icon">🔄</span>
                            <div>
                                <div class="retention-card__title">Switch to digital-only</div>
                                <div class="retention-card__sub">Lower price, same great content</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Reason collection -->
                <div class="cancel-step" id="cancel-step-2">
                    <p style="font-size:14px; color:var(--ink-soft); margin-bottom:4px;">
                        Help us improve — why are you cancelling?
                    </p>
                    <div class="reason-list" id="reason-list">
                        <?php
                        $reasons = [
                                'too_expensive' => 'Too expensive',
                                'not_using' => 'Not using it enough',
                                'switching' => 'Switching to another product',
                                'technical_issues' => 'Technical issues',
                                'other' => 'Other reason',
                        ];
                        foreach ($reasons as $val => $label): ?>
                            <label class="reason-radio" onclick="selectReason(this)">
                                <input type="radio" name="cancel_reason" value="<?= $val ?>">
                                <?= htmlspecialchars($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <textarea id="other-reason-text" class="reason-other-textarea"
                              placeholder="Tell us more (optional)" style="display:none;"></textarea>
                </div>

                <!-- Step 3: Final confirmation -->
                <div class="cancel-step" id="cancel-step-3">
                    <div class="confirm-danger-box">
                        <strong>This action is final.</strong> Your subscription will be cancelled and you'll retain
                        access until <strong id="confirm-end-date">—</strong>.
                        No further charges will be made.
                    </div>
                    <p style="font-size:13px; color:var(--ink-muted); line-height:1.6;">
                        Refund eligibility depends on your subscription terms.
                        If you believe you're entitled to a refund, please contact support after cancelling.
                    </p>
                </div>
            </div>

            <div class="modal__footer" id="cancel-modal-footer"></div>
        </div>
    </div>

</main>
</div><!-- /.shell -->
</body>
</html>


<script>
    let cancelSubscriptionId = null;
    let cancelStep = 1;

    const STEPS = [{label: 'Review'}, {label: 'Reason'}, {label: 'Confirm'}];

    function openCancelModal(id, planName, endDate) {
        cancelSubscriptionId = id;
        cancelStep = 1;
        document.getElementById('cancel-plan-name').textContent = planName;
        document.getElementById('cancel-end-date').textContent = endDate || '—';
        document.getElementById('confirm-end-date').textContent = endDate || '—';
        document.querySelectorAll('.reason-radio').forEach(r => r.classList.remove('selected'));
        document.querySelectorAll('input[name="cancel_reason"]').forEach(r => r.checked = false);
        const otherBox = document.getElementById('other-reason-text');
        otherBox.style.display = 'none';
        otherBox.value = '';
        renderCancelModal();
        document.getElementById('cancel-modal').classList.add('open');
    }

    function closeCancelModal() {
        document.getElementById('cancel-modal').classList.remove('open');
        cancelSubscriptionId = null;
    }

    function closeModalWithAction(action) {
        closeCancelModal();
        if (action === 'pause') {
            alert('Pause functionality coming soon. Please contact support to pause your subscription.');
        } else if (action === 'switch') {
            window.location.href = '/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions';
        }
    }

    function renderCancelModal() {
        let html = '<div class="steps">';
        STEPS.forEach((s, i) => {
            const n = i + 1;
            const cls = n < cancelStep ? 'done' : (n === cancelStep ? 'active' : '');
            html += `<div class="step ${cls}"><div class="step__num">${n < cancelStep ? '✓' : n}</div> ${s.label}</div>`;
            if (i < STEPS.length - 1) html += '<div class="step__divider"></div>';
        });
        html += '</div>';
        document.getElementById('step-indicator').innerHTML = html;

        [1, 2, 3].forEach(n => {
            document.getElementById(`cancel-step-${n}`).classList.toggle('active', n === cancelStep);
        });

        const footer = document.getElementById('cancel-modal-footer');
        if (cancelStep === 1) {
            footer.innerHTML = `
                <button class="btn btn--ghost" onclick="closeCancelModal()">Keep Subscription</button>
                <button class="btn btn--danger" onclick="advanceCancelStep()">Continue to Cancel</button>`;
        } else if (cancelStep === 2) {
            footer.innerHTML = `
                <button class="btn btn--ghost" onclick="retreatCancelStep()">Back</button>
                <button class="btn btn--danger" onclick="advanceCancelStep()" id="next-btn-step2">Next</button>`;
        } else {
            footer.innerHTML = `
                <button class="btn btn--ghost" onclick="retreatCancelStep()">Back</button>
                <button class="btn btn--danger" onclick="submitCancellation()" id="confirm-cancel-btn">Confirm Cancellation</button>`;
        }
    }

    function advanceCancelStep() {
        if (cancelStep === 2) {
            const selected = document.querySelector('input[name="cancel_reason"]:checked');
            if (!selected) {
                document.querySelectorAll('.reason-radio').forEach(r => r.style.borderColor = 'var(--red)');
                setTimeout(() => document.querySelectorAll('.reason-radio').forEach(r => r.style.borderColor = ''), 1400);
                return;
            }
        }
        cancelStep = Math.min(3, cancelStep + 1);
        renderCancelModal();
    }

    function retreatCancelStep() {
        cancelStep = Math.max(1, cancelStep - 1);
        renderCancelModal();
    }

    function selectReason(label) {
        document.querySelectorAll('.reason-radio').forEach(r => r.classList.remove('selected'));
        label.classList.add('selected');
        const val = label.querySelector('input').value;
        document.getElementById('other-reason-text').style.display = val === 'other' ? 'block' : 'none';
    }

    async function submitCancellation() {
        const btn = document.getElementById('confirm-cancel-btn');
        btn.disabled = true;
        btn.textContent = 'Cancelling…';

        const reason = document.querySelector('input[name="cancel_reason"]:checked')?.value ?? '';
        const otherText = document.getElementById('other-reason-text').value;

        try {
            const res = await fetch(`/account/subscriptions/${cancelSubscriptionId}/cancel`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({reason, other_text: otherText}),
            });
            const data = await res.json();
            if (data.success) {
                closeCancelModal();
                window.location.reload();
            } else {
                alert(data.message ?? 'Something went wrong. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Confirm Cancellation';
            }
        } catch (e) {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Confirm Cancellation';
        }
    }

    document.getElementById('cancel-modal').addEventListener('click', function (e) {
        if (e.target === this) closeCancelModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeCancelModal();
    });
</script>