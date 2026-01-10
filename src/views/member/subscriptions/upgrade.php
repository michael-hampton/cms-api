<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 * @var array $upgradeInfo
 * @var int $subscriptionId
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade to Insider - <?= htmlspecialchars($site->name) ?></title>
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

        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        .plan-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .plan-card.upgrade {
            border: 3px solid #667eea;
            background: linear-gradient(135deg, #ffffff, #f8f9ff);
        }

        .plan-badge {
            position: absolute;
            top: -12px;
            right: 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 20px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .plan-header {
            margin-bottom: 24px;
        }

        .plan-name {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .plan-price {
            font-size: 36px;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 8px;
        }

        .plan-price .currency {
            font-size: 20px;
        }

        .plan-period {
            color: #64748b;
            font-size: 14px;
        }

        .features-list {
            list-style: none;
            margin: 24px 0;
        }

        .features-list li {
            padding: 12px 0;
            display: flex;
            align-items: start;
            gap: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .feature-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .feature-icon.check {
            color: #10b981;
        }

        .feature-icon.new {
            color: #667eea;
        }

        .feature-icon.missing {
            color: #cbd5e1;
        }

        .upgrade-benefits {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 32px;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .benefit-card {
            background: linear-gradient(135deg, #f8f9ff, #ffffff);
            padding: 24px;
            border-radius: 12px;
            border: 2px solid #e0e7ff;
        }

        .benefit-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .benefit-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .benefit-description {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
        }

        .pricing-summary {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 32px;
        }

        .pricing-row {
            display: flex;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .pricing-row:last-child {
            border-bottom: none;
            padding-top: 24px;
            margin-top: 8px;
            border-top: 2px solid #e2e8f0;
        }

        .pricing-label {
            font-weight: 600;
            color: #64748b;
        }

        .pricing-value {
            font-weight: 700;
            color: #1e293b;
        }

        .pricing-row.total .pricing-label {
            font-size: 20px;
            color: #1e293b;
        }

        .pricing-row.total .pricing-value {
            font-size: 24px;
            color: #667eea;
        }

        .proration-note {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
            font-size: 14px;
            color: #92400e;
        }

        .action-buttons {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }

        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

    </style>