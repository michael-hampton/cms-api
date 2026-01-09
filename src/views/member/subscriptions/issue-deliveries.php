<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 * @var \App\Models\Subscription $subscription
 * @var \App\Framework\Support\Collection $upcomingDeliveries
 * @var \App\Framework\Support\Collection $pastDeliveries
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Delivery Schedule - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #2c3e50;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 32px;
            border-radius: 20px;
            margin-bottom: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 32px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .header p {
            color: #64748b;
            font-size: 16px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .subscription-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .deliveries-timeline {
            position: relative;
        }

        .delivery-item {
            position: relative;
            padding: 24px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }

        .delivery-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .delivery-item.delivered {
            border-left-color: #10b981;
            opacity: 0.7;
        }

        .delivery-item.in-transit {
            border-left-color: #f59e0b;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
        }

        .delivery-item.overdue {
            border-left-color: #ef4444;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
        }

        .delivery-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .issue-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 24px;
            font-size: 13px;
            font-weight: 700;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .status-badge.scheduled {
            color: #667eea;
        }

        .status-badge.in-transit {
            color: #f59e0b;
        }

        .status-badge.delivered {
            color: #10b981;
        }

        .delivery-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
        }

        .detail-value {
            font-size: 15px;
            color: #1e293b;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #64748b;
        }

        .empty-state-icon {
            font-size: 72px;
            margin-bottom: 20px;
            opacity: 0.4;
        }

        @media (max-width: 768px) {
            .delivery-header {
                flex-direction: column;
                gap: 12px;
            }

            .delivery-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<div class="container" style="margin-top: 40px;">
    <div class="header">
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions" class="back-link">
            ← Back to Subscriptions
        </a>

        <h1>📦 Issue Delivery Schedule</h1>
        <p>Track your upcoming and past issue deliveries</p>

        <div class="subscription-info">
            <div class="info-row">
                <span style="color: #64748b; font-weight: 600;">Subscription</span>
                <span style="color: #1e293b; font-weight: 700;"><?= htmlspecialchars($subscription->plan_name) ?></span>
            </div>
            <div class="info-row">
                <span style="color: #64748b; font-weight: 600;">Delivery Type</span>
                <span style="color: #1e293b; font-weight: 700;">📦 Print</span>
            </div>
            <?php if ($subscription->end_date): ?>
                <div class="info-row">
                    <span style="color: #64748b; font-weight: 600;">Subscription End</span>
                    <span style="color: #1e293b; font-weight: 700;"><?= $subscription->end_date->format('M d, Y') ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Deliveries -->
    <div class="card">
        <h2>
            <span>📅</span>
            Upcoming Issues
        </h2>

        <?php if ($upcomingDeliveries->isEmpty()): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No Upcoming Deliveries</h3>
                <p>Your delivery schedule will appear here</p>
            </div>
        <?php else: ?>
            <div class="deliveries-timeline">
                <?php foreach ($upcomingDeliveries as $delivery): ?>
                    <?php
                    $status = $delivery->calculateStatus();
                    $statusClass = strtolower(str_replace(' ', '-', $status));
                    $isOverdue = $delivery->isOverdue();
                    ?>
                    <div class="delivery-item <?= $statusClass ?> <?= $isOverdue ? 'overdue' : '' ?>">
                        <div class="delivery-header">
                            <div class="issue-title">
                                <?= htmlspecialchars($delivery->issue_title) ?>
                                <?php if ($isOverdue): ?>
                                    <span style="color: #ef4444; font-size: 14px;">(Overdue)</span>
                                <?php endif; ?>
                            </div>
                            <span class="status-badge <?= $statusClass ?>">
                                <?= $delivery->getStatusLabel() ?>
                            </span>
                        </div>

                        <div class="delivery-details">
                            <div class="detail-item">
                                <span class="detail-label">Issue Number</span>
                                <span class="detail-value">#<?= $delivery->issue_number ?></span>
                            </div>
                            <?php if ($delivery->on_sale_date): ?>
                                <div class="detail-item">
                                    <span class="detail-label">On Sale Date</span>
                                    <span class="detail-value"><?= $delivery->on_sale_date->format('M d, Y') ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <span class="detail-label">Est. Delivery</span>
                                <span class="detail-value"><?= $delivery->estimated_delivery_date->format('M d, Y') ?></span>
                            </div>
                        </div>

                        <?php if ($delivery->tracking_info && !empty($delivery->tracking_info['notes'])): ?>
                            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                                <span class="detail-label">Notes</span>
                                <p style="margin-top: 8px; color: #1e293b;"><?= htmlspecialchars($delivery->tracking_info['notes']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Past Deliveries -->
    <?php if (!$pastDeliveries->isEmpty()): ?>
        <div class="card">
            <h2>
                <span>✓</span>
                Past Issues
            </h2>

            <div class="deliveries-timeline">
                <?php foreach ($pastDeliveries as $delivery): ?>
                    <?php
                    $status = $delivery->calculateStatus();
                    $statusClass = strtolower(str_replace(' ', '-', $status));
                    ?>
                    <div class="delivery-item delivered">
                        <div class="delivery-header">
                            <div class="issue-title">
                                <?= htmlspecialchars($delivery->issue_title) ?>
                            </div>
                            <span class="status-badge delivered">
                            ✓ Delivered
                        </span>
                        </div>

                        <div class="delivery-details">
                            <div class="detail-item">
                                <span class="detail-label">Issue Number</span>
                                <span class="detail-value">#<?= $delivery->issue_number ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Delivered</span>
                                <span class="detail-value"><?= $delivery->estimated_delivery_date->format('M d, Y') ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>