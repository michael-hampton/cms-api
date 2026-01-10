<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 * @var \App\Framework\Support\Collection $payments
 * @var array $paymentSummary
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - <?= htmlspecialchars($site->name) ?></title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payments-table th,
        .payments-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .payments-table th {
            background: var(--bg-light);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            color: var(--text-secondary);
        }

        .payments-table tr:hover {
            background: var(--bg-light);
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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

            .payments-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<main class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Payment History</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                View all your subscription payments
            </p>
        </div>
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions" class="btn btn-secondary">
            ← Back to Subscriptions
        </a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Payments</div>
            <div class="stat-value"><?= $paymentSummary['total_count'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Paid</div>
            <div class="stat-value">
                <?= htmlspecialchars($paymentSummary['currency']) ?> <?= number_format($paymentSummary['total_paid'], 2) ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Successful Payments</div>
            <div class="stat-value" style="color: var(--success-color);">
                <?= $paymentSummary['successful_count'] ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Failed Payments</div>
            <div class="stat-value" style="color: var(--danger-color);">
                <?= $paymentSummary['failed_count'] ?>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">All Payments</h2>

        <?php if ($payments->isEmpty()): ?>
            <div class="empty-state">
                <div class="empty-state-icon">💳</div>
                <h3>No Payments Yet</h3>
                <p>Your payment history will appear here</p>
            </div>
        <?php else: ?>
            <table class="payments-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Method</th>
                    <th>Transaction Id</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= $payment->created_at->format('M d, Y') ?></td>
                        <td>
                            <?php if ($payment->subscription_id): ?>
                                Subscription Payment
                            <?php else: ?>
                                One-time Payment
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 600;">
                            <?= htmlspecialchars($payment->currency) ?> <?= number_format($payment->amount, 2) ?>
                        </td>
                        <td>
        <span class="status-badge <?= strtolower($payment->status) ?>">
            <?= htmlspecialchars($payment->status) ?>
        </span>
                        </td>
                        <td><?= htmlspecialchars(ucfirst($payment->payment_method)) ?></td>
                        <td>
                            <?php if ($payment->status === 'completed'): ?>
                                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/invoices/<?= $payment->id ?>/download"
                                   class="btn btn-secondary btn-sm"
                                   style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                    </svg>
                                    Download Invoice
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><?= $payment->transaction_id ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

</body>
</html>