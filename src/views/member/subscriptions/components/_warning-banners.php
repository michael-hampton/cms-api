<?php
$defaultHasWarning = false;
if ($member->stripe_customer_id) {
    $stripeProcessor = app(\App\Services\Billing\PaymentProviders\StripePaymentProcessor::class);
    $warningsResult = $stripeProcessor->getPaymentMethodsWithWarnings($member);

    if (!empty($warningsResult['warnings'])) {
        $defaultPaymentMethodId = $warningsResult['default_payment_method_id'] ?? null;
        foreach ($warningsResult['warnings'] as $warning) {
            if ($warning['payment_method']->id === $defaultPaymentMethodId) {
                $defaultHasWarning = true;
                $defaultWarningMessage = $warning['message'];
                break;
            }
        }
    }
}
?>

<?php if ($defaultHasWarning): ?>
    <div class="warning-banner warning">
        <span style="font-size: 24px;">⚠️</span>
        <div>
            <div class="warning-title">Payment Method Action Required</div>
            <div class="warning-message">
                <?= htmlspecialchars($defaultWarningMessage) ?>.
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/payment-methods">Update now</a>
                to avoid subscription interruption.
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Renewal warnings logic
// Calculate renewal warnings
$showRenewalWarning = false;
$renewalWarningMessage = '';
$renewalWarningType = 'info';

if ($activeSubscription && $activeSubscription->next_billing_date) {
    $daysUntilRenewal = $activeSubscription->getDaysUntilRenewal();

    if ($daysUntilRenewal <= 7 && $daysUntilRenewal > 0) {
        $showRenewalWarning = true;
        $renewalWarningType = 'warning';
        $renewalWarningMessage = "Your subscription will renew in {$daysUntilRenewal} day" . ($daysUntilRenewal > 1 ? 's' : '');
    } elseif ($daysUntilRenewal <= 0) {
        $showRenewalWarning = true;
        $renewalWarningType = 'danger';
        $renewalWarningMessage = "Your subscription renewal is due";
    } elseif ($daysUntilRenewal <= 30) {
        $showRenewalWarning = true;
        $renewalWarningType = 'info';
        $renewalWarningMessage = "Your subscription will renew in {$daysUntilRenewal} days";
    }
}
?>

<?php if ($showRenewalWarning): ?>
    <div style="background: <?= $renewalWarningType === 'danger' ? '#fee2e2' : ($renewalWarningType === 'warning' ? '#fef3c7' : '#dbeafe') ?>;
            border-left: 4px solid <?= $renewalWarningType === 'danger' ? '#dc2626' : ($renewalWarningType === 'warning' ? '#f59e0b' : '#3b82f6') ?>;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;">
            <span style="font-size: 24px;">
                <?= $renewalWarningType === 'danger' ? '⚠️' : ($renewalWarningType === 'warning' ? '⏰' : 'ℹ️') ?>
            </span>
        <div>
            <div style="font-weight: 600; color: <?= $renewalWarningType === 'danger' ? '#991b1b' : ($renewalWarningType === 'warning' ? '#92400e' : '#1e40af') ?>;">
                <?= $renewalWarningMessage ?>
            </div>
            <?php if ($activeSubscription->auto_renew): ?>
                <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                    Auto-renewal is enabled. Payment will be processed automatically.
                </div>
            <?php else: ?>
                <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                    Auto-renewal is disabled. You'll need to renew manually.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$isCancelling =
        $activeSubscription &&
        $activeSubscription->status === 'active' &&
        $activeSubscription->cancelled_at !== null &&
        $activeSubscription->end_date > new \DateTime('today');
?>


<?php if ($isCancelling): ?>
    <div style="background: #fef3c7;
        border-left: 4px solid #f59e0b;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;">
        <span style="font-size: 24px;">🔔</span>
        <div>
            <div style="font-weight: 600; color: #92400e;">
                Subscription Set to Cancel
            </div>
            <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                <?php
                $daysRemaining = $activeSubscription->end_date ?
                        (new \DateTime())->diff($activeSubscription->end_date)->days : null;
                ?>
                Your access will end on <?= $activeSubscription->end_date->format('F d, Y') ?>
                <?php if ($daysRemaining): ?>
                    (<strong style="color: #667eea;"><?= $daysRemaining ?> days remaining</strong>)
                <?php endif; ?>.
                You can reactivate anytime before then to continue your subscription.
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$defaultHasWarning = false;
// Check if default payment method is expiring/expired
if ($member->stripe_customer_id) {
    $stripeProcessor = app(\App\Services\Billing\PaymentProviders\StripePaymentProcessor::class);
    $warningsResult = $stripeProcessor->getPaymentMethodsWithWarnings($member);

    // Check if default payment method has warnings
    $defaultHasWarning = false;
    $defaultWarningMessage = '';

    if (!empty($warningsResult['warnings'])) {
        $defaultPaymentMethodId = $warningsResult['default_payment_method_id'] ?? null;
        foreach ($warningsResult['warnings'] as $warning) {
            if ($warning['payment_method']->id === $defaultPaymentMethodId) {
                $defaultHasWarning = true;
                $defaultWarningMessage = $warning['message'];
                break;
            }
        }
    }
}
?>



<?php if ($defaultHasWarning): ?>
    <div style="background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;">
        <span style="font-size: 24px;">⚠️</span>
        <div>
            <div style="font-weight: 600; color: #92400e;">
                Payment Method Action Required
            </div>
            <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                <?= htmlspecialchars($defaultWarningMessage) ?>.
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/payment-methods"
                   style="color: #f59e0b; font-weight: 600;">Update now</a> to avoid subscription
                interruption.
            </div>
        </div>
    </div>
<?php endif; ?>
