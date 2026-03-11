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

            @include('member.subscriptions.components._warning-banners', ['activeSubscription' => $activeSubscription])

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

                @include('member.subscriptions.components._renewal-buttons', ['activeSubscription' => $activeSubscription])

                @include('member.subscriptions.components._upgrade-section', ['activeSubscription' => $activeSubscription])

                @include('member.subscriptions.components._subscription-info-rows', ['activeSubscription' => $activeSubscription])

                <?php if ($activeSubscription->isPrint()): ?>
                    @include('member.subscriptions.components._delivery-management', ['activeSubscription' => $activeSubscription])

                <?php endif; ?>

                <?php if ($activeSubscription->isDigital() && $activeSubscription->hasValidDownload()): ?>
                    @include('member.subscriptions.components._digital-access', ['activeSubscription' => $activeSubscription])
                <?php endif; ?>

                @include('member.subscriptions.components._action-buttons', ['activeSubscription' => $activeSubscription, 'isCancelling' => $isCancelling])
            <?php else: ?>
                @include('member.subscriptions.components._no-subscription-state', ['plans' => $plans]))
            <?php endif; ?>
        </div>

        <!-- Email Preferences Card -->
        @include('member.subscriptions.components._email-preferences-card', ['subscriptionSummary' =>
        $subscriptionSummary])
    </div>

    <!-- Subscription History -->
    @include('member.subscriptions.components._subscription-history', ['subscriptionHistory' => $subscriptionHistory])
</div>

<!-- Pause Delivery Modal -->
@include('member.subscriptions.components._pause-delivery-modal')

<!-- Renewal Modal -->
@include('member.subscriptions.components._renewal-modal')

@include('member.subscriptions.components._billing-date-modal')
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
            const response = await fetch('/api/<?= SiteContext::slug() ?>/member/current-address');
            const responseData = await response.json();
            const data = responseData?.data;

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

    async function updateAutoRenew(subscriptionId, enabled) {
        try {
            const response = await fetch(`/<?= SiteContext::slug() ?>/member/subscriptions/${subscriptionId}/auto-renew`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({auto_renew: enabled, consent_given: enabled})
            });
            const result = await response.json();
            if (result.success) {
                showNotification(enabled ? 'Auto-renewal enabled' : 'Auto-renewal disabled', 'success');
            } else {
                showNotification(result.message || 'Failed to update', 'error');
                // Revert checkbox
                document.getElementById('auto-renew-toggle').checked = !enabled;
            }
        } catch {
            showNotification('An error occurred', 'error');
            document.getElementById('auto-renew-toggle').checked = !enabled;
        }
    }
</script>

@include('components/subscription-modal', ['subscriptionModalData' => $subscriptionModalData])
</body>
</html>