<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Confirmed - YourStore</title>
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
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
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
            box-shadow: var(--shadow-lg);
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
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .success-icon svg {
            width: 48px;
            height: 48px;
            stroke: var(--success-color);
            stroke-width: 3;
        }

        .success-banner h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .success-banner p {
            font-size: 1.125rem;
            opacity: 0.95;
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
            color: var(--text-primary);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .detail-value {
            color: var(--text-primary);
            text-align: right;
            font-size: 0.95rem;
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
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--bg-light);
        }

        .download-section,
        .shipping-section {
            background: var(--bg-light);
            border: 2px dashed var(--border-color);
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            margin-top: 1.5rem;
        }

        .download-section svg,
        .shipping-section svg {
            width: 48px;
            height: 48px;
            stroke: var(--primary-color);
            margin: 0 auto 1rem;
        }

        .download-section h3,
        .shipping-section h3 {
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .download-section p,
        .shipping-section p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .highlight-row {
            background: var(--bg-light);
            border-radius: 0.5rem;
            padding: 1rem !important;
            margin: 1rem 0;
            font-weight: 700;
            font-size: 1.125rem;
            border: 2px solid var(--primary-color) !important;
        }

        .discount-row {
            color: var(--success-color);
        }

        .info-box {
            background: #eff6ff;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1.5rem 0;
        }

        .info-box p {
            color: var(--text-primary);
            margin: 0;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .success-banner {
                padding: 2rem 1rem;
            }

            .success-banner h1 {
                font-size: 1.5rem;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .detail-value {
                text-align: left;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
            }
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
    // Prepare payment breakdown with defaults
    $breakdown = $payment_breakdown ?? [
            'subtotal' => $subscription['price'] ?? 0,
            'discount' => $subscription['discount_amount'] ?? 0,
            'shipping' => 0,
            'tax' => 0,
            'total' => $subscription['price'] ?? 0
    ];
    ?>

    <div class="card">
        <h2>Subscription Details</h2>

        <div class="detail-row">
            <span class="detail-label">Plan:</span>
            <span class="detail-value"><?= htmlspecialchars($subscription['plan_name'] ?? 'N/A') ?></span>
        </div>

        <?php if (!empty($subscription['delivery_type'])): ?>
            <div class="detail-row">
                <span class="detail-label">Delivery Type:</span>
                <span class="detail-value"><?= htmlspecialchars(ucfirst($subscription['delivery_type'])) ?></span>
            </div>
        <?php endif; ?>

        <div class="detail-row">
            <span class="detail-label">Status:</span>
            <span class="status-badge <?= htmlspecialchars($subscription['status'] ?? 'active') ?>">
                <?= htmlspecialchars(ucfirst($subscription['status'] ?? 'Active')) ?>
            </span>
        </div>

        <?php if (!empty($subscription['start_date'])): ?>
            <div class="detail-row">
                <span class="detail-label">Start Date:</span>
                <span class="detail-value">
                    <?= $subscription['start_date']->format('F j, Y') ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if (!empty($subscription['end_date'])): ?>
            <div class="detail-row">
                <span class="detail-label">End Date:</span>
                <span class="detail-value">
                    <?= $subscription['end_date']->format('F j, Y') ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Breakdown -->
    <div class="card">
        <h2>Payment Summary</h2>

        <div class="detail-row">
            <span class="detail-label">Subtotal:</span>
            <span class="detail-value">£<?= number_format($breakdown['subtotal'], 2) ?></span>
        </div>

        <?php if ($breakdown['discount'] > 0): ?>
            <div class="detail-row discount-row">
                <span class="detail-label">Discount:</span>
                <span class="detail-value">-£<?= number_format($breakdown['discount'], 2) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($breakdown['shipping'] > 0): ?>
            <div class="detail-row">
                <span class="detail-label">Shipping:</span>
                <span class="detail-value">£<?= number_format($breakdown['shipping'], 2) ?></span>
            </div>
        <?php endif; ?>

        <div class="detail-row">
            <span class="detail-label">Tax:</span>
            <span class="detail-value">£<?= number_format($breakdown['tax'], 2) ?></span>
        </div>

        <div class="detail-row highlight-row">
            <span class="detail-label">Total Paid:</span>
            <span class="detail-value">£<?= number_format($breakdown['total'], 2) ?></span>
        </div>
    </div>

    <!-- Digital Download Section -->
    <?php if (($subscription['delivery_type'] ?? '') === 'digital' && ($can_download ?? false)): ?>
        <div class="card">
            <div class="download-section">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <h3>Your Content is Ready</h3>
                <p>
                    Download expires: <?= date('F j, Y', strtotime($download_expires_at ?? '+30 days')) ?>
                </p>
                <a href="<?= htmlspecialchars($subscription['download_url'] ?? '#') ?>"
                   class="btn btn-primary">
                    Download Now
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Print Shipping Section -->
    <?php if (($subscription['delivery_type'] ?? '') === 'print'): ?>
        <div class="card">
            <div class="shipping-section">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
                <h3>Your Order is Being Prepared</h3>
                <p>
                    You'll receive a tracking number via email once your order ships.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Next Steps Info -->
    <div class="info-box">
        <p>
            <strong>What's Next?</strong><br>
            A confirmation email has been sent to your inbox with all the details of your subscription.
            If you have any questions, please contact our support team.
        </p>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="/subscriptions" class="btn btn-secondary">
            Browse More Subscriptions
        </a>
        <a href="/" class="btn btn-primary">
            Return to Home
        </a>
    </div>
</div>
</body>
</html>