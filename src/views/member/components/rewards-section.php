<?php
// Create new file: src/views/member/components/rewards-section.php
/**
 * @var \App\Framework\Support\Collection $unclaimedRewards
 */

if (!isset($unclaimedRewards) || $unclaimedRewards->count() === 0) {
    return;
}
?>

<style>
    .rewards-section {
        background: white;
        border-radius: 1rem;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
        border: 2px solid #e5e7eb;
    }

    .rewards-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        border-radius: 50%;
        z-index: 0;
    }

    .rewards-content {
        position: relative;
        z-index: 1;
    }

    .rewards-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .rewards-icon {
        font-size: 2.5rem;
        animation: bounce 2s infinite;
    }

    @keyframes bounce {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-10px);
        }
    }

    .rewards-title {
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .rewards-count {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
        color: #667eea;
        border: 1px solid rgba(102, 126, 234, 0.2);
    }

    .rewards-grid {
        display: grid;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .reward-card {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        border: 2px solid rgba(102, 126, 234, 0.15);
        border-radius: 0.75rem;
        padding: 1.5rem;
        transition: all 0.3s ease;
    }

    .reward-card:hover {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.15);
    }

    .reward-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .reward-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1f2937;
        margin: 0 0 0.25rem 0;
    }

    .reward-type {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .reward-description {
        color: #4b5563;
        margin-bottom: 1rem;
        line-height: 1.5;
        font-size: 0.9375rem;
    }

    .reward-value-box {
        background: white;
        border: 2px solid rgba(102, 126, 234, 0.2);
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .reward-value-label {
        font-size: 0.8125rem;
        color: #6b7280;
        margin-bottom: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .reward-value {
        font-size: 1.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .reward-actions {
        display: flex;
        gap: 0.75rem;
    }

    .btn-claim {
        flex: 1;
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.9375rem;
    }

    .btn-claim:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
    }

    .btn-view-all {
        padding: 0.75rem 1.5rem;
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }

    .btn-view-all:hover {
        background: #667eea;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }

    @media (max-width: 768px) {
        .rewards-section {
            padding: 1.5rem;
        }

        .rewards-title {
            font-size: 1.5rem;
        }

        .reward-card {
            padding: 1.25rem;
        }
    }
</style>

<div class="rewards-section">
    <div class="rewards-content">
        <div class="rewards-header">
            <span class="rewards-icon">🎁</span>
            <div>
                <h2 class="rewards-title">Unclaimed Rewards</h2>
                <span class="rewards-count">
                    <?= $unclaimedRewards->count() ?> reward<?= $unclaimedRewards->count() > 1 ? 's' : '' ?> available
                </span>
            </div>
        </div>

        <div class="rewards-grid">
            <?php foreach ($unclaimedRewards as $reward): ?>
                <div class="reward-card">
                    <div class="reward-card-header">
                        <div>
                            <h3 class="reward-name">
                                <?= htmlspecialchars($reward->rewardDefinition->name) ?>
                            </h3>
                            <div class="reward-type">
                                <?= htmlspecialchars(ucfirst($reward->rewardDefinition->reward_type)) ?>
                            </div>
                        </div>
                    </div>

                    <p class="reward-description">
                        <?= htmlspecialchars($reward->rewardDefinition->description) ?>
                    </p>

                    <?php if ($reward->reward_data): ?>
                        <div class="reward-value-box">
                            <?php if (isset($reward->reward_data['voucher_code'])): ?>
                                <div class="reward-value-label">Voucher Value</div>
                                <div class="reward-value">
                                    <?= htmlspecialchars($reward->reward_data['currency']) ?>
                                    <?= number_format($reward->reward_data['value'], 2) ?>
                                </div>
                            <?php elseif (isset($reward->reward_data['discount_value'])): ?>
                                <div class="reward-value-label">Discount</div>
                                <div class="reward-value">
                                    <?= $reward->reward_data['discount_type'] === 'percentage' ? '' : '$' ?>
                                    <?= htmlspecialchars($reward->reward_data['discount_value']) ?>
                                    <?= $reward->reward_data['discount_type'] === 'percentage' ? '%' : '' ?> OFF
                                </div>
                            <?php elseif (isset($reward->reward_data['points'])): ?>
                                <div class="reward-value-label">Points</div>
                                <div class="reward-value">
                                    <?= htmlspecialchars($reward->reward_data['points']) ?> points
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="reward-actions">
                        <button class="btn-claim" onclick="claimReward(<?= $reward->id ?>)">
                            Claim Reward
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/rewards" class="btn-view-all">
            View All Rewards
        </a>
    </div>
</div>