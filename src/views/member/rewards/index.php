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
            <div class="faq-question" onclick="RewardsPage.toggleFaq(this)">
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
            <div class="faq-question" onclick="RewardsPage.toggleFaq(this)">
                <span>How do I earn a reward?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Each reward has specific criteria listed on its card. Complete the required actions, and the reward
                will automatically appear in your "Available to Claim" section.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="RewardsPage.toggleFaq(this)">
                <span>Can I track my reward progress?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Yes! Check the "Earn These Rewards" section to see what you need to do to unlock them.
                Your progress can be tracked in your activity dashboard.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="RewardsPage.toggleFaq(this)">
                <span>How long does verification take?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Gift card rewards can take 7 to 90 days to verify. Travel-related reward verification
                periods are up to 90 days from the date of departure.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="RewardsPage.toggleFaq(this)">
                <span>Why wasn't my reward approved?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Rewards may not be approved if criteria weren't fully met, if there was a return or cancellation,
                or if terms weren't followed. Contact customer service if you believe there was an error.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="RewardsPage.toggleFaq(this)">
                <span>What happens if I miss the deadline to claim my reward?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Unclaimed rewards expire after their expiration date. Make sure to claim them before they expire.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="RewardsPage.toggleFaq(this)">
                <span>Can I work towards multiple rewards at once?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Yes! You can work towards and claim multiple rewards simultaneously with no limit.
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question" onclick="RewardsPage.toggleFaq(this)">
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
    class RewardsStore {
        constructor() {
            this.state = {
                rewards: null,
                loading: false,
                error: null,
                claimingIds: new Set(),
            };
            this.listeners = [];
        }

        subscribe(listener) {
            this.listeners.push(listener);
            listener(this.state);
        }

        setState(patch) {
            this.state = {
                ...this.state,
                ...patch,
            };

            this.listeners.forEach(listener => listener(this.state));
        }

        setClaiming(id, claiming) {
            const claimingIds = new Set(this.state.claimingIds);

            if (claiming) {
                claimingIds.add(id);
            } else {
                claimingIds.delete(id);
            }

            this.setState({claimingIds});
        }
    }

    class RewardsPage {
        static toggleFaq(element) {
            element.classList.toggle('active');
        }

        constructor() {
            this.store = new RewardsStore();
            this.content = document.getElementById('rewards-content');
            this.loadingNode = document.getElementById('rewards-loading');
            this.store.subscribe(state => this.render(state));
        }

        async load() {
            this.store.setState({loading: true, error: null});

            try {
                const json = await api(`/api/${SITE_SLUG}/member/rewards`);
                this.store.setState({
                    rewards: json.data,
                    loading: false,
                });
            } catch (_) {
                this.store.setState({
                    loading: false,
                    error: 'Failed to load rewards. Please refresh.',
                });
                UI.render(this.loadingNode, [
                    UI.el('p', {style: {color: '#dc3545'}}, ['Failed to load rewards. Please refresh.']),
                ]);
            }
        }

        render(state) {
            if (state.loading || !state.rewards) {
                return;
            }

            if (state.error) {
                UI.render(this.loadingNode, [
                    UI.el('p', {style: {color: '#dc3545'}}, [state.error]),
                ]);
                return;
            }

            const {stats, unclaimed_rewards, claimed_rewards, top_rewards} = state.rewards;

            UI.render(this.content, [
                this._summary(stats),
                unclaimed_rewards.length ? this._notification(unclaimed_rewards.length) : null,
                unclaimed_rewards.length ? this._section('⭐ Available to Claim', unclaimed_rewards,
                    r => this._unclaimedCard(r), 'unclaimed-grid') : null,
                claimed_rewards.length ? this._section('✅ Claimed Rewards', claimed_rewards,
                    r => this._claimedCard(r)) : null,
                top_rewards.length ? this._section('🎯 Earn These Rewards', top_rewards,
                    r => this._lockedCard(r)) : null,
                !unclaimed_rewards.length && !claimed_rewards.length ? UI.emptyState({
                    icon: '🎁', title: 'No Rewards Yet',
                    body: 'Keep engaging with our content to earn rewards!',
                }) : null,
            ]);
            this.loadingNode.style.display = 'none';
            this.content.style.display = 'block';
        }

        _summary(stats) {
            const cards = [
                {n: stats.active_count ?? 0, label: 'Active Rewards'},
                {n: stats.claimed_count ?? 0, label: 'Rewards Claimed'},
                {
                    n: `${stats.currency_symbol ?? '$'}${parseFloat(stats.gift_card_total ?? 0).toFixed(2)}`,
                    label: 'Gift Card Total'
                },
            ];
            return UI.el('div', {className: 'rewards-summary'}, [
                UI.el('h2', {className: 'summary-title'}, ['Rewards Summary']),
                UI.el('div', {className: 'summary-grid'}, cards.map(c =>
                    UI.el('div', {className: 'summary-card'}, [
                        UI.el('div', {className: 'summary-number'}, [String(c.n)]),
                        UI.el('div', {className: 'summary-label'}, [c.label]),
                    ])
                )),
            ]);
        }

        _notification(count) {
            return UI.el('div', {className: 'notifications'}, [
                UI.el('div', {className: 'notification'}, [
                    `⚠️ You have ${count} unclaimed reward${count !== 1 ? 's' : ''}. Claim them before they expire!`,
                ]),
            ]);
        }

        _section(title, items, cardFn, gridId) {
            const grid = UI.el('div', {className: 'rewards-grid', ...(gridId ? {id: gridId} : {})});
            items.forEach(item => grid.appendChild(cardFn(item)));
            return UI.el('div', {className: 'section'}, [
                UI.el('div', {className: 'section-header'}, [
                    UI.el('h2', {className: 'section-title'}, [title]),
                    UI.el('span', {className: 'section-badge'}, [String(items.length)]),
                ]),
                grid,
            ]);
        }

        _rewardHeader(def, locked = false) {
            return UI.el('div', {className: `reward-header${locked ? ' locked' : ''}`}, [
                UI.el('div', {className: 'reward-type'}, [
                    def.type.charAt(0).toUpperCase() + def.type.slice(1),
                ]),
                UI.el('div', {className: 'reward-name'}, [def.name]),
                UI.el('div', {className: 'reward-description'}, [def.description]),
            ]);
        }

        _valueBlock(data) {
            if (!data) return null;
            if (data.voucher_code) return this._valRow('🎟️', 'Voucher Value', `${data.currency ?? '$'}${data.value ?? 0}`);
            if (data.points) return this._valRow('⭐', 'Points', `${data.points} pts`);
            if (data.discount_value) {
                const prefix = data.discount_type === 'percentage' ? '' : '$';
                const suffix = data.discount_type === 'percentage' ? '%' : '';
                return this._valRow('💰', 'Discount', `${prefix}${data.discount_value}${suffix} OFF`);
            }
            return null;
        }

        _valRow(icon, label, amount) {
            return UI.el('div', {className: 'reward-value'}, [
                UI.el('div', {className: 'reward-value-icon'}, [icon]),
                UI.el('div', {className: 'reward-value-content'}, [
                    UI.el('div', {className: 'reward-value-label'}, [label]),
                    UI.el('div', {className: 'reward-value-amount'}, [amount]),
                ]),
            ]);
        }

        _expiry(r) {
            if (r.is_expired) return UI.el('div', {className: 'expiry-warning'}, ['⚠️ This reward has expired']);
            if (r.expires_at) {
                const days = Math.floor((new Date(r.expires_at) - new Date()) / 86400000);
                if (days <= 7) return UI.el('div', {className: 'expiry-warning'},
                    [`⏰ Expires in ${days} day${days !== 1 ? 's' : ''}`]);
            }
            return null;
        }

        _unclaimedCard(r) {
            const claimBtn = UI.el('button', {
                className: 'btn btn-primary',
                ...(r.is_expired || this.store.state.claimingIds.has(r.id) ? {disabled: true} : {}),
            }, [r.is_expired ? 'Expired' : (this.store.state.claimingIds.has(r.id) ? 'Claiming…' : 'Claim Reward')]);
            claimBtn.addEventListener('click', () => this._claim(r.id, claimBtn));

            return UI.el('div', {className: 'reward-card', id: `reward-card-${r.id}`}, [
                this._rewardHeader(r.definition),
                UI.el('div', {className: 'reward-body'}, [
                    this._expiry(r),
                    this._valueBlock(r.reward_data),
                    this._metaBlock([
                        ['Status', UI.el('span', {className: `reward-status status-${r.status}`},
                            [r.status.charAt(0).toUpperCase() + r.status.slice(1)])],
                        ['Earned', UI.formatDate(r.earned_at)],
                        r.expires_at ? ['Expires', UI.formatDate(r.expires_at)] : null,
                    ]),
                    UI.el('div', {className: 'reward-actions'}, [claimBtn]),
                ]),
            ]);
        }

        _claimedCard(r) {
            const voucherEl = r.reward_data?.voucher_code ? this._voucherBlock(r) : this._valueBlock(r.reward_data);
            return UI.el('div', {className: 'reward-card'}, [
                this._rewardHeader(r.definition),
                UI.el('div', {className: 'reward-body'}, [
                    voucherEl,
                    this._metaBlock([['Claimed On', UI.formatDate(r.claimed_at)]]),
                ]),
            ]);
        }

        _voucherBlock(r) {
            const copyBtn = UI.el('button', {
                className: 'btn btn-secondary',
                style: {marginTop: '10px'}
            }, ['Copy Code']);
            copyBtn.addEventListener('click', () => {
                navigator.clipboard.writeText(r.reward_data.voucher_code).then(() => {
                    fetch(`/api/${SITE_SLUG}/member/rewards/${r.id}/track/copy_code`, {method: 'POST'});
                    UI.toast('Voucher code copied!', 'success');
                });
            });
            return UI.el('div', {className: 'voucher-code'}, [
                UI.el('div', {className: 'voucher-code-label'}, ['Your Voucher Code']),
                UI.el('div', {className: 'voucher-code-value'}, [r.reward_data.voucher_code]),
                copyBtn,
            ]);
        }

        _lockedCard(d) {
            return UI.el('div', {className: 'reward-card locked'}, [
                this._rewardHeader(d, true),
                UI.el('div', {className: 'reward-body'}, [
                    UI.el('div', {className: 'reward-criteria'}, [
                        UI.el('div', {className: 'criteria-label'}, ['How to Earn:']),
                        ...(d.criteria ?? []).map(c => UI.el('div', {className: 'criteria-item'}, [`• ${c}`])),
                    ]),
                    this._metaBlock([['Status',
                        UI.el('span', {className: 'reward-status status-locked'}, ['Locked'])]]),
                ]),
            ]);
        }

        _metaBlock(rows) {
            return UI.el('div', {className: 'reward-meta'}, rows.filter(Boolean).map(([label, value]) =>
                UI.el('div', {className: 'reward-meta-item'}, [
                    UI.el('span', {className: 'reward-meta-label'}, [label]),
                    value instanceof Node ? value : UI.el('span', {className: 'reward-meta-value'}, [String(value)]),
                ])
            ));
        }

        async _claim(rewardId, btn) {
            if (!confirm('Claim this reward?')) return;
            this.store.setClaiming(rewardId, true);
            try {
                const res = await api(`/api/${SITE_SLUG}/member/rewards/${rewardId}/claim`, {method: 'POST'});
                if (res.data?.success) {
                    UI.toast(res.data.message || 'Reward claimed!', 'success');
                    await this.load();
                } else throw new Error(res.data?.message);
            } catch (e) {
                UI.toast(e.message || 'Failed to claim reward.', 'error');
            } finally {
                this.store.setClaiming(rewardId, false);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => new RewardsPage().load());

</script>
</body>
</html>
