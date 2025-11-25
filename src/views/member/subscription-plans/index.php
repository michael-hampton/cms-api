<?php
// views/member/subscription-plans/index.php
/**
 * @var \App\Models\Site $site
 * @var \App\Framework\Support\Collection $plans
 * @var \App\Models\Member|null $member
 * @var \App\Models\Subscription|null $currentSubscription
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans - <?= htmlspecialchars($site->name) ?></title>
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

        .header {
            text-align: center;
            padding: 60px 40px;
            background: white;
            border-radius: 24px;
            margin-bottom: 48px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .header h1 {
            font-size: 52px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .header p {
            font-size: 20px;
            color: #64748b;
            font-weight: 500;
        }

        .current-plan-alert {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 24px 32px;
            border-radius: 16px;
            margin-bottom: 32px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 32px;
            margin-bottom: 48px;
        }

        .plan-card {
            background: white;
            border-radius: 24px;
            padding: 44px 36px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            border: 3px solid transparent;
        }

        .plan-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }

        .plan-card.featured {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            transform: scale(1.05);
            box-shadow: 0 20px 50px rgba(102, 126, 234, 0.4);
        }

        .plan-card.featured:hover {
            transform: translateY(-12px) scale(1.08);
            box-shadow: 0 25px 60px rgba(102, 126, 234, 0.5);
        }

        .featured-badge {
            position: absolute;
            top: -16px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 8px 24px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.5);
        }

        .plan-name {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 12px;
            color: inherit;
            letter-spacing: -0.5px;
        }

        .trial-badge {
            display: inline-block;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            margin-left: 12px;
            letter-spacing: 0.3px;
        }

        .plan-card.featured .trial-badge {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
        }

        .plan-description {
            color: #64748b;
            margin-bottom: 32px;
            min-height: 60px;
            font-size: 16px;
            line-height: 1.6;
        }

        .plan-card.featured .plan-description {
            color: rgba(255, 255, 255, 0.9);
        }

        .plan-price {
            font-size: 56px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
            letter-spacing: -2px;
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 4px;
        }

        .plan-card.featured .plan-price {
            color: white;
        }

        .plan-price .currency {
            font-size: 28px;
            opacity: 0.7;
        }

        .plan-period {
            color: #64748b;
            margin-bottom: 32px;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
        }

        .plan-card.featured .plan-period {
            color: rgba(255, 255, 255, 0.85);
        }

        .plan-features {
            list-style: none;
            margin-bottom: 32px;
            padding: 24px 0;
            border-top: 2px solid #f1f5f9;
            border-bottom: 2px solid #f1f5f9;
        }

        .plan-card.featured .plan-features {
            border-color: rgba(255, 255, 255, 0.2);
        }

        .plan-features li {
            padding: 12px 0;
            color: #334155;
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .plan-card.featured .plan-features li {
            color: rgba(255, 255, 255, 0.95);
        }

        .plan-features li:before {
            content: "✓";
            color: #10b981;
            font-weight: bold;
            font-size: 20px;
            flex-shrink: 0;
        }

        .plan-card.featured .plan-features li:before {
            color: #4ade80;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 18px 32px;
            border: none;
            border-radius: 14px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
            letter-spacing: 0.3px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
        }

        .btn-primary.featured {
            background: white;
            color: #667eea;
        }

        .btn-primary.featured:hover {
            background: #f8fafc;
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #64748b;
            cursor: not-allowed;
        }

        .current-plan-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 16px;
            display: inline-block;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn:disabled,
        .btn.loading {
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
            width: 24px;
            height: 24px;
            top: 50%;
            left: 50%;
            margin-left: -12px;
            margin-top: -12px;
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

        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 24px;
            opacity: 0.4;
        }

        .empty-state p {
            font-size: 20px;
            color: #64748b;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 38px;
            }

            .plan-card.featured {
                transform: scale(1);
            }

            .plan-price {
                font-size: 48px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🚀 Choose Your Plan</h1>
        <p>Select the perfect plan and get started in seconds</p>
    </div>

    <?php if ($currentSubscription): ?>
        <div class="current-plan-alert">
            ✓ Current Plan: <?= htmlspecialchars($currentSubscription->plan_name) ?>
        </div>
    <?php endif; ?>

    <div class="plans-grid">
        <?php foreach ($plans as $plan): ?>
            <div class="plan-card <?= $plan->is_featured ? 'featured' : '' ?>">
                <?php if ($plan->is_featured): ?>
                    <div class="featured-badge">⭐ Most Popular</div>
                <?php endif; ?>

                <?php if ($currentSubscription && $currentSubscription->plan_id === $plan->id): ?>
                    <div class="current-plan-badge">✓ Your Plan</div>
                <?php endif; ?>

                <div class="plan-name">
                    <?= htmlspecialchars($plan->name) ?>
                    <?php if ($plan->trial_days > 0): ?>
                        <span class="trial-badge">🎉 <?= $plan->trial_days ?>d Trial</span>
                    <?php endif; ?>
                </div>

                <div class="plan-description">
                    <?= htmlspecialchars($plan->description ?? 'Get access to all premium features') ?>
                </div>

                <div class="plan-price">
                    <span class="currency"><?= htmlspecialchars($plan->currency) ?></span>
                    <span><?= number_format($plan->price, 2) ?></span>
                </div>

                <div class="plan-period">
                    per <?= htmlspecialchars($plan->getBillingPeriodLabel()) ?>
                </div>

                <?php if ($plan->features): ?>
                    <ul class="plan-features">
                        <?php foreach ($plan->features as $feature): ?>
                            <li><?= htmlspecialchars($feature) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($currentSubscription && $currentSubscription->plan_id === $plan->id): ?>
                    <button class="btn btn-secondary" disabled>
                        Current Plan
                    </button>
                <?php elseif ($currentSubscription): ?>
                    <button class="btn btn-secondary" disabled>
                        Already Subscribed
                    </button>
                <?php else: ?>
                    <button
                            class="btn btn-primary <?= $plan->is_featured ? 'featured' : '' ?>"
                            onclick="subscribeToPlan('<?= htmlspecialchars($plan->slug) ?>', this)">
                        Subscribe Now
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$plans->count()): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📦</div>
            <p>No subscription plans available at this time.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    function subscribeToPlan(slug, button) {
        if (!button) return;

        button.disabled = true;
        button.classList.add('loading');
        const originalText = button.textContent;

        fetch('/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscription-plans/' + slug + '/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.textContent = '✓ Subscribed!';
                    button.classList.remove('loading');
                    button.style.background = 'linear-gradient(135deg, #10b981, #059669)';

                    setTimeout(() => {
                        window.location.href = '/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions';
                    }, 1200);
                } else {
                    alert(data.message || 'Failed to subscribe. Please try again.');
                    button.disabled = false;
                    button.classList.remove('loading');
                    button.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Subscription error:', error);
                alert('An error occurred. Please try again.');
                button.disabled = false;
                button.classList.remove('loading');
                button.textContent = originalText;
            });
    }
</script>
</body>
</html>