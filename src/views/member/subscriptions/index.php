<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 * @var \App\Models\Subscription|null $activeSubscription
 * @var \App\Framework\Support\Collection $subscriptionHistory
 * @var array $subscriptionSummary
 * @var \App\Framework\Support\Collection $plans
 */
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
                <div class="subscription-status">
                    <div class="status-icon active">✓</div>
                    <div>
                        <div style="font-weight: 700; font-size: 20px; color: #1e293b;">
                            <?= htmlspecialchars($activeSubscription->plan_name) ?>
                        </div>
                        <div style="color: #64748b; font-size: 15px; font-weight: 500;">Active subscription</div>
                    </div>
                </div>

                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="badge badge-success">Active</span>
                </div>

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

                <div class="btn-group">
                    <button class="btn btn-danger" onclick="cancelSubscription(<?= $activeSubscription->id ?>)">
                        Cancel Subscription
                    </button>
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
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Price</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($subscriptionHistory as $sub): ?>
                    <tr>
                        <td style="font-weight: 600;"><?= htmlspecialchars($sub->plan_name) ?></td>
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
<script>
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

</script>

@include('components/subscription-modal', ['subscriptionModalData' => $subscriptionModalData])
</body>
</html>