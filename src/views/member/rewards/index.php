<?php
/**
 * @var Member $member
 * @var Site $site
 * @var Collection $rewards
 * @var Collection $unclaimedRewards
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rewards - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f7fa;
        }

        .secondary-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .secondary-header-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .secondary-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header-subtitle {
            opacity: 0.9;
            font-size: 16px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Rewards Summary Section */
        .rewards-summary {
            margin-bottom: 40px;
        }

        .summary-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .summary-grid {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .summary-card {
            flex: 1;
            min-width: 200px;
            max-width: calc(50% - 10px);
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 2px solid #e5e7eb;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        .summary-number {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #667eea;
        }

        .summary-label {
            font-size: 16px;
            color: #666;
            font-weight: 500;
        }

        /* Notifications Section */
        .notifications {
            margin-bottom: 30px;
        }

        .notification {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 15px;
            color: #856404;
        }

        .notification.info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }

        /* Section Styling */
        .section {
            margin-bottom: 50px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 24px;
            color: #2c3e50;
            font-weight: 600;
        }

        .section-badge {
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        /* Rewards Grid */
        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .reward-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }

        .reward-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .reward-card.locked {
            opacity: 0.8;
        }

        .reward-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }

        .reward-header.locked {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }

        .reward-type {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .reward-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .reward-description {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.4;
        }

        .reward-body {
            padding: 20px;
        }

        .reward-value {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .reward-value-icon {
            font-size: 32px;
        }

        .reward-value-content {
            flex: 1;
        }

        .reward-value-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .reward-value-amount {
            font-size: 24px;
            font-weight: 700;
            color: #4CAF50;
        }

        .reward-criteria {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .criteria-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .criteria-item {
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
        }

        .criteria-item:last-child {
            margin-bottom: 0;
        }

        .progress-bar-container {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            transition: width 0.3s;
        }

        .reward-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }

        .reward-meta-item {
            display: flex;
            justify-content: space-between;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .reward-meta-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .reward-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-claimed {
            background: #d4edda;
            color: #155724;
        }

        .status-locked {
            background: #e9ecef;
            color: #6c757d;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .expiry-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 4px;
            font-size: 14px;
            color: #856404;
            margin-bottom: 15px;
        }

        .voucher-code {
            background: #f8f9fa;
            border: 2px dashed #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }

        .voucher-code-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .voucher-code-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }

        /* FAQ Section */
        .faq-section {
            margin-top: 50px;
        }

        .faq-item {
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .faq-question {
            padding: 20px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
        }

        .faq-question:hover {
            background: #f8f9fa;
        }

        .faq-toggle {
            font-size: 20px;
            transition: transform 0.3s;
        }

        .faq-question.active .faq-toggle {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s, padding 0.3s;
        }

        .faq-question.active + .faq-answer {
            padding: 20px;
            max-height: 500px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state-title {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .secondary-nav-links {
            display: flex;
            gap: 10px;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .secondary-nav-link {
            padding: 10px 20px;
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .secondary-nav-link:hover {
            background: #667eea;
            color: white;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 24px;
            }

            .rewards-grid {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                flex-direction: column;
            }

            .summary-card {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<div class="secondary-header">
    <div class="secondary-header-content">
        <h1>🎁 My Rewards</h1>
        <p class="header-subtitle">Track and claim your earned rewards</p>
    </div>
</div>

<div class="container">
    <!-- Rewards Summary -->
    <div class="rewards-summary">
        <h2 class="summary-title">Rewards Summary</h2>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-number"><?= $rewardStats['active_count'] ?></div>
                <div class="summary-label">Active Rewards</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?= $rewardStats['claimed_count'] ?></div>
                <div class="summary-label">Rewards Claimed</div>
            </div>
            <div class="summary-card">
                <div class="summary-number">
                    <?= htmlspecialchars($rewardStats['currency_symbol']) ?><?= number_format($rewardStats['gift_card_total'], 2) ?>
                </div>
                <div class="summary-label">Gift Card Total</div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <?php if ($unclaimedRewards->count() > 0): ?>
        <div class="notifications">
            <div class="notification">
                ⚠️ You have <?= $unclaimedRewards->count() ?> unclaimed
                reward<?= $unclaimedRewards->count() !== 1 ? 's' : '' ?>. Claim them before they expire!
            </div>
        </div>
    <?php endif; ?>

    <!-- User Rewards List (Available to Claim) -->
    <?php if ($unclaimedRewards->count() > 0): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">⭐ Available to Claim</h2>
                <span class="section-badge"><?= $unclaimedRewards->count() ?></span>
            </div>
            <div class="rewards-grid">
                <?php foreach ($unclaimedRewards as $reward): ?>
                    <?php $definition = $reward->rewardDefinition(true); ?>
                    <div class="reward-card">
                        <div class="reward-header">
                            <div class="reward-type"><?= htmlspecialchars(ucfirst($definition->reward_type ?? 'Reward')) ?></div>
                            <div class="reward-name"><?= htmlspecialchars($definition->name ?? 'Special Reward') ?></div>
                            <div class="reward-description"><?= htmlspecialchars($definition->description ?? '') ?></div>
                        </div>
                        <div class="reward-body">
                            <?php if ($reward->isExpired()): ?>
                                <div class="expiry-warning">
                                    ⚠️ This reward has expired
                                </div>
                            <?php elseif ($reward->expires_at): ?>
                                <?php
                                $daysUntilExpiry = floor((($reward->expires_at) - time()) / 86400);
                                if ($daysUntilExpiry <= 7):
                                    ?>
                                    <div class="expiry-warning">
                                        ⏰ Expires in <?= $daysUntilExpiry ?> day<?= $daysUntilExpiry !== 1 ? 's' : '' ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (isset($reward->reward_data['voucher_code'])): ?>
                                <div class="reward-value">
                                    <div class="reward-value-icon">🎟️</div>
                                    <div class="reward-value-content">
                                        <div class="reward-value-label">Voucher Value</div>
                                        <div class="reward-value-amount">
                                            <?= htmlspecialchars($reward->reward_data['currency'] ?? '$') ?><?= htmlspecialchars($reward->reward_data['value'] ?? '0') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif (isset($reward->reward_data['points'])): ?>
                                <div class="reward-value">
                                    <div class="reward-value-icon">⭐</div>
                                    <div class="reward-value-content">
                                        <div class="reward-value-label">Points</div>
                                        <div class="reward-value-amount">
                                            <?= htmlspecialchars($reward->reward_data['points']) ?> pts
                                        </div>
                                    </div>
                                </div>
                            <?php elseif (isset($reward->reward_data['discount_value'])): ?>
                                <div class="reward-value">
                                    <div class="reward-value-icon">💰</div>
                                    <div class="reward-value-content">
                                        <div class="reward-value-label">Discount</div>
                                        <div class="reward-value-amount">
                                            <?= $reward->reward_data['discount_type'] === 'percentage' ? '' : '$' ?>
                                            <?= htmlspecialchars($reward->reward_data['discount_value']) ?>
                                            <?= $reward->reward_data['discount_type'] === 'percentage' ? '%' : '' ?> OFF
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="reward-meta">
                                <div class="reward-meta-item">
                                    <span class="reward-meta-label">Status</span>
                                    <span class="reward-status status-<?= htmlspecialchars($reward->status) ?>">
                                        <?= htmlspecialchars(ucfirst($reward->status)) ?>
                                    </span>
                                </div>
                                <div class="reward-meta-item">
                                    <span class="reward-meta-label">Earned</span>
                                    <span class="reward-meta-value">
                                        <?= $reward->earned_at?->format('M j, Y') ?>
                                    </span>
                                </div>
                                <?php if ($reward->expires_at): ?>
                                    <div class="reward-meta-item">
                                        <span class="reward-meta-label">Expires</span>
                                        <span class="reward-meta-value">
                                            <?= $reward->expires_at->format('M j, Y') ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="reward-actions">
                                <button
                                        class="btn btn-primary"
                                        onclick="claimReward(<?= $reward->id ?>)"
                                        <?= $reward->isExpired() ? 'disabled' : '' ?>
                                >
                                    <?= $reward->isExpired() ? 'Expired' : 'Claim Reward' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Claimed Rewards -->
    <?php
    $claimedRewards = $rewards->filter(fn($r) => $r->isClaimed());
    ?>
    <?php if ($claimedRewards->count() > 0): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">✅ Claimed Rewards</h2>
                <span class="section-badge"><?= $claimedRewards->count() ?></span>
            </div>
            <div class="rewards-grid">
                <?php foreach ($claimedRewards as $reward): ?>
                    <?php $definition = $reward->rewardDefinition; ?>
                    <div class="reward-card">
                        <div class="reward-header">
                            <div class="reward-type"><?= htmlspecialchars(ucfirst($definition->reward_type ?? 'Reward')) ?></div>
                            <div class="reward-name"><?= htmlspecialchars($definition->name ?? 'Special Reward') ?></div>
                            <div class="reward-description"><?= htmlspecialchars($definition->description ?? '') ?></div>
                        </div>
                        <div class="reward-body">
                            <?php if (isset($reward->reward_data['voucher_code'])): ?>
                                <div class="voucher-code">
                                    <div class="voucher-code-label">Your Voucher Code</div>
                                    <div class="voucher-code-value"><?= htmlspecialchars($reward->reward_data['voucher_code']) ?></div>
                                </div>
                            <?php elseif (isset($reward->reward_data['points'])): ?>
                                <div class="reward-value">
                                    <div class="reward-value-icon">⭐</div>
                                    <div class="reward-value-content">
                                        <div class="reward-value-label">Points Added</div>
                                        <div class="reward-value-amount">
                                            <?= htmlspecialchars($reward->reward_data['points']) ?> pts
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="reward-meta">
                                <div class="reward-meta-item">
                                    <span class="reward-meta-label">Claimed On</span>
                                    <span class="reward-meta-value">
                                        <?= $reward->claimed_at?->format('M j, Y') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Top Rewards (Not Yet Available) -->
    <?php if ($topRewards->count() > 0): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">🎯 Earn These Rewards</h2>
                <span class="section-badge"><?= $topRewards->count() ?> Available</span>
            </div>
            <div class="rewards-grid">
                <?php foreach ($topRewards as $definition): ?>
                    <div class="reward-card locked">
                        <div class="reward-header locked">
                            <div class="reward-type"><?= htmlspecialchars(ucfirst($definition->reward_type)) ?></div>
                            <div class="reward-name"><?= htmlspecialchars($definition->name) ?></div>
                            <div class="reward-description"><?= htmlspecialchars($definition->description) ?></div>
                        </div>
                        <div class="reward-body">
                            <div class="reward-criteria">
                                <div class="criteria-label">How to Earn:</div>
                                <?php if (is_array($definition->criteria)): ?>
                                    <?php foreach ($definition->criteria as $criterion): ?>
                                        <div class="criteria-item">
                                            • <?= htmlspecialchars($definition->formatCriterion($criterion)) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="reward-meta">
                                <div class="reward-meta-item">
                                    <span class="reward-meta-label">Status</span>
                                    <span class="reward-status status-locked">Locked</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- FAQ Section -->
    <div class="faq-section">
        <div class="section-header">
            <h2 class="section-title">❓ Frequently Asked Questions</h2>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>What kind of rewards can I earn?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                You can earn various rewards including gift cards, discount vouchers, loyalty points, and exclusive
                access to content or products. Rewards are earned by completing specific actions like reading articles,
                making purchases, or engaging with our community.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>How do I earn a reward?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Each reward has specific criteria listed on its card. Complete the required actions (such as earning
                badges, making purchases, or engaging with content), and the reward will automatically appear in your
                "Available to Claim" section.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>Can I track my reward progress?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Yes! Check the "Earn These Rewards" section to see what rewards are available and what you need to do to
                unlock them. Your progress towards badges and other criteria can be tracked in your activity dashboard.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>How long does verification take?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Gift card rewards can take 7 to 90 days to verify depending on the return policy of the retailer.
                Travel-related reward verification periods are up to 90 days from the date of departure.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>Why wasn't my reward approved?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Rewards may not be approved if the qualifying criteria weren't fully met, if there was a return or
                cancellation, or if terms and conditions weren't followed. Contact customer service if you believe there
                was an error.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>What happens if I miss the deadline to request my reward?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Unclaimed rewards will expire after their expiration date. Make sure to claim your rewards before they
                expire to avoid losing them.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>Can I register for multiple rewards at once?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Yes! You can work towards and claim multiple rewards simultaneously. There's no limit to how many
                rewards you can earn.
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>How do I contact customer service?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                You can contact our customer service team through the contact form on our website, or by emailing
                support@<?= htmlspecialchars($site->domain ?? 'example.com') ?>. We aim to respond within 24-48 hours.
            </div>
        </div>
    </div>


    <?php if ($rewards->count() === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🎁</div>
            <h2 class="empty-state-title">No Rewards Yet</h2>
            <p class="empty-state-text">
                Keep engaging with our content to earn rewards!<br>
                Check back soon for exciting offers.
            </p>
        </div>
    <?php endif; ?>

    <div class="secondary-nav-links">
        <a href="/<?= htmlspecialchars($site->slug) ?>" class="secondary-nav-link">← Back to Home</a>
        <a href="/member/dashboard" class="secondary-nav-link">My Dashboard</a>
    </div>
</div>

<script>
    function toggleFaq(element) {
        element.classList.toggle('active');
    }

    async function claimReward(rewardId) {
        if (!confirm('Are you sure you want to claim this reward?')) {
            return;
        }

        const button = event.target;
        const originalText = button.textContent;
        button.textContent = 'Claiming...';
        button.disabled = true;

        try {
            const response = await fetch(`/<?= htmlspecialchars($site->slug) ?>/member/rewards/${rewardId}/claim`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.data && data.data.success) {
                alert(data.data.message || 'Reward claimed successfully!');
                window.location.reload();
            } else {
                throw new Error(data.data?.message || 'Failed to claim reward');
            }
        } catch (error) {
            alert(error.message || 'An error occurred while claiming the reward');
            button.textContent = originalText;
            button.disabled = false;
        }
    }

    // Update the existing copyVoucherCode or add if missing
    function copyVoucherCode(rewardId, code) {
        navigator.clipboard.writeText(code).then(() => {
            // Track the copy action
            fetch(`/<?= htmlspecialchars($site->slug) ?>/member/rewards/${rewardId}/track/copy_code`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'}
            });

            alert('Voucher code copied to clipboard!');
        });
    }

    // Track view when reward modal/detail is opened
    function trackRewardView(rewardId) {
        fetch(`/<?= htmlspecialchars($site->slug) ?>/member/rewards/${rewardId}/track/view`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        });
    }
</script>
</body>
</html>