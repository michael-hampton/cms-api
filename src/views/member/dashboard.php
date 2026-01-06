<?php

use App\Framework\Support\SiteContext;

?>
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

<div class="container">
    <?php if ($msg = message()): ?>
        <div class="message success">
            <span>✓</span>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($error = error()): ?>
        <div class="message error">
            <span>⚠</span>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$member->isEmailVerified()): ?>
        <!-- Email Verification Required Banner -->
        <div class="verification-banner">
            <h2>
                <span>⚠️</span>
                Email Verification Required
            </h2>
            <p>
                Welcome! Please verify your email address to unlock your full account and access all features.
                We've sent a verification link to <strong><?= htmlspecialchars($member->email) ?></strong>.
            </p>
            <div class="verification-actions">
                <button class="btn-resend" id="resendBtn" onclick="resendVerification()">
                    <span>📧</span>
                    Resend Verification Email
                </button>
            </div>
        </div>

        <!-- Limited Access Section -->
        <div class="limited-access-section">
            <h2>Your Account Overview</h2>
            <div class="limited-access-grid">
                <div class="info-card">
                    <h3>
                        <span>👤</span>
                        Profile Information
                    </h3>
                    <p>
                        <strong>Name:</strong> <?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?>
                        <br>
                        <strong>Email:</strong> <?= htmlspecialchars($member->email) ?><br>
                        <strong>Member Since:</strong> <?= $member->created_at->format('M j, Y') ?>
                    </p>
                </div>

                <?php if (!empty($stats['orders'])): ?>
                    <div class="info-card">
                        <h3>
                            <span>🛍️</span>
                            Your Orders
                        </h3>
                        <p>
                            You have <strong><?= $stats['orders'] ?></strong>
                            order<?= $stats['orders'] !== 1 ? 's' : '' ?>.
                            Verify your email to view order details and tracking information.
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($stats['subscriptions'])): ?>
                    <div class="info-card">
                        <h3>
                            <span>⭐</span>
                            Your Subscriptions
                        </h3>
                        <p>
                            You have <strong><?= $stats['subscriptions'] ?></strong> active
                            subscription<?= $stats['subscriptions'] !== 1 ? 's' : '' ?>.
                            Verify your email to manage your subscriptions.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Disabled Feature Cards -->
        <h2 class="section-title">Available After Verification</h2>
        <div class="dashboard-grid">
            <div class="dashboard-card disabled">
                <div class="card-header">
                    <div class="card-icon orders">🛍️</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>My Orders</h3>
                    <p>View and track your order history and current shipments.</p>
                </div>
            </div>

            <div class="dashboard-card disabled">
                <div class="card-header">
                    <div class="card-icon newsletters">📧</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Newsletters</h3>
                    <p>Manage your newsletter subscriptions and preferences.</p>
                </div>
            </div>

            <div class="dashboard-card disabled">
                <div class="card-header">
                    <div class="card-icon subscriptions">⭐</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Subscriptions</h3>
                    <p>View and manage your active subscriptions and membership plans.</p>
                </div>
            </div>

            <div class="dashboard-card disabled">
                <div class="card-header">
                    <div class="card-icon comments">💬</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Comments</h3>
                    <p>View and manage your comments across the site.</p>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Full Dashboard for Verified Users -->
        <div class="welcome-section">
            <h1>Welcome back, <?= htmlspecialchars($member->first_name ?? 'Member') ?>!</h1>
            <p>Manage your account, track your orders, and explore exclusive content.</p>
        </div>

        <h2 class="section-title">Quick Access</h2>

        <div class="dashboard-grid">
            <a href="/<?= $site->slug ?>/member/orders" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon orders">🛍️</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>My Orders</h3>
                    <p>View and track your order history and current shipments.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/newsletters" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon newsletters">📧</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Newsletters</h3>
                    <p>Manage your newsletter subscriptions and preferences.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/subscriptions" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon subscriptions">⭐</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Subscriptions</h3>
                    <p>View and manage your active subscriptions and membership plans.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/addresses" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon addresses">📍</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Addresses</h3>
                    <p>Manage your shipping and billing addresses.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/comments" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon comments">💬</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Comments</h3>
                    <p>View and manage your comments across the site.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/account-details" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #6b728020 0%, #4b556320 100%);">
                        👤
                    </div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Account Details</h3>
                    <p>View and update your personal information and account status.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/settings" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon settings">⚙️</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Security Settings</h3>
                    <p>Update your password and security preferences.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/reading-history" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #ec489920 0%, #f5717620 100%);">
                        📚
                    </div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Reading History</h3>
                    <p>View pages you've read and track your reading progress.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/liked-pages" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #ef444420 0%, #dc262620 100%);">
                        ❤️
                    </div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Liked Pages</h3>
                    <p>Access your collection of liked pages and content.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/wishlist" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon orders">🛍️</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>My Favorites</h3>
                    <p>View your saved favorite items.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/consent" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon orders">🔒</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Privacy & Consent</h3>
                    <p>Control how your personal data is used.</p>
                </div>
            </a>

            <a href="/<?= $site->slug ?>/member/activity" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon orders">🏆</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>Activity & Achievements</h3>
                    <p>Track your engagement and earn badges.</p>
                </div>
            </a>
        </div>

        <h2 class="section-title">Recent Activity</h2>

        <div class="dashboard-grid">
            <?php
            // Get all orders including subscriptions
            $recentOrders = \App\Models\Order::where('user_id', $member->id)
                    ->where('site_id', SiteContext::getId())
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

            // Get all subscriptions (active and expired)
            $allSubscriptions = \App\Models\Subscription::where('member_id', $member->id)
                    ->where('site_id', SiteContext::getId())
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

            if ($recentOrders->count() > 0 || $allSubscriptions->count() > 0):
                ?>
                <div style="background: white; border-radius: 1rem; padding: 2rem; box-shadow: var(--shadow); margin-bottom: 2rem;">
                    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-color);">
                        <button class="tab-btn active" onclick="switchTab('orders')" id="ordersTab"
                                style="padding: 1rem; background: none; border: none; font-weight: 600; cursor: pointer; border-bottom: 3px solid var(--primary-color); margin-bottom: -2px;">
                            Orders (<?= $recentOrders->count() ?>)
                        </button>
                        <button class="tab-btn" onclick="switchTab('subscriptions')" id="subscriptionsTab"
                                style="padding: 1rem; background: none; border: none; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
                            Subscriptions (<?= $allSubscriptions->count() ?>)
                        </button>
                    </div>

                    <!-- Orders Tab -->
                    <div id="ordersContent" style="overflow-x: auto;">
                        <?php if ($recentOrders->count() > 0): ?>
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                <tr style="background: var(--bg-light);">
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Date
                                    </th>
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Order #
                                    </th>
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Type
                                    </th>
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Total
                                    </th>
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Status
                                    </th>
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Action
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 0.75rem;"><?= $order->created_at->format('M d, Y') ?></td>
                                        <td style="padding: 0.75rem; font-weight: 600;">
                                            #<?= htmlspecialchars($order->order_number) ?></td>
                                        <td style="padding: 0.75rem;">
                                            <?php if ($order->one_time_subscription_id): ?>
                                                📋 Subscription
                                            <?php else: ?>
                                                🛍️ Order
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.75rem; font-weight: 600;">
                                            <?= htmlspecialchars($order->currency) ?> <?= number_format($order->total, 2) ?>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                <span style="padding: 0.375rem 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600;
                                        background: <?= $order->status === 'completed' ? '#d1fae5' : ($order->status === 'pending' ? '#fef3c7' : '#fee2e2') ?>;
                                        color: <?= $order->status === 'completed' ? '#065f46' : ($order->status === 'pending' ? '#92400e' : '#991b1b') ?>;">
                                    <?= ucfirst($order->status) ?>
                                </span>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <a href="/<?= $site->slug ?>/member/orders/<?= $order->id ?>"
                                               style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                                                View Details →
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="margin-top: 1rem; text-align: center;">
                                <a href="/<?= $site->slug ?>/member/orders"
                                   style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                                    View All Orders →
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                <p>No orders yet</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Subscriptions Tab -->
                    <div id="subscriptionsContent" style="display: none; overflow-x: auto;">
                        <?php if ($allSubscriptions->count() > 0): ?>
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                <tr style="background: var(--bg-light);">
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Plan
                                    </th>
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Type
                                    </th>
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Start Date
                                    </th>
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        End Date
                                    </th>
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Status
                                    </th>
                                    <th style="padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">
                                        Action
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($allSubscriptions as $sub): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 0.75rem; font-weight: 600;">
                                            <?= htmlspecialchars($sub->plan_name) ?>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); font-weight: normal; margin-top: 0.25rem;">
                                                <?= $sub->isPrint() ? '📦 Print' : '💻 Digital' ?>
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                <span style="padding: 0.25rem 0.5rem; background: <?= $sub->type === 'paid' ? '#e0e7ff' : '#f3f4f6' ?>;
                                        color: <?= $sub->type === 'paid' ? '#3730a3' : '#374151' ?>; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">
                                    <?= ucfirst($sub->type ?? 'standard') ?>
                                </span>
                                        </td>
                                        <td style="padding: 0.75rem;"><?= $sub->start_date->format('M d, Y') ?></td>
                                        <td style="padding: 0.75rem;">
                                            <?= $sub->end_date ? $sub->end_date->format('M d, Y') : 'Ongoing' ?>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                <span style="padding: 0.375rem 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600;
                                        background: <?= $sub->status === 'active' ? '#d1fae5' : ($sub->status === 'expired' ? '#fee2e2' : '#fef3c7') ?>;
                                        color: <?= $sub->status === 'active' ? '#065f46' : ($sub->status === 'expired' ? '#991b1b' : '#92400e') ?>;">
                                    <?= ucfirst($sub->status) ?>
                                </span>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <a href="/<?= $site->slug ?>/member/subscriptions"
                                               style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                                                Manage →
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="margin-top: 1rem; text-align: center;">
                                <a href="/<?= $site->slug ?>/member/subscriptions"
                                   style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                                    View All Subscriptions →
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                <p>No subscriptions yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <h2 class="section-title">Your Activity</h2>

        <?php if (!empty($stats)): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['orders'] ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?= $stats['newsletters'] ?></div>
                    <div class="stat-label">Newsletters</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?= $stats['subscriptions'] ?></div>
                    <div class="stat-label">Active Subscriptions</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?= $stats['comments'] ?></div>
                    <div class="stat-label">Comments Posted</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?= $stats['pages_read'] ?></div>
                    <div class="stat-label">Pages Read</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?= $stats['likes'] ?></div>
                    <div class="stat-label">Pages Liked</div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    function switchTab(tab) {
        // Hide all content
        document.getElementById('ordersContent').style.display = 'none';
        document.getElementById('subscriptionsContent').style.display = 'none';

        // Reset all tabs
        document.getElementById('ordersTab').classList.remove('active');
        document.getElementById('subscriptionsTab').classList.remove('active');
        document.getElementById('ordersTab').style.borderBottom = 'none';
        document.getElementById('subscriptionsTab').style.borderBottom = 'none';
        document.getElementById('ordersTab').style.color = 'var(--text-secondary)';
        document.getElementById('subscriptionsTab').style.color = 'var(--text-secondary)';

        // Show selected content and activate tab
        if (tab === 'orders') {
            document.getElementById('ordersContent').style.display = 'block';
            document.getElementById('ordersTab').classList.add('active');
            document.getElementById('ordersTab').style.borderBottom = '3px solid var(--primary-color)';
            document.getElementById('ordersTab').style.color = 'var(--text-primary)';
        } else {
            document.getElementById('subscriptionsContent').style.display = 'block';
            document.getElementById('subscriptionsTab').classList.add('active');
            document.getElementById('subscriptionsTab').style.borderBottom = '3px solid var(--primary-color)';
            document.getElementById('subscriptionsTab').style.color = 'var(--text-primary)';
        }
    }
    async function resendVerification() {
        const btn = document.getElementById('resendBtn');
        btn.disabled = true;
        btn.innerHTML = '<span>⏳</span> Sending...';

        try {
            const response = await fetch('/<?= $site->slug ?>/member/resend-verification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();

            if (result.success) {
                btn.innerHTML = '<span>✓</span> Email Sent!';
                btn.style.background = 'linear-gradient(135deg, var(--success-color), #059669)';

                setTimeout(() => {
                    btn.innerHTML = '<span>📧</span> Resend Verification Email';
                    btn.style.background = '';
                    btn.disabled = false;
                }, 3000);
            } else {
                alert(result.message || 'Failed to send email. Please try again.');
                btn.innerHTML = '<span>📧</span> Resend Verification Email';
                btn.disabled = false;
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
            btn.innerHTML = '<span>📧</span> Resend Verification Email';
            btn.disabled = false;
        }
    }
</script>
</body>
</html>