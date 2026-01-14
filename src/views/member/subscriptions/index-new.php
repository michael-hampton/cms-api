<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 * @var \App\Models\Subscription|null $activeSubscription
 * @var \App\Framework\Support\Collection $subscriptionHistory
 * @var array $subscriptionSummary
 * @var \App\Framework\Support\Collection $plans
 */

?>
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

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .card {
            background: white;
            padding: 32px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .card h2 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }

        .icon {
            font-size: 28px;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 24px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .badge-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .badge-warning {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
        }

        .badge-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 2px solid #f1f5f9;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
            font-weight: 600;
            font-size: 15px;
        }

        .info-value {
            color: #1e293b;
            font-weight: 700;
            font-size: 15px;
        }

        .btn {
            display: inline-block;
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
            letter-spacing: 0.3px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .subscription-status {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            margin-bottom: 24px;
        }

        .status-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
        }

        .status-icon.active {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .status-icon.inactive {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th,
        .history-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 2px solid #f1f5f9;
        }

        .history-table th {
            background: #f8fafc;
            font-weight: 700;
            color: #1e293b;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .history-table tr:hover {
            background: #f8fafc;
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

        .empty-state h3 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 24px;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn.loading {
            position: relative;
            color: transparent;
        }

        .btn.loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .header h1 {
                font-size: 32px;
            }
        }


        /* Keep all existing styles and add these for the dialog */
        .dialog-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9998;
            animation: fadeIn 0.2s ease;
        }

        .dialog-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            animation: slideUp 0.3s ease;
            width: 90%;
            max-width: 500px;
        }

        .dialog-content {
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .dialog-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            animation: scaleIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .icon-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
        }

        .icon-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #f59e0b;
        }

        .icon-info {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #3b82f6;
        }

        .icon-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #10b981;
        }

        .dialog-title {
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            line-height: 1.3;
            text-align: center;
        }

        .dialog-message {
            font-size: 16px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 28px;
            text-align: center;
        }

        .dialog-options {
            margin: 28px 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .option-item {
            display: flex;
            align-items: center;
            padding: 18px 20px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .option-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .option-item.selected {
            background: linear-gradient(135deg, #ede9fe, #ddd6fe);
            border-color: #7c3aed;
        }

        .option-item input[type="radio"] {
            margin-right: 16px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #7c3aed;
        }

        .option-content {
            flex: 1;
            text-align: left;
        }

        .option-label {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .option-description {
            font-size: 13px;
            color: #64748b;
            line-height: 1.4;
        }

        .option-check {
            color: #7c3aed;
            font-size: 24px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .option-item.selected .option-check {
            opacity: 1;
        }

        .dialog-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 32px;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .btn-confirm {
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
@include('member._header')

<div class="container" style="margin-top: 40px;">
    <div class="grid">
        @include('member/subscriptions/components/_active-subscription-card', [
        'activeSubscription' => $activeSubscription,
        'member' => $member,
        'plans' => $plans
        ])

        @include('member/subscriptions/components/_email-preferences-card', [
        'subscriptionSummary' => $subscriptionSummary
        ])
    </div>

    @include('member/subscriptions/components/_subscription-history', [
    'subscriptionHistory' => $subscriptionHistory
    ])
</div>

@include('member/subscriptions/_pause-delivery-modal')
@include('member/subscriptions/_renewal-modal', [
'activeSubscription' => $activeSubscription
])
@include('member/subscriptions/_billing-date-modal')
@include('member/subscriptions/_scripts')
@include('components/subscription-modal', ['subscriptionModalData' => $subscriptionModalData])
</body>
</html>