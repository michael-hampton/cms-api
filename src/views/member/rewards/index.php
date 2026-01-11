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

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header h1 {
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 4px solid;
        }

        .stat-card.unclaimed {
            border-left-color: #ffc107;
        }

        .stat-card.claimed {
            border-left-color: #4CAF50;
        }

        .stat-card.expired {
            border-left-color: #dc3545;
        }

        .stat-card.total {
            border-left-color: #667eea;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section {
            margin-bottom: 40px;
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

        .reward-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            position: relative;
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

        .reward-meta-label {
            font-weight: 500;
        }

        .reward-meta-value {
            color: #2c3e50;
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

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        .reward-actions {
            margin-top: 15px;
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

        .btn-success {
            background: #4CAF50;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
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

        .empty-state-text {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
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

        .expiry-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 4px;
            font-size: 14px;
            color: #856404;
            margin-bottom: 15px;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .nav-link {
            padding: 10px 20px;
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-link:hover {
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

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="header">
    <div class="header-content">
        <h1>🎁 My Rewards</h1>
        <p class="header-subtitle">Track and claim your earned rewards</p>
    </div>
</div>

<div class="container">
    <?php
    $claimedCount = $rewards->filter(fn($r) => $r->status === 'claimed')->count();
    $expiredCount = $rewards->filter(fn($r) => $r->status === 'expired' || $r->isExpired())->count();
    $totalCount = $rewards->count();
    $unclaimedCount = $unclaimedRewards->count();
    ?>

    <div class="stats-grid">
        <div class="stat-card unclaimed">
            <div class="stat-number"><?= $unclaimedCount ?></div>
            <div class="stat-label">Unclaimed Rewards</div>
        </div>
        <div class="stat-card claimed">
            <div class="stat-number"><?= $claimedCount ?></div>
            <div class="stat-label">Claimed Rewards</div>
        </div>
        <div class="stat-card expired">
            <div class="stat-number"><?= $expiredCount ?></div>
            <div class="stat-label">Expired Rewards</div>
        </div>
        <div class="stat-card total">
            <div class="stat-number"><?= $totalCount ?></div>
            <div class="stat-label">Total Rewards</div>
        </div>
    </div>

    <?php if ($unclaimedRewards->count() > 0): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">⭐ Unclaimed Rewards</h2>
                <span class="section-badge"><?= $unclaimedRewards->count() ?> Available</span>
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

    <?php
    $claimedRewards = $rewards->filter(fn($r) => $r->status === 'claimed');

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
                                <?php if (isset($reward->reward_data['provider'])): ?>
                                    <div style="text-align: center; font-size: 14px; color: #666; margin-bottom: 15px;">
                                        Redeemable at:
                                        <strong><?= htmlspecialchars($reward->reward_data['provider']) ?></strong>
                                    </div>
                                <?php endif; ?>
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
                            <?php elseif (isset($reward->reward_data['discount_value'])): ?>
                                <div class="reward-value">
                                    <div class="reward-value-icon">💰</div>
                                    <div class="reward-value-content">
                                        <div class="reward-value-label">Your Discount</div>
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
                                    <span class="reward-status status-claimed">Claimed</span>
                                </div>
                                <div class="reward-meta-item">
                                    <span class="reward-meta-label">Claimed On</span>
                                    <span class="reward-meta-value">
                                            <?= $reward->claimed_at?->format('M j, Y') ?>
                                        </span>
                                </div>
                                <?php if ($reward->expires_at): ?>
                                    <div class="reward-meta-item">
                                        <span class="reward-meta-label">Valid Until</span>
                                        <span class="reward-meta-value">
                                                <?= $reward->expires_at->format('M j, Y') ?>
                                            </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

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

    <div class="nav-links">
        <a href="/<?= htmlspecialchars($site->slug) ?>" class="nav-link">← Back to Home</a>
        <a href="/member/dashboard" class="nav-link">My Dashboard</a>
    </div>
</div>

<script>
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
</script>
</body>
</html>