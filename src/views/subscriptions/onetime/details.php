<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Details - YourStore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --success-color: #10b981;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: var(--bg-light);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 20px;
        }

        .success-banner {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
            text-align: center;
            padding: 3rem 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .success-icon svg {
            width: 48px;
            height: 48px;
            stroke: var(--success-color);
            stroke-width: 3;
        }

        .card {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .card h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .detail-value {
            color: var(--text-primary);
        }

        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }

        .btn {
            display: inline-block;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1e40af;
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .download-section {
            background: var(--bg-light);
            border: 2px dashed var(--border-color);
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            margin-top: 1.5rem;
        }

        .download-section svg {
            width: 48px;
            height: 48px;
            stroke: var(--primary-color);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="success-banner">
        <div class="success-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <h1>Subscription Activated!</h1>
        <p>Thank you for your purchase. Your subscription is now active.</p>
    </div>

    <?php
    $breakdown = $payment_breakdown ?? [
            'subtotal' => $subscription['price'],
            'discount' => $subscription['discount_amount'] ?? 0,
            'shipping' => 0,
            'tax' => 0,
            'total' => $subscription['price']
    ];
    ?>

    <div class="card">
        <h2>Subscription Details</h2>
        <div class="detail-row">
            <span class="detail-label">Plan:</span>
            <span class="detail-value"><?= htmlspecialchars($subscription['plan_name']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Delivery Type:</span>
            <span class="detail-value"><?= htmlspecialchars(ucfirst($subscription['delivery_type'])) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status:</span>
            <span class="status-badge <?= htmlspecialchars($subscription['status']) ?>">
                    <?= htmlspecialchars(ucfirst($subscription['status'])) ?>
                </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Start Date:</span>
            <span class="detail-value"><?= $subscription['start_date']->format('F j, Y') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">End Date:</span>
            <span class="detail-value"><?= $subscription['end_date']->format('F j, Y') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Subtotal:</span>
            <span class="detail-value">$<?= number_format($breakdown['subtotal'], 2) ?></span>
        </div>

        <?php if ($breakdown['discount'] > 0): ?>
            <div class="detail-row" style="color: var(--success-color);">
                <span class="detail-label">Discount:</span>
                <span class="detail-value">-$<?= number_format($breakdown['discount'], 2) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($breakdown['shipping'] > 0): ?>
            <div class="detail-row">
                <span class="detail-label">Shipping:</span>
                <span class="detail-value">$<?= number_format($breakdown['shipping'], 2) ?></span>
            </div>
        <?php endif; ?>

        <div class="detail-row">
            <span class="detail-label">Tax:</span>
            <span class="detail-value">$<?= number_format($breakdown['tax'], 2) ?></span>
        </div>

        <div class="detail-row"
             style="font-weight: 700; font-size: 1.125rem; padding-top: 1rem; margin-top: 1rem; border-top: 2px solid var(--border-color);">
            <span class="detail-label">Total Paid:</span>
            <span class="detail-value">$<?= number_format($breakdown['total'], 2) ?></span>
        </div>

        <?php if ($subscription['delivery_type'] === 'digital' && $can_download): ?>
            <div class="download-section">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <h3 style="margin-bottom: 0.5rem;">Your Content is Ready</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                    Download expires: <?= date('F j, Y', strtotime($download_expires_at)) ?>
                </p>
                <a href="<?= htmlspecialchars($subscription['download_url']) ?>" class="btn btn-primary">
                    Download Now
                </a>
            </div>
        <?php elseif ($subscription['delivery_type'] === 'print'): ?>
            <div class="download-section">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
                <h3 style="margin-bottom: 0.5rem;">Your Order is Being Shipped</h3>
                <p style="color: var(--text-secondary);">
                    You'll receive a tracking number via email once your order ships.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <div style="text-align: center;">
        <a href="/" class="btn btn-secondary">Return to Home</a>
    </div>
</div>
</body>
</html>