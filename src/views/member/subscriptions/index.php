<?php

/** @var \App\Models\Member $member */
/** @var \App\Models\Site $site */
/** @var \App\Framework\Support\Collection $plans */
/** @var array $subscriptionModalData */
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
            flex-wrap: wrap;
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
            flex-shrink: 0;
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
            content: '';
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

        .warning-banner {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border-left: 4px solid;
        }

        .warning-banner.warning {
            background: #fef3c7;
            border-color: #f59e0b;
        }

        .warning-banner.danger {
            background: #fee2e2;
            border-color: #dc2626;
        }

        .warning-banner.info {
            background: #dbeafe;
            border-color: #3b82f6;
        }

        .warning-title {
            font-weight: 600;
        }

        .warning-message {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }

        .warning-message a {
            font-weight: 600;
        }

        /* Skeleton loader */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 0.5rem;
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        /* Dialog */
        .dialog-backdrop {
            position: fixed;
            inset: 0;
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
            text-align: center;
            line-height: 1.3;
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

        /* Modals */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.5);
            padding: 20px;
            overflow-y: auto;
        }

        .modal-box {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
        }

        .form-textarea {
            resize: vertical;
        }

        .form-hint {
            font-size: 13px;
            color: #64748b;
            margin-top: 8px;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.9375rem;
            font-weight: 500;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            pointer-events: all;
            animation: slideIn 0.3s ease;
            max-width: 360px;
        }

        .toast.success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .toast.error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .toast.info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            font-size: 1.1rem;
        }

        .toast-close:hover {
            opacity: 1;
        }

        /* Upgrade section */
        .upgrade-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 24px;
            border-radius: 16px;
            margin: 24px 0;
        }

        .upgrade-plan-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        /* Renewal prompt */
        .renewal-prompt {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
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
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
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
        }
    </style>
</head>
<body>
@include('member._header')

<div class="toast-container" id="toastContainer"></div>

<div class="container" style="margin-top: 40px;">
    <div class="grid">
        <!-- Active Subscription Card -->
        <div class="card" id="subscription-card">
            <h2><span class="icon">📋</span> Current Plan</h2>
            <div id="subscription-card-body">
                <div class="skeleton" style="height:80px;border-radius:16px;margin-bottom:24px;"></div>
                <div class="skeleton" style="height:20px;margin-bottom:12px;"></div>
                <div class="skeleton" style="height:20px;margin-bottom:12px;width:70%;"></div>
                <div class="skeleton" style="height:20px;width:50%;"></div>
            </div>
        </div>

        <!-- Email Preferences Card -->
        <div class="card" id="email-prefs-card">
            <h2><span class="icon">✉️</span> Email Preferences</h2>
            <div id="email-prefs-body">
                <div class="skeleton" style="height:80px;border-radius:16px;margin-bottom:24px;"></div>
                <div class="skeleton" style="height:20px;margin-bottom:12px;"></div>
                <div class="skeleton" style="height:20px;width:60%;"></div>
            </div>
        </div>
    </div>

    <!-- Subscription History -->
    <div class="card" id="history-card">
        <h2><span class="icon">📜</span> Subscription History</h2>
        <div id="history-body">
            <div class="skeleton" style="height:40px;margin-bottom:8px;"></div>
            <div class="skeleton" style="height:40px;margin-bottom:8px;"></div>
            <div class="skeleton" style="height:40px;"></div>
        </div>
    </div>
</div>

<!-- Pause Delivery Modal -->
<div id="pauseDeliveryModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Pause Delivery</h2>
            <button class="modal-close" id="closePauseModal">×</button>
        </div>
        <div class="modal-body">
            <p style="color:#64748b;margin-bottom:24px;">
                Pause your magazine deliveries temporarily. Your subscription will remain active and unused issues will
                be available when you resume.
            </p>
            <div class="form-group">
                <label class="form-label">Pause Start Date</label>
                <input type="date" id="pauseStartDate" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Resume Date (Pause End)</label>
                <input type="date" id="pauseEndDate" class="form-input">
                <p class="form-hint">Maximum pause period: 90 days</p>
            </div>
            <div class="form-group">
                <label class="form-label">Reason (Optional)</label>
                <textarea id="pauseReason" class="form-textarea" rows="3"
                          placeholder="e.g., Holiday, Moving house..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelPauseBtn">Cancel</button>
            <button class="btn btn-primary" id="confirmPauseBtn">Pause Delivery</button>
        </div>
        <input type="hidden" id="pauseSubscriptionId">
    </div>
</div>

<!-- Billing Date Modal -->
<div id="billingDateModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Change Billing Date</h2>
            <button class="modal-close" id="closeBillingModal">×</button>
        </div>
        <div class="modal-body">
            <p style="color:#64748b;margin-bottom:24px;">Select the day of the month you'd like to be charged. Your
                payment will be adjusted accordingly.</p>
            <div class="form-group">
                <label class="form-label">Current Billing Date</label>
                <div style="background:#f8fafc;padding:16px;border-radius:8px;">
                    <span id="currentBillingDay" style="font-weight:700;font-size:18px;"></span> of each month
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">New Billing Day</label>
                <select id="billingDaySelect" class="form-select">
                    <?php for ($day = 1; $day <= 28; $day++): ?>
                        <option value="<?= $day ?>">
                            <?= $day ?><?= match (true) {
                                $day === 1 => 'st',
                                $day === 2 => 'nd',
                                $day === 3 => 'rd',
                                default => 'th'
                            } ?> of each month
                        </option>
                    <?php endfor; ?>
                </select>
                <p class="form-hint">We recommend choosing a day between 1–28 to avoid issues in shorter months.</p>
            </div>
            <div id="prorationPreview"
                 style="display:none;background:#f0f4ff;padding:16px;border-radius:8px;border-left:4px solid #667eea;margin-top:16px;">
                <div style="font-weight:600;margin-bottom:8px;">Billing Adjustment</div>
                <div id="prorationDetails" style="font-size:14px;color:#334155;"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelBillingBtn">Cancel</button>
            <button class="btn btn-primary" id="confirmBillingBtn">Update Billing Date</button>
        </div>
        <input type="hidden" id="billingSubscriptionId">
    </div>
</div>

<!-- Renewal Modal -->
<div id="renewalModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Renew Your Subscription</h2>
            <button class="modal-close" id="closeRenewalModal">×</button>
        </div>
        <div class="modal-body">
            <div style="background:#f8fafc;padding:16px;border-radius:8px;margin-bottom:24px;">
                <div style="font-weight:600;margin-bottom:4px;">Current Plan</div>
                <div style="color:#64748b;font-size:14px;" id="currentPlanName"></div>
            </div>

            <div style="margin-bottom:24px;">
                <label class="form-label">Choose Renewal Type</label>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <label style="border:2px solid #e2e8f0;border-radius:8px;padding:16px;cursor:pointer;transition:all 0.2s;"
                           class="renewal-option" data-type="fixed">
                        <input type="radio" name="renewal_type" value="fixed" checked style="margin-right:12px;">
                        <div style="display:inline-block;">
                            <div style="font-weight:600;margin-bottom:4px;">Fixed Term</div>
                            <div style="font-size:14px;color:#64748b;">Choose 1 or 2 year subscription</div>
                        </div>
                    </label>
                    <label style="border:2px solid #e2e8f0;border-radius:8px;padding:16px;cursor:pointer;transition:all 0.2s;"
                           class="renewal-option" data-type="auto">
                        <input type="radio" name="renewal_type" value="auto" style="margin-right:12px;">
                        <div style="display:inline-block;">
                            <div style="font-weight:600;margin-bottom:4px;">Auto-Renewing</div>
                            <div style="font-size:14px;color:#64748b;">Automatically renews, cancel anytime</div>
                        </div>
                    </label>
                </div>
            </div>

            <div id="renewalAddressSection" style="margin-bottom:24px;">
                <label class="form-label">Delivery Address</label>
                <div style="background:#f8fafc;padding:16px;border-radius:8px;margin-bottom:12px;" id="currentAddress">
                    Loading address...
                </div>
                <button id="updateAddressBtn" class="btn btn-secondary" style="font-size:14px;padding:8px 16px;">
                    Update Address
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelRenewalBtn">Cancel</button>
            <button class="btn btn-primary" id="renewalSubmitBtn">Continue to Payment</button>
        </div>
    </div>
</div>

<script>
    const API_BASE = '/api/' + SITE_SLUG;
</script>
<script>
    /* ─────────────────────────────────────────────────────────────────────────
     * Helpers
     * ───────────────────────────────────────────────────────────────────────── */

    /**
     * Format a money amount with currency prefix.
     */
    function fmt(amount, currency = 'GBP') {
        return (currency || 'GBP') + ' ' + Number(amount).toFixed(2);
    }

    /**
     * Build an .info-row element. valueNode may be a DOM node or a plain string.
     */
    function infoRow(label, valueNode) {
        return UI.el('div', {className: 'info-row'}, [
            UI.el('span', {className: 'info-label'}, [label]),
            valueNode instanceof Node
                ? valueNode
                : UI.el('span', {className: 'info-value'}, [String(valueNode ?? '')]),
        ]);
    }

    /**
     * Build a coloured badge element.
     */
    function badge(text, type = 'success') {
        return UI.el('span', {className: `badge badge-${type}`}, [text]);
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * ConfirmDialog
     * Wraps the confirm/cancel UX in a Promise. Resolves with the selected
     * option value (or `true` for simple yes/no). Rejects when dismissed.
     * ───────────────────────────────────────────────────────────────────────── */

    class ConfirmDialog {
        static #iconMap = {danger: '⚠️', warning: '⚡', info: 'ℹ️', success: '✓'};

        static show(config) {
            return new Promise((resolve, reject) => {
                const backdrop = UI.el('div', {className: 'dialog-backdrop'});
                const container = UI.el('div', {className: 'dialog-container'});

                let selectedOption = config.showOptions && config.options
                    ? config.options[0].value
                    : null;

                // Build options markup
                let optionsHTML = '';
                if (config.showOptions && config.options) {
                    optionsHTML = '<div class="dialog-options">';
                    config.options.forEach((option, index) => {
                        optionsHTML += `
                            <label class="option-item ${index === 0 ? 'selected' : ''}" data-value="${option.value}">
                                <input type="radio" name="dialog-option" value="${option.value}" ${index === 0 ? 'checked' : ''}>
                                <div class="option-content">
                                    <div class="option-label">${UI.esc(option.label)}</div>
                                    ${option.description ? `<div class="option-description">${UI.esc(option.description)}</div>` : ''}
                                </div>
                                <div class="option-check">✓</div>
                            </label>`;
                    });
                    optionsHTML += '</div>';
                }

                container.innerHTML = `
                    <div class="dialog-content">
                        <div class="dialog-icon icon-${config.type}">
                            <span>${ConfirmDialog.#iconMap[config.type] ?? ConfirmDialog.#iconMap.info}</span>
                        </div>
                        <h2 class="dialog-title">${UI.esc(config.title)}</h2>
                        <p class="dialog-message">${UI.esc(config.message)}</p>
                        ${optionsHTML}
                        <div class="dialog-actions">
                            <button class="btn btn-cancel">${UI.esc(config.cancelText || 'Cancel')}</button>
                            <button class="btn btn-confirm btn-${config.type}">${UI.esc(config.confirmText || 'Confirm')}</button>
                        </div>
                    </div>`;

                document.body.appendChild(backdrop);
                document.body.appendChild(container);

                if (config.showOptions) {
                    container.querySelectorAll('.option-item').forEach(item => {
                        item.addEventListener('click', () => {
                            container.querySelectorAll('.option-item').forEach(i => i.classList.remove('selected'));
                            item.classList.add('selected');
                            const raw = item.dataset.value;
                            selectedOption = raw === 'true' ? true : raw === 'false' ? false : raw;
                        });
                    });
                }

                const close = () => {
                    backdrop.remove();
                    container.remove();
                };

                backdrop.addEventListener('click', () => {
                    close();
                    reject();
                });
                container.querySelector('.btn-cancel').addEventListener('click', () => {
                    close();
                    reject();
                });
                container.querySelector('.btn-confirm').addEventListener('click', () => {
                    close();
                    resolve(config.showOptions ? selectedOption : true);
                });
            });
        }
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * WarningBanners
     * Renders payment-method, renewal and cancellation banners from API data.
     * ───────────────────────────────────────────────────────────────────────── */

    class WarningBanners {
        /**
         * @param {object}      sub           Active subscription (may be null)
         * @param {object|null} paymentWarning { message: string } or null
         */
        render(sub, paymentWarning = null) {
            const banners = [];

            // Payment method warning (from API)
            if (paymentWarning?.message) {
                banners.push(UI.el('div', {className: 'warning-banner warning'}, [
                    UI.el('span', {style: {fontSize: '24px'}}, ['⚠️']),
                    UI.el('div', {}, [
                        UI.el('div', {
                            className: 'warning-title',
                            style: {color: '#92400e'}
                        }, ['Payment Method Action Required']),
                        UI.el('div', {className: 'warning-message'}, [
                            paymentWarning.message + '. ',
                            UI.el('a', {
                                href: `/${SITE_SLUG}/member/payment-methods`,
                                style: {color: '#f59e0b'},
                            }, ['Update now']),
                            ' to avoid subscription interruption.',
                        ]),
                    ]),
                ]));
            }

            if (!sub) return UI.el('div', {}, banners);

            // Cancellation banner
            const isExpired = sub.end_date ? new Date(sub.end_date) < new Date() : false;
            const isCancelling = sub.status === 'active' && sub.cancelled_at && !isExpired;
            if (isCancelling) {
                const daysRemaining = sub.end_date
                    ? Math.max(0, Math.ceil((new Date(sub.end_date) - Date.now()) / 86_400_000))
                    : null;

                banners.push(UI.el('div', {className: 'warning-banner warning'}, [
                    UI.el('span', {style: {fontSize: '24px'}}, ['🔔']),
                    UI.el('div', {}, [
                        UI.el('div', {
                            className: 'warning-title',
                            style: {color: '#92400e'}
                        }, ['Subscription Set to Cancel']),
                        UI.el('div', {className: 'warning-message'}, [
                            `Your access will end on ${UI.formatDate(sub.end_date)}`,
                            daysRemaining != null
                                ? UI.el('strong', {style: {color: '#667eea'}}, [` (${daysRemaining} days remaining)`])
                                : null,
                            '. You can reactivate anytime before then to continue your subscription.',
                        ]),
                    ]),
                ]));
            }

            // Renewal warning
            if (sub.next_billing_date) {
                const daysUntil = Math.ceil((new Date(sub.next_billing_date) - Date.now()) / 86_400_000);
                let type = null, icon = '', msg = '';

                if (daysUntil <= 0) {
                    type = 'danger';
                    icon = '⚠️';
                    msg = 'Your subscription renewal is due.';
                } else if (daysUntil <= 7) {
                    type = 'warning';
                    icon = '⏰';
                    msg = `Your subscription will renew in ${daysUntil} day${daysUntil > 1 ? 's' : ''}.`;
                } else if (daysUntil <= 30) {
                    type = 'info';
                    icon = 'ℹ️';
                    msg = `Your subscription will renew in ${daysUntil} days.`;
                }

                if (type) {
                    banners.push(UI.el('div', {className: `warning-banner ${type}`}, [
                        UI.el('span', {style: {fontSize: '24px'}}, [icon]),
                        UI.el('div', {}, [
                            UI.el('div', {className: 'warning-title'}, [msg]),
                            UI.el('div', {className: 'warning-message'}, [
                                sub.auto_renew
                                    ? 'Auto-renewal is enabled. Payment will be processed automatically.'
                                    : 'Auto-renewal is disabled. You\'ll need to renew manually.',
                            ]),
                        ]),
                    ]));
                }
            }

            return UI.el('div', {}, banners);
        }
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * ActiveSubscriptionCard
     * ───────────────────────────────────────────────────────────────────────── */

    class ActiveSubscriptionCard {
        constructor(app) {
            this.app = app;
        }

        render(sub, plans, paymentWarning = null) {
            const root = document.getElementById('subscription-card-body');
            UI.render(root, Object.keys(sub).length ? this._renderActive(sub, paymentWarning) : this._renderNoSub(plans));
        }

        // ── Active state ──────────────────────────────────────────────────────

        _renderActive(sub, paymentWarning) {
            // Derive flags from raw API fields — these properties don't exist on the response object
            const isExpired = sub.end_date ? new Date(sub.end_date) < new Date() : false;
            const isPrint = sub.delivery_type === 'print';
            const isDigital = !!sub.download_url;
            const hasStripeSubscription = !!sub.payment_subscription_id;

            // Build a normalised view object so every helper receives consistent properties
            const s = Object.assign({}, sub, {isExpired, isPrint, isDigital, hasStripeSubscription});

            const nodes = [
                new WarningBanners().render(s, paymentWarning),
                this._renewalPrompt(s),
                this._upgradeSection(s),
                this._statusBlock(s),
                this._infoRows(s),
            ];

            if (isPrint && !isExpired) {
                nodes.push(this._deliverySection(s));
            }

            if (isDigital && sub.download_url) {
                nodes.push(this._digitalAccess(s));
            }

            if (hasStripeSubscription && sub.auto_renew) {
                alert('yes')
                nodes.push(this._changeBillingDateBtn(s));
            }

            if (isPrint) {
                nodes.push(this._viewScheduleBtn(s));
            }


            nodes.push(this._autoRenewToggle(s));
            nodes.push(this._actionButtons(s));

            return nodes;
        }

        _statusBlock(sub) {
            return UI.el('div', {className: 'subscription-status'}, [
                UI.el('div', {className: 'status-icon active'}, ['✓']),
                UI.el('div', {}, [
                    UI.el('div', {style: {fontWeight: '700', fontSize: '20px', color: '#1e293b'}}, [sub.plan_name]),
                    UI.el('div', {
                        style: {
                            color: '#64748b',
                            fontSize: '15px',
                            fontWeight: '500'
                        }
                    }, ['Active subscription']),
                ]),
            ]);
        }

        _infoRows(sub) {
            const rows = [
                infoRow('Status', badge('Active', 'success')),
                infoRow('Price', fmt(sub.price, sub.currency)),
                infoRow('Start Date', UI.formatDate(sub.start_date)),
            ];

            if (sub.next_billing_date) {
                rows.push(infoRow('Next Billing Date',
                    UI.el('span', {className: 'info-value', style: {fontWeight: '800', color: '#667eea'}},
                        [UI.formatDate(sub.next_billing_date)])));
            }

            if (sub.end_date) {
                rows.push(infoRow('End Date', UI.formatDate(sub.end_date)));
            }

            rows.push(infoRow('Auto Renew', sub.auto_renew ? '✓ Yes' : '✗ No'));
            rows.push(infoRow('Delivery Type', sub.isPrint ? '📦 Print' : '💻 Digital'));

            return UI.el('div', {}, rows);
        }

        _renewalPrompt(sub) {
            if (!sub || sub.auto_renew || !sub.end_date) return UI.el('span', {});

            const daysUntilEnd = Math.ceil((new Date(sub.end_date) - Date.now()) / 86_400_000);
            if (daysUntilEnd > 30 || daysUntilEnd <= 0) return UI.el('span', {});

            const renewBtn = UI.el('button', {
                className: 'btn btn-primary',
                style: {background: 'white', color: '#667eea'}
            }, ['Renew Subscription']);
            renewBtn.addEventListener('click', () => this.app.renewalModal.open());

            return UI.el('div', {className: 'renewal-prompt'}, [
                UI.el('div', {style: {fontSize: '24px', marginBottom: '8px'}}, ['⏰']),
                UI.el('div', {style: {fontWeight: '700', fontSize: '18px', marginBottom: '8px'}},
                    [`Your subscription expires in ${daysUntilEnd} day${daysUntilEnd > 1 ? 's' : ''}`]),
                UI.el('div', {style: {fontSize: '14px', marginBottom: '16px', opacity: '0.9'}},
                    ['Renew now to continue enjoying uninterrupted access']),
                renewBtn,
            ]);
        }

        _upgradeSection(sub) {
            if (!sub.available_upgrades?.length) return UI.el('span', {});

            const upgrades = sub.available_upgrades.slice(0, 3).map(upgrade => {
                const plan = upgrade.plan;
                const accessNames = (upgrade.new_access ?? []).map(a =>
                    a.identifier.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
                ).join(', ');

                const upgradeLink = UI.el('a', {
                    href: `/${SITE_SLUG}/member/subscriptions/${sub.id}/upgrade?plan_id=${plan.id}`,
                    className: 'btn btn-primary',
                    style: {background: 'white', color: '#667eea'},
                }, ['Upgrade']);

                return UI.el('div', {className: 'upgrade-plan-card'}, [
                    UI.el('div', {}, [
                        UI.el('div', {style: {fontWeight: '700', fontSize: '18px', marginBottom: '4px'}}, [plan.name]),
                        UI.el('div', {style: {fontSize: '14px', opacity: '0.9'}}, [`Unlock: ${accessNames}`]),
                    ]),
                    upgradeLink,
                ]);
            });

            return UI.el('div', {className: 'upgrade-section'}, [
                UI.el('h3', {
                    style: {
                        fontSize: '22px',
                        fontWeight: '700',
                        marginBottom: '16px',
                        color: 'white'
                    }
                }, ['🎁 Available Upgrades']),
                ...upgrades,
            ]);
        }

        _deliverySection(sub) {
            const nodes = [];
            const now = new Date();

            // Next delivery info
            if (sub.next_delivery) {
                const delivery = sub.next_delivery;
                const deliveryDate = new Date(delivery.estimated_delivery_date);
                const daysUntil = Math.ceil((deliveryDate - now) / 86_400_000);

                const valueNode = UI.el('span', {
                    className: 'info-value',
                    style: {display: 'flex', alignItems: 'center', gap: '8px'}
                }, [
                    UI.el('span', {
                        style: {fontWeight: '800', color: '#667eea', fontSize: '18px'}
                    }, [UI.formatDate(delivery.estimated_delivery_date)]),

                    UI.el('span', {
                        className: 'badge',
                        style: {background: 'linear-gradient(135deg,#667eea,#764ba2)', color: 'white', fontSize: '11px'}
                    }, [`Issue #${delivery.issue_number}`]),

                    (daysUntil >= 0 && daysUntil <= 7)
                        ? UI.el('span', {
                            style: {fontSize: '13px', color: '#f59e0b', fontWeight: '600'}
                        }, [`(${daysUntil} day${daysUntil !== 1 ? 's' : ''} away)`])
                        : null,
                ]);

                nodes.push(infoRow('Next Issue Delivery', valueNode));

                // Tracking
                const tracking = delivery.tracking_info;
                if (tracking?.tracking_number) {
                    nodes.push(infoRow('Tracking',
                        UI.el('a', {
                            href: tracking.tracking_url || '#',
                            target: '_blank',
                            style: {color: '#667eea', textDecoration: 'none', fontWeight: '600'},
                        }, [
                            tracking.tracking_number,
                            UI.el('span', {style: {fontSize: '12px'}}, [' ↗'])
                        ])
                    ));
                }
            } else {
                const msg = (sub.end_date && new Date(sub.end_date) < now)
                    ? 'Subscription ended'
                    : 'Schedule being prepared';

                nodes.push(infoRow('Next Issue Delivery',
                    UI.el('span', {
                        style: {color: '#64748b', fontSize: '14px'}
                    }, [msg])
                ));
            }

            // Shipping address (async)
            const addrValueEl = UI.el('span', {
                className: 'info-value',
                style: {fontSize: '14px', color: '#64748b'}
            }, ['Loading…']);

            nodes.push(infoRow('Shipping Address', addrValueEl));

            fetch(`/api/${SITE_SLUG}/member/current-address`)
                .then(r => r.json())
                .then(json => {
                    const addr = json?.data?.address;

                    if (addr) {
                        const lines = [addr.address_line_1];
                        if (addr.address_line_2) lines.push(addr.address_line_2);
                        lines.push(`${addr.city}, ${addr.postcode}`);

                        addrValueEl.style.lineHeight = '1.6';
                        addrValueEl.style.color = '#1e293b';
                        addrValueEl.innerHTML = lines.map(l => UI.esc(l)).join('<br>');
                    } else {
                        addrValueEl.innerHTML = `<a href="/${SITE_SLUG}/member/addresses" style="color:#667eea;">Add shipping address</a>`;
                    }
                })
                .catch(() => {
                    addrValueEl.innerHTML = `<a href="/${SITE_SLUG}/member/addresses" style="color:#667eea;">Add shipping address</a>`;
                });

            // ✅ Clean pause logic (based on your domain: pause ends in future = active)
            const isDeliveryPaused =
                sub.delivery_paused === true &&
                sub.delivery_pause_start &&
                sub.delivery_pause_end &&
                new Date(sub.delivery_pause_start) <= now &&
                new Date(sub.delivery_pause_end) > now;

            // Pause banner
            if (isDeliveryPaused) {
                const pauseEnd = new Date(sub.delivery_pause_end);
                const daysRemaining = Math.ceil((pauseEnd - now) / 86_400_000);

                nodes.push(UI.el('div', {
                    style: {
                        background: '#fef3c7',
                        borderLeft: '4px solid #f59e0b',
                        padding: '16px',
                        borderRadius: '8px',
                        marginBottom: '24px',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '12px'
                    }
                }, [
                    UI.el('span', {style: {fontSize: '24px'}}, ['⏸️']),
                    UI.el('div', {}, [
                        UI.el('div', {
                            style: {fontWeight: '600', color: '#92400e'}
                        }, ['Delivery Paused']),

                        UI.el('div', {
                            style: {fontSize: '14px', color: '#64748b', marginTop: '4px'}
                        }, [
                            `Your deliveries are paused until ${UI.formatDate(sub.delivery_pause_end)} (${daysRemaining} days remaining)`
                        ]),
                    ]),
                ]));
            }

            // Pause / Resume button
            if (!sub.isExpired) {
                const actionBtn = isDeliveryPaused
                    ? UI.el('button', {className: 'btn btn-primary'}, ['▶️ Resume Delivery Now'])
                    : UI.el('button', {className: 'btn btn-secondary'}, ['⏸️ Pause Delivery']);

                actionBtn.addEventListener('click', () => {
                    if (isDeliveryPaused) {
                        this.app.resumeDelivery(sub.id);
                    } else {
                        this.app.pauseModal.open(sub.id);
                    }
                });

                nodes.push(UI.el('div', {className: 'btn-group'}, [actionBtn]));
            }

            return UI.el('div', {}, nodes);
        }

        _digitalAccess(sub) {
            return UI.el('div', {}, [
                infoRow('Digital Access',
                    UI.el('a', {
                            href: sub.download_url,
                            style: {color: '#667eea', textDecoration: 'none', fontWeight: '600'}
                        },
                        ['Download Now →'])),
                infoRow('Download Expires', UI.formatDate(sub.download_expires_at)),
            ]);
        }

        _changeBillingDateBtn(sub) {
            const btn = UI.el('button', {className: 'btn btn-secondary'}, ['📅 Change Billing Date']);
            btn.addEventListener('click', () => this.app.billingModal.open(sub.id, sub.next_billing_date));
            return UI.el('div', {className: 'btn-group'}, [btn]);
        }

        _viewScheduleBtn(sub) {
            return UI.el('div', {className: 'btn-group', style: {marginTop: '24px'}}, [
                UI.el('a', {
                    href: `/${SITE_SLUG}/member/subscriptions/${sub.id}/issue-deliveries`,
                    className: 'btn btn-primary',
                }, ['📅 View Issue Delivery Schedule']),
            ]);
        }

        _autoRenewToggle(sub) {
            if (sub.isExpired) return UI.el('span', {});

            const checkbox = UI.el('input', {
                type: 'checkbox',
                id: 'auto-renew-toggle',
                style: {width: '18px', height: '18px', cursor: 'pointer', accentColor: '#667eea'},
            });
            if (sub.auto_renew) checkbox.checked = true;
            checkbox.addEventListener('change', e => this.app.updateAutoRenew(sub.id, e.target.checked));

            return infoRow('Auto-Renewal',
                UI.el('label', {style: {display: 'flex', alignItems: 'center', gap: '10px', cursor: 'pointer'}}, [
                    checkbox,
                    UI.el('span', {
                        style: {
                            fontSize: '14px',
                            color: '#64748b'
                        }
                    }, ['Automatically renew at end of billing period']),
                ]));
        }

        _actionButtons(sub) {
            const isCancelling = sub.status === 'active' && sub.cancelled_at && !sub.isExpired;
            const btns = [];

            if (isCancelling) {
                const btn = UI.el('button', {className: 'btn btn-primary'}, ['Reactivate Subscription']);
                btn.addEventListener('click', () => this.app.reactivateSubscription(sub.id));
                btns.push(btn);
            } else if (!sub.isExpired) {
                const btn = UI.el('button', {className: 'btn btn-danger'}, ['Cancel Subscription']);
                btn.addEventListener('click', () => this.app.cancelSubscription(sub.id));
                btns.push(btn);
            }

            return btns.length ? UI.el('div', {className: 'btn-group'}, btns) : UI.el('span', {});
        }

        // ── No-subscription state ─────────────────────────────────────────────

        _renderNoSub(plans) {
            const statusBlock = UI.el('div', {className: 'subscription-status'}, [
                UI.el('div', {className: 'status-icon inactive'}, ['✗']),
                UI.el('div', {}, [
                    UI.el('div', {
                        style: {
                            fontWeight: '700',
                            fontSize: '20px',
                            color: '#1e293b'
                        }
                    }, ['No Active Subscription']),
                    UI.el('div', {
                        style: {
                            color: '#64748b',
                            fontSize: '15px',
                            fontWeight: '500'
                        }
                    }, ['Choose a plan to get started']),
                ]),
            ]);

            if (!plans?.length) {
                return [statusBlock, UI.emptyState({
                    icon: '📭',
                    title: 'No Plans Available',
                    body: 'Please check back later for subscription options.'
                })];
            }

            const planCards = plans.map(plan => {
                const btn = UI.el('button', {className: 'btn btn-primary', style: {width: '100%', marginTop: '12px'}},
                    [`Subscribe to ${plan.name}`]);
                btn.addEventListener('click', () => {
                    if (typeof window.openSubscriptionModal === 'function') {
                        window.openSubscriptionModal(plan.slug);
                    } else {
                        window.location.href = `/${SITE_SLUG}/checkout?plan_slug=${plan.slug}`;
                    }
                });

                return UI.el('div', {
                    style: {
                        padding: '20px',
                        background: 'linear-gradient(135deg,#f8fafc,#f1f5f9)',
                        borderRadius: '12px',
                        border: '2px solid #e2e8f0',
                        marginBottom: '16px'
                    },
                }, [
                    UI.el('div', {
                        style: {
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'flex-start',
                            marginBottom: '12px'
                        }
                    }, [
                        UI.el('div', {}, [
                            UI.el('div', {
                                style: {
                                    fontWeight: '700',
                                    fontSize: '18px',
                                    color: '#1e293b',
                                    marginBottom: '4px'
                                }
                            }, [plan.name]),
                            plan.description
                                ? UI.el('div', {style: {fontSize: '14px', color: '#64748b'}}, [plan.description])
                                : null,
                        ]),
                        UI.el('div', {style: {textAlign: 'right'}}, [
                            UI.el('div', {
                                style: {
                                    fontWeight: '800',
                                    fontSize: '24px',
                                    color: '#667eea'
                                }
                            }, [fmt(plan.price, plan.currency)]),
                            UI.el('div', {style: {fontSize: '12px', color: '#64748b', fontWeight: '600'}},
                                [`per ${plan.billing_period_label ?? 'period'}`]),
                        ]),
                    ]),
                    btn,
                ]);
            });

            const viewAllLink = UI.el('div', {style: {textAlign: 'center', marginTop: '20px'}}, [
                UI.el('a', {
                    href: `/${SITE_SLUG}/member/subscription-plans`,
                    style: {color: '#667eea', textDecoration: 'none', fontWeight: '600', fontSize: '15px'},
                }, ['View All Plans & Compare Features →']),
            ]);

            return [statusBlock, ...planCards, viewAllLink];
        }
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * EmailPreferencesCard
     * ───────────────────────────────────────────────────────────────────────── */

    class EmailPreferencesCard {
        render(summary) {
            const root = document.getElementById('email-prefs-body');

            UI.render(root, [
                UI.el('div', {className: 'subscription-status'}, [
                    UI.el('div', {className: `status-icon ${summary.is_active ? 'active' : 'inactive'}`}, [summary.is_active ? '✓' : '✗']),
                    UI.el('div', {}, [
                        UI.el('div', {style: {fontWeight: '700', fontSize: '20px', color: '#1e293b'}},
                            [summary.is_active ? 'Subscribed' : 'Unsubscribed']),
                        UI.el('div', {
                            style: {
                                color: '#64748b',
                                fontSize: '15px',
                                fontWeight: '500'
                            }
                        }, ['Email notifications']),
                    ]),
                ]),
                infoRow('Email Notifications', badge(summary.email_notifications ? 'Enabled' : 'Disabled', summary.email_notifications ? 'success' : 'danger')),
                infoRow('Frequency', UI.el('span', {className: 'info-value'}, [
                    (summary.frequency || 'weekly').charAt(0).toUpperCase() + (summary.frequency || 'weekly').slice(1),
                ])),
                infoRow('Content Types', String(summary.content_types?.length ? summary.content_types.length : 'All')),
                infoRow('Categories', String(summary.category_preferences?.length ? summary.category_preferences.length : 'All')),
                UI.el('div', {className: 'btn-group'}, [
                    UI.el('a', {href: `/${SITE_SLUG}/member/subscriptions/preferences`, className: 'btn btn-primary'},
                        ['Manage Preferences']),
                ]),
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * HistoryCard
     * ───────────────────────────────────────────────────────────────────────── */

    class HistoryCard {
        render(history) {
            const root = document.getElementById('history-body');

            if (!history?.length) {
                UI.render(root, UI.emptyState({icon: '📋', title: 'No subscription history'}));
                return;
            }

            const headers = ['Plan', 'Type', 'Status', 'Start Date', 'End Date', 'Price'];
            const thead = UI.el('thead', {}, [
                UI.el('tr', {}, headers.map(h => UI.el('th', {}, [h]))),
            ]);

            const tbody = UI.el('tbody', {}, history.map(sub =>
                UI.el('tr', {}, [
                    UI.el('td', {style: {fontWeight: '600'}}, [
                        sub.plan_name,
                        sub.delivery_type
                            ? UI.el('span', {
                                    style: {
                                        fontSize: '12px',
                                        color: '#64748b',
                                        display: 'block',
                                        marginTop: '4px'
                                    }
                                },
                                [sub.download_url ? '💻 Digital' : '📦 Print'])
                            : null,
                    ]),
                    UI.el('td', {}, [
                        UI.el('span', {
                                className: 'badge',
                                style: {
                                    background: sub.type === 'paid' ? '#e0e7ff' : '#f3f4f6',
                                    color: sub.type === 'paid' ? '#3730a3' : '#374151'
                                }
                            },
                            [(sub.type || 'standard').charAt(0).toUpperCase() + (sub.type || 'standard').slice(1)]),
                    ]),
                    UI.el('td', {}, [badge(
                        sub.status.charAt(0).toUpperCase() + sub.status.slice(1),
                        sub.status === 'active' ? 'success' : 'warning',
                    )]),
                    UI.el('td', {}, [UI.formatDate(sub.start_date)]),
                    UI.el('td', {}, [sub.end_date ? UI.formatDate(sub.end_date) : '—']),
                    UI.el('td', {style: {fontWeight: '600'}}, [fmt(sub.price, sub.currency)]),
                ])
            ));

            UI.render(root, UI.el('table', {className: 'history-table'}, [thead, tbody]));
        }
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * PauseDeliveryModal
     * ───────────────────────────────────────────────────────────────────────── */

    class PauseDeliveryModal {
        constructor(app) {
            this.app = app;
            this.modal = document.getElementById('pauseDeliveryModal');

            document.getElementById('closePauseModal').addEventListener('click', () => this.close());
            document.getElementById('cancelPauseBtn').addEventListener('click', () => this.close());
            document.getElementById('confirmPauseBtn').addEventListener('click', () => this._confirm());
        }

        open(subscriptionId) {
            document.getElementById('pauseSubscriptionId').value = subscriptionId;

            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const twoWeeks = new Date();
            twoWeeks.setDate(twoWeeks.getDate() + 14);

            document.getElementById('pauseStartDate').value = tomorrow.toISOString().split('T')[0];
            document.getElementById('pauseEndDate').value = twoWeeks.toISOString().split('T')[0];
            document.getElementById('pauseReason').value = '';

            this.modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        close() {
            this.modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        async _confirm() {
            const btn = document.getElementById('confirmPauseBtn');
            const subscriptionId = document.getElementById('pauseSubscriptionId').value;
            const pauseStart = document.getElementById('pauseStartDate').value;
            const pauseEnd = document.getElementById('pauseEndDate').value;
            const reason = document.getElementById('pauseReason').value;

            if (!pauseStart || !pauseEnd) {
                UI.toast('Please select both start and end dates.', 'error');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Pausing…';

            try {
                await api(`${API_BASE}/member/subscriptions/${subscriptionId}/pause-delivery`, {
                    method: 'POST',
                    body: JSON.stringify({pause_start: pauseStart, pause_end: pauseEnd, reason}),
                });
                UI.toast('Delivery paused successfully.', 'success');
                this.close();
                await this.app.init();
            } catch (e) {
                UI.toast(e.message || 'Failed to pause delivery.', 'error');
                btn.disabled = false;
                btn.textContent = 'Pause Delivery';
            }
        }
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * BillingDateModal
     * ───────────────────────────────────────────────────────────────────────── */

    class BillingDateModal {
        constructor(app) {
            this.app = app;
            this.modal = document.getElementById('billingDateModal');

            document.getElementById('closeBillingModal').addEventListener('click', () => this.close());
            document.getElementById('cancelBillingBtn').addEventListener('click', () => this.close());
            document.getElementById('confirmBillingBtn').addEventListener('click', () => this._confirm());
            document.getElementById('billingDaySelect').addEventListener('change', () => this._preview());
        }

        open(subscriptionId, nextBillingDate) {
            document.getElementById('billingSubscriptionId').value = subscriptionId;

            const currentDay = nextBillingDate ? new Date(nextBillingDate).getDate() : 1;
            UI.setTxt('currentBillingDay', currentDay);
            document.getElementById('billingDaySelect').value = currentDay;
            document.getElementById('prorationPreview').style.display = 'none';

            this.modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        close() {
            this.modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        async _preview() {
            const day = document.getElementById('billingDaySelect').value;
            const subscriptionId = document.getElementById('billingSubscriptionId').value;

            try {
                const result = await api(`${API_BASE}/member/subscriptions/${subscriptionId}/preview-billing-change`, {
                    method: 'POST',
                    body: JSON.stringify({day_of_month: day}),
                });

                const preview = result.data;
                let msg = `Your next billing date will be <strong>${UI.esc(preview.new_billing_date)}</strong>.<br>`;

                if (Math.abs(preview.proration_amount) > 0.01) {
                    msg += preview.is_credit
                        ? `You'll receive a credit of <strong>${fmt(Math.abs(preview.proration_amount))}</strong> for unused days.`
                        : `You'll be charged an additional <strong>${fmt(preview.proration_amount)}</strong> to align your billing cycle.`;
                } else {
                    msg += 'No additional charges or credits will apply.';
                }

                UI.setHtml('prorationDetails', msg);
                document.getElementById('prorationPreview').style.display = 'block';
            } catch (_) {
                // Preview failure is non-critical — silently ignore.
            }
        }

        async _confirm() {
            const btn = document.getElementById('confirmBillingBtn');
            const day = document.getElementById('billingDaySelect').value;
            const subscriptionId = document.getElementById('billingSubscriptionId').value;

            btn.disabled = true;
            btn.textContent = 'Updating…';

            try {
                await api(`${API_BASE}/member/subscriptions/${subscriptionId}/update-billing-date`, {
                    method: 'POST',
                    body: JSON.stringify({day_of_month: day}),
                });
                UI.toast('Billing date updated successfully.', 'success');
                this.close();
                await this.app.init();
            } catch (e) {
                UI.toast(e.message || 'Failed to update billing date.', 'error');
                btn.disabled = false;
                btn.textContent = 'Update Billing Date';
            }
        }
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * RenewalModal
     * ───────────────────────────────────────────────────────────────────────── */

    class RenewalModal {
        #sub;

        constructor() {
            this.modal = document.getElementById('renewalModal');

            document.getElementById('closeRenewalModal').addEventListener('click', () => this.close());
            document.getElementById('cancelRenewalBtn').addEventListener('click', () => this.close());
            document.getElementById('renewalSubmitBtn').addEventListener('click', () => this._processRenewal());
            document.getElementById('updateAddressBtn').addEventListener('click', () => {
                window.location.href = `/${SITE_SLUG}/member/addresses?return=/${SITE_SLUG}/member/subscriptions`;
            });
        }

        /** Must be called after API data loads so we have subscription context. */
        setSub(sub) {
            this.#sub = sub;
        }

        open() {
            if (!this.#sub) return;

            const sub = this.#sub;

            UI.setTxt('currentPlanName', `${sub.plan_name} — ${sub.delivery_type === 'print' ? 'Print' : 'Digital'}`);

            const addressSection = document.getElementById('renewalAddressSection');
            if (sub.delivery_type === 'print') {
                addressSection.style.display = 'block';
                this._loadAddress();
            } else {
                addressSection.style.display = 'none';
            }

            // Highlight renewal-type options on click
            this.modal.querySelectorAll('.renewal-option').forEach(option => {
                option.addEventListener('click', function () {
                    document.querySelectorAll('.renewal-option').forEach(opt => {
                        opt.style.borderColor = '#e2e8f0';
                        opt.style.background = 'white';
                    });
                    this.style.borderColor = '#667eea';
                    this.style.background = '#f0f4ff';
                    this.querySelector('input').checked = true;
                });
            });

            this.modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        close() {
            this.modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        async _loadAddress() {
            try {
                const response = await fetch(`/${SITE_SLUG}/api/member/current-address`);
                const responseData = await response.json();
                const addr = responseData?.data?.address;

                if (addr) {
                    const lines = [UI.esc(addr.address_line_1)];
                    if (addr.address_line_2) lines.push(UI.esc(addr.address_line_2));
                    lines.push(`${UI.esc(addr.city)}, ${UI.esc(addr.postcode)}`);
                    UI.setHtml('currentAddress', lines.join('<br>'));
                }
            } catch (e) {
                console.error('Failed to load address:', e);
            }
        }

        async _processRenewal() {
            const sub = this.#sub;
            const renewalType = document.querySelector('input[name="renewal_type"]:checked')?.value ?? 'fixed';
            const submitBtn = document.getElementById('renewalSubmitBtn');

            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing…';

            try {
                window.location.href = `/${SITE_SLUG}/checkout?plan_id=${sub.plan_id}&renewal=true&type=${renewalType}&delivery=${sub.delivery_type}`;
            } catch (e) {
                UI.toast('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Continue to Payment';
            }
        }
    }

    /* ─────────────────────────────────────────────────────────────────────────
     * SubscriptionsApp — orchestrates all components
     * ───────────────────────────────────────────────────────────────────────── */

    class SubscriptionsApp {
        constructor() {
            this.subscriptionCard = new ActiveSubscriptionCard(this);
            this.emailPrefsCard = new EmailPreferencesCard();
            this.historyCard = new HistoryCard();
            this.pauseModal = new PauseDeliveryModal(this);
            this.billingModal = new BillingDateModal(this);
            this.renewalModal = new RenewalModal();
        }

        async init() {
            try {
                const json = await api(`${API_BASE}/member/subscriptions/overview`);
                const {activeSubscription, subscriptionHistory, subscriptionSummary, plans, payment_warning} = json;

                // Give the renewal modal access to the active subscription data
                if (activeSubscription) {
                    this.renewalModal.setSub(activeSubscription);
                }

                this.subscriptionCard.render(activeSubscription, plans, payment_warning ?? null);
                this.emailPrefsCard.render(subscriptionSummary);
                this.historyCard.render(subscriptionHistory);
            } catch (e) {
                console.log('e', e)
                UI.toast('Failed to load subscription data. Please refresh the page.', 'error');
            }
        }

        /* ── Actions ─────────────────────────────────────────────────────────── */

        async cancelSubscription(subscriptionId) {
            let cancelAtPeriodEnd;
            try {
                cancelAtPeriodEnd = await ConfirmDialog.show({
                    title: 'Cancel Subscription',
                    message: 'How would you like to cancel your subscription?',
                    confirmText: 'Cancel Subscription',
                    type: 'danger',
                    showOptions: true,
                    options: [
                        {
                            label: 'Cancel at end of billing period',
                            value: true,
                            description: "You'll keep access until the current period ends (Recommended)"
                        },
                        {label: 'Cancel immediately', value: false, description: 'Your access ends right away'},
                    ],
                });
            } catch {
                return; // user dismissed
            }

            try {
                await api(`${API_BASE}/member/subscriptions/${subscriptionId}/cancel`, {
                    method: 'POST',
                    body: JSON.stringify({cancel_at_period_end: cancelAtPeriodEnd}),
                });
                UI.toast('Subscription cancelled successfully.', 'success');
                await this.init();
            } catch (e) {
                if (e?.message) UI.toast(e.message, 'error');
            }
        }

        async reactivateSubscription(subscriptionId) {
            try {
                await ConfirmDialog.show({
                    title: 'Reactivate Subscription',
                    message: 'Reactivate your subscription? Billing will resume on the next scheduled date.',
                    confirmText: 'Reactivate',
                    type: 'success',
                });
            } catch {
                return; // user dismissed
            }

            try {
                await api(`${API_BASE}/member/subscriptions/${subscriptionId}/reactivate`, {method: 'POST'});
                UI.toast('Subscription reactivated successfully.', 'success');
                await this.init();
            } catch (e) {
                if (e?.message) UI.toast(e.message, 'error');
            }
        }

        async updateAutoRenew(subscriptionId, enabled) {
            try {
                await api(`${API_BASE}/member/subscriptions/${subscriptionId}/auto-renew`, {
                    method: 'POST',
                    body: JSON.stringify({auto_renew: enabled, consent_given: enabled}),
                });
                UI.toast(enabled ? 'Auto-renewal enabled.' : 'Auto-renewal disabled.', 'success');
            } catch (e) {
                UI.toast(e.message || 'Failed to update auto-renewal.', 'error');
                const toggle = document.getElementById('auto-renew-toggle');
                if (toggle) toggle.checked = !enabled;
            }
        }

        async resumeDelivery(subscriptionId) {
            try {
                await ConfirmDialog.show({
                    title: 'Resume Delivery',
                    message: 'Resume delivery now? Your next issue will be delivered as scheduled.',
                    confirmText: 'Resume',
                    type: 'info',
                });
            } catch {
                return; // user dismissed
            }

            try {
                await api(`${API_BASE}/member/subscriptions/${subscriptionId}/resume-delivery`, {method: 'POST'});
                UI.toast('Delivery resumed successfully.', 'success');
                await this.init();
            } catch (e) {
                if (e?.message) UI.toast(e.message, 'error');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => new SubscriptionsApp().init());
</script>

@include('components/subscription-modal', ['subscriptionModalData' => $subscriptionModalData])
</body>
</html>