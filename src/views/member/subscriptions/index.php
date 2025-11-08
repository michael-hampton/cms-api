<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subscriptions - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav {
            display: flex;
            gap: 1.5rem;
        }

        .nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav a:hover {
            color: var(--primary-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .active-subscription {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 1rem;
            padding: 2.5rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3);
        }

        .subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
        }

        .subscription-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .subscription-badge {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .subscription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .detail-box {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .subscription-actions {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            gap: 1rem;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-outline:hover {
            background: white;
            color: var(--primary-color);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .history-list {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .history-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-item:last-child {
            border-bottom: none;
        }

        .history-info h4 {
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }

        .history-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .history-status {
            text-align: right;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.expired {
            background: #fef3c7;
            color: #92400e;
        }

        .history-price {
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .subscription-header {
                flex-direction: column;
                gap: 1rem;
            }

            .subscription-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .history-item {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .history-status {
                text-align: left;
                width: 100%;
            }
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-content">
        <a href="/" class="logo"><?= htmlspecialchars($site->name) ?></a>
        <nav class="nav">
            <a href="/member/dashboard">Dashboard</a>
            <a href="/member/orders">Orders</a>
            <a href="/member/subscriptions">Subscriptions</a>
            <a href="/member/settings">Settings</a>
            <a href="/member/logout">Logout</a>
        </nav>
    </div>
</header>

<main class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">My Subscriptions</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                Manage your subscription plans and billing
            </p>
        </div>
        <a href="/member/dashboard" class="btn btn-secondary">
            ← Back to Dashboard
        </a>
    </div>

    <div id="alert-container"></div>

    <?php if ($activeSubscription): ?>
    <div class="active-subscription">
        <div class="subscription-header">
            <div>
                <div class="subscription-title"><?= htmlspecialchars($activeSubscription->plan_name) ?></div>
                <p style="opacity: 0.9;">Your current active subscription</p>
            </div>
            <span class="subscription-badge">Active</span>
        </div>

        <div class="subscription-details">
            <div class="detail-box">
                <span class="detail-label">Price</span>
                <span class="detail-value">
                        <?= htmlspecialchars($activeSubscription->currency) ?> <?= number_format($activeSubscription->price, 2) ?>
                    </span>
            </div>
            <div class="detail-box">
                <span class="detail-label">Start Date</span>
                <span class="detail-value"><?= $activeSubscription->start_date->format('M j, Y') ?>
                    </span>
            </div>
            <?php if ($activeSubscription->end_date): ?>
                <div class="detail-box">
                    <span class="detail-label">Next Renewal</span>
                    <span class="detail-value">
                            <?= $activeSubscription->end_date->format('M j, Y') ?>
                        </span>
                </div>
            <?php endif; ?>
            <div class="detail-box">
                <span class="detail-label">Auto Renew</span>
                <span class="detail-value">
                        <?= $activeSubscription->auto_renew ? 'Enabled' : 'Disabled' ?>
                    </span>
            </div>
        </div>

        <div class="subscription-actions">
            <button onclick="cancelSubscription(<?= $activeSubscription->id ?>)" class="btn btn-outline">
                Cancel Subscription
            </button>
        </div>
    </div>
    <?php else: ?>
        <div class="empty-state" style="margin-bottom: 2rem;">
            <div class="empty-state-icon">⭐</div>
            <h3>No Active Subscription</h3>
            <p>You don't have an active subscription plan.</p>
            <a href="/" class="btn btn-primary">Browse Plans</a>
        </div>
    <?php endif; ?>

    <?php if (!$subscriptionHistory->isEmpty()): ?>
        <h2 class="section-title">Subscription History</h2>
        <div class="history-list">
            <?php foreach ($subscriptionHistory as $subscription): ?>
                <div class="history-item">
                    <div class="history-info">
                        <h4><?= htmlspecialchars($subscription->plan_name) ?></h4>
                        <div class="history-meta">
                            Started: <?= $subscription->start_date->format('M j, Y') ?>
                            <?php if ($subscription->end_date): ?>
                                - Ended: <?= $subscription->end_date->format('M j, Y') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="history-status">
                        <span class="status-badge <?= strtolower($subscription->status) ?>">
                            <?= htmlspecialchars($subscription->status) ?>
                        </span>
                        <div class="history-price">
                            <?= htmlspecialchars($subscription->currency) ?> <?= number_format($subscription->price, 2) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
    async function cancelSubscription(subscriptionId) {
        if (!confirm('Are you sure you want to cancel your subscription? You will lose access to premium features.')) {
            return;
        }

        try {
            const response = await fetch(`/member/subscriptions/${subscriptionId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Subscription cancelled successfully', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert(data.message || 'Failed to cancel subscription', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to cancel subscription', 'error');
        }
    }

    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alert-container');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

        alertContainer.innerHTML = `
            <div class="alert ${alertClass}">
                <span>${type === 'success' ? '✓' : '✕'}</span>
                ${escapeHtml(message)}
            </div>
        `;

        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>
</body>
</html>