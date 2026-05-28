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

        /* ── Recommended section ───────────────────────── */
        #recommendations-section {
            margin-bottom: 3rem;
            display: none; /* hidden until recommendations load */
        }

        #recommendations-section.visible {
            display: block;
        }

        .recommendation-reason {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8125rem;
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 0.75rem;
            padding: 0.4rem 0.75rem;
            background: #eff6ff;
            border-radius: 0.4rem;
            width: fit-content;
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

        .newsletter-card.recommended {
            border-color: var(--primary-color);
            background: linear-gradient(to bottom, #eff6ff, white);
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

        .status-badge.recommended {
            background: #dbeafe;
            color: #1e40af;
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

        /* ── Newsletter modal ──────────────────────────── */
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

    <!-- ── Recommended for You (lazy-loaded, hidden until populated) ─── -->
    <section id="recommendations-section" aria-label="Recommended newsletters">
        <h2 class="section-title"><span>✨</span> Recommended for You</h2>
        <div id="recommendations-grid" class="newsletters-grid"></div>
    </section>

    <!-- ── All Available Newsletters ────────────────────────────────── -->
    <h2 class="section-title"><span>📬</span> All Available Newsletters</h2>
    <div id="newsletters-grid" class="newsletters-grid">
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
        <div class="modal-body" id="upgradeModalBody"></div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
    class NewsletterStore {
        constructor() {
            this.state = {
                newslettersWithAccess: [],
                availableNewsletters: [],
                subscriptions: [],
                recommendations: [],
                selected: new Set(),
                loadingNewsletters: false,
                loadingRecommendations: false,
                error: null,
            };
            this.listeners = [];
        }

        subscribe(listener) {
            this.listeners.push(listener);
            listener(this.state);
        }

        setState(patch) {
            this.state = {
                ...this.state,
                ...patch,
            };

            this.listeners.forEach(listener => listener(this.state));
        }
    }

    // ── Recommendation section ────────────────────────────────────────────

    class RecommendationsSection {
        constructor(manager) {
            this.mgr = manager;
            this.section = document.getElementById('recommendations-section');
            this.grid = document.getElementById('recommendations-grid');
        }

        render(items) {
            if (!items.length) {
                this.section.classList.remove('visible');
                UI.render(this.grid, []);
                return;
            }

            UI.render(this.grid, items.map(item => this._card(item)));
            this.section.classList.add('visible');
        }

        _card(item) {
            const reasonEl = UI.el('div', {className: 'recommendation-reason'}, [
                '✦ ', item.reason,
            ]);

            const subscribeBtn = UI.el('button', {className: 'btn btn-primary btn-sm btn-full'}, ['Subscribe']);
            subscribeBtn.addEventListener('click', () => this.mgr.quickSubscribe(item.newsletter_id));

            return UI.el('div', {className: 'newsletter-card recommended'}, [
                UI.el('div', {className: 'newsletter-header'}, [
                    UI.el('div', {className: 'newsletter-icon'}, ['📧']),
                    UI.el('span', {className: 'status-badge recommended'}, ['Recommended']),
                ]),
                UI.el('div', {className: 'newsletter-content'}, [
                    UI.el('h3', {className: 'newsletter-title'}, [item.title]),
                    reasonEl,
                ]),
                subscribeBtn,
            ]);
        }
    }

    // ── Newsletter grid ───────────────────────────────────────────────────

    class NewsletterGrid {
        constructor(manager) {
            this.mgr = manager;
        }

        render(newsletters) {
            const grid = document.getElementById('newsletters-grid');
            if (!newsletters.length) {
                UI.render(grid, [UI.emptyState({icon: '📧', title: 'No Newsletters Available'})]);
                return;
            }
            UI.render(grid, newsletters.map(item => this._card(item)));
        }

        _card(item) {
            const isLocked = !item.has_access;
            const isSubscribed = item.is_subscribed;
            const isSelected = this.mgr.store.state.selected.has(item.id);

            const statusBadge = UI.el('span', {
                className: `status-badge ${isLocked ? 'locked' : isSubscribed ? 'subscribed' : 'unsubscribed'}`,
            }, [isLocked ? 'Requires Upgrade' : isSubscribed ? '✓ Subscribed' : 'Not Subscribed']);

            let topRight = null;
            if (isLocked) {
                topRight = UI.el('div', {className: 'lock-badge'}, ['🔒 Locked']);
            } else if (!isSubscribed) {
                const cb = UI.el('input', {
                    type: 'checkbox',
                    className: 'newsletter-checkbox',
                    'data-newsletter-id': item.id,
                    ...(isSelected ? {checked: true} : {}),
                });
                cb.addEventListener('change', () => this.mgr.toggleSelected(item.id, cb.checked));
                topRight = cb;
            }

            let actionBtn;
            if (isLocked) {
                actionBtn = UI.el('button', {className: 'btn btn-warning btn-sm btn-full'}, ['🔓 Upgrade to Access']);
                actionBtn.addEventListener('click', () =>
                    this.mgr.upgradeModal.show(item.access_reason, item.id, item.title));
            } else if (isSubscribed) {
                actionBtn = UI.el('button', {className: 'btn btn-danger btn-sm btn-full'}, ['Unsubscribe']);
                actionBtn.addEventListener('click', () => this.mgr.quickUnsubscribe(item.id));
            } else {
                actionBtn = UI.el('button', {className: 'btn btn-primary btn-sm btn-full'}, ['Subscribe']);
                actionBtn.addEventListener('click', () => this.mgr.quickSubscribe(item.id));
            }

            const accessMsg = (isLocked && item.access_message)
                ? UI.el('div', {className: 'access-message'}, [UI.el('p', {}, [item.access_message])])
                : null;

            return UI.el('div', {
                className: `newsletter-card${isSubscribed ? ' subscribed' : ''}${isLocked ? ' locked' : ''}`,
                'data-newsletter-id': item.id,
            }, [
                topRight,
                UI.el('div', {className: 'newsletter-header'}, [
                    UI.el('div', {className: 'newsletter-icon'}, ['📧']),
                    UI.el('div', {}, [statusBadge]),
                ]),
                UI.el('div', {className: 'newsletter-content'}, [
                    UI.el('h3', {className: 'newsletter-title'}, [item.title]),
                    item.content ? UI.el('p', {className: 'newsletter-description'}, [item.content]) : null,
                    accessMsg,
                    UI.el('div', {className: 'newsletter-meta'}, [
                        UI.el('div', {className: 'meta-item'}, [
                            `${item.interval.charAt(0).toUpperCase() + item.interval.slice(1)}`,
                        ]),
                        item.active ? UI.el('div', {className: 'meta-item'}, ['✓ Active']) : null,
                    ]),
                ]),
                actionBtn,
            ]);
        }
    }

    // ── NewsletterManager ─────────────────────────────────────────────────

    class NewsletterManager {
        constructor() {
            this.store = new NewsletterStore();
            this.grid = new NewsletterGrid(this);
            this.recommendations = new RecommendationsSection(this);
            this.upgradeModal = new UpgradeModal(this);

            this.store.subscribe(state => this.render(state));
        }

        async load() {
            this.store.setState({
                loadingNewsletters: true,
                loadingRecommendations: true,
                error: null,
            });

            await Promise.all([
                this._loadNewsletters(),
                this._loadRecommendations(),
            ]);
        }

        async _loadNewsletters() {
            try {
                const json = await api(`/api/${SITE_SLUG}/member/newsletters`);
                this.store.setState({
                    newslettersWithAccess: json.data.newsletters_with_access || [],
                    availableNewsletters: json.data.available_newsletters || [],
                    subscriptions: json.data.subscriptions || [],
                    selected: new Set(),
                    loadingNewsletters: false,
                });
            } catch {
                this.store.setState({
                    loadingNewsletters: false,
                    error: 'Failed to load newsletters. Please refresh.',
                });
                UI.toast('Failed to load newsletters. Please refresh.', 'error');
            }
        }

        async _loadRecommendations() {
            try {
                const json = await api(`/api/${SITE_SLUG}/member/newsletters/recommendations`);
                this.store.setState({
                    recommendations: json.data ?? [],
                    loadingRecommendations: false,
                });
            } catch {
                this.store.setState({
                    recommendations: [],
                    loadingRecommendations: false,
                });
            }
        }

        render(state) {
            if (!state.loadingNewsletters) {
                this.grid.render(state.newslettersWithAccess);
                this._populateModal();
                document.getElementById('subscribeBtn').disabled = false;
                document.getElementById('selectedCount').textContent = state.selected.size;
                document.getElementById('floatingActionBar').classList.toggle('show', state.selected.size > 0);
            }

            if (!state.loadingRecommendations) {
                this.recommendations.render(state.recommendations);
            }
        }

        _populateModal() {
            const list = document.getElementById('modalNewsletterList');
            const availableNewsletters = this.store.state.availableNewsletters;

            if (!availableNewsletters.length) {
                UI.render(list, [UI.el('p', {style: {textAlign: 'center', color: 'var(--text-secondary)'}},
                    ['No newsletters available.'])]);
                return;
            }
            UI.render(list, availableNewsletters.map(n => {
                const subbed = n.is_subscribed;
                const selected = this.store.state.selected.has(n.id);
                const cb = UI.el('input', {
                    type: 'checkbox',
                    className: 'modal-item-checkbox',
                    'data-newsletter-id': n.id,
                    ...(subbed ? {disabled: true, checked: true} : selected ? {checked: true} : {}),
                });
                const item = UI.el('div', {
                    className: `modal-newsletter-item${subbed ? ' already-subscribed' : ''}${selected ? ' selected' : ''}`,
                    'data-newsletter-id': n.id,
                }, [
                    cb,
                    UI.el('div', {style: {flex: '1'}}, [
                        UI.el('div', {className: 'modal-item-title'}, [
                            n.title,
                            subbed ? UI.el('span', {className: 'already-label'}, [' (Already subscribed)']) : null,
                        ]),
                        UI.el('div', {className: 'modal-item-desc'}, [
                            n.content || 'Stay updated with our latest content',
                            UI.el('br'),
                            UI.el('strong', {}, ['Frequency: ']),
                            n.interval.charAt(0).toUpperCase() + n.interval.slice(1),
                        ]),
                    ]),
                ]);
                if (!subbed) item.addEventListener('click', () => {
                    const nextChecked = !cb.checked;
                    cb.checked = nextChecked;
                    this.toggleSelected(n.id, nextChecked);
                });
                return item;
            }));
        }

        toggleSelected(id, checked) {
            const selected = new Set(this.store.state.selected);
            if (checked) {
                selected.add(id);
            } else {
                selected.delete(id);
            }

            this.store.setState({selected});
        }

        async quickSubscribe(id) {
            try {
                await api(`/api/${SITE_SLUG}/member/newsletter/signup`, {
                    method: 'POST',
                    body: JSON.stringify({newsletter_id: id}),
                });
                UI.toast('Successfully subscribed!', 'success');
                await this.load();
            } catch (e) {
                UI.toast(e.message || 'Failed to subscribe.', 'error');
            }
        }

        async quickUnsubscribe(id) {
            if (!confirm('Unsubscribe from this newsletter?')) return;
            const sub = this.store.state.subscriptions.find(s => s.newsletter_id === id);
            if (!sub) {
                UI.toast('Subscription not found.', 'error');
                return;
            }
            try {
                await api(`/api/${SITE_SLUG}/member/newsletters/unsubscribe`, {
                    method: 'POST',
                    body: JSON.stringify({subscriber_id: sub.id}),
                });
                UI.toast('Successfully unsubscribed.', 'success');
                await this.load();
            } catch (e) {
                UI.toast(e.message || 'Failed to unsubscribe.', 'error');
            }
        }

        async bulkSubscribe(ids) {
            try {
                await api(`/api/${SITE_SLUG}/member/newsletters/bulk-subscribe`, {
                    method: 'POST',
                    body: JSON.stringify({newsletter_ids: ids}),
                });
                UI.toast(`Subscribed to ${ids.length} newsletter(s).`, 'success');
                await this.load();
            } catch (e) {
                UI.toast(e.message || 'Failed to subscribe.', 'error');
            }
        }

        subscribeSelected() {
            if (!this.store.state.selected.size) {
                UI.toast('Select at least one newsletter.', 'error');
                return;
            }
            this.bulkSubscribe(Array.from(this.store.state.selected));
        }

        subscribeModalSelected() {
            const ids = Array.from(this.store.state.selected);
            if (!ids.length) {
                UI.toast('Select at least one newsletter.', 'error');
                return;
            }
            this.bulkSubscribe(ids);
            this._closeModal();
        }

        openModal() {
            document.getElementById('newsletterModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        _closeModal() {
            document.getElementById('newsletterModal').classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('selectAllCheckbox').checked = false;
        }

        selectAll(checked) {
            const selected = new Set(this.store.state.selected);
            this.store.state.availableNewsletters.forEach(item => {
                if (!item.is_subscribed) {
                    if (checked) {
                        selected.add(item.id);
                    } else {
                        selected.delete(item.id);
                    }
                }
            });
            this.store.setState({selected});
        }
    }

    // ── UpgradeModal (unchanged logic, included for completeness) ─────────

    class UpgradeModal {
        constructor(mgr) {
            this.mgr = mgr;
            this.newsletterId = null;
            this.planId = this.planPrice = this.planCurrency = null;
            this.stripe = this.elements = this.cardEl = null;
        }

        show(reason, newsletterId, title) {
            this.newsletterId = newsletterId;
            this._initStripe();
            UI.text(document.getElementById('upgradeNewsletterTitle'), title);
            this._renderPlansStep();
            this._loadPlans();
            document.getElementById('upgradeModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        close() {
            document.getElementById('upgradeModal').classList.remove('show');
            document.body.style.overflow = '';
            this.cardEl?.destroy();
            this.cardEl = null;
            this.planId = this.planPrice = this.planCurrency = null;
        }

        _initStripe() {
            if (this.stripe || typeof Stripe === 'undefined' || !STRIPE_KEY) return;
            try {
                this.stripe = Stripe(STRIPE_KEY);
                this.elements = this.stripe.elements();
            } catch {
            }
        }

        _renderPlansStep() {
            const body = document.getElementById('upgradeModalBody');
            const list = UI.el('div', {id: 'upgradePlansList', className: 'plans-grid'}, [
                UI.el('div', {
                    style: {
                        textAlign: 'center',
                        padding: '2rem',
                        color: 'var(--text-secondary)'
                    }
                }, ['Loading plans…']),
            ]);
            const cancelBtn = UI.el('button', {className: 'btn btn-secondary'}, ['Cancel']);
            cancelBtn.addEventListener('click', () => this.close());
            UI.render(body, [
                UI.el('h3', {style: {marginBottom: '1rem', fontSize: '1.125rem'}}, ['Choose Your Subscription Plan']),
                list,
                UI.el('div', {style: {display: 'flex', justifyContent: 'flex-end', marginTop: '1.5rem'}}, [cancelBtn]),
            ]);
        }

        async _loadPlans() {
            const list = document.getElementById('upgradePlansList');
            try {
                const json = await api(`/api/${SITE_SLUG}/member/newsletters/upgrade-options`, {
                    method: 'POST',
                    body: JSON.stringify({newsletter_id: this.newsletterId, site_id: SITE_ID}),
                });
                const plans = json.data?.plans ?? json.plans ?? [];
                if (!plans.length) {
                    UI.render(list, [UI.el('div', {
                        style: {
                            color: 'var(--text-secondary)',
                            padding: '1rem'
                        }
                    }, ['No plans available.'])]);
                    return;
                }
                UI.render(list, plans.map(plan => {
                    const btn = UI.el('button', {className: 'btn btn-primary btn-full'}, ['Select Plan']);
                    btn.addEventListener('click', () =>
                        this._selectPlan(plan.id, plan.name, plan.price, plan.currency, plan.billing_period));
                    return UI.el('div', {className: `plan-card${plan.is_featured ? ' featured' : ''}`}, [
                        plan.is_featured ? UI.el('div', {className: 'plan-badge'}, ['Most Popular']) : null,
                        UI.el('div', {className: 'plan-name'}, [plan.name]),
                        UI.el('div', {className: 'plan-price'}, [
                            UI.el('span', {className: 'price'}, [`${plan.currency} ${plan.price}`]),
                            UI.el('span', {className: 'period'}, [` / ${plan.billing_period}`]),
                        ]),
                        UI.el('div', {className: 'plan-description'}, [plan.description ?? '']),
                        btn,
                    ]);
                }));
            } catch {
                UI.render(list, [UI.el('div', {
                    style: {
                        color: 'var(--danger-color)',
                        padding: '1rem'
                    }
                }, ['Failed to load plans.'])]);
            }
        }

        _selectPlan(id, name, price, currency, period) {
            this.planId = id;
            this.planPrice = price;
            this.planCurrency = currency;
            const body = document.getElementById('upgradeModalBody');
            const summary = UI.el('div', {className: 'selected-plan-summary'}, [
                UI.el('h4', {}, [name]),
                UI.el('div', {id: 'finalPriceDisplay', className: 'selected-plan-price'}, [
                    `${currency} ${price}`,
                    UI.el('span', {
                        style: {
                            fontSize: '1rem',
                            fontWeight: '400',
                            color: 'var(--text-secondary)'
                        }
                    }, [` / ${period}`]),
                ]),
            ]);
            const methodSelect = UI.el('select', {id: 'paymentMethod', className: 'form-control'}, [
                UI.el('option', {value: ''}, ['Select payment method']),
                UI.el('option', {value: 'stripe'}, ['Credit / Debit Card (Stripe)']),
                UI.el('option', {value: 'paypal'}, ['PayPal']),
            ]);
            methodSelect.addEventListener('change', () => this._onMethodChange());
            const cardContainer = UI.el('div', {id: 'stripeCardContainer', style: {display: 'none'}}, [
                UI.el('label', {}, ['Card Details']),
                UI.el('div', {id: 'card-element', className: 'stripe-card-element'}),
                UI.el('span', {id: 'card-errors', className: 'card-error-text'}),
            ]);
            const backBtn = UI.el('button', {className: 'btn btn-secondary'}, ['← Back']);
            backBtn.addEventListener('click', () => {
                this._renderPlansStep();
                this._loadPlans();
            });
            const submitBtn = UI.el('button', {
                id: 'submitPaymentBtn',
                className: 'btn btn-primary'
            }, ['Complete Subscription']);
            submitBtn.addEventListener('click', () => this._handleSubmit());
            const errBox = UI.el('div', {id: 'paymentError', className: 'payment-error-box', style: {display: 'none'}});
            UI.render(body, [summary,
                UI.el('div', {className: 'form-group'}, [UI.el('label', {}, ['Payment Method']), methodSelect]),
                cardContainer, errBox,
                UI.el('div', {className: 'modal-actions'}, [backBtn, submitBtn]),
            ]);
        }

        _onMethodChange() {
            const method = document.getElementById('paymentMethod').value;
            const container = document.getElementById('stripeCardContainer');
            if (method === 'stripe') {
                container.style.display = 'block';
                if (!this.cardEl && this.elements) {
                    this.cardEl = this.elements.create('card', {hidePostalCode: true});
                    this.cardEl.mount('#card-element');
                    this.cardEl.on('change', e => {
                        document.getElementById('card-errors').textContent = e.error?.message ?? '';
                    });
                }
            } else {
                container.style.display = 'none';
                this.cardEl?.destroy();
                this.cardEl = null;
            }
        }

        async _handleSubmit() {
            const method = document.getElementById('paymentMethod').value;
            if (!method) {
                this._showErr('Please select a payment method.');
                return;
            }
            this._setLoading(true);
            try {
                if (method === 'stripe') await this._stripePayment();
                else if (method === 'paypal') await this._paypalPayment();
            } catch (e) {
                this._showErr(e.message || 'Payment processing failed.');
                this._setLoading(false);
            }
        }

        async _stripePayment() {
            const json = await api(`/api/${SITE_SLUG}/member/newsletters/process-upgrade`, {
                method: 'POST',
                body: JSON.stringify({
                    newsletter_id: this.newsletterId,
                    plan_id: this.planId,
                    payment_method: 'stripe',
                    setup_only: true
                }),
            });
            if (json.data?.client_secret) {
                const {error, paymentIntent} = await this.stripe.confirmCardPayment(
                    json.data.client_secret, {payment_method: {card: this.cardEl}});
                if (error) throw new Error(error.message);
                if (paymentIntent.status === 'succeeded') await this._confirmSub(json.data.subscription_id);
            } else if (json.data?.subscription_id) {
                this._renderSuccess();
                this._setLoading(false);
            }
        }

        async _paypalPayment() {
            const json = await api(`/api/${SITE_SLUG}/member/newsletters/process-upgrade`, {
                method: 'POST',
                body: JSON.stringify({
                    newsletter_id: this.newsletterId,
                    plan_id: this.planId,
                    payment_method: 'paypal'
                }),
            });
            if (json.redirect_url) window.location.href = json.redirect_url;
            else {
                this._renderSuccess();
                this._setLoading(false);
            }
        }

        async _confirmSub(subId) {
            await api(`/api/${SITE_SLUG}/member/newsletters/confirm-upgrade`, {
                method: 'POST', body: JSON.stringify({subscription_id: subId}),
            });
            this._renderSuccess();
            this._setLoading(false);
        }

        _renderSuccess() {
            const closeBtn = UI.el('button', {className: 'btn btn-primary'}, ['Continue']);
            closeBtn.addEventListener('click', () => {
                this.close();
                this.mgr.load();
            });
            UI.render(document.getElementById('upgradeModalBody'), [
                UI.el('div', {className: 'state-container'}, [
                    UI.el('div', {className: 'success-icon'}, ['✓']),
                    UI.el('h3', {
                        style: {
                            color: 'var(--success-color)',
                            marginBottom: '0.75rem'
                        }
                    }, ['Subscription Successful!']),
                    UI.el('p', {
                        style: {
                            color: 'var(--text-secondary)',
                            marginBottom: '1.5rem'
                        }
                    }, ['You now have access to this newsletter.']),
                    closeBtn,
                ]),
            ]);
        }

        _showErr(msg) {
            const el = document.getElementById('paymentError');
            if (!el) return;
            UI.text(el, msg);
            el.style.display = 'block';
            setTimeout(() => {
                el.style.display = 'none';
            }, 5000);
        }

        _setLoading(loading) {
            const btn = document.getElementById('submitPaymentBtn');
            if (!btn) return;
            btn.disabled = loading;
            UI.text(btn, loading ? 'Processing…' : 'Complete Subscription');
        }
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        const mgr = new NewsletterManager();

        window.openNewsletterModal = () => mgr.openModal();
        window.closeNewsletterModal = () => mgr._closeModal();
        window.selectAllNewsletters = checked => mgr.selectAll(checked);
        window.subscribeSelected = () => mgr.subscribeSelected();
        window.subscribeModalSelected = () => mgr.subscribeModalSelected();
        window.clearSelection = () => mgr.store.setState({selected: new Set()});
        window.closeUpgradeModal = () => mgr.upgradeModal.close();

        mgr.load();
    });
</script>
</body>
</html>
