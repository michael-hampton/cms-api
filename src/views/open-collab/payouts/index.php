@section('logic')
<?php
/**
 * Template: open-collab/payouts/index.php
 * Variables:
 *   $availableBalancePence  — int
 *   $availableBalancePounds — string  e.g. "123.45"
 *   $payouts                — Collection of Payout models
 *   $currentUser            — AuthenticatedUser
 *   $site                   — string (site slug)
 */

$pageTitle = 'Payouts';
$activeNav = 'earnings';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/contributor/dashboard'],
    ['label' => 'Payouts'],
];
$pageClass = 'oc-page--wide';

$minimumPayoutPounds = '50.00';
$canRequest = $availableBalancePence >= 5000;
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')

<div class="oc-stats" style="animation:fadeSlideIn .4s ease;">
    <div class="oc-stat oc-stat--accent">
        <div class="oc-stat__label">Available Balance</div>
        <div class="oc-stat__value">£<?= htmlspecialchars($availableBalancePounds) ?></div>
        <div class="oc-stat__sub">Ready to withdraw</div>
    </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Minimum Payout</div>
        <div class="oc-stat__value">£<?= $minimumPayoutPounds ?></div>
        <div class="oc-stat__sub">Per request</div>
    </div>
    <div class="oc-stat oc-stat--green">
        <div class="oc-stat__label">Total Requests</div>
        <div class="oc-stat__value"><?= count($payouts) ?></div>
        <div class="oc-stat__sub">All time</div>
    </div>
</div>

<div class="oc-grid-sidebar" style="align-items:start;">

    <!-- Payout history table -->
    <div class="oc-card" style="animation:fadeSlideIn .45s ease;">
        <div class="oc-card__header">
            <span class="oc-card__title">Payout History</span>
        </div>

        <?php if (empty($payouts) || count($payouts) === 0): ?>
            <div style="padding:48px 24px;text-align:center;color:var(--slate);">
                <svg viewBox="0 0 20 20" fill="currentColor" width="32"
                     style="opacity:.2;display:block;margin:0 auto 12px;">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                          clip-rule="evenodd"/>
                </svg>
                <div style="font-weight:500;margin-bottom:6px;">No payout requests yet</div>
                <div style="font-size:.85rem;">Once your balance reaches £<?= $minimumPayoutPounds ?>, you can request a
                    payout.
                </div>
            </div>
        <?php else: ?>
            <table class="oc-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Reference</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($payouts as $payout): ?>
                    <tr>
                        <td style="white-space:nowrap;color:var(--slate);">
                            <?= $payout->created_at ? date('d M Y', strtotime($payout->created_at)) : '–' ?>
                        </td>
                        <td style="font-weight:600;font-family:var(--font-display);font-size:1rem;color:var(--navy);">
                            £<?= number_format($payout->amount / 100, 2) ?>
                        </td>
                        <td style="color:var(--slate);font-size:.85rem;">
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $payout->method ?? ''))) ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = match ($payout->status) {
                                'paid' => 'published',
                                'approved' => 'free',
                                'pending' => 'draft',
                                'rejected' => 'revoked',
                                default => 'draft',
                            };
                            ?>
                            <span class="oc-badge oc-badge--<?= $statusClass ?>">
                                <?= ucfirst(htmlspecialchars($payout->status ?? '')) ?>
                            </span>
                        </td>
                        <td style="color:var(--slate);font-size:.82rem;font-family:monospace;">
                            <?= htmlspecialchars($payout->reference ?? '–') ?>
                        </td>
                    </tr>
                    <?php if (!empty($payout->rejection_reason)): ?>
                        <tr>
                            <td colspan="5"
                                style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;">
                                <strong>Rejection reason:</strong> <?= htmlspecialchars($payout->rejection_reason) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Request payout sidebar -->
    <div style="position:sticky;top:calc(var(--header-h) + 20px);">
        <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.95rem;">Request Payout</span>
            </div>
            <div class="oc-card__body">

                <div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px;text-align:center;">
                    <div style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">
                        Available now
                    </div>
                    <div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy);">
                        £<?= htmlspecialchars($availableBalancePounds) ?>
                    </div>
                </div>

                <div id="payout-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
                <div id="payout-success" class="oc-alert oc-alert--success"
                     style="display:none;margin-bottom:12px;"></div>

                <?php if ($canRequest): ?>
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
                    <button onclick="requestPayout()" class="oc-btn oc-btn--amber oc-btn--block" id="payout-btn">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                                  clip-rule="evenodd"/>
                        </svg>
                        Request £<?= htmlspecialchars($availableBalancePounds) ?>
                    </button>
                <?php else: ?>
                    <div style="padding:16px;text-align:center;border:1.5px dashed var(--border);border-radius:var(--radius);">
                        <div style="font-size:.85rem;font-weight:500;margin-bottom:4px;color:var(--navy);">
                            Minimum not reached
                        </div>
                        <div style="font-size:.78rem;color:var(--slate);margin-bottom:0;">
                            You need at least <strong>£<?= $minimumPayoutPounds ?></strong> to request a payout.
                            You currently have <strong>£<?= htmlspecialchars($availableBalancePounds) ?></strong>.
                        </div>
                    </div>
                <?php endif; ?>

                <div style="font-size:.72rem;color:var(--slate);line-height:1.6;padding-top:12px;border-top:1px solid var(--border);margin-top:16px;">
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
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    async function requestPayout() {
        const btn = document.getElementById('payout-btn');
        const errBox = document.getElementById('payout-errors');
        const okBox = document.getElementById('payout-success');
        const method = document.getElementById('payout-method').value;

        errBox.style.display = 'none';
        //okBox.style.display = 'none';

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Submitting…';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/payouts`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${TOKEN()}`,
                },
                body: JSON.stringify({method}),
            });

            const data = await res.json();

            if (res.ok) {
                //okBox.textContent = '✓ Payout request submitted. Our team will process it shortly.';
                //okBox.style.display = 'flex';
                btn.style.display = 'none';
                // Reload after a moment so the new payout appears in the list
                setTimeout(() => window.location.reload(), 2000);
            } else {
                errBox.textContent = data.message || data.error || 'Request failed. Please try again.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = 'Request payout';
            }
        } catch {
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = 'Request payout';
        }
    }
</script>
@endsection