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
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
        }

        /* ── Toast ─────────────────────────────────────── */
        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
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
            box-shadow: var(--shadow-lg);
            pointer-events: all;
            animation: toastIn 0.3s ease;
            max-width: 380px;
        }

        .toast.success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .toast.info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid var(--primary-color);
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            font-size: 1.1rem;
            padding: 0;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes toastOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        /* ── Layout ────────────────────────────────────── */
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        /* ── Buttons ───────────────────────────────────── */
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
            font-size: 0.9375rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
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

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-full {
            width: 100%;
            justify-content: center;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
        }

        /* ── Newsletter grid ───────────────────────────── */
        .newsletters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .newsletter-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            position: relative;
            border: 2px solid transparent;
        }

        .newsletter-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        .newsletter-card.subscribed {
            border-color: var(--success-color);
            background: linear-gradient(to bottom, #f0fdf4, white);
        }

        .newsletter-card.locked {
            opacity: 0.75;
        }

        .newsletter-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        .newsletter-icon {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .status-badge {
            padding: 0.3rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.subscribed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.unsubscribed {
            background: #f3f4f6;
            color: #6b7280;
        }

        .status-badge.locked {
            background: #fee2e2;
            color: #991b1b;
        }

        .lock-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--warning-color);
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .newsletter-checkbox {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .newsletter-content {
            margin: 0.75rem 0 1rem;
        }

        .newsletter-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .newsletter-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.6;
            margin-bottom: 0.75rem;
        }

        .access-message {
            background: #fffbeb;
            border-left: 4px solid var(--warning-color);
            padding: 0.875rem 1rem;
            margin: 0.75rem 0;
            border-radius: 0.5rem;
        }

        .access-message p {
            color: #92400e;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .newsletter-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-top: 0.875rem;
            border-top: 1px solid var(--border-color);
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* ── Floating action bar ───────────────────────── */
        .floating-action-bar {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 1rem 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: none;
            align-items: center;
            gap: 1.5rem;
            z-index: 100;
            border: 1px solid var(--border-color);
        }

        .floating-action-bar.show {
            display: flex;
        }

        .selected-count {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* ── Empty / skeleton ──────────────────────────── */
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
        }

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

        .skeleton-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        /* ── Newsletter modal (subscribe) ──────────────── */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .modal-backdrop.show {
            display: flex;
        }

        .modal-box {
            background: white;
            border-radius: 1rem;
            width: 100%;
            max-width: 720px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: modalIn 0.25s ease;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(.96);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-header {
            padding: 1.75rem 2rem;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .modal-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-subtitle {
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }

        .modal-close-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.75rem;
            color: var(--text-secondary);
            line-height: 1;
            padding: 0;
            flex-shrink: 0;
        }

        .modal-close-btn:hover {
            color: var(--text-primary);
        }

        .modal-body {
            padding: 1.75rem 2rem;
        }

        .modal-footer {
            padding: 1.25rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-light);
            position: sticky;
            bottom: 0;
            border-radius: 0 0 1rem 1rem;
        }

        .select-all-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            font-size: 0.9375rem;
        }

        .select-all-container input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
            cursor: pointer;
        }

        .modal-newsletter-list {
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
        }

        .modal-newsletter-item {
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .modal-newsletter-item:hover {
            border-color: var(--primary-color);
            background: #f8f9ff;
        }

        .modal-newsletter-item.selected {
            border-color: var(--primary-color);
            background: linear-gradient(to right, #eff6ff, #f8f9ff);
        }

        .modal-newsletter-item.already-subscribed {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f9fafb;
        }

        .modal-newsletter-item.already-subscribed:hover {
            border-color: var(--border-color);
            background: #f9fafb;
        }

        .modal-item-checkbox {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
            margin-top: 0.2rem;
            accent-color: var(--primary-color);
            cursor: pointer;
        }

        .modal-item-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.3rem;
        }

        .modal-item-title .already-label {
            color: var(--success-color);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .modal-item-desc {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        /* ── Upgrade modal ─────────────────────────────── */
        .upgrade-modal-box {
            max-width: 800px;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
            margin-top: 1rem;
        }
        .plan-card {
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            position: relative;
            transition: all 0.2s;
        }

        .plan-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-lg);
        }

        .plan-card.featured {
            border-color: var(--primary-color);
            background: #f5f7ff;
        }
        .plan-badge {
            position: absolute;
            top: -12px;
            right: 1rem;
            background: var(--primary-color);
            color: white;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .plan-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .plan-price {
            margin-bottom: 0.75rem;
        }

        .plan-price .price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .plan-price .period {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-left: 0.25rem;
        }

        .plan-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1rem;
            min-height: 2.5rem;
        }

        .plan-features {
            list-style: none;
            margin: 0 0 1.25rem;
            padding: 0;
        }

        .plan-features li {
            padding: 0.4rem 0 0.4rem 1.5rem;
            position: relative;
            font-size: 0.875rem;
        }

        .plan-features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success-color);
            font-weight: 700;
        }

        .selected-plan-summary {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }

        .selected-plan-summary h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .selected-plan-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .upgrade-step {
            display: none;
        }

        .upgrade-step.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9375rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .voucher-row {
            display: flex;
            gap: 0.5rem;
        }

        .voucher-row .form-control {
            flex: 1;
        }

        .voucher-message {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .voucher-message.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .voucher-message.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .stripe-card-element {
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 0.875rem;
            background: white;
        }

        .stripe-card-element.StripeElement--focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .stripe-card-element.StripeElement--invalid {
            border-color: var(--danger-color);
        }

        .card-error-text {
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: block;
        }

        .payment-error-box {
            background: #fef2f2;
            color: #991b1b;
            padding: 0.875rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        /* Processing / success states */
        .state-container {
            text-align: center;
            padding: 3rem 1rem;
        }
        .spinner {
            border: 3px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            width: 48px;
            height: 48px;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1.25rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        .success-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--success-color);
            color: white;
            font-size: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
        }

        .button-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .newsletters-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .btn-group {
                flex-wrap: wrap;
            }

            .floating-action-bar {
                left: 1rem;
                right: 1rem;
                transform: none;
                flex-direction: column;
                gap: 1rem;
            }

            .plans-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<div class="toast-container" id="toastContainer"></div>

<main class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">My Newsletters</h1>
            <p style="color:var(--text-secondary);margin-top:0.5rem;">Manage your newsletter subscriptions</p>
        </div>
        <div class="btn-group">
            <button onclick="openNewsletterModal()" class="btn btn-primary" id="subscribeBtn" disabled>
                📧 Subscribe to Newsletters
            </button>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/dashboard" class="btn btn-secondary">
                ← Back to Dashboard
            </a>
        </div>
    </div>

    <h2 class="section-title"><span>📬</span> All Available Newsletters</h2>

    <div id="newsletters-grid" class="newsletters-grid">
        <!-- Skeleton placeholders while loading -->
        <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="skeleton-card">
                <div style="display:flex;justify-content:space-between;margin-bottom:1rem;">
                    <div class="skeleton" style="width:3rem;height:3rem;border-radius:.75rem;"></div>
                    <div class="skeleton" style="width:6rem;height:1.5rem;border-radius:1rem;"></div>
                </div>
                <div class="skeleton" style="height:1.25rem;width:70%;margin-bottom:.75rem;"></div>
                <div class="skeleton" style="height:1rem;margin-bottom:.5rem;"></div>
                <div class="skeleton" style="height:1rem;width:80%;margin-bottom:1.5rem;"></div>
                <div class="skeleton" style="height:2.5rem;border-radius:.5rem;"></div>
            </div>
        <?php endfor; ?>
    </div>
</main>

<!-- Floating selection bar -->
<div id="floatingActionBar" class="floating-action-bar">
    <span class="selected-count"><span id="selectedCount">0</span> newsletter(s) selected</span>
    <div class="btn-group">
        <button onclick="subscribeSelected()" class="btn btn-primary">Subscribe to Selected</button>
        <button onclick="clearSelection()" class="btn btn-secondary">Clear</button>
    </div>
</div>

<!-- Subscribe modal -->
<div id="newsletterModal" class="modal-backdrop" onclick="handleBackdropClick(event,'newsletterModal')">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-row">
                <h2 class="modal-title">Subscribe to Newsletters</h2>
                <button class="modal-close-btn" onclick="closeNewsletterModal()">×</button>
            </div>
            <p class="modal-subtitle">Select the newsletters you'd like to receive</p>
        </div>
        <div class="modal-body">
            <div class="modal-newsletter-list" id="modalNewsletterList">
                <div style="text-align:center;padding:2rem;color:var(--text-secondary);">Loading…</div>
            </div>
        </div>
        <div class="modal-footer">
            <label class="select-all-container">
                <input type="checkbox" id="selectAllCheckbox" onchange="selectAllNewsletters(this.checked)">
                Select All Available
            </label>
            <div class="btn-group">
                <button onclick="closeNewsletterModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="subscribeModalSelected()" class="btn btn-primary">Subscribe to Selected</button>
            </div>
        </div>
    </div>
</div>

<!-- Upgrade modal -->
<div id="upgradeModal" class="modal-backdrop" onclick="handleBackdropClick(event,'upgradeModal')">
    <div class="modal-box upgrade-modal-box">
        <div class="modal-header">
            <div class="modal-header-row">
                <h2 class="modal-title">Upgrade Your Subscription</h2>
                <button class="modal-close-btn" onclick="closeUpgradeModal()">×</button>
            </div>
            <p class="modal-subtitle">Choose a plan to unlock <strong id="upgradeNewsletterTitle"></strong></p>
        </div>
        <div class="modal-body" id="upgradeModalBody">
            <!-- Steps injected here -->
        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
    /* ─── Config ─────────────────────────────────────────── */
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    const SITE_ID = <?= \App\Framework\Support\SiteContext::getId() ?>;
    const STRIPE_KEY = '<?= htmlspecialchars($_ENV['STRIPE_PUBLIC_KEY'] ?? '') ?>';

    /* ─── State ──────────────────────────────────────────── */
    let newslettersWithAccess = [];
    let availableNewsletters = [];
    let subscriptions = [];       // [{id, newsletter_id}]
    let selectedNewsletters = new Set();

    let stripe = null;
    let elements = null;
    let cardElement = null;
    let upgradeStep = 'plans';
    let selectedNewsletterId = null;
    let selectedPlanId = null;
    let selectedPlanPrice = null;
    let selectedPlanCurrency = null;

    /* ─── Toast ──────────────────────────────────────────── */
    function showToast(message, type = 'info', duration = 5000) {
        const icons = {success: '✓', error: '✕', info: 'ℹ'};
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span>${icons[type] || 'ℹ'}</span>
            <span style="flex:1;">${esc(message)}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>`;
        container.appendChild(toast);
        setTimeout(() => {
            if (!toast.parentElement) return;
            toast.style.animation = 'toastOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    /* ─── Boot ───────────────────────────────────────────── */
    async function loadNewsletters() {
        try {
            const res = await fetch(`/api/${SITE}/member/newsletters`);
            if (!res.ok) throw new Error('Server error ' + res.status);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load');

            newslettersWithAccess = json.data.newsletters_with_access;
            availableNewsletters = json.data.available_newsletters;
            subscriptions = json.data.subscriptions;

            renderGrid();
            populateModal();
            document.getElementById('subscribeBtn').disabled = false;

        } catch (e) {
            console.error(e);
            showToast('Failed to load newsletters. Please refresh.', 'error');
            document.getElementById('newsletters-grid').innerHTML = `
                <div class="empty-state" style="grid-column:1/-1;">
                    <div class="empty-state-icon">⚠️</div>
                    <h3>Failed to Load</h3>
                    <p>Please try refreshing the page.</p>
                    <button class="btn btn-primary" style="margin-top:1.5rem;" onclick="loadNewsletters()">Retry</button>
                </div>`;
        }
    }

    /* ─── Render main grid ───────────────────────────────── */
    function renderGrid() {
        const grid = document.getElementById('newsletters-grid');

        if (!newslettersWithAccess.length) {
            grid.innerHTML = `
                <div class="empty-state" style="grid-column:1/-1;">
                    <div class="empty-state-icon">📧</div>
                    <h3>No Newsletters Available</h3>
                    <p>Check back later for newsletter options.</p>
                </div>`;
            return;
        }

        grid.innerHTML = newslettersWithAccess.map(item => {
            const isLocked = !item.has_access;
            const isSubscribed = item.is_subscribed;

            const statusBadge = isLocked
                ? `<span class="status-badge locked">Requires Upgrade</span>`
                : `<span class="status-badge ${isSubscribed ? 'subscribed' : 'unsubscribed'}">
                       ${isSubscribed ? '✓ Subscribed' : 'Not Subscribed'}
                   </span>`;

            const topRight = isLocked
                ? `<div class="lock-badge">🔒 Locked</div>`
                : (!isSubscribed
                    ? `<input type="checkbox" class="newsletter-checkbox"
                          data-newsletter-id="${item.id}" onchange="updateSelection()">`
                    : '');

            const accessMsg = (isLocked && item.access_message)
                ? `<div class="access-message"><p>${esc(item.access_message)}</p></div>`
                : '';

            const actionBtn = isLocked
                ? `<button onclick="showUpgradeModal('${esc(item.access_reason)}', ${item.id}, '${esc(item.title)}')"
                          class="btn btn-warning btn-sm btn-full">🔓 Upgrade to Access</button>`
                : isSubscribed
                    ? `<button onclick="quickUnsubscribe(${item.id})"
                              class="btn btn-danger btn-sm btn-full">Unsubscribe</button>`
                    : `<button onclick="quickSubscribe(${item.id})"
                              class="btn btn-primary btn-sm btn-full">Subscribe</button>`;

            return `
            <div class="newsletter-card ${isSubscribed ? 'subscribed' : ''} ${isLocked ? 'locked' : ''}"
                 data-newsletter-id="${item.id}">
                ${topRight}
                <div class="newsletter-header">
                    <div class="newsletter-icon">📧</div>
                    <div>${statusBadge}</div>
                </div>
                <div class="newsletter-content">
                    <h3 class="newsletter-title">${esc(item.title)}</h3>
                    ${item.content ? `<p class="newsletter-description">${esc(item.content)}</p>` : ''}
                    ${accessMsg}
                    <div class="newsletter-meta">
                        <div class="meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                            ${esc(ucFirst(item.interval))}
                        </div>
                        ${item.active ? `<div class="meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg> Active</div>` : ''}
                    </div>
                </div>
                ${actionBtn}
            </div>`;
        }).join('');
    }

    /* ─── Populate subscribe modal ───────────────────────── */
    function populateModal() {
        const list = document.getElementById('modalNewsletterList');
        if (!availableNewsletters.length) {
            list.innerHTML = '<p style="text-align:center;color:var(--text-secondary);">No newsletters available.</p>';
            return;
        }
        list.innerHTML = availableNewsletters.map(n => {
            const subbed = n.is_subscribed;
            return `
            <div class="modal-newsletter-item ${subbed ? 'already-subscribed' : ''}"
                 data-newsletter-id="${n.id}"
                 onclick="${!subbed ? 'toggleModalItem(this)' : ''}">
                <input type="checkbox" class="modal-item-checkbox"
                    data-newsletter-id="${n.id}"
                    ${subbed ? 'disabled checked' : ''}
                    onclick="event.stopPropagation()">
                <div style="flex:1;">
                    <div class="modal-item-title">
                        ${esc(n.title)}
                        ${subbed ? '<span class="already-label"> (Already subscribed)</span>' : ''}
                    </div>
                    <div class="modal-item-desc">
                        ${esc(n.content || 'Stay updated with our latest content')}
                        <br><strong>Frequency:</strong> ${esc(ucFirst(n.interval))}
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    /* ─── Selection helpers ──────────────────────────────── */
    function updateSelection() {
        selectedNewsletters.clear();
        document.querySelectorAll('.newsletter-checkbox:checked').forEach(cb => {
            selectedNewsletters.add(parseInt(cb.dataset.newsletterId));
        });
        const bar = document.getElementById('floatingActionBar');
        document.getElementById('selectedCount').textContent = selectedNewsletters.size;
        bar.classList.toggle('show', selectedNewsletters.size > 0);
    }

    function clearSelection() {
        document.querySelectorAll('.newsletter-checkbox').forEach(cb => cb.checked = false);
        updateSelection();
    }

    /* ─── Subscribe / unsubscribe ────────────────────────── */
    async function quickSubscribe(newsletterId) {
        try {
            const res = await fetch(`/api/${SITE}/member/newsletter/signup`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({newsletter_id: newsletterId})
            });
            const data = await res.json();
            if (data.success) {
                showToast('Successfully subscribed!', 'success');
                setTimeout(loadNewsletters, 900);
            } else {
                showToast(data.message || 'Failed to subscribe.', 'error');
            }
        } catch {
            showToast('Failed to subscribe.', 'error');
        }
    }

    async function quickUnsubscribe(newsletterId) {
        if (!confirm('Are you sure you want to unsubscribe from this newsletter?')) return;

        const sub = subscriptions.find(s => s.newsletter_id === newsletterId);
        if (!sub) {
            showToast('Subscription not found.', 'error');
            return;
        }

        try {
            const res = await fetch(`/api/${SITE}/member/newsletters/unsubscribe`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({subscriber_id: sub.id})
            });
            const data = await res.json();
            if (data.success) {
                showToast('Successfully unsubscribed.', 'success');
                setTimeout(loadNewsletters, 900);
            } else {
                showToast(data.message || 'Failed to unsubscribe.', 'error');
            }
        } catch {
            showToast('Failed to unsubscribe.', 'error');
        }
    }

    async function subscribeSelected() {
        if (!selectedNewsletters.size) {
            showToast('Please select at least one newsletter.', 'error');
            return;
        }
        await bulkSubscribe(Array.from(selectedNewsletters));
        clearSelection();
    }

    async function subscribeModalSelected() {
        const ids = Array.from(document.querySelectorAll('.modal-item-checkbox:checked:not([disabled])'))
            .map(cb => parseInt(cb.dataset.newsletterId));
        if (!ids.length) {
            showToast('Please select at least one newsletter.', 'error');
            return;
        }
        await bulkSubscribe(ids);
        closeNewsletterModal();
    }

    async function bulkSubscribe(ids) {
        try {
            const res = await fetch(`/api/${SITE}/member/newsletters/bulk-subscribe`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({newsletter_ids: ids})
            });
            const data = await res.json();
            if (data.success) {
                showToast(`Successfully subscribed to ${ids.length} newsletter(s).`, 'success');
                setTimeout(loadNewsletters, 900);
            } else {
                showToast(data.message || 'Failed to subscribe.', 'error');
            }
        } catch {
            showToast('Failed to subscribe.', 'error');
        }
    }

    /* ─── Subscribe modal ────────────────────────────────── */
    function openNewsletterModal() {
        document.getElementById('newsletterModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeNewsletterModal() {
        document.getElementById('newsletterModal').classList.remove('show');
        document.body.style.overflow = '';
        document.querySelectorAll('.modal-item-checkbox:not([disabled])').forEach(cb => {
            cb.checked = false;
            cb.closest('.modal-newsletter-item')?.classList.remove('selected');
        });
        document.getElementById('selectAllCheckbox').checked = false;
    }

    function toggleModalItem(item) {
        const cb = item.querySelector('.modal-item-checkbox');
        if (!cb.disabled) {
            cb.checked = !cb.checked;
            item.classList.toggle('selected', cb.checked);
        }
    }

    function selectAllNewsletters(checked) {
        document.querySelectorAll('.modal-item-checkbox:not([disabled])').forEach(cb => {
            cb.checked = checked;
            cb.closest('.modal-newsletter-item')?.classList.toggle('selected', checked);
        });
    }

    /* ─── Upgrade modal ──────────────────────────────────── */
    function showUpgradeModal(reason, newsletterId, newsletterTitle) {
        selectedNewsletterId = newsletterId;
        upgradeStep = 'plans';
        initializeStripe();
        document.getElementById('upgradeNewsletterTitle').textContent = newsletterTitle;
        renderUpgradeStep('plans');
        loadUpgradePlans(newsletterId);
        document.getElementById('upgradeModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeUpgradeModal() {
        document.getElementById('upgradeModal').classList.remove('show');
        document.body.style.overflow = '';
        if (cardElement) {
            cardElement.destroy();
            cardElement = null;
        }
        selectedPlanId = selectedPlanPrice = selectedPlanCurrency = null;
        upgradeStep = 'plans';
    }

    function renderUpgradeStep(step) {
        upgradeStep = step;
        const body = document.getElementById('upgradeModalBody');

        if (step === 'plans') {
            body.innerHTML = `
                <h3 style="margin-bottom:1rem;font-size:1.125rem;">Choose Your Subscription Plan</h3>
                <div id="upgradePlansList" class="plans-grid">
                    <div style="text-align:center;padding:2rem;color:var(--text-secondary);">Loading plans…</div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:1.5rem;">
                    <button class="btn btn-secondary" onclick="closeUpgradeModal()">Cancel</button>
                </div>`;

        } else if (step === 'payment') {
            body.innerHTML = `
                <div class="selected-plan-summary" id="selectedPlanSummary"></div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select id="paymentMethod" class="form-control" onchange="handlePaymentMethodChange()">
                        <option value="">Select payment method</option>
                        <option value="stripe">Credit / Debit Card (Stripe)</option>
                        <option value="paypal">PayPal</option>
                    </select>
                </div>
                <div id="stripeCardContainer" style="display:none;" class="form-group">
                    <label>Card Details</label>
                    <div id="card-element" class="stripe-card-element"></div>
                    <span id="card-errors" class="card-error-text"></span>
                </div>
                <div class="form-group">
                    <label>Voucher Code <span style="font-weight:400;color:var(--text-secondary);">(Optional)</span></label>
                    <div class="voucher-row">
                        <input type="text" id="voucherCode" class="form-control" placeholder="Enter voucher code">
                        <button type="button" class="btn btn-secondary" onclick="applyVoucher()">Apply</button>
                    </div>
                    <div id="voucherMessage" style="display:none;" class="voucher-message"></div>
                </div>
                <div id="paymentError" style="display:none;" class="payment-error-box"></div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="renderUpgradeStep('plans');loadUpgradePlans(selectedNewsletterId);">← Back</button>
                    <button type="button" class="btn btn-primary" id="submitPaymentBtn" onclick="handlePaymentSubmit()">
                        <span id="btnText">Complete Subscription</span>
                        <span id="btnSpinner" class="button-spinner" style="display:none;"></span>
                    </button>
                </div>`;

        } else if (step === 'processing') {
            body.innerHTML = `
                <div class="state-container">
                    <div class="spinner"></div>
                    <p style="color:var(--text-secondary);">Processing your subscription…</p>
                </div>`;

        } else if (step === 'success') {
            body.innerHTML = `
                <div class="state-container">
                    <div class="success-icon">✓</div>
                    <h3 style="color:var(--success-color);margin-bottom:0.75rem;">Subscription Successful!</h3>
                    <p style="color:var(--text-secondary);margin-bottom:1.5rem;">You now have access to this newsletter.</p>
                    <button class="btn btn-primary" onclick="closeUpgradeModal();loadNewsletters();">Continue</button>
                </div>`;
        }
    }

    async function loadUpgradePlans(newsletterId) {
        const list = document.getElementById('upgradePlansList');
        if (!list) return;

        try {
            const res = await fetch(`/api/${SITE}/member/newsletters/upgrade-options`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({newsletter_id: newsletterId, site_id: SITE_ID})
            });
            const data = await res.json();

            if (!data.success) {
                list.innerHTML = `<div style="color:var(--danger-color);padding:1rem;">${esc(data.message)}</div>`;
                return;
            }

            const plans = data.data?.plans ?? data.plans ?? [];
            if (!plans.length) {
                list.innerHTML = '<div style="color:var(--text-secondary);padding:1rem;">No subscription plans available.</div>';
                return;
            }

            list.innerHTML = plans.map(plan => `
                <div class="plan-card ${plan.is_featured ? 'featured' : ''}">
                    ${plan.is_featured ? '<div class="plan-badge">Most Popular</div>' : ''}
                    <div class="plan-name">${esc(plan.name)}</div>
                    <div class="plan-price">
                        <span class="price">${esc(plan.currency)} ${plan.price}</span>
                        <span class="period">/ ${esc(plan.billing_period)}</span>
                    </div>
                    <div class="plan-description">${esc(plan.description || '')}</div>
                    ${plan.features?.length ? `
                        <ul class="plan-features">
                            ${plan.features.map(f => `<li>${esc(f)}</li>`).join('')}
                        </ul>` : ''}
                    <button class="btn btn-primary btn-full"
                        onclick="selectPlan(${plan.id},'${esc(plan.name)}',${plan.price},'${esc(plan.currency)}','${esc(plan.billing_period)}')">
                        Select Plan
                    </button>
                </div>`).join('');

        } catch {
            if (list) list.innerHTML = '<div style="color:var(--danger-color);padding:1rem;">Failed to load plans.</div>';
        }
    }

    function selectPlan(planId, planName, price, currency, billingPeriod) {
        selectedPlanId = planId;
        selectedPlanPrice = price;
        selectedPlanCurrency = currency;
        renderUpgradeStep('payment');
        document.getElementById('selectedPlanSummary').innerHTML = `
            <h4>${esc(planName)}</h4>
            <div class="selected-plan-price" id="finalPriceDisplay">
                ${esc(currency)} ${price} <span style="font-size:1rem;font-weight:400;color:var(--text-secondary);">/ ${esc(billingPeriod)}</span>
            </div>`;
    }

    function handlePaymentMethodChange() {
        const method = document.getElementById('paymentMethod').value;
        const container = document.getElementById('stripeCardContainer');
        if (method === 'stripe') {
            container.style.display = 'block';
            setupStripeElements();
        } else {
            container.style.display = 'none';
            if (cardElement) {
                cardElement.destroy();
                cardElement = null;
            }
        }
    }

    function initializeStripe() {
        if (stripe || typeof Stripe === 'undefined' || !STRIPE_KEY) return;
        try {
            stripe = Stripe(STRIPE_KEY);
            elements = stripe.elements();
        } catch {
        }
    }

    function setupStripeElements() {
        if (!initializeStripe() && !stripe) {
            showPaymentError('Failed to initialise payment system. Please refresh.');
            return;
        }
        if (cardElement) return;
        try {
            cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#32325d',
                        fontFamily: '-apple-system,sans-serif',
                        '::placeholder': {color: '#aab7c4'}
                    },
                    invalid: {color: '#ef4444', iconColor: '#ef4444'}
                },
                hidePostalCode: true,
            });
            cardElement.mount('#card-element');
            cardElement.on('change', e => {
                document.getElementById('card-errors').textContent = e.error?.message ?? '';
            });
        } catch {
            showPaymentError('Failed to load payment form. Please refresh.');
        }
    }

    async function applyVoucher() {
        const code = document.getElementById('voucherCode').value.trim();
        const msg = document.getElementById('voucherMessage');
        if (!code) return;
        try {
            const res = await fetch('/api/vouchers/validate', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({voucher_code: code, plan_id: selectedPlanId})
            });
            const data = await res.json();
            msg.style.display = 'block';
            if (data.success && data.valid) {
                msg.className = 'voucher-message success';
                msg.textContent = `✓ Voucher applied! Discount: ${selectedPlanCurrency} ${data.discount}`;
                document.getElementById('finalPriceDisplay').innerHTML =
                    `<span style="text-decoration:line-through;color:#9ca3af;">${selectedPlanCurrency} ${selectedPlanPrice}</span>
                     <span style="color:var(--success-color);font-weight:700;"> ${selectedPlanCurrency} ${data.final_price}</span>`;
            } else {
                msg.className = 'voucher-message error';
                msg.textContent = `✗ ${data.message || 'Invalid voucher code'}`;
            }
        } catch {
            const msg = document.getElementById('voucherMessage');
            msg.style.display = 'block';
            msg.className = 'voucher-message error';
            msg.textContent = '✗ Failed to validate voucher';
        }
    }

    async function handlePaymentSubmit() {
        const method = document.getElementById('paymentMethod').value;
        if (!method) {
            showPaymentError('Please select a payment method.');
            return;
        }
        setPaymentLoading(true);
        try {
            if (method === 'stripe') await handleStripePayment();
            else if (method === 'paypal') await handlePayPalPayment();
        } catch (e) {
            showPaymentError(e.message || 'Payment processing failed. Please try again.');
            setPaymentLoading(false);
        }
    }

    async function handleStripePayment() {
        if (!stripe || !cardElement) throw new Error('Stripe not initialised');
        const voucher = document.getElementById('voucherCode').value.trim();
        const res = await fetch(`/api/${SITE}/member/newsletters/process-upgrade`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                newsletter_id: selectedNewsletterId,
                plan_id: selectedPlanId,
                payment_method: 'stripe',
                voucher_code: voucher || null,
                setup_only: true
            })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.data?.message ?? data.message);

        if (data.data?.client_secret) {
            const {error, paymentIntent} = await stripe.confirmCardPayment(data.data.client_secret, {
                payment_method: {card: cardElement}
            });
            if (error) throw new Error(error.message);
            if (paymentIntent.status === 'succeeded') await confirmSubscription(data.data.subscription_id);
        } else if (data.data?.subscription_id) {
            renderUpgradeStep('success');
            setPaymentLoading(false);
        }
    }

    async function handlePayPalPayment() {
        const voucher = document.getElementById('voucherCode').value.trim();
        const res = await fetch(`/api/${SITE}/member/newsletters/process-upgrade`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                newsletter_id: selectedNewsletterId,
                plan_id: selectedPlanId,
                payment_method: 'paypal',
                voucher_code: voucher || null
            })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        if (data.redirect_url) {
            window.location.href = data.redirect_url;
        } else {
            renderUpgradeStep('success');
            setPaymentLoading(false);
        }
    }

    async function confirmSubscription(subscriptionId) {
        const res = await fetch(`/api/${SITE}/member/newsletters/confirm-upgrade`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({subscription_id: subscriptionId})
        });
        const data = await res.json();
        if (data.success) {
            renderUpgradeStep('success');
        } else {
            throw new Error(data.message || 'Failed to confirm subscription');
        }
        setPaymentLoading(false);
    }

    function setPaymentLoading(loading) {
        const btn = document.getElementById('submitPaymentBtn');
        const text = document.getElementById('btnText');
        const spinner = document.getElementById('btnSpinner');
        if (!btn) return;
        btn.disabled = loading;
        text.style.display = loading ? 'none' : 'inline';
        spinner.style.display = loading ? 'inline-block' : 'none';
    }

    function showPaymentError(msg) {
        const el = document.getElementById('paymentError');
        if (!el) return;
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(() => {
            el.style.display = 'none';
        }, 5000);
    }

    /* ─── Misc helpers ───────────────────────────────────── */
    function handleBackdropClick(e, modalId) {
        if (e.target === document.getElementById(modalId)) {
            modalId === 'upgradeModal' ? closeUpgradeModal() : closeNewsletterModal();
        }
    }

    function ucFirst(str) {
        if (!str) return '';
        return String(str).charAt(0).toUpperCase() + String(str).slice(1);
    }

    function esc(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    document.addEventListener('DOMContentLoaded', loadNewsletters);
</script>
</body>
</html>