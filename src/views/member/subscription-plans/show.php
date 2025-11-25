<?php
// views/member/subscription-plans/show.php
/**
 * @var \App\Models\Site $site
 * @var \App\Models\SubscriptionPlan $plan
 * @var \App\Models\Member|null $member
 * @var array $canSubscribe
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($plan->name) ?> - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
        }

        .plan-detail {
            background: white;
            border-radius: 12px;
            padding: 60px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .plan-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .plan-name {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .plan-description {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 30px;
        }

        .plan-price {
            font-size: 64px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .plan-price .currency {
            font-size: 32px;
            vertical-align: super;
        }

        .plan-period {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 40px;
        }

        .trial-info {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }

        .features-section {
            margin-bottom: 40px;
        }

        .features-section h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .features-list {
            list-style: none;
        }

        .features-list li {
            padding: 15px 0;
            border-bottom: 1px solid #ecf0f1;
            font-size: 16px;
        }

        .features-list li:before {
            content: "✓";
            color: #27ae60;
            font-weight: bold;
            margin-right: 15px;
            font-size: 20px;
        }

        .subscribe-form {
            margin-top: 40px;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 20px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
            cursor: not-allowed;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
<div class="container">
    <a href="/member/subscription-plans" class="back-link">← Back to Plans</a>

    <div class="plan-detail">
        <div class="plan-header">
            <div class="plan-name"><?= htmlspecialchars($plan->name) ?></div>

            <?php if ($plan->description): ?>
                <div class="plan-description">
                    <?= htmlspecialchars($plan->description) ?>
                </div>
            <?php endif; ?>

            <div class="plan-price">
                <span class="currency"><?= htmlspecialchars($plan->currency) ?></span>
                <?= number_format($plan->price, 2) ?>
            </div>

            <div class="plan-period">
                <?= htmlspecialchars($plan->getBillingPeriodLabel()) ?>
            </div>

            <?php if ($plan->trial_days > 0): ?>
                <div class="trial-info">
                    <strong>🎉 <?= $plan->trial_days ?> Day Free Trial</strong>
                    <div style="margin-top: 10px; font-size: 14px;">
                        Try it risk-free for <?= $plan->trial_days ?> days
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($plan->features): ?>
            <div class="features-section">
                <h3>What's Included</h3>
                <ul class="features-list">
                    <?php foreach ($plan->features as $feature): ?>
                        <li><?= htmlspecialchars($feature) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="subscribe-form">
            <?php if (!$member): ?>
                <div class="alert alert-info">
                    Please <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/login">login</a> or <a
                            href="/member/register">register</a> to subscribe to this plan.
                </div>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/login" class="btn btn-primary">
                    Login to Subscribe
                </a>
            <?php elseif (!$canSubscribe['can_subscribe']): ?>
                <div class="alert alert-warning">
                    <?= htmlspecialchars($canSubscribe['reason']) ?>
                </div>
                <button class="btn btn-secondary" disabled>
                    Cannot Subscribe
                </button>
            <?php else: ?>
                <form method="POST"
                      action="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscription-plans/<?= htmlspecialchars($plan->slug) ?>/subscribe">
                    <button type="submit" class="btn btn-primary">
                        Subscribe to <?= htmlspecialchars($plan->name) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>