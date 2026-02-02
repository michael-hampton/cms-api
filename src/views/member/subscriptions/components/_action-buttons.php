<?php
$isCancelling =
        $activeSubscription &&
        $activeSubscription->status === 'active' &&
        $activeSubscription->cancelled_at !== null &&
        $activeSubscription->end_date > new \DateTime('today');

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