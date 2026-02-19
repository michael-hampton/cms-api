<?php
$isCancelling =
        $activeSubscription &&
        $activeSubscription->status === 'active' &&
        $activeSubscription->cancelled_at !== null &&
        $activeSubscription->end_date > new \DateTime('today');

echo $activeSubscription->status;

if ($activeSubscription && $activeSubscription->status === 'cancelled') {
    $isCancelling = true;
}
?>

<?php if ($activeSubscription->hasStripeSubscription() && $activeSubscription->auto_renew): ?>
    <div class="btn-group">
        <!-- existing buttons -->
        <button class="btn btn-secondary"
                onclick="openChangeBillingDateModal(<?= $activeSubscription->id ?>, '<?= $activeSubscription->next_billing_date?->format('d') ?>')">
            📅 Change Billing Date
        </button>
    </div>
<?php endif; ?>

<?php if ($activeSubscription->isPrint()): ?>
    <div class="btn-group" style="margin-top: 24px;">
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/<?= $activeSubscription->id ?>/issue-deliveries"
           class="btn btn-primary">
            📅 View Issue Delivery Schedule
        </a>
    </div>
<?php endif; ?>

<div class="btn-group">
    <?php if ($isCancelling): ?>
        <button class="btn btn-primary"
                onclick="reactivateSubscription(<?= $activeSubscription->id ?>)">
            Reactivate Subscription
        </button>
    <?php else: ?>
        <button class="btn btn-danger" onclick="cancelSubscription(<?= $activeSubscription->id ?>)">
            Cancel Subscription
        </button>
    <?php endif; ?>
</div>

<div class="info-row">
    <span class="info-label">Auto-Renewal</span>
    <span class="info-value">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <input type="checkbox"
                   id="auto-renew-toggle"
                   <?= $activeSubscription->auto_renew ? 'checked' : '' ?>
                   onchange="updateAutoRenew(<?= $activeSubscription->id ?>, this.checked)"
                   style="width:18px;height:18px;cursor:pointer;accent-color:#667eea;">
            <span style="font-size:14px;color:#64748b;">
                Automatically renew at end of billing period
            </span>
        </label>
    </span>
</div>