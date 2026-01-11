<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 * @var array $upgradeInfo
 * @var int $subscriptionId
 */

use App\Framework\Support\SiteContext;

$canUpgrade = $upgradeInfo['can_upgrade'] ?? false;
$currentSub = $upgradeInfo['current_subscription'] ?? null;
$options = $upgradeInfo['options'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade to Insider - <?= htmlspecialchars($site->name) ?></title>
    <style>
        /* Keep existing styles from document and add: */

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
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

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .loader {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .upgrade-cta {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 24px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 32px;
        }

        .upgrade-cta h2 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .comparison-grid {
                grid-template-columns: 1fr;
            }

            .benefits-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container" style="margin-top: 40px;">
    <a href="/<?= SiteContext::slug() ?>/member/subscriptions" class="back-link">
        ← Back to My Subscriptions
    </a>

    <div class="header">
        <h1>🔓 Upgrade to Insider Access</h1>
        <p>Add premium digital content to your existing print subscription</p>
    </div>

    <?php if (!$canUpgrade): ?>
        <div class="upgrade-benefits">
            <h2 style="font-size: 24px; margin-bottom: 16px; color: #1e293b;">
                <?= htmlspecialchars($upgradeInfo['reason'] ?? 'Upgrade not available') ?>
            </h2>
            <p style="color: #64748b; margin-bottom: 24px;">
                This subscription is not eligible for upgrade at this time.
            </p>
            <a href="/<?= SiteContext::slug() ?>/member/subscriptions" class="btn btn-secondary">
                Return to Subscriptions
            </a>
        </div>
    <?php else: ?>

        <?php if (!empty($options)): ?>
            <?php $upgradePlan = $options[0]; ?>

            <div class="upgrade-cta">
                <h2>✨ Unlock Premium Content</h2>
                <p style="font-size: 16px; opacity: 0.95;">
                    <?php if (count($upgradePlan['premium_access']) === 1): ?>
                        Get instant access to <?= htmlspecialchars($upgradePlan['premium_access'][0]['identifier']) ?>
                    <?php else: ?>
                        Get instant access to <?= count($upgradePlan['premium_access']) ?> premium features
                    <?php endif; ?>
                </p>
            </div>

            <!-- Show what premium access they're getting -->
            <div class="upgrade-benefits">
                <h2>What You'll Unlock:</h2>
                <div class="benefits-grid">
                    <?php foreach ($upgradePlan['premium_access'] as $access): ?>
                        <div class="benefit-card">
                            <div class="benefit-icon">
                                <?php
                                $icons = [
                                        'newsletter' => '📧',
                                        'archive' => '📚',
                                        'video' => '🎥',
                                        'podcast' => '🎙️',
                                ];
                                echo $icons[$access['type']] ?? '⭐';
                                ?>
                            </div>
                            <div class="benefit-title">
                                <?= htmlspecialchars(ucwords(str_replace('-', ' ', $access['identifier']))) ?>
                            </div>
                            <div class="benefit-description">
                                <?= htmlspecialchars($access['type']) ?> access
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="upgrade-benefits">
                <h2 style="font-size: 24px; margin-bottom: 16px;">No upgrade options available</h2>
                <p style="color: #64748b;">There are currently no upgrade options for your subscription.</p>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
    let isProcessing = false;

    async function processUpgrade(upgradePlanId) {
        if (isProcessing) return;

        const button = document.getElementById('upgradeBtn');
        const originalText = button.innerHTML;

        isProcessing = true;
        button.disabled = true;
        button.innerHTML = '<span class="loader"></span> Processing...';

        try {
            // First, preview the upgrade to get exact pricing
            const previewResponse = await fetch(
                '/<?= SiteContext::slug() ?>/member/subscriptions/<?= $subscriptionId ?>/upgrade/preview',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        upgrade_plan_id: upgradePlanId
                    })
                }
            );

            const previewData = await previewResponse.json();

            if (!previewData.success) {
                throw new Error(previewData.message || 'Failed to preview upgrade');
            }

            // Confirm with user
            const confirmMessage = `Confirm upgrade?\n\nYou'll be charged $${previewData.data.pricing.immediate_charge.toFixed(2)} today.\n\nYou'll get immediate access to all Insider content.`;

            if (!confirm(confirmMessage)) {
                isProcessing = false;
                button.disabled = false;
                button.innerHTML = originalText;
                return;
            }

            // Process the upgrade
            const upgradeResponse = await fetch(
                '/<?= SiteContext::slug() ?>/member/subscriptions/<?= $subscriptionId ?>/upgrade',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        upgrade_plan_id: upgradePlanId,
                        payment_method_id: null // Will use default payment method
                    })
                }
            );

            const upgradeData = await upgradeResponse.json();

            if (upgradeData.success) {
                // Check if we need to confirm payment
                if (upgradeData.data.client_secret) {
                    // Handle 3D Secure or payment confirmation if needed
                    showNotification('Payment confirmation required...', 'info');
                    // You would integrate Stripe here for payment confirmation
                    // For now, we'll assume payment succeeds
                }

                // Success!
                showNotification('🎉 Upgrade successful! You now have Insider access.', 'success');

                setTimeout(() => {
                    window.location.href = '/<?= SiteContext::slug() ?>/member/subscriptions';
                }, 2000);

            } else {
                throw new Error(upgradeData.message || 'Upgrade failed');
            }

        } catch (error) {
            console.error('Upgrade error:', error);
            showNotification(error.message || 'An error occurred. Please try again.', 'error');

            isProcessing = false;
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 12px;
        font-weight: 600;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        max-width: 400px;
    `;

        const colors = {
            success: 'linear-gradient(135deg, #10b981, #059669)',
            error: 'linear-gradient(135deg, #ef4444, #dc2626)',
            info: 'linear-gradient(135deg, #3b82f6, #2563eb)',
            warning: 'linear-gradient(135deg, #f59e0b, #d97706)'
        };

        notification.style.background = colors[type] || colors.info;
        notification.style.color = 'white';
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
</script>

<style>
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

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
</style>
</body>
</html>