<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 * @var \App\Models\Subscription|null $activeSubscription
 * @var \App\Framework\Support\Collection $subscriptionHistory
 * @var array $subscriptionSummary
 * @var \App\Framework\Support\Collection $plans
 */

use App\Framework\Support\SiteContext;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subscriptions - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #2c3e50;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .card {
            background: white;
            padding: 32px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .card h2 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }

        .icon {
            font-size: 28px;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 24px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .badge-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .badge-warning {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
        }

        .badge-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 2px solid #f1f5f9;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
            font-weight: 600;
            font-size: 15px;
        }

        .info-value {
            color: #1e293b;
            font-weight: 700;
            font-size: 15px;
        }

        .btn {
            display: inline-block;
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
            letter-spacing: 0.3px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .subscription-status {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            margin-bottom: 24px;
        }

        .status-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
        }

        .status-icon.active {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .status-icon.inactive {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th,
        .history-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 2px solid #f1f5f9;
        }

        .history-table th {
            background: #f8fafc;
            font-weight: 700;
            color: #1e293b;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .history-table tr:hover {
            background: #f8fafc;
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #64748b;
        }

        .empty-state-icon {
            font-size: 72px;
            margin-bottom: 20px;
            opacity: 0.4;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 24px;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn.loading {
            position: relative;
            color: transparent;
        }

        .btn.loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .header h1 {
                font-size: 32px;
            }
        }


        /* Keep all existing styles and add these for the dialog */
        .dialog-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9998;
            animation: fadeIn 0.2s ease;
        }

        .dialog-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            animation: slideUp 0.3s ease;
            width: 90%;
            max-width: 500px;
        }

        .dialog-content {
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .dialog-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            animation: scaleIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .icon-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
        }

        .icon-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #f59e0b;
        }

        .icon-info {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #3b82f6;
        }

        .icon-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #10b981;
        }

        .dialog-title {
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            line-height: 1.3;
            text-align: center;
        }

        .dialog-message {
            font-size: 16px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 28px;
            text-align: center;
        }

        .dialog-options {
            margin: 28px 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .option-item {
            display: flex;
            align-items: center;
            padding: 18px 20px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .option-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .option-item.selected {
            background: linear-gradient(135deg, #ede9fe, #ddd6fe);
            border-color: #7c3aed;
        }

        .option-item input[type="radio"] {
            margin-right: 16px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #7c3aed;
        }

        .option-content {
            flex: 1;
            text-align: left;
        }

        .option-label {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .option-description {
            font-size: 13px;
            color: #64748b;
            line-height: 1.4;
        }

        .option-check {
            color: #7c3aed;
            font-size: 24px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .option-item.selected .option-check {
            opacity: 1;
        }

        .dialog-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 32px;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .btn-confirm {
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
@include('member._header')
<div class="container" style="margin-top: 40px;">

    <div class="grid">
        <!-- Active Subscription Card -->
        <div class="card">
            <h2>
                <span class="icon">📋</span>
                Current Plan
            </h2>

            <?php if ($activeSubscription): ?>
                <?php
                $defaultHasWarning = false;
                // Check if default payment method is expiring/expired
                if ($member->stripe_customer_id) {
                    $stripeProcessor = new \App\Services\Payment\StripePaymentProcessor(
                            new \App\Repositories\Members\PaymentRepository()
                    );
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

                <div class="subscription-status">
                    <div class="status-icon active">✓</div>
                    <div>
                        <div style="font-weight: 700; font-size: 20px; color: #1e293b;">
                            <?= htmlspecialchars($activeSubscription->plan_name) ?>
                        </div>
                        <div style="color: #64748b; font-size: 15px; font-weight: 500;">Active subscription</div>
                    </div>
                </div>

                <?php
                // Calculate renewal warnings
                $showRenewalWarning = false;
                $renewalWarningMessage = '';
                $renewalWarningType = 'info';

                if ($activeSubscription->next_billing_date) {
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

                $today = new \DateTime('today');

                // Check if subscription is set to cancel
// A subscription is cancelling if it has an end_date in the future AND auto_renew is disabled
// If auto_renew is true, the subscription will renew and not actually end
                $isCancelling =
                        $activeSubscription->status === 'active' &&
                        $activeSubscription->cancelled_at !== null &&
                        $activeSubscription->end_date > new \DateTime('today');
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
                if ($activeSubscription && !$activeSubscription->auto_renew && $activeSubscription->end_date) {
                    $daysUntilEnd = (new \DateTime())->diff($activeSubscription->end_date)->days;
                    $showRenewalPrompt = $daysUntilEnd <= 30 && $daysUntilEnd > 0;

                    if ($showRenewalPrompt):
                        ?>
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    text-align: center;">
                            <div style="font-size: 24px; margin-bottom: 8px;">⏰</div>
                            <div style="font-weight: 700; font-size: 18px; margin-bottom: 8px;">
                                Your subscription expires in <?= $daysUntilEnd ?> day<?= $daysUntilEnd > 1 ? 's' : '' ?>
                            </div>
                            <div style="font-size: 14px; margin-bottom: 16px; opacity: 0.9;">
                                Renew now to continue enjoying uninterrupted access
                            </div>
                            <button onclick="openRenewalModal()" class="btn btn-primary"
                                    style="background: white; color: #667eea;">
                                Renew Subscription
                            </button>
                        </div>
                    <?php
                    endif;
                }
                ?>

                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="badge badge-success">Active</span>
                </div>

                <?php if ($activeSubscription && $activeSubscription->isActive()): ?>
                    <?php
                    $availableUpgrades = $activeSubscription->getAvailableUpgrades();
                    if (!empty($availableUpgrades)):
                        ?>
                        <div style="background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 24px;
            border-radius: 16px;
            margin: 24px 0;">
                            <h3 style="font-size: 22px; font-weight: 700; margin-bottom: 16px;">
                                🎁 Available Upgrades
                            </h3>

                            <?php foreach (array_slice($availableUpgrades, 0, 3) as $upgrade): ?>
                                <?php $plan = $upgrade['plan']; ?>
                                <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 12px; margin-bottom: 12px;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div>
                                            <div style="font-weight: 700; font-size: 18px; margin-bottom: 4px;">
                                                <?= htmlspecialchars($plan->name) ?>
                                            </div>
                                            <div style="font-size: 14px; opacity: 0.9;">
                                                Unlock:
                                                <?php
                                                $accessNames = array_map(function ($a) {
                                                    return ucwords(str_replace('-', ' ', $a['identifier']));
                                                }, $upgrade['new_access']);
                                                echo htmlspecialchars(implode(', ', $accessNames));
                                                ?>
                                            </div>
                                        </div>
                                        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/<?= $activeSubscription->id ?>/upgrade?plan_id=<?= $plan->id ?>"
                                           class="btn btn-primary"
                                           style="background: white; color: #667eea;">
                                            Upgrade
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="info-row">
                    <span class="info-label">Price</span>
                    <span class="info-value">
                        <?= htmlspecialchars($activeSubscription->currency) ?>
                        <?= number_format($activeSubscription->price, 2) ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">Start Date</span>
                    <span class="info-value">
                        <?= $activeSubscription->start_date->format('M d, Y') ?>
                    </span>
                </div>

                <?php if ($activeSubscription->next_billing_date): ?>
                    <div class="info-row">
                        <span class="info-label">Next Billing Date</span>
                        <span class="info-value" style="font-weight: 800; color: #667eea;">
                <?= $activeSubscription->next_billing_date->format('M d, Y') ?>
            </span>
                    </div>
                <?php endif; ?>

                <?php if ($activeSubscription->end_date): ?>
                    <div class="info-row">
                        <span class="info-label">End Date</span>
                        <span class="info-value">
                            <?= $activeSubscription->end_date->format('M d, Y') ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="info-row">
                    <span class="info-label">Auto Renew</span>
                    <span class="info-value">
                        <?= $activeSubscription->auto_renew ? '✓ Yes' : '✗ No' ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">Delivery Type</span>
                    <span class="info-value">
        <?= $activeSubscription->isPrint() ? '📦 Print' : '💻 Digital' ?>
    </span>
                </div>

                <?php if ($activeSubscription->isPrint()): ?>

                    <?php if ($activeSubscription->isPrint()): ?>
                        <?php
                        // Get next upcoming delivery
                        $issueDeliveryRepo = new \App\Repositories\Subscriptions\IssueDeliveryRepository();
                        $nextDelivery = $issueDeliveryRepo->getUpcomingDeliveries($activeSubscription->id, 1)->first();
                        ?>

                        <div class="info-row">
                            <span class="info-label">Next Issue Delivery</span>
                            <span class="info-value" style="display: flex; align-items: center; gap: 8px;">
            <?php if ($nextDelivery): ?>
                <span style="font-weight: 800; color: #667eea; font-size: 18px;">
                    <?= $nextDelivery->estimated_delivery_date->format('M d, Y') ?>
                </span>
                <span class="badge"
                      style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; font-size: 11px;">
                    Issue #<?= $nextDelivery->issue_number ?>
                </span>
                <?php
                $daysUntil = (new \DateTime())->diff($nextDelivery->estimated_delivery_date)->days;
                if ($daysUntil <= 7 && $daysUntil >= 0):
                    ?>
                    <span style="font-size: 13px; color: #f59e0b; font-weight: 600;">
                        (<?= $daysUntil ?> day<?= $daysUntil != 1 ? 's' : '' ?> away)
                    </span>
                <?php endif; ?>
            <?php else: ?>
                <span style="color: #64748b; font-size: 14px;">
                    <?php if ($activeSubscription->end_date && $activeSubscription->end_date < new \DateTime()): ?>
                        Subscription ended
                    <?php else: ?>
                        Schedule being prepared
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </span>
                        </div>

                        <?php if ($nextDelivery && $nextDelivery->tracking_info && !empty($nextDelivery->tracking_info['tracking_number'])): ?>
                            <div class="info-row">
                                <span class="info-label">Tracking</span>
                                <span class="info-value">
                <a href="<?= htmlspecialchars($nextDelivery->tracking_info['tracking_url'] ?? '#') ?>"
                   target="_blank"
                   style="color: #667eea; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                    <?= htmlspecialchars($nextDelivery->tracking_info['tracking_number']) ?>
                    <span style="font-size: 12px;">↗</span>
                </a>
            </span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($activeSubscription->isDeliveryPaused()): ?>
                        <div style="background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;">
                            <span style="font-size: 24px;">⏸️</span>
                            <div>
                                <div style="font-weight: 600; color: #92400e;">
                                    Delivery Paused
                                </div>
                                <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                                    Your deliveries are paused
                                    until <?= $activeSubscription->delivery_pause_end->format('F d, Y') ?>
                                    (<?= $activeSubscription->getDaysUntilPauseEnds() ?> days remaining)
                                </div>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="resumeDeliveryNow(<?= $activeSubscription->id ?>)">
                                ▶️ Resume Delivery Now
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="btn-group">
                            <button class="btn btn-secondary"
                                    onclick="openPauseDeliveryModal(<?= $activeSubscription->id ?>)">
                                ⏸️ Pause Delivery
                            </button>
                        </div>
                    <?php endif; ?>


                    <div class="info-row">
                        <span class="info-label">Shipping Address</span>
                        <span class="info-value" style="font-size: 14px; line-height: 1.6;">
            <?php
            // First check if subscription has an associated order with shipping address
            $shippingAddress = null;

            if ($activeSubscription->one_time_subscription_id) {
                // Look up the order associated with this subscription
                $order = \App\Models\Order::where('one_time_subscription_id', $activeSubscription->id)
                        ->first();

                if ($order) {
                    // Try to get address from order's relationship first
                    if ($order->shipping_address_id) {
                        $shippingAddress = \App\Models\Address::find($order->shipping_address_id);
                    } elseif ($order->shipping_address && is_array($order->shipping_address)) {
                        // Use the stored address array from order
                        echo htmlspecialchars($order->shipping_address['address_line_1'] ?? $order->shipping_address['line1'] ?? '') . '<br>';
                        if (!empty($order->shipping_address['address_line_2'] ?? $order->shipping_address['line2'] ?? '')) {
                            echo htmlspecialchars($order->shipping_address['address_line_2'] ?? $order->shipping_address['line2']) . '<br>';
                        }
                        echo htmlspecialchars($order->shipping_address['city'] ?? '') . ', ';
                        echo htmlspecialchars($order->shipping_address['postcode'] ?? '');
                        $shippingAddress = 'displayed'; // Flag that we've shown it
                    }
                }
            }

            // If no order address found, fall back to member's default shipping address
            if (!$shippingAddress || $shippingAddress !== 'displayed') {
                if ($shippingAddress) {
                    // We have an Address object
                    echo htmlspecialchars($shippingAddress->address_line_1) . '<br>';
                    if ($shippingAddress->address_line_2) {
                        echo htmlspecialchars($shippingAddress->address_line_2) . '<br>';
                    }
                    echo htmlspecialchars($shippingAddress->city) . ', ' . htmlspecialchars($shippingAddress->postcode);
                } else {
                    // Try to get default shipping address from member
                    $defaultAddress = \App\Models\Address::where('member_id', $member->id)
                            ->where('site_id', \App\Framework\Support\SiteContext::getId())
                            ->where('is_default', true)
                            ->whereIn('type', ['shipping', 'both'])
                            ->first();

                    if ($defaultAddress) {
                        echo htmlspecialchars($defaultAddress->address_line_1) . '<br>';
                        if ($defaultAddress->address_line_2) {
                            echo htmlspecialchars($defaultAddress->address_line_2) . '<br>';
                        }
                        echo htmlspecialchars($defaultAddress->city) . ', ' . htmlspecialchars($defaultAddress->postcode);
                    } else {
                        ?>
                        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/addresses"
                           style="color: #667eea;">
                            Add shipping address
                        </a>
                        <?php
                    }
                }
            }
            ?>
        </span>
                    </div>
                <?php endif; ?>

                <?php if ($activeSubscription->isDigital() && $activeSubscription->hasValidDownload()): ?>
                    <div class="info-row">
                        <span class="info-label">Digital Access</span>
                        <span class="info-value">
            <a href="<?= htmlspecialchars($activeSubscription->download_url) ?>"
               style="color: #667eea; text-decoration: none; font-weight: 600;">
                Download Now →
            </a>
        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Download Expires</span>
                        <span class="info-value" style="font-size: 14px;">
            <?= $activeSubscription->download_expires_at->format('M d, Y') ?>
        </span>
                    </div>
                <?php endif; ?>

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
            <?php else: ?>
                <div class="subscription-status">
                    <div class="status-icon inactive">✗</div>
                    <div>
                        <div style="font-weight: 700; font-size: 20px; color: #1e293b;">
                            No Active Subscription
                        </div>
                        <div style="color: #64748b; font-size: 15px; font-weight: 500;">Choose a plan to get started
                        </div>
                    </div>
                </div>

                <?php if (isset($plans) && $plans->count() > 0): ?>
                    <div style="margin-top: 24px;">
                        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px; color: #1e293b;">Available
                            Plans</h3>

                        <div style="display: grid; gap: 16px;">
                            <?php foreach ($plans as $plan): ?>
                                <div data-plan-id="<?= $plan->id ?>"
                                     style="padding: 20px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 12px; border: 2px solid #e2e8f0; transition: all 0.3s ease;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                        <div>
                                            <div style="font-weight: 700; font-size: 18px; color: #1e293b; margin-bottom: 4px;">
                                                <?= htmlspecialchars($plan->name) ?>
                                            </div>
                                            <?php if ($plan->description): ?>
                                                <div style="font-size: 14px; color: #64748b;">
                                                    <?= htmlspecialchars($plan->description) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-weight: 800; font-size: 24px; color: #667eea;">
                                                <?= htmlspecialchars($plan->currency) ?><?= number_format($plan->price, 2) ?>
                                            </div>
                                            <div style="font-size: 12px; color: #64748b; font-weight: 600;">
                                                per <?= htmlspecialchars($plan->getBillingPeriodLabel()) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($plan->features && is_array($plan->features) && count($plan->features) > 0): ?>
                                        <ul style="list-style: none; margin: 16px 0; padding: 0;">
                                            <?php foreach (array_slice($plan->features, 0, 3) as $feature): ?>
                                                <li style="padding: 6px 0; color: #334155; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                                    <span style="color: #10b981; font-weight: bold;">✓</span>
                                                    <?= htmlspecialchars($feature) ?>
                                                </li>
                                            <?php endforeach; ?>
                                            <?php if (count($plan->features) > 3): ?>
                                                <li style="padding: 6px 0; color: #64748b; font-size: 13px; font-style: italic;">
                                                    And <?= count($plan->features) - 3 ?> more features...
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <button class="btn btn-primary" style="width: 100%; margin-top: 12px;"
                                            onclick="quickSubscribe('<?= htmlspecialchars($plan->slug) ?>', this)">
                                        Subscribe to <?= htmlspecialchars($plan->name) ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="text-align: center; margin-top: 20px;">
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscription-plans"
                               style="color: #667eea; text-decoration: none; font-weight: 600; font-size: 15px; display: inline-flex; align-items: center; gap: 6px;">
                                View All Plans & Compare Features
                                <span style="font-size: 18px;">→</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>No Plans Available</h3>
                        <p>Please check back later for subscription options</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Email Preferences Card -->
        <div class="card">
            <h2>
                <span class="icon">✉️</span>
                Email Preferences
            </h2>

            <div class="subscription-status">
                <div class="status-icon <?= $subscriptionSummary['is_active'] ? 'active' : 'inactive' ?>">
                    <?= $subscriptionSummary['is_active'] ? '✓' : '✗' ?>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 20px; color: #1e293b;">
                        <?= $subscriptionSummary['is_active'] ? 'Subscribed' : 'Unsubscribed' ?>
                    </div>
                    <div style="color: #64748b; font-size: 15px; font-weight: 500;">Email notifications</div>
                </div>
            </div>

            <div class="info-row">
                <span class="info-label">Email Notifications</span>
                <span class="badge <?= $subscriptionSummary['email_notifications'] ? 'badge-success' : 'badge-danger' ?>">
                <?= $subscriptionSummary['email_notifications'] ? 'Enabled' : 'Disabled' ?>
            </span>
            </div>

            <div class="info-row">
                <span class="info-label">Frequency</span>
                <span class="info-value">
                <?= ucfirst(htmlspecialchars($subscriptionSummary['frequency'])) ?>
            </span>
            </div>

            <div class="info-row">
                <span class="info-label">Content Types</span>
                <span class="info-value">
                <?= empty($subscriptionSummary['content_types']) ? 'All' : count($subscriptionSummary['content_types']) ?>
            </span>
            </div>

            <div class="info-row">
                <span class="info-label">Categories</span>
                <span class="info-value">
                <?= empty($subscriptionSummary['category_preferences']) ? 'All' : count($subscriptionSummary['category_preferences']) ?>
            </span>
            </div>

            <div class="btn-group">
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/preferences"
                   class="btn btn-primary">
                    Manage Preferences
                </a>
            </div>
        </div>
    </div>

    <!-- Subscription History -->
    <div class="card">
        <h2>
            <span class="icon">📜</span>
            Subscription History
        </h2>

        <?php if ($subscriptionHistory->count() > 0): ?>
            <table class="history-table">
                <thead>
                <tr>
                    <th>Plan</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Price</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($subscriptionHistory as $sub): ?>
                    <tr>
                        <td style="font-weight: 600;">
                            <?= htmlspecialchars($sub->plan_name) ?>
                            <?php if ($sub->delivery_type): ?>
                                <span style="font-size: 12px; color: #64748b; display: block; margin-top: 4px;">
                                <?= $sub->isDigital() ? '💻 Digital' : '📦 Print' ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                        <span class="badge"
                              style="background: <?= $sub->type === 'paid' ? '#e0e7ff' : '#f3f4f6' ?>; color: <?= $sub->type === 'paid' ? '#3730a3' : '#374151' ?>;">
                            <?= ucfirst(htmlspecialchars($sub->type ?? 'standard')) ?>
                        </span>
                        </td>
                        <td>
                        <span class="badge badge-<?= $sub->status === 'active' ? 'success' : 'warning' ?>">
                            <?= ucfirst(htmlspecialchars($sub->status)) ?>
                        </span>
                        </td>
                        <td><?= $sub->start_date->format('M d, Y') ?></td>
                        <td>
                            <?= $sub->end_date ? $sub->end_date->format('M d, Y') : 'N/A' ?>
                        </td>
                        <td style="font-weight: 600;">
                            <?= htmlspecialchars($sub->currency) ?>
                            <?= number_format($sub->price, 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <p>No subscription history</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pause Delivery Modal -->
<div id="pauseDeliveryModal"
     style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); padding: 20px; overflow-y: auto;">
    <div style="max-width: 600px; margin: 40px auto; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="padding: 24px; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 24px;">Pause Delivery</h2>
                <button onclick="closePauseDeliveryModal()"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">×
                </button>
            </div>
        </div>

        <div style="padding: 24px;">
            <p style="color: #64748b; margin-bottom: 24px;">
                Pause your magazine deliveries temporarily. Your subscription will remain active and unused issues will
                be available when you resume.
            </p>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                    Pause Start Date
                </label>
                <input type="date" id="pauseStartDate"
                       min="<?= (new \DateTime())->format('Y-m-d') ?>"
                       style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                    Resume Date (Pause End)
                </label>
                <input type="date" id="pauseEndDate"
                       min="<?= (new \DateTime('+1 day'))->format('Y-m-d') ?>"
                       style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;">
                <p style="font-size: 13px; color: #64748b; margin-top: 8px;">
                    Maximum pause period: 90 days
                </p>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                    Reason (Optional)
                </label>
                <textarea id="pauseReason" rows="3"
                          placeholder="e.g., Holiday, Moving house..."
                          style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; gap: 12px;">
                <button onclick="closePauseDeliveryModal()"
                        style="flex: 1; padding: 12px; background: #e2e8f0; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Cancel
                </button>
                <button onclick="confirmPauseDelivery()" id="confirmPauseBtn"
                        style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Pause Delivery
                </button>
            </div>
        </div>

        <input type="hidden" id="pauseSubscriptionId">
    </div>
</div>

<!-- Renewal Modal -->
<div id="renewalModal"
     style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); padding: 20px; overflow-y: auto;">
    <div style="max-width: 600px; margin: 40px auto; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="padding: 24px; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 24px;">Renew Your Subscription</h2>
                <button onclick="closeRenewalModal()"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">×
                </button>
            </div>
        </div>

        <div style="padding: 24px;">
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                <div style="font-weight: 600; margin-bottom: 4px;">Current Plan</div>
                <div style="color: #64748b; font-size: 14px;" id="currentPlanName"></div>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 12px;">
                    Choose Renewal Type
                </label>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="border: 2px solid #e2e8f0; border-radius: 8px; padding: 16px; cursor: pointer; transition: all 0.2s;"
                           class="renewal-option" data-type="fixed">
                        <input type="radio" name="renewal_type" value="fixed" checked style="margin-right: 12px;">
                        <div>
                            <div style="font-weight: 600; margin-bottom: 4px;">Fixed Term</div>
                            <div style="font-size: 14px; color: #64748b;">Choose 1 or 2 year subscription</div>
                        </div>
                    </label>
                    <label style="border: 2px solid #e2e8f0; border-radius: 8px; padding: 16px; cursor: pointer; transition: all 0.2s;"
                           class="renewal-option" data-type="auto">
                        <input type="radio" name="renewal_type" value="auto" style="margin-right: 12px;">
                        <div>
                            <div style="font-weight: 600; margin-bottom: 4px;">Auto-Renewing</div>
                            <div style="font-size: 14px; color: #64748b;">Automatically renews, cancel anytime</div>
                        </div>
                    </label>
                </div>
            </div>

            <div id="addressSection" style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 12px;">
                    Delivery Address
                </label>
                <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 12px;"
                     id="currentAddress">
                    Loading address...
                </div>
                <button onclick="updateAddress()" class="btn btn-secondary" style="font-size: 14px; padding: 8px 16px;">
                    Update Address
                </button>
            </div>
        </div>

        <div style="padding: 24px; border-top: 1px solid #e2e8f0; display: flex; gap: 12px; justify-content: flex-end;">
            <button onclick="closeRenewalModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="processRenewal()" class="btn btn-primary" id="renewalSubmitBtn">
                Continue to Payment
            </button>
        </div>
    </div>
</div>

<div id="billingDateModal"
     style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); padding: 20px; overflow-y: auto;">
    <div style="max-width: 600px; margin: 40px auto; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="padding: 24px; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 24px;">Change Billing Date</h2>
                <button onclick="closeBillingDateModal()"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">×
                </button>
            </div>
        </div>

        <div style="padding: 24px;">
            <p style="color: #64748b; margin-bottom: 24px;">
                Select the day of the month you'd like to be charged. Your payment will be adjusted accordingly.
            </p>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 12px;">
                    Current Billing Date
                </label>
                <div style="background: #f8fafc; padding: 16px; border-radius: 8px;">
                    <span id="currentBillingDay" style="font-weight: 700; font-size: 18px;"></span> of each month
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 600; margin-bottom: 12px;">
                    New Billing Day
                </label>
                <select id="billingDaySelect"
                        style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;">
                    <?php for ($day = 1; $day <= 28; $day++): ?>
                        <option value="<?= $day ?>"><?= $day ?><?= $day == 1 ? 'st' : ($day == 2 ? 'nd' : ($day == 3 ? 'rd' : 'th')) ?>
                            of each month
                        </option>
                    <?php endfor; ?>
                </select>
                <p style="font-size: 13px; color: #64748b; margin-top: 8px;">
                    Note: We recommend choosing a day between 1-28 to avoid issues in shorter months
                </p>
            </div>

            <div id="prorationPreview"
                 style="display: none; background: #f0f4ff; padding: 16px; border-radius: 8px; margin-bottom: 24px; border-left: 4px solid #667eea;">
                <div style="font-weight: 600; margin-bottom: 8px;">Billing Adjustment</div>
                <div id="prorationDetails" style="font-size: 14px; color: #334155;"></div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button onclick="closeBillingDateModal()"
                        style="flex: 1; padding: 12px; background: #e2e8f0; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Cancel
                </button>
                <button onclick="confirmBillingDateChange()" id="confirmBillingBtn"
                        style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Update Billing Date
                </button>
            </div>
        </div>

        <input type="hidden" id="billingSubscriptionId">
    </div>
</div>
<script>
    let currentSubscriptionId = null;

    function openChangeBillingDateModal(subscriptionId, currentDay) {
        currentSubscriptionId = subscriptionId;
        document.getElementById('billingSubscriptionId').value = subscriptionId;
        document.getElementById('currentBillingDay').textContent = currentDay;
        document.getElementById('billingDaySelect').value = currentDay;
        document.getElementById('billingDateModal').style.display = 'block';
        document.body.style.overflow = 'hidden';

        // Preview on change
        document.getElementById('billingDaySelect').addEventListener('change', previewBillingChange);
    }

    function closeBillingDateModal() {
        document.getElementById('billingDateModal').style.display = 'none';
        document.body.style.overflow = 'auto';
        document.getElementById('prorationPreview').style.display = 'none';
        currentSubscriptionId = null;
    }

    async function previewBillingChange() {
        const dayOfMonth = document.getElementById('billingDaySelect').value;
        const subscriptionId = document.getElementById('billingSubscriptionId').value;

        try {
            const response = await fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/${subscriptionId}/preview-billing-change`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({day_of_month: dayOfMonth})
            });

            const result = await response.json();

            if (result.success && result.data) {
                const preview = document.getElementById('prorationPreview');
                const details = document.getElementById('prorationDetails');

                let message = `Your next billing date will be <strong>${result.data.new_billing_date}</strong>.<br>`;

                if (Math.abs(result.data.proration_amount) > 0.01) {
                    if (result.data.is_credit) {
                        message += `You'll receive a credit of <strong>$${Math.abs(result.data.proration_amount).toFixed(2)}</strong> for the unused days.`;
                    } else {
                        message += `You'll be charged an additional <strong>$${result.data.proration_amount.toFixed(2)}</strong> to align your billing cycle.`;
                    }
                } else {
                    message += 'No additional charges or credits will apply.';
                }

                details.innerHTML = message;
                preview.style.display = 'block';
            }
        } catch (error) {
            console.error('Error previewing billing change:', error);
        }
    }

    async function confirmBillingDateChange() {
        const button = document.getElementById('confirmBillingBtn');
        const dayOfMonth = document.getElementById('billingDaySelect').value;
        const subscriptionId = document.getElementById('billingSubscriptionId').value;

        button.disabled = true;
        button.textContent = 'Updating...';

        try {
            const response = await fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/${subscriptionId}/update-billing-date`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({day_of_month: dayOfMonth})
            });

            const result = await response.json();

            if (result.success) {
                showNotification('Billing date updated successfully', 'success');
                closeBillingDateModal();
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(result.message || 'Failed to update billing date', 'error');
                button.disabled = false;
                button.textContent = 'Update Billing Date';
            }
        } catch (error) {
            showNotification('An error occurred. Please try again.', 'error');
            button.disabled = false;
            button.textContent = 'Update Billing Date';
        }
    }

    // Notification Helper
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = 'position: fixed;         top: 20px;         right: 20px;         padding: 16px 24px;         border-radius: 12px;         font-weight: 600;         z-index: 10000;         animation: slideIn 0.3s ease;         box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);';
        if (type === 'success') {
            notification.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            notification.style.color = 'white';
        } else if (type === 'error') {
            notification.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            notification.style.color = 'white';
        }

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    function quickSubscribe(slug, button) {
        // Get plan ID from the button or nearby element
        // You'll need to add data-plan-id to the button in the PHP
        const planCard = button.closest('div[data-plan-id]');
        const planId = planCard ? planCard.dataset.planId : null;

        if (planId) {
            // Show modal with pre-selected plan
            if (typeof showSubscriptionModalWithPlan === 'function') {
                showSubscriptionModalWithPlan(slug, planId);
            } else {
                // Fallback to direct checkout page
                window.location.href = '/<?= \App\Framework\Support\SiteContext::slug() ?>/checkout?plan_slug=' + slug;
            }
        } else {
            // Fallback if plan ID not found
            window.location.href = '/<?= \App\Framework\Support\SiteContext::slug() ?>/checkout?plan_slug=' + slug;
        }
    }


    function cancelSubscription(subscriptionId) {
        const dialog = createConfirmDialog({
            title: 'Cancel Subscription',
            message: 'How would you like to cancel your subscription?',
            confirmText: 'Cancel Subscription',
            type: 'danger',
            showOptions: true,
            options: [
                {
                    label: 'Cancel at end of billing period',
                    value: true,
                    description: 'You\'ll keep access until the current period ends (Recommended)'
                },
                {
                    label: 'Cancel immediately',
                    value: false,
                    description: 'Your access ends right away'
                }
            ]
        });

        dialog.then(cancelAtPeriodEnd => {
            const button = event.target;
            button.disabled = true;
            button.classList.add('loading');
            const originalText = button.textContent;

            fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/${subscriptionId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    cancel_at_period_end: cancelAtPeriodEnd
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Subscription cancelled successfully', 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showNotification(data.message || 'Failed to cancel subscription', 'error');
                        button.disabled = false;
                        button.classList.remove('loading');
                        button.textContent = originalText;
                    }
                })
                .catch(error => {
                    showNotification('An error occurred. Please try again.', 'error');
                    console.error('Error:', error);
                    button.disabled = false;
                    button.classList.remove('loading');
                    button.textContent = originalText;
                });
        }).catch(() => {
            // User cancelled
        });
    }

    function reactivateSubscription(subscriptionId) {
        const dialog = createConfirmDialog({
            title: 'Reactivate Subscription',
            message: 'Reactivate your subscription? Billing will resume on the next scheduled date.',
            confirmText: 'Reactivate',
            type: 'success'
        });

        dialog.then(confirmed => {
            if (confirmed) {
                const button = event.target;
                button.disabled = true;
                button.classList.add('loading');
                const originalText = button.textContent;

                fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/${subscriptionId}/reactivate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('Subscription reactivated successfully', 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showNotification(data.message || 'Failed to reactivate subscription', 'error');
                            button.disabled = false;
                            button.classList.remove('loading');
                            button.textContent = originalText;
                        }
                    })
                    .catch(error => {
                        showNotification('An error occurred. Please try again.', 'error');
                        console.error('Error:', error);
                        button.disabled = false;
                        button.classList.remove('loading');
                        button.textContent = originalText;
                    });
            }
        }).catch(() => {
            // User cancelled
        });
    }

    // Confirmation Dialog Helper
    function createConfirmDialog(config) {
        return new Promise((resolve, reject) => {
            const backdrop = document.createElement('div');
            backdrop.className = 'dialog-backdrop';

            const container = document.createElement('div');
            container.className = 'dialog-container';

            const iconMap = {
                danger: '⚠️',
                warning: '⚡',
                info: 'ℹ️',
                success: '✓'
            };

            let selectedOption = config.showOptions && config.options ? config.options[0].value : null;

            let optionsHTML = '';
            if (config.showOptions && config.options) {
                optionsHTML = '<div class="dialog-options">';
                config.options.forEach((option, index) => {
                    optionsHTML += `
                    <label class="option-item ${index === 0 ? 'selected' : ''}" data-value="${option.value}">
                        <input type="radio" name="dialog-option" value="${option.value}" ${index === 0 ? 'checked' : ''}>
                        <div class="option-content">
                            <div class="option-label">${option.label}</div>
                            ${option.description ? `<div class="option-description">${option.description}</div>` : ''}
                        </div>
                        <div class="option-check">✓</div>
                    </label>
                `;
                });
                optionsHTML += '</div>';
            }

            container.innerHTML = `
            <div class="dialog-content">
                <div class="dialog-icon icon-${config.type}">
                    <span>${iconMap[config.type] || iconMap.info}</span>
                </div>
                <h2 class="dialog-title">${config.title}</h2>
                <p class="dialog-message">${config.message}</p>
${optionsHTML}
<div class="dialog-actions">
<button class="btn btn-cancel">${config.cancelText || 'Cancel'}</button>
<button class="btn btn-confirm btn-${config.type}">${config.confirmText || 'Confirm'}</button>
</div>
</div>
`;
            document.body.appendChild(backdrop);
            document.body.appendChild(container);

            // Handle option selection
            if (config.showOptions) {
                container.querySelectorAll('.option-item').forEach(item => {
                    item.addEventListener('click', () => {
                        container.querySelectorAll('.option-item').forEach(i => i.classList.remove('selected'));
                        item.classList.add('selected');
                        selectedOption = item.dataset.value === 'true' ? true : item.dataset.value === 'false' ? false : item.dataset.value;
                    });
                });
            }

            const closeDialog = () => {
                backdrop.remove();
                container.remove();
            };

            backdrop.addEventListener('click', () => {
                closeDialog();
                reject();
            });

            container.querySelector('.btn-cancel').addEventListener('click', () => {
                closeDialog();
                reject();
            });

            container.querySelector('.btn-confirm').addEventListener('click', () => {
                closeDialog();
                resolve(config.showOptions ? selectedOption : true);
            });
        });
    }

    function openRenewalModal() {
        const modal = document.getElementById('renewalModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';

        // Load current plan details
        <?php if (isset($activeSubscription)): ?>
        document.getElementById('currentPlanName').textContent = '<?= htmlspecialchars($activeSubscription->plan_name) ?> - <?= $activeSubscription->isPrint() ? "Print" : "Digital" ?>';

        // Load address if print subscription
        <?php if ($activeSubscription->isPrint()): ?>
        loadCurrentAddress();
        <?php else: ?>
        document.getElementById('addressSection').style.display = 'none';
        <?php endif; ?>
        <?php endif; ?>

        // Setup option selection
        document.querySelectorAll('.renewal-option').forEach(option => {
            option.addEventListener('click', function () {
                document.querySelectorAll('.renewal-option').forEach(opt => {
                    opt.style.borderColor = '#e2e8f0';
                    opt.style.background = 'white';
                });
                this.style.borderColor = '#667eea';
                this.style.background = '#f0f4ff';
                this.querySelector('input').checked = true;
            });
        });
    }

    function closeRenewalModal() {
        document.getElementById('renewalModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    async function loadCurrentAddress() {
        try {
            const response = await fetch('/<?= SiteContext::slug() ?>/api/member/current-address');
            const data = await response.json();

            if (data.success && data.address) {
                const addr = data.address;
                document.getElementById('currentAddress').innerHTML = `
                ${escapeHtml(addr.address_line_1)}<br>
                ${addr.address_line_2 ? escapeHtml(addr.address_line_2) + '<br>' : ''}
                ${escapeHtml(addr.city)}, ${escapeHtml(addr.postcode)}
            `;
            }
        } catch (error) {
            console.error('Error loading address:', error);
        }
    }

    function updateAddress() {
        window.location.href = '/<?= SiteContext::slug() ?>/member/addresses?return=/<?= SiteContext::slug() ?>/member/subscriptions';
    }

    async function processRenewal() {
        const renewalType = document.querySelector('input[name="renewal_type"]:checked').value;
        const submitBtn = document.getElementById('renewalSubmitBtn');

        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        try {
            <?php if (isset($activeSubscription)): ?>
            const planId = <?= $activeSubscription->plan_id ?>;
            const deliveryType = '<?= $activeSubscription->delivery_type ?>';

            // Redirect to checkout with renewal parameters
            window.location.href = `/<?= SiteContext::slug() ?>/checkout?plan_id=${planId}&renewal=true&type=${renewalType}&delivery=${deliveryType}`;
            <?php endif; ?>
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Continue to Payment';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function openPauseDeliveryModal(subscriptionId) {
        document.getElementById('pauseSubscriptionId').value = subscriptionId;
        document.getElementById('pauseDeliveryModal').style.display = 'block';
        document.body.style.overflow = 'hidden';

        // Set default dates
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('pauseStartDate').value = tomorrow.toISOString().split('T')[0];

        const twoWeeks = new Date();
        twoWeeks.setDate(twoWeeks.getDate() + 14);
        document.getElementById('pauseEndDate').value = twoWeeks.toISOString().split('T')[0];
    }

    function closePauseDeliveryModal() {
        document.getElementById('pauseDeliveryModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    async function confirmPauseDelivery() {
        const button = document.getElementById('confirmPauseBtn');
        const subscriptionId = document.getElementById('pauseSubscriptionId').value;
        const pauseStart = document.getElementById('pauseStartDate').value;
        const pauseEnd = document.getElementById('pauseEndDate').value;
        const reason = document.getElementById('pauseReason').value;

        if (!pauseStart || !pauseEnd) {
            showNotification('Please select both start and end dates', 'error');
            return;
        }

        button.disabled = true;
        button.textContent = 'Pausing...';

        try {
            const response = await fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/${subscriptionId}/pause-delivery`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    pause_start: pauseStart,
                    pause_end: pauseEnd,
                    reason: reason
                })
            });

            const result = await response.json();

            if (result.success) {
                showNotification('Delivery paused successfully', 'success');
                closePauseDeliveryModal();

                // Update the subscription display
                updateSubscriptionDisplay(result.subscription);

                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(result.message || 'Failed to pause delivery', 'error');
                button.disabled = false;
                button.textContent = 'Pause Delivery';
            }
        } catch (error) {
            showNotification('An error occurred. Please try again.', 'error');
            button.disabled = false;
            button.textContent = 'Pause Delivery';
        }
    }

    async function resumeDeliveryNow(subscriptionId) {
        if (!confirm('Resume delivery now? Your next issue will be delivered as scheduled.')) {
            return;
        }

        try {
            const response = await fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/${subscriptionId}/resume-delivery`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();

            if (result.success) {
                showNotification('Delivery resumed successfully', 'success');

                // Update the subscription display
                updateSubscriptionDisplay(result.subscription);

                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(result.message || 'Failed to resume delivery', 'error');
            }
        } catch (error) {
            showNotification('An error occurred. Please try again.', 'error');
        }
    }

    function updateSubscriptionDisplay(subscription) {
        // Remove existing pause warning if present
        const existingWarning = document.querySelector('.delivery-pause-warning');
        if (existingWarning) {
            existingWarning.remove();
        }

        if (subscription.delivery_paused) {
            // Add pause warning
            const pauseStart = new Date(subscription.delivery_pause_start);
            const pauseEnd = new Date(subscription.delivery_pause_end);
            const daysRemaining = Math.ceil((pauseEnd - new Date()) / (1000 * 60 * 60 * 24));

            const warningHtml = `
            <div class="delivery-pause-warning" style="background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 16px;
                border-radius: 8px;
                margin-bottom: 24px;
                display: flex;
                align-items: center;
                gap: 12px;">
                <span style="font-size: 24px;">⏸️</span>
                <div>
                    <div style="font-weight: 600; color: #92400e;">
                        Delivery Paused
                    </div>
                    <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                        Your deliveries are paused until ${pauseEnd.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })}
                        (${daysRemaining} days remaining)
                    </div>
                </div>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="resumeDeliveryNow(${subscription.id})">
                    ▶️ Resume Delivery Now
                </button>
            </div>
        `;

            // Insert after the subscription status
            const statusElement = document.querySelector('.subscription-status');
            if (statusElement) {
                statusElement.insertAdjacentHTML('afterend', warningHtml);
            }

            // Hide the pause button, show resume
            const pauseBtn = document.querySelector(`button[onclick*="openPauseDeliveryModal"]`);
            if (pauseBtn) {
                pauseBtn.style.display = 'none';
            }
        } else {
            // Show pause button, hide resume
            const pauseBtn = document.querySelector(`button[onclick*="openPauseDeliveryModal"]`);
            if (pauseBtn) {
                pauseBtn.style.display = 'inline-flex';
            }

            const resumeBtn = document.querySelector(`button[onclick*="resumeDeliveryNow"]`);
            if (resumeBtn && resumeBtn.parentElement) {
                resumeBtn.parentElement.remove();
            }
        }
    }
</script>

@include('components/subscription-modal', ['subscriptionModalData' => $subscriptionModalData])
</body>
</html>