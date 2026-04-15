<?php use App\Framework\Support\SiteContext; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - <?= htmlspecialchars($site->name ?? 'Site') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .verification-banner {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid var(--warning-color);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .verification-banner h2 {
            color: #92400e;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .verification-banner p {
            color: #78350f;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .verification-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-resend {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: white;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-resend:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3);
        }

        .btn-resend:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .welcome-section {
            background: white;
            border-radius: 1rem;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .welcome-section h1 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .message {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 2px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .dashboard-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 2px solid transparent;
            position: relative;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .dashboard-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .dashboard-card.disabled::after {
            content: '🔒 Verify Email to Unlock';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .dashboard-card.disabled:hover::after {
            opacity: 1;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
        }

        .card-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .card-icon.orders {
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
        }

        .card-icon.newsletters {
            background: linear-gradient(135deg, #10b98120 0%, #059f6920 100%);
        }

        .card-icon.subscriptions {
            background: linear-gradient(135deg, #f59e0b20 0%, #d9770620 100%);
        }

        .card-icon.addresses {
            background: linear-gradient(135deg, #3b82f620 0%, #2563eb20 100%);
        }

        .card-icon.comments {
            background: linear-gradient(135deg, #8b5cf620 0%, #7c3aed20 100%);
        }

        .card-icon.settings {
            background: linear-gradient(135deg, #6b728020 0%, #4b556320 100%);
        }

        .card-content h3 {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .card-content p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-top: 0.5rem;
        }

        .card-arrow {
            color: var(--primary-color);
            font-size: 1.25rem;
            transition: transform 0.2s ease;
        }

        .dashboard-card:hover .card-arrow {
            transform: translateX(4px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .limited-access-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .limited-access-section h2 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .limited-access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .info-card {
            background: var(--bg-light);
            border-radius: 0.75rem;
            padding: 1.5rem;
            border: 2px solid var(--border-color);
        }

        .info-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-section h1 {
                font-size: 1.5rem;
            }

            .verification-banner {
                padding: 1.5rem;
            }

            .verification-actions {
                flex-direction: column;
            }

            .btn-resend {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
@include('member._header')

<div class="container" id="dashboard-root">
    @include('member/components/dashboard-banner')
    <div id="dashboard-loading" style="text-align:center;padding:4rem;">
        <p style="color:var(--text-secondary);">Loading your dashboard…</p>
    </div>
    <div id="dashboard-content" style="display:none;"></div>
    <div id="gift-modal-container"></div>

    @include('member/components/recommended-section')
    @include('member/components/trending-section')
    @include('member/components/gifted-articles-section')
    @include('member/components/newsletter-preferences')

    @include('member/components/rewards-section')

    @include('member/components/recommended-products-section', ['recommendedProducts' => $recommendedProducts])

    @include('member/components/subscription-listing', ['groupedSubscriptions' => $groupedSubscriptions])

    @include('member/components/back-to-top')
</div>

<script>
    const SITE_SLUG = '<?= htmlspecialchars(SiteContext::slug()) ?>';

    async function loadDashboard() {
        try {
            const res = await fetch(`/${SITE_SLUG}/api/member/dashboard`);
            if (res.status === 401) {
                window.location.href = `/${SITE_SLUG}/member/login`;
                return;
            }
            const json = await res.json();
            if (!json.success) throw new Error(json.message ?? 'Failed to load');
            renderDashboard(json.data);
        } catch (e) {
            document.getElementById('dashboard-loading').innerHTML =
                `<p style="color:var(--danger-color);">Failed to load dashboard. Please refresh.</p>`;
        }
    }

    function renderDashboard(data) {
        const {member, stats, recent_orders, all_subscriptions} = data;
        const root = document.getElementById('dashboard-content');

        const verified = member.email_verified_at !== null;

        root.innerHTML = verified
            ? renderVerifiedDashboard(data)
            : renderUnverifiedDashboard(member, stats);

        document.getElementById('dashboard-loading').style.display = 'none';
        root.style.display = 'block';

        // Re-attach gift modal listener
        document.getElementById('giftModal')?.addEventListener('click', e => {
            if (e.target.id === 'giftModal') closeGiftModal();
        });
    }

    function renderVerifiedDashboard({
                                         member, stats, recent_orders, all_subscriptions,
                                         badges, progress, activity_trends, recent_activities,
                                         recommended_pages, recommended_products, trending_pages,
                                         trending_conversations, unclaimed_rewards, gifted_articles,
                                         grouped_subscriptions
                                     }) {

        const navCards = [
            {
                href: 'orders',
                icon: '🛍️',
                cls: 'orders',
                title: 'My Orders',
                desc: 'View and track your order history and current shipments.'
            },
            {
                href: 'newsletters',
                icon: '📧',
                cls: 'newsletters',
                title: 'Newsletters',
                desc: 'Manage your newsletter subscriptions and preferences.'
            },
            {
                href: 'subscriptions',
                icon: '⭐',
                cls: 'subscriptions',
                title: 'Subscriptions',
                desc: 'View and manage your active subscriptions and membership plans.'
            },
            {
                href: 'addresses',
                icon: '📍',
                cls: 'addresses',
                title: 'Addresses',
                desc: 'Manage your shipping and billing addresses.'
            },
            {
                href: 'comments',
                icon: '💬',
                cls: 'comments',
                title: 'Comments',
                desc: 'View and manage your comments across the site.'
            },
            {
                href: 'account-details',
                icon: '👤',
                cls: '',
                title: 'Account Details',
                desc: 'View and update your personal information and account status.'
            },
            {
                href: 'settings',
                icon: '⚙️',
                cls: 'settings',
                title: 'Security Settings',
                desc: 'Update your password and security preferences.'
            },
            {
                href: 'reading-history',
                icon: '📚',
                cls: '',
                title: 'Reading History',
                desc: "View pages you've read and track your reading progress."
            },
            {
                href: 'liked-pages',
                icon: '❤️',
                cls: '',
                title: 'Liked Pages',
                desc: 'Access your collection of liked pages and content.'
            },
            {
                href: 'wishlist',
                icon: '🛍️',
                cls: 'orders',
                title: 'My Favorites',
                desc: 'View your saved favorite items.'
            },
            {
                href: 'consent',
                icon: '🔒',
                cls: 'orders',
                title: 'Privacy & Consent',
                desc: 'Control how your personal data is used.'
            },
            {
                href: 'activity',
                icon: '🏆',
                cls: 'orders',
                title: 'Activity & Achievements',
                desc: 'Track your engagement and earn badges.'
            },
        ].map(c => `
        <a href="/${SITE_SLUG}/member/${c.href}" class="dashboard-card">
            <div class="card-header">
                <div class="card-icon ${c.cls}">${c.icon}</div>
                <div class="card-arrow">→</div>
            </div>
            <div class="card-content">
                <h3>${c.title}</h3>
                <p>${c.desc}</p>
            </div>
        </a>`).join('');

        const statsHtml = `
        <div class="stats-grid">
            ${[
            ['orders', 'Total Orders'],
            ['newsletters', 'Newsletters'],
            ['subscriptions', 'Active Subscriptions'],
            ['comments', 'Comments Posted'],
            ['pages_read', 'Pages Read'],
            ['likes', 'Pages Liked'],
        ].map(([key, label]) => `
                <div class="stat-card">
                    <div class="stat-number">${stats[key] ?? 0}</div>
                    <div class="stat-label">${label}</div>
                </div>`).join('')}
        </div>`;

        const ordersRows = (recent_orders ?? []).map(o => `
        <tr style="border-bottom:1px solid var(--border-color);">
            <td style="padding:.75rem;">${o.created_at}</td>
            <td style="padding:.75rem;font-weight:600;">#${escHtml(o.order_number)}</td>
            <td style="padding:.75rem;">${o.one_time_subscription_id ? '📋 Subscription' : '🛍️ Order'}</td>
            <td style="padding:.75rem;font-weight:600;">${escHtml(o.currency)} ${parseFloat(o.total).toFixed(2)}</td>
            <td style="padding:.75rem;">${statusBadge(o.status)}</td>
            <td style="padding:.75rem;"><a href="/${SITE_SLUG}/member/orders/${o.id}" style="color:var(--primary-color);text-decoration:none;font-weight:600;">View →</a></td>
        </tr>`).join('');

        const subsRows = (all_subscriptions ?? []).map(s => `
        <tr style="border-bottom:1px solid var(--border-color);">
            <td style="padding:.75rem;">${s.created_at}</td>
            <td style="padding:.75rem;">${escHtml(s.plan_name ?? '')}</td>
            <td style="padding:.75rem;">${statusBadge(s.status)}</td>
            <td style="padding:.75rem;"><a href="/${SITE_SLUG}/member/subscriptions" style="color:var(--primary-color);text-decoration:none;font-weight:600;">Manage →</a></td>
        </tr>`).join('');

        const activitySection = (recent_orders?.length > 0 || all_subscriptions?.length > 0) ? `
        <div style="background:white;border-radius:1rem;padding:2rem;box-shadow:var(--shadow);margin-bottom:2rem;">
            <div style="display:flex;gap:1rem;margin-bottom:1.5rem;border-bottom:2px solid var(--border-color);">
                <button class="tab-btn active" onclick="switchTab('orders')" id="ordersTab"
                    style="padding:1rem;background:none;border:none;font-weight:600;cursor:pointer;border-bottom:3px solid var(--primary-color);margin-bottom:-2px;">
                    Orders (${recent_orders?.length ?? 0})
                </button>
                <button class="tab-btn" onclick="switchTab('subscriptions')" id="subscriptionsTab"
                    style="padding:1rem;background:none;border:none;font-weight:600;cursor:pointer;color:var(--text-secondary);">
                    Subscriptions (${all_subscriptions?.length ?? 0})
                </button>
            </div>
            <div id="ordersContent" style="overflow-x:auto;">
                ${ordersRows.length ? `<table style="width:100%;border-collapse:collapse;"><thead><tr style="background:var(--bg-light);">
                    <th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">Date</th>
                    <th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">Order #</th>
                    <th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">Type</th>
                    <th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">Total</th>
                    <th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">Status</th>
                    <th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">Action</th>
                    </tr></thead><tbody>${ordersRows}</tbody></table>`
            : '<div style="text-align:center;padding:2rem;color:var(--text-secondary);">No orders yet</div>'}
            </div>
            <div id="subscriptionsContent" style="overflow-x:auto;display:none;">
                ${subsRows.length ? `<table style="width:100%;border-collapse:collapse;"><thead><tr style="background:var(--bg-light);">
                    <th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">Date</th>
                    <th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">Plan</th>
                    <th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">Status</th>
                    <th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">Action</th>
                    </tr></thead><tbody>${subsRows}</tbody></table>`
            : '<div style="text-align:center;padding:2rem;color:var(--text-secondary);">No subscriptions yet</div>'}
            </div>
        </div>` : '';

        return `
        <div class="welcome-section">
            <h1>Welcome back, ${escHtml(member.first_name ?? 'Member')}!</h1>
            <p>Manage your account, track your orders, and explore exclusive content.</p>
        </div>
        <h2 class="section-title">Quick Access</h2>
        <div class="dashboard-grid">${navCards}</div>
        <h2 class="section-title">Recent Activity</h2>
        <div class="dashboard-grid">${activitySection}</div>
        <h2 class="section-title">Your Activity</h2>
        ${statsHtml}
        <div id="giftModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;">
            <div style="background:white;border-radius:12px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;position:relative;">
                <div style="padding:30px;">
                    <button onclick="closeGiftModal()"
                        style="position:absolute;top:15px;right:15px;background:none;border:none;font-size:28px;cursor:pointer;color:#666;">&times;</button>
                    <h2 style="margin-bottom:20px;color:#2c3e50;">🎁 Gift This Article</h2>
                    <div id="giftModalContent"></div>
                </div>
            </div>
        </div>`;
    }

    function renderUnverifiedDashboard(member, stats) {
        return `
        <div class="verification-banner">
            <h2><span>⚠️</span> Email Verification Required</h2>
            <p>Welcome! Please verify your email address to unlock your full account.
               We've sent a verification link to <strong>${escHtml(member.email)}</strong>.</p>
            <div class="verification-actions">
                <button class="btn-resend" id="resendBtn" onclick="resendVerification()">
                    <span>📧</span> Resend Verification Email
                </button>
            </div>
        </div>
        <div class="limited-access-section">
            <h2>Your Account Overview</h2>
            <div class="limited-access-grid">
                <div class="info-card">
                    <h3><span>👤</span> Profile Information</h3>
                    <p><strong>Name:</strong> ${escHtml(member.first_name + ' ' + member.last_name)}<br>
                       <strong>Email:</strong> ${escHtml(member.email)}<br>
                       <strong>Member Since:</strong> ${member.created_at}</p>
                </div>
            </div>
        </div>`;
    }

    function statusBadge(status) {
        const colours = {
            completed: ['#d1fae5', '#065f46'],
            active: ['#d1fae5', '#065f46'],
            pending: ['#fef3c7', '#92400e'],
            expired: ['#fee2e2', '#991b1b'],
        };
        const [bg, fg] = colours[status] ?? ['#f3f4f6', '#4b5563'];
        return `<span style="padding:.375rem .75rem;border-radius:.5rem;font-size:.875rem;font-weight:600;background:${bg};color:${fg};">${escHtml(status)}</span>`;
    }

    function escHtml(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function switchTab(tab) {
        document.getElementById('ordersContent').style.display = tab === 'orders' ? 'block' : 'none';
        document.getElementById('subscriptionsContent').style.display = tab === 'subscriptions' ? 'block' : 'none';
        ['orders', 'subscriptions'].forEach(t => {
            const el = document.getElementById(t + 'Tab');
            el.style.borderBottom = t === tab ? '3px solid var(--primary-color)' : 'none';
            el.style.color = t === tab ? 'var(--text-primary)' : 'var(--text-secondary)';
        });
    }

    async function claimReward(rewardId) {
        try {
            const res = await fetch(`/${SITE_SLUG}/member/rewards/${rewardId}/claim`, {
                method: 'POST', headers: {'Content-Type': 'application/json'}
            });
            const result = await res.json();
            alert(result.message);
            if (result.success) window.location.reload();
        } catch {
            alert('An error occurred. Please try again.');
        }
    }

    async function resendVerification() {
        const btn = document.getElementById('resendBtn');
        btn.disabled = true;
        btn.innerHTML = '<span>⏳</span> Sending...';
        try {
            const res = await fetch(`/${SITE_SLUG}/member/resend-verification`, {
                method: 'POST', headers: {'Content-Type': 'application/json'}
            });
            const result = await res.json();
            btn.innerHTML = result.success ? '<span>✓</span> Email Sent!' : '<span>📧</span> Resend Verification Email';
            btn.disabled = !result.success;
            if (!result.success) alert(result.message || 'Failed to send email.');
        } catch {
            alert('An error occurred.');
            btn.innerHTML = '<span>📧</span> Resend Verification Email';
            btn.disabled = false;
        }
    }

    let currentGiftPage = null;

    async function openGiftModal(pageSlug, pageTitle) {
        const modal = document.getElementById('giftModal');
        const content = document.getElementById('giftModalContent');
        content.innerHTML = '<div style="text-align:center;padding:40px;"><p>Loading...</p></div>';
        modal.style.display = 'flex';
        try {
            const res = await fetch(`/${SITE_SLUG}/member/gift-modal/${pageSlug}`);
            const data = await res.json();
            if (data.success) {
                currentGiftPage = data.data.page;
                renderGiftModalContent(data.data, pageTitle, content);
            } else {
                content.innerHTML = `<div style="color:#721c24;">${data.message || 'Failed to load gift form'}</div>
                <button onclick="closeGiftModal()" class="btn btn-secondary" style="width:100%;margin-top:1rem;">Close</button>`;
            }
        } catch {
            content.innerHTML = `<div style="color:#721c24;">An error occurred.</div>
            <button onclick="closeGiftModal()" class="btn btn-secondary" style="width:100%;margin-top:1rem;">Close</button>`;
        }
    }

    function renderGiftModalContent({allowance}, pageTitle, container) {
        const limitHtml = !allowance.can_gift
            ? `<div style="background:#f8d7da;border-left:4px solid #dc3545;padding:15px;border-radius:4px;margin-bottom:20px;color:#721c24;">
            <strong>Gift limit reached!</strong> You've used all ${allowance.annual_limit} of your annual article gifts.</div>`
            : allowance.remaining_gifts <= 2
                ? `<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:15px;border-radius:4px;margin-bottom:20px;color:#856404;">
            <strong>Almost there!</strong> You have ${allowance.remaining_gifts} gift${allowance.remaining_gifts !== 1 ? 's' : ''} remaining this year.</div>`
                : `<div style="background:#e8f5e9;padding:15px;border-radius:4px;margin-bottom:20px;">
            You have <strong>${allowance.remaining_gifts}</strong> gifts remaining out of ${allowance.annual_limit} this year.</div>`;

        container.innerHTML = `
        <div style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px;">
            <div style="font-weight:600;color:#2c3e50;margin-bottom:5px;">${escHtml(pageTitle)}</div>
            <div style="font-size:14px;color:#666;">Share this article with someone special</div>
        </div>
        ${limitHtml}
        <div id="giftModalMessages"></div>
        ${allowance.can_gift ? `
        <form id="modalGiftForm">
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:5px;font-weight:500;color:#555;">Recipient's Email Address *</label>
                <input type="email" id="modal_recipient_email" required
                    style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box;"
                    placeholder="friend@example.com">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:5px;font-weight:500;color:#555;">Personal Message (Optional)</label>
                <textarea id="modal_personal_message" maxlength="500"
                    style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box;resize:vertical;min-height:100px;font-family:inherit;"
                    placeholder="Add a personal note..."></textarea>
                <div style="font-size:12px;color:#666;margin-top:5px;"><span id="modal_charCount">0</span>/500</div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Send Gift</button>
                <button type="button" onclick="closeGiftModal()" class="btn btn-secondary" style="flex:1;">Cancel</button>
            </div>
        </form>` : `<button onclick="closeGiftModal()" class="btn btn-secondary" style="width:100%;">Close</button>`}`;

        if (allowance.can_gift) {
            const textarea = document.getElementById('modal_personal_message');
            const charCount = document.getElementById('modal_charCount');
            textarea.addEventListener('input', () => {
                charCount.textContent = textarea.value.length;
            });
            document.getElementById('modalGiftForm').addEventListener('submit', handleModalGiftSubmit);
        }
    }

    async function handleModalGiftSubmit(e) {
        e.preventDefault();
        const email = document.getElementById('modal_recipient_email').value.trim();
        const message = document.getElementById('modal_personal_message').value.trim();
        const submitBtn = e.target.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        try {
            const res = await fetch(`/${SITE_SLUG}/gift-article/${currentGiftPage.slug}`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({recipient_email: email, personal_message: message})
            });
            const data = await res.json();
            if (data.data?.success) {
                document.getElementById('giftModalMessages').innerHTML =
                    `<div style="background:#d4edda;color:#155724;padding:15px;border-radius:4px;margin-bottom:20px;">✓ ${data.data.message}</div>`;
                e.target.style.display = 'none';
                setTimeout(() => {
                    closeGiftModal();
                    window.location.reload();
                }, 3000);
            } else {
                throw new Error(data.data?.message || 'Failed to send gift');
            }
        } catch (err) {
            document.getElementById('giftModalMessages').innerHTML =
                `<div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:4px;margin-bottom:20px;">⚠ ${escHtml(err.message)}</div>`;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Gift';
        }
    }

    function closeGiftModal() {
        document.getElementById('giftModal').style.display = 'none';
        currentGiftPage = null;
    }

    document.addEventListener('DOMContentLoaded', loadDashboard);
</script>
</body>
</html>