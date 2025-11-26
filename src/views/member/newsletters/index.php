<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Newsletters - <?= htmlspecialchars($site->name) ?></title>
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
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

        .newsletters-grid {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .newsletter-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s;
        }

        .newsletter-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .newsletter-info {
            flex: 1;
        }

        .newsletter-icon {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, #10b98120 0%, #059f6920 100%);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .newsletter-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .newsletter-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .newsletter-badge {
            padding: 0.25rem 0.75rem;
            background: #d1fae5;
            color: #065f46;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .newsletter-actions {
            display: flex;
            gap: 0.75rem;
        }

        .available-newsletters {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .available-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
        }

        .available-item:last-child {
            border-bottom: none;
        }

        .available-info h4 {
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }

        .available-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .frequency-badge {
            padding: 0.25rem 0.75rem;
            background: var(--bg-light);
            color: var(--text-secondary);
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
            display: inline-block;
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

            .newsletter-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .newsletter-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .available-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<main class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">My Newsletters</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                Manage your newsletter subscriptions
            </p>
        </div>
        <a href="/member/dashboard" class="btn btn-secondary">
            ← Back to Dashboard
        </a>
    </div>

    <div id="alert-container"></div>

    <?php if (!$subscriptions->isEmpty()): ?>
        <h2 class="section-title">Active Subscriptions</h2>
        <div class="newsletters-grid">
            <?php foreach ($subscriptions as $subscription): ?>
                <div class="newsletter-card">
                    <div class="newsletter-icon">📧</div>
                    <div class="newsletter-info">
                        <div class="newsletter-title"><?= htmlspecialchars($subscription->email) ?></div>
                        <div class="newsletter-meta">
                            <span>Subscribed: <?= $subscription->subscribed_at->format('M j, Y') ?></span>
                            <?php if ($subscription->isConfirmed()): ?>
                                <span class="newsletter-badge">Confirmed</span>
                            <?php else: ?>
                                <span class="newsletter-badge" style="background: #fef3c7; color: #92400e;">Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="newsletter-actions">
                        <button onclick="unsubscribe(<?= $subscription->id ?>)" class="btn btn-danger btn-sm">
                            Unsubscribe
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state" style="margin-bottom: 3rem;">
            <div class="empty-state-icon">📧</div>
            <h3>No Active Subscriptions</h3>
            <p>You're not subscribed to any newsletters yet.</p>
        </div>
    <?php endif; ?>

    <?php if (!$availableNewsletters->isEmpty()): ?>
        <h2 class="section-title">Available Newsletters</h2>
        <div class="available-newsletters">
            <?php foreach ($availableNewsletters as $newsletter): ?>
                <div class="available-item">
                    <div class="available-info">
                        <h4><?= htmlspecialchars($newsletter->title) ?></h4>
                        <?php if ($newsletter->content): ?>
                            <p class="available-description"><?= htmlspecialchars($newsletter->content) ?></p>
                        <?php endif; ?>
                        <span class="frequency-badge">
                            <?= ucfirst($newsletter->interval) ?> Newsletter
                        </span>
                    </div>
                    <?php
                    $isSubscribed = false;
                    foreach ($subscriptions as $sub) {
                        if ($sub->email === $member->email) {
                            $isSubscribed = true;
                            break;
                        }
                    }
                    ?>
                    <?php if (!$isSubscribed): ?>
                        <button onclick="subscribe('<?= htmlspecialchars($newsletter->id) ?>')"
                                class="btn btn-secondary btn-sm">
                            Subscribe
                        </button>
                    <?php else: ?>
                        <span style="color: var(--success-color); font-weight: 600;">✓ Subscribed</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
    async function unsubscribe(subscriberId) {
        if (!confirm('Are you sure you want to unsubscribe from this newsletter?')) {
            return;
        }

        try {
            const response = await fetch('/<?= \App\Framework\Support\SiteContext::slug() ?>/member/newsletters/unsubscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ subscriber_id: subscriberId })
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Successfully unsubscribed from newsletter', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to unsubscribe', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to unsubscribe', 'error');
        }
    }

    async function subscribe(newsletterId) {
        try {
            const response = await fetch('/<?= \App\Framework\Support\SiteContext::slug() ?>/member/newsletter/signup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({newsletter_id: newsletterId})
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Successfully subscribed! Please check your email to confirm.', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert(data.error || 'Failed to subscribe', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to subscribe', 'error');
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