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
    <div id="rewards-loading" style="text-align:center;padding:4rem;">
        <p style="color:#666;">Loading your rewards…</p>
    </div>
    <div id="rewards-content" style="display:none;"></div>

    <!-- FAQ is static, no reason to defer it -->
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
                Each reward has specific criteria listed on its card. Complete the required actions, and the reward
                will automatically appear in your "Available to Claim" section.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>Can I track my reward progress?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Yes! Check the "Earn These Rewards" section to see what you need to do to unlock them.
                Your progress can be tracked in your activity dashboard.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>How long does verification take?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Gift card rewards can take 7 to 90 days to verify. Travel-related reward verification
                periods are up to 90 days from the date of departure.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>Why wasn't my reward approved?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Rewards may not be approved if criteria weren't fully met, if there was a return or cancellation,
                or if terms weren't followed. Contact customer service if you believe there was an error.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>What happens if I miss the deadline to claim my reward?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Unclaimed rewards expire after their expiration date. Make sure to claim them before they expire.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>Can I work towards multiple rewards at once?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Yes! You can work towards and claim multiple rewards simultaneously with no limit.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
                <span>How do I contact customer service?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Contact us via the contact form on our website or email
                support@<?= htmlspecialchars($site->domain ?? 'example.com') ?>.
                We aim to respond within 24–48 hours.
            </div>
        </div>
    </div>

    <div class="secondary-nav-links">
        <a href="/<?= htmlspecialchars($site->slug) ?>" class="secondary-nav-link">← Back to Home</a>
        <a href="/<?= htmlspecialchars($site->slug) ?>/member/dashboard" class="secondary-nav-link">My Dashboard</a>
    </div>
</div>


<script>
    async function loadRewards() {
        try {
            const token = getMemberApiToken();
            const headers = token ? {Authorization: `Bearer ${token}`} : {};

            const res = await fetch(`/api/${SITE_SLUG}/member/rewards`, {headers});

            if (res.status === 401) {
                window.location.href = `/${SITE_SLUG}/member/login`;
                return;
            }

            const json = await res.json();
            if (!json.success) throw new Error('Failed to load rewards');

            renderRewards(json.data);
        } catch {
            document.getElementById('rewards-loading').innerHTML =
                `<p style="color:#dc3545;">Failed to load rewards. Please refresh.</p>`;
        }
    }

    function renderRewards({stats, unclaimed_rewards, claimed_rewards, top_rewards}) {
        const loading = document.getElementById('rewards-loading');
        const content = document.getElementById('rewards-content');

        const totalRewards = unclaimed_rewards.length + claimed_rewards.length;

        content.innerHTML = `
            ${renderSummary(stats)}
            ${unclaimed_rewards.length ? renderUnclaimedNotification(unclaimed_rewards.length) : ''}
            ${unclaimed_rewards.length ? renderUnclaimedSection(unclaimed_rewards) : ''}
            ${claimed_rewards.length ? renderClaimedSection(claimed_rewards) : ''}
            ${top_rewards.length ? renderTopRewardsSection(top_rewards) : ''}
            ${!totalRewards ? renderEmptyState() : ''}
        `;

        loading.style.display = 'none';
        content.style.display = 'block';
    }

    function renderSummary(stats) {
        return `
            <div class="rewards-summary">
                <h2 class="summary-title">Rewards Summary</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-number">${stats.active_count ?? 0}</div>
                        <div class="summary-label">Active Rewards</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-number">${stats.claimed_count ?? 0}</div>
                        <div class="summary-label">Rewards Claimed</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-number">
                            ${escHtml(stats.currency_symbol ?? '$')}${parseFloat(stats.gift_card_total ?? 0).toFixed(2)}
                        </div>
                        <div class="summary-label">Gift Card Total</div>
                    </div>
                </div>
            </div>`;
    }

    function renderUnclaimedNotification(count) {
        return `
            <div class="notifications">
                <div class="notification">
                    ⚠️ You have ${count} unclaimed reward${count !== 1 ? 's' : ''}. Claim them before they expire!
                </div>
            </div>`;
    }

    function renderRewardValue(reward_data) {
        if (!reward_data) return '';
        if (reward_data.voucher_code) {
            return `<div class="reward-value">
                <div class="reward-value-icon">🎟️</div>
                <div class="reward-value-content">
                    <div class="reward-value-label">Voucher Value</div>
                    <div class="reward-value-amount">${escHtml(reward_data.currency ?? '$')}${escHtml(String(reward_data.value ?? '0'))}</div>
                </div>
            </div>`;
        }
        if (reward_data.points) {
            return `<div class="reward-value">
                <div class="reward-value-icon">⭐</div>
                <div class="reward-value-content">
                    <div class="reward-value-label">Points</div>
                    <div class="reward-value-amount">${escHtml(String(reward_data.points))} pts</div>
                </div>
            </div>`;
        }
        if (reward_data.discount_value) {
            const prefix = reward_data.discount_type === 'percentage' ? '' : '$';
            const suffix = reward_data.discount_type === 'percentage' ? '%' : '';
            return `<div class="reward-value">
                <div class="reward-value-icon">💰</div>
                <div class="reward-value-content">
                    <div class="reward-value-label">Discount</div>
                    <div class="reward-value-amount">${prefix}${escHtml(String(reward_data.discount_value))}${suffix} OFF</div>
                </div>
            </div>`;
        }
        return '';
    }

    function renderExpiryWarning(reward) {
        if (reward.is_expired) {
            return `<div class="expiry-warning">⚠️ This reward has expired</div>`;
        }
        if (reward.expires_at) {
            const days = Math.floor((new Date(reward.expires_at) - new Date()) / 86400000);
            if (days <= 7) {
                return `<div class="expiry-warning">⏰ Expires in ${days} day${days !== 1 ? 's' : ''}</div>`;
            }
        }
        return '';
    }

    function renderUnclaimedSection(rewards) {
        const cards = rewards.map(r => `
            <div class="reward-card" id="reward-card-${r.id}">
                <div class="reward-header">
                    <div class="reward-type">${escHtml(r.definition.type.charAt(0).toUpperCase() + r.definition.type.slice(1))}</div>
                    <div class="reward-name">${escHtml(r.definition.name)}</div>
                    <div class="reward-description">${escHtml(r.definition.description)}</div>
                </div>
                <div class="reward-body">
                    ${renderExpiryWarning(r)}
                    ${renderRewardValue(r.reward_data)}
                    <div class="reward-meta">
                        <div class="reward-meta-item">
                            <span class="reward-meta-label">Status</span>
                            <span class="reward-status status-${escHtml(r.status)}">${escHtml(r.status.charAt(0).toUpperCase() + r.status.slice(1))}</span>
                        </div>
                        <div class="reward-meta-item">
                            <span class="reward-meta-label">Earned</span>
                            <span class="reward-meta-value">${formatDate(r.earned_at)}</span>
                        </div>
                        ${r.expires_at ? `<div class="reward-meta-item">
                            <span class="reward-meta-label">Expires</span>
                            <span class="reward-meta-value">${formatDate(r.expires_at)}</span>
                        </div>` : ''}
                    </div>
                    <div class="reward-actions">
                        <button class="btn btn-primary"
                                onclick="claimReward(${r.id}, this)"
                                ${r.is_expired ? 'disabled' : ''}>
                            ${r.is_expired ? 'Expired' : 'Claim Reward'}
                        </button>
                    </div>
                </div>
            </div>`).join('');

        return `
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">⭐ Available to Claim</h2>
                    <span class="section-badge" id="unclaimed-badge">${rewards.length}</span>
                </div>
                <div class="rewards-grid" id="unclaimed-grid">${cards}</div>
            </div>`;
    }

    function renderClaimedSection(rewards) {
        console.log('reewards', rewards)
        const cards = rewards.map(r => {
            const voucherHtml = r.reward_data?.voucher_code
                ? `<div class="voucher-code">
                    <div class="voucher-code-label">Your Voucher Code</div>
                    <div class="voucher-code-value">${escHtml(r.reward_data.voucher_code)}</div>
                    <button class="btn btn-secondary" style="margin-top:10px;"
                            onclick="copyVoucherCode(${r.id}, '${escHtml(r.reward_data.voucher_code)}')">
                        Copy Code
                    </button>
                   </div>`
                : renderRewardValue(r.reward_data);

            return `
                <div class="reward-card">
                    <div class="reward-header">
                        <div class="reward-type">${escHtml(r.definition.type.charAt(0).toUpperCase() + r.definition.type.slice(1))}</div>
                        <div class="reward-name">${escHtml(r.definition.name)}</div>
                        <div class="reward-description">${escHtml(r.definition.description)}</div>
                    </div>
                    <div class="reward-body">
                        ${voucherHtml}
                        <div class="reward-meta">
                            <div class="reward-meta-item">
                                <span class="reward-meta-label">Claimed On</span>
                                <span class="reward-meta-value">${formatDate(r.claimed_at)}</span>
                            </div>
                        </div>
                    </div>
                </div>`;
        }).join('');

        return `
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">✅ Claimed Rewards</h2>
                    <span class="section-badge">${rewards.length}</span>
                </div>
                <div class="rewards-grid">${cards}</div>
            </div>`;
    }

    function renderTopRewardsSection(definitions) {
        const cards = definitions.map(d => `
            <div class="reward-card locked">
                <div class="reward-header locked">
                    <div class="reward-type">${escHtml(d.type.charAt(0).toUpperCase() + d.type.slice(1))}</div>
                    <div class="reward-name">${escHtml(d.name)}</div>
                    <div class="reward-description">${escHtml(d.description)}</div>
                </div>
                <div class="reward-body">
                    <div class="reward-criteria">
                        <div class="criteria-label">How to Earn:</div>
                        ${(d.criteria ?? []).map(c => `<div class="criteria-item">• ${escHtml(c)}</div>`).join('')}
                    </div>
                    <div class="reward-meta">
                        <div class="reward-meta-item">
                            <span class="reward-meta-label">Status</span>
                            <span class="reward-status status-locked">Locked</span>
                        </div>
                    </div>
                </div>
            </div>`).join('');

        return `
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">🎯 Earn These Rewards</h2>
                    <span class="section-badge">${definitions.length} Available</span>
                </div>
                <div class="rewards-grid">${cards}</div>
            </div>`;
    }

    function renderEmptyState() {
        return `
            <div class="empty-state">
                <div class="empty-state-icon">🎁</div>
                <h2 class="empty-state-title">No Rewards Yet</h2>
                <p class="empty-state-text">Keep engaging with our content to earn rewards!<br>Check back soon for exciting offers.</p>
            </div>`;
    }

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
            const response = await fetch(`/api/<?= htmlspecialchars($site->slug) ?>/member/rewards/${rewardId}/claim`, {
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
            fetch(`/api/<?= htmlspecialchars($site->slug) ?>/member/rewards/${rewardId}/track/copy_code`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'}
            });

            alert('Voucher code copied to clipboard!');
        });
    }

    // Track view when reward modal/detail is opened
    function trackRewardView(rewardId) {
        fetch(`/api/<?= htmlspecialchars($site->slug) ?>/member/rewards/${rewardId}/track/view`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        });
    }

    function formatDate(str) {
        if (!str) return '';
        return new Date(str).toLocaleDateString('en-GB', {year: 'numeric', month: 'short', day: 'numeric'});
    }

    function escHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    document.addEventListener('DOMContentLoaded', loadRewards);

</script>
</body>
</html>