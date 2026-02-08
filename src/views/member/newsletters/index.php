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

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
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
        }

        .newsletter-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .newsletter-card.subscribed {
            border: 2px solid var(--success-color);
            background: linear-gradient(to bottom, #f0fdf4 0%, white 100%);
        }

        .newsletter-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .newsletter-icon {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .newsletter-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
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

        .newsletter-content {
            margin: 1rem 0;
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
            margin-bottom: 1rem;
        }

        .newsletter-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .newsletter-checkbox {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: var(--primary-color);
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
        }

        .floating-action-bar.show {
            display: flex;
        }

        .selected-count {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Newsletter Modal */
        .newsletter-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .newsletter-modal-overlay.show {
            display: flex;
        }

        .newsletter-modal {
            background: white;
            border-radius: 1rem;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .newsletter-modal-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .newsletter-modal-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .newsletter-modal-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .newsletter-modal-body {
            padding: 2rem;
        }

        .newsletter-modal-grid {
            display: grid;
            gap: 1rem;
        }

        .newsletter-modal-item {
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            gap: 1rem;
        }

        .newsletter-modal-item:hover {
            border-color: var(--primary-color);
            background: #f8f9ff;
        }

        .newsletter-modal-item.selected {
            border-color: var(--primary-color);
            background: linear-gradient(to right, #eff6ff 0%, #f8f9ff 100%);
        }

        .newsletter-modal-item.already-subscribed {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f9fafb;
        }

        .newsletter-modal-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: var(--primary-color);
            flex-shrink: 0;
            margin-top: 0.25rem;
        }

        .newsletter-modal-content {
            flex: 1;
        }

        .newsletter-modal-item-title {
            font-weight: 600;
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }

        .newsletter-modal-item-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .newsletter-modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-light);
            position: sticky;
            bottom: 0;
        }

        .select-all-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .close-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            color: var(--text-secondary);
            font-size: 1.5rem;
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
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
                width: 100%;
                flex-direction: column;
            }

            .floating-action-bar {
                left: 1rem;
                right: 1rem;
                transform: none;
                flex-direction: column;
                gap: 1rem;
            }
        }

        /* Upgrade Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: 1px solid #888;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover,
        .close:focus {
            color: #000;
        }

        .modal-body {
            padding: 30px;
        }

        /* Plans List */
        .plans-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .plan-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px;
            position: relative;
            transition: all 0.3s ease;
        }

        .plan-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
        }

        .plan-card.featured {
            border-color: #007bff;
            background-color: #f8f9ff;
        }

        .plan-badge {
            position: absolute;
            top: -12px;
            right: 20px;
            background-color: #007bff;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .plan-card h4 {
            margin: 0 0 15px 0;
            font-size: 20px;
        }

        .plan-price {
            margin-bottom: 15px;
        }

        .plan-price .price {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }

        .plan-price .period {
            font-size: 14px;
            color: #666;
        }

        .plan-description {
            color: #666;
            margin-bottom: 15px;
            min-height: 40px;
        }

        .plan-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .plan-features li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
        }

        .plan-features li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }

        .select-plan-btn {
            width: 100%;
            margin-top: 15px;
        }

        /* Payment Step */
        .plan-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .selected-plan-info h4 {
            margin: 0 0 10px 0;
        }

        .selected-plan-info .plan-price {
            font-size: 24px;
            color: #007bff;
            margin: 0;
        }

        #upgrade-payment-form .form-group {
            margin-bottom: 20px;
        }

        #upgrade-payment-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        #upgrade-payment-form input,
        #upgrade-payment-form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        #upgrade-payment-form input:focus,
        #upgrade-payment-form select:focus {
            outline: none;
            border-color: #007bff;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        /* Processing Step */
        .processing-container {
            text-align: center;
            padding: 60px 20px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        /* Success Step */
        .success-container {
            text-align: center;
            padding: 60px 20px;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #28a745;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 20px;
        }

        .success-container h3 {
            color: #28a745;
            margin-bottom: 15px;
        }

        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* Error State */
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        /* Stripe Card Element */
        .stripe-card-element {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px;
            background-color: white;
        }

        .stripe-card-element.StripeElement--focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .stripe-card-element.StripeElement--invalid {
            border-color: #fa755a;
        }

        .error-text {
            color: #fa755a;
            font-size: 14px;
            margin-top: 8px;
            display: block;
        }

        .success-text {
            color: #28a745;
            font-size: 14px;
        }

        /* Voucher Message */
        .voucher-message {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .voucher-message.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .voucher-message.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }

        /* Apply Voucher Button */
        .btn-link {
            background: none;
            border: none;
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
            margin-left: 10px;
        }

        .btn-link:hover {
            color: #0056b3;
        }

        /* Button Spinner */
        .button-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
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
        <div class="btn-group">
            <button onclick="openNewsletterModal()" class="btn btn-primary">
                📧 Subscribe to Newsletters
            </button>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/dashboard" class="btn btn-secondary">
                ← Back to Dashboard
            </a>
        </div>
    </div>

    <div id="alert-container"></div>

    <h2 class="section-title">
        <span>📬</span>
        All Available Newsletters
    </h2>

    <?php if ($newslettersWithAccess->isEmpty()): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📧</div>
            <h3>No Newsletters Available</h3>
            <p>Check back later for newsletter options</p>
        </div>
    <?php else: ?>
        <div class="newsletters-grid">
            <?php foreach ($newslettersWithAccess as $item): ?>
                <?php
                $newsletter = $item['newsletter'];
                $hasAccess = $item['has_access'];
                $accessReason = $item['access_reason'];
                $accessMessage = $item['access_message'];

                $isSubscribed = false;
                foreach ($subscriptions as $sub) {
                    if ($sub->newsletter_id === $newsletter->id) {
                        $isSubscribed = true;
                        break;
                    }
                }

                $isLocked = !$hasAccess;
                ?>
                <div class="newsletter-card <?= $isSubscribed ? 'subscribed' : '' ?> <?= $isLocked ? 'locked' : '' ?>"
                     data-newsletter-id="<?= $newsletter->id ?>"
                     style="<?= $isLocked ? 'opacity: 0.6;' : '' ?>">

                    <?php if ($isLocked): ?>
                        <div class="lock-badge"
                             style="position: absolute; top: 1rem; right: 1rem; background: #f59e0b; color: white; padding: 0.5rem 1rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            🔒 Locked
                        </div>
                    <?php elseif (!$isSubscribed): ?>
                        <input type="checkbox"
                               class="newsletter-checkbox"
                               data-newsletter-id="<?= $newsletter->id ?>"
                               onchange="updateSelection()">
                    <?php endif; ?>

                    <div class="newsletter-header">
                        <div class="newsletter-icon">📧</div>
                        <div class="newsletter-status">
                            <?php if ($isLocked): ?>
                                <span class="status-badge" style="background: #fee2e2; color: #991b1b;">
                                Requires Upgrade
                            </span>
                            <?php else: ?>
                                <span class="status-badge <?= $isSubscribed ? 'subscribed' : 'unsubscribed' ?>">
                                <?= $isSubscribed ? '✓ Subscribed' : 'Not Subscribed' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="newsletter-content">
                        <h3 class="newsletter-title"><?= htmlspecialchars($newsletter->title) ?></h3>
                        <?php if ($newsletter->content): ?>
                            <p class="newsletter-description"><?= htmlspecialchars($newsletter->content) ?></p>
                        <?php endif; ?>

                        <?php if ($isLocked && $accessMessage): ?>
                            <div class="access-message"
                                 style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin: 1rem 0; border-radius: 0.5rem;">
                                <p style="color: #92400e; font-size: 0.875rem; font-weight: 600;">
                                    <?= htmlspecialchars($accessMessage) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="newsletter-meta">
                            <div class="meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <?= ucfirst($newsletter->interval) ?>
                            </div>
                            <?php if ($newsletter->active): ?>
                                <div class="meta-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Active
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($isLocked): ?>
                        <button onclick="showUpgradeModal('<?= $accessReason ?>', <?= $newsletter->id ?>, '<?= $newsletter->title ?>')"
                                class="btn btn-primary btn-sm"
                                style="width: 100%; background: linear-gradient(135deg, #f59e0b, #d97706);">
                            🔓 Upgrade to Access
                        </button>
                    <?php elseif ($isSubscribed): ?>
                        <button onclick="quickUnsubscribe(<?= $newsletter->id ?>)"
                                class="btn btn-danger btn-sm" style="width: 100%;">
                            Unsubscribe
                        </button>
                    <?php else: ?>
                        <button onclick="quickSubscribe(<?= $newsletter->id ?>)"
                                class="btn btn-primary btn-sm" style="width: 100%;">
                            Subscribe
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Floating Action Bar -->
<div id="floatingActionBar" class="floating-action-bar">
    <span class="selected-count">
        <span id="selectedCount">0</span> newsletter(s) selected
    </span>
    <div class="btn-group">
        <button onclick="subscribeSelected()" class="btn btn-primary">
            Subscribe to Selected
        </button>
        <button onclick="clearSelection()" class="btn btn-secondary">
            Clear
        </button>
    </div>
</div>

<!-- Newsletter Modal -->
<div id="newsletterModal" class="newsletter-modal-overlay">
    <div class="newsletter-modal">
        <div class="newsletter-modal-header">
            <button onclick="closeNewsletterModal()" class="close-btn">×</button>
            <h2 class="newsletter-modal-title">Subscribe to Newsletters</h2>
            <p class="newsletter-modal-subtitle">
                Select the newsletters you'd like to receive
            </p>
        </div>

        <div class="newsletter-modal-body">
            <div class="newsletter-modal-grid" id="modalNewsletterGrid">
                <?php foreach ($availableNewsletters as $newsletter): ?>
                    <?php
                    $isSubscribed = false;
                    $subscriberId = null;
                    foreach ($subscriptions as $sub) {
                        if ($sub->newsletter_id === $newsletter->id) {
                            $isSubscribed = true;
                            $subscriberId = $sub->id;
                            break;
                        }
                    }
                    ?>
                    <div class="newsletter-modal-item <?= $isSubscribed ? 'already-subscribed' : '' ?>"
                         data-newsletter-id="<?= $newsletter->id ?>"
                         onclick="<?= !$isSubscribed ? 'toggleModalCheckbox(this)' : '' ?>">
                        <input type="checkbox"
                               class="newsletter-modal-checkbox"
                               data-newsletter-id="<?= $newsletter->id ?>"
                                <?= $isSubscribed ? 'disabled checked' : '' ?>
                               onclick="event.stopPropagation()">
                        <div class="newsletter-modal-content">
                            <div class="newsletter-modal-item-title">
                                <?= htmlspecialchars($newsletter->title) ?>
                                <?= $isSubscribed ? '<span style="color: var(--success-color); font-size: 0.875rem;">(Already subscribed)</span>' : '' ?>
                            </div>
                            <div class="newsletter-modal-item-description">
                                <?= htmlspecialchars($newsletter->content ?: 'Stay updated with our latest content') ?>
                                <br>
                                <strong>Frequency:</strong> <?= ucfirst($newsletter->interval) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="newsletter-modal-footer">
            <div class="select-all-container">
                <input type="checkbox" id="selectAllCheckbox" onchange="selectAllNewsletters(this.checked)">
                <label for="selectAllCheckbox" style="cursor: pointer; user-select: none;">
                    Select All Available
                </label>
            </div>
            <div class="btn-group">
                <button onclick="closeNewsletterModal()" class="btn btn-secondary">
                    Cancel
                </button>
                <button onclick="subscribeModalSelected()" class="btn btn-primary">
                    Subscribe to Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Upgrade Modal -->
<div id="upgradeModal" class="newsletter-modal-overlay">
    <div class="newsletter-modal">
        <div class="newsletter-modal-header">
            <button onclick="closeUpgradeModal()" class="close-btn">×</button>
            <h2 class="newsletter-modal-title">Upgrade Your Subscription</h2>
            <p class="newsletter-modal-subtitle">
                Choose a plan to access this newsletter
            </p>
        </div>

        <div class="newsletter-modal-body">
            <div id="upgradeOptions" class="newsletter-modal-grid">
                <!-- Populated dynamically -->
            </div>
        </div>

        <div class="newsletter-modal-footer">
            <button onclick="closeUpgradeModal()" class="btn btn-secondary">
                Cancel
            </button>
        </div>
    </div>
</div>
<script src="https://js.stripe.com/v3/"></script>
<script>
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    let selectedNewsletters = new Set();
    let upgradeModal = null;
    let selectedNewsletterId = null;
    let selectedPlanId = null;
    let selectedPlanPrice = null;
    let selectedPlanCurrency = null;
    let upgradeStep = 'plans'; // 'plans', 'payment', 'processing', 'success'
    let stripe = null;
    let elements = null;
    let cardElement = null;
    let paymentIntentClientSecret = null;
    const STRIPE_KEY = '<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? '' ?>';

    // Initialize Stripe
    function initializeStripe() {
        if (stripe) {
            return true; // Already initialized
        }

        if (typeof Stripe === 'undefined') {
            console.error('Stripe.js not loaded');
            return false;
        }

        try {
            stripe = Stripe(STRIPE_KEY);
            elements = stripe.elements();
            return true;
        } catch (error) {
            console.error('Failed to initialize Stripe:', error);
            return false;
        }
    }

    function updateSelection() {
        selectedNewsletters.clear();
        document.querySelectorAll('.newsletter-checkbox:checked').forEach(checkbox => {
            selectedNewsletters.add(parseInt(checkbox.dataset.newsletterId));
        });

        const floatingBar = document.getElementById('floatingActionBar');
        const count = selectedNewsletters.size;

        document.getElementById('selectedCount').textContent = count;

        if (count > 0) {
            floatingBar.classList.add('show');
        } else {
            floatingBar.classList.remove('show');
        }
    }

    function clearSelection() {
        document.querySelectorAll('.newsletter-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelection();
    }

    async function subscribeSelected() {
        if (selectedNewsletters.size === 0) {
            showAlert('Please select at least one newsletter', 'error');
            return;
        }

        const newsletterIds = Array.from(selectedNewsletters);

        try {
            const response = await fetch(`/${SITE}/member/newsletters/bulk-subscribe`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({newsletter_ids: newsletterIds})
            });

            const data = await response.json();

            if (data.success) {
                showAlert(`Successfully subscribed to ${newsletterIds.length} newsletter(s)`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to subscribe', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to subscribe', 'error');
        }
    }

    async function quickSubscribe(newsletterId) {
        try {
            const response = await fetch(`/${SITE}/member/newsletter/signup`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({newsletter_id: newsletterId})
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Successfully subscribed!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to subscribe', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to subscribe', 'error');
        }
    }

    async function quickUnsubscribe(newsletterId) {
        if (!confirm('Are you sure you want to unsubscribe from this newsletter?')) {
            return;
        }

        // Find subscriber ID
        const subscriberId = findSubscriberId(newsletterId);
        if (!subscriberId) {
            showAlert('Subscription not found', 'error');
            return;
        }

        try {
            const response = await fetch(`/${SITE}/member/newsletters/unsubscribe`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({subscriber_id: subscriberId})
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Successfully unsubscribed', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to unsubscribe', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to unsubscribe', 'error');
        }
    }

    function findSubscriberId(newsletterId) {
        // This will need to be populated from PHP data
        const subscriptions = <?= json_encode($subscriptions->map(function ($sub) {
            return ['newsletter_id' => $sub->newsletter_id, 'id' => $sub->id];
        })->toArray()) ?>;

        const sub = subscriptions.find(s => s.newsletter_id === newsletterId);
        return sub ? sub.id : null;
    }

    function openNewsletterModal() {
        document.getElementById('newsletterModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeNewsletterModal() {
        document.getElementById('newsletterModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        // Reset checkboxes
        document.querySelectorAll('.newsletter-modal-checkbox:not([disabled])').forEach(cb => {
            cb.checked = false;
            cb.closest('.newsletter-modal-item').classList.remove('selected');
        });
        document.getElementById('selectAllCheckbox').checked = false;
    }

    function toggleModalCheckbox(item) {
        const checkbox = item.querySelector('.newsletter-modal-checkbox');
        if (!checkbox.disabled) {
            checkbox.checked = !checkbox.checked;
            item.classList.toggle('selected', checkbox.checked);
        }
    }

    function selectAllNewsletters(checked) {
        document.querySelectorAll('.newsletter-modal-checkbox:not([disabled])').forEach(checkbox => {
            checkbox.checked = checked;
            checkbox.closest('.newsletter-modal-item').classList.toggle('selected', checked);
        });
    }

    async function subscribeModalSelected() {
        const selected = Array.from(document.querySelectorAll('.newsletter-modal-checkbox:checked:not([disabled])'))
            .map(cb => parseInt(cb.dataset.newsletterId));

        if (selected.length === 0) {
            showAlert('Please select at least one newsletter', 'error');
            return;
        }

        try {
            const response = await fetch(`/${SITE}/member/newsletters/bulk-subscribe`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({newsletter_ids: selected})
            });

            const data = await response.json();

            if (data.success) {
                showAlert(`Successfully subscribed to ${selected.length} newsletter(s)`, 'success');
                closeNewsletterModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to subscribe', 'error');
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

        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showUpgradeModal(reason, newsletterId, newsletterTitle) {
        selectedNewsletterId = newsletterId;
        upgradeStep = 'plans';

        // Initialize Stripe if not already done
        initializeStripe();

        // Create modal if it doesn't exist
        if (!upgradeModal) {
            createUpgradeModal();
        }

        // Update modal title
        document.getElementById('upgrade-modal-newsletter-title').textContent = newsletterTitle;

        // Load available plans
        loadUpgradePlans(newsletterId);

        // Show modal
        upgradeModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function createUpgradeModal() {
        const modalHtml = `
        <div id="upgrade-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Upgrade to Access <span id="upgrade-modal-newsletter-title"></span></h2>
                    <span class="close" onclick="closeUpgradeModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <!-- Step 1: Plan Selection -->
                    <div id="upgrade-step-plans" class="upgrade-step">
                        <h3>Choose Your Subscription Plan</h3>
                        <div id="upgrade-plans-list" class="plans-list">
                            <div class="loading">Loading plans...</div>
                        </div>
                    </div>

                    <!-- Step 2: Payment Details -->
                    <div id="upgrade-step-payment" class="upgrade-step" style="display: none;">
                        <h3>Payment Details</h3>
                        <div id="selected-plan-summary" class="plan-summary"></div>

                        <form id="upgrade-payment-form">
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="payment_method" id="payment-method" required onchange="handlePaymentMethodChange()">
                                    <option value="">Select payment method</option>
                                    <option value="stripe">Credit/Debit Card (Stripe)</option>
                                    <option value="paypal">PayPal</option>
                                </select>
                            </div>

                            <!-- Stripe Card Element Container -->
                            <div id="stripe-card-container" style="display: none;">
                                <div class="form-group">
                                    <label>Card Details</label>
                                    <div id="card-element" class="stripe-card-element"></div>
                                    <div id="card-errors" class="error-text" role="alert"></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Voucher Code (Optional)</label>
                                <input type="text" name="voucher_code" id="voucher-code" placeholder="Enter voucher code">
                                <button type="button" class="btn btn-link" id="apply-voucher-btn" onclick="applyVoucher()">Apply</button>
                            </div>

                            <div id="voucher-message" class="voucher-message" style="display: none;"></div>
                            <div id="payment-error" class="error-message" style="display: none;"></div>

                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" onclick="goToPlansStep()">Back</button>
                                <button type="submit" class="btn btn-primary" id="submit-payment-btn">
                                    <span id="button-text">Complete Subscription</span>
                                    <span id="button-spinner" class="button-spinner" style="display: none;"></span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Step 3: Processing -->
                    <div id="upgrade-step-processing" class="upgrade-step" style="display: none;">
                        <div class="processing-container">
                            <div class="spinner"></div>
                            <p>Processing your subscription...</p>
                        </div>
                    </div>

                    <!-- Step 4: Success -->
                    <div id="upgrade-step-success" class="upgrade-step" style="display: none;">
                        <div class="success-container">
                            <div class="success-icon">✓</div>
                            <h3>Subscription Successful!</h3>
                            <p>You now have access to this newsletter.</p>
                            <button class="btn btn-primary" onclick="closeUpgradeModalAndReload()">Continue</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        upgradeModal = document.getElementById('upgrade-modal');

        // Setup form submission
        document.getElementById('upgrade-payment-form').addEventListener('submit', handlePaymentSubmit);
    }

    async function loadUpgradePlans(newsletterId) {
        const plansList = document.getElementById('upgrade-plans-list');
        plansList.innerHTML = '<div class="loading">Loading plans...</div>';

        try {
            const response = await fetch('/' + SITE + '/member/newsletters/upgrade-options', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    newsletter_id: newsletterId,
                    site_id: <?= \App\Framework\Support\SiteContext::getId() ?>
                })
            });

            const data = await response.json();

            if (!data.success) {
                plansList.innerHTML = `<div class="error">${data.message}</div>`;
                return;
            }

            if (data.data.plans.length === 0) {
                plansList.innerHTML = '<div class="error">No subscription plans available.</div>';
                return;
            }

            // Render plans
            plansList.innerHTML = data.data.plans.map(plan => `
            <div class="plan-card ${plan.is_featured ? 'featured' : ''}" data-plan-id="${plan.id}">
                ${plan.is_featured ? '<div class="plan-badge">Most Popular</div>' : ''}
                <h4>${plan.name}</h4>
                <div class="plan-price">
                    <span class="price">${plan.currency} ${plan.price}</span>
                    <span class="period">/ ${plan.billing_period}</span>
                </div>
                <p class="plan-description">${plan.description || ''}</p>
                ${plan.features && plan.features.length > 0 ? `
                    <ul class="plan-features">
                        ${plan.features.map(feature => `<li>${feature}</li>`).join('')}
                    </ul>
                ` : ''}
                <button class="btn btn-primary select-plan-btn" onclick="selectPlan(${plan.id}, '${plan.name}', ${plan.price}, '${plan.currency}', '${plan.billing_period}')">
                    Select Plan
                </button>
            </div>
        `).join('');

        } catch (error) {
            console.error('Error loading plans:', error);
            plansList.innerHTML = '<div class="error">Failed to load plans. Please try again.</div>';
        }
    }

    function selectPlan(planId, planName, price, currency, billingPeriod) {
        selectedPlanId = planId;
        selectedPlanPrice = price;
        selectedPlanCurrency = currency;

        // Update plan summary
        document.getElementById('selected-plan-summary').innerHTML = `
        <div class="selected-plan-info">
            <h4>${planName}</h4>
            <p class="plan-price" id="final-price-display">${currency} ${price} / ${billingPeriod}</p>
        </div>
    `;

        // Move to payment step
        showUpgradeStep('payment');
    }

    function handlePaymentMethodChange() {
        const paymentMethod = document.getElementById('payment-method').value;
        const stripeContainer = document.getElementById('stripe-card-container');

        if (paymentMethod === 'stripe') {
            stripeContainer.style.display = 'block';
            setupStripeElements();
        } else {
            stripeContainer.style.display = 'none';
            if (cardElement) {
                cardElement.destroy();
                cardElement = null;
            }
        }
    }

    function setupStripeElements() {
        // Initialize Stripe if not already done
        if (!initializeStripe()) {
            showPaymentError('Failed to initialize payment system. Please refresh and try again.');
            return;
        }

        if (cardElement) {
            return; // Already set up
        }

        try {
            cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#32325d',
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                        '::placeholder': {
                            color: '#aab7c4'
                        }
                    },
                    invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                    }
                },
                hidePostalCode: true,
            });

            cardElement.mount('#card-element');

            cardElement.on('change', function (event) {
                const displayError = document.getElementById('card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });
        } catch (error) {
            console.error('Error setting up Stripe Elements:', error);
            showPaymentError('Failed to load payment form. Please refresh and try again.');
        }
    }

    async function applyVoucher() {
        const voucherCode = document.getElementById('voucher-code').value.trim();
        const voucherMessage = document.getElementById('voucher-message');

        if (!voucherCode) {
            return;
        }

        try {
            const response = await fetch('/api/vouchers/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    voucher_code: voucherCode,
                    plan_id: selectedPlanId
                })
            });

            const data = await response.json();

            if (data.success && data.valid) {
                const finalPrice = data.final_price;
                const discount = data.discount;

                voucherMessage.innerHTML = `
                <span class="success-text">✓ Voucher applied! Discount: ${selectedPlanCurrency} ${discount}</span>
            `;
                voucherMessage.style.display = 'block';
                voucherMessage.className = 'voucher-message success';

                // Update displayed price
                document.getElementById('final-price-display').innerHTML = `
                <span style="text-decoration: line-through; color: #999;">${selectedPlanCurrency} ${selectedPlanPrice}</span>
                <span style="color: #28a745; font-weight: bold;"> ${selectedPlanCurrency} ${finalPrice}</span>
            `;
            } else {
                voucherMessage.innerHTML = `
                <span class="error-text">✗ ${data.message || 'Invalid voucher code'}</span>
            `;
                voucherMessage.style.display = 'block';
                voucherMessage.className = 'voucher-message error';
            }
        } catch (error) {
            console.error('Error validating voucher:', error);
            voucherMessage.innerHTML = '<span class="error-text">Failed to validate voucher</span>';
            voucherMessage.style.display = 'block';
            voucherMessage.className = 'voucher-message error';
        }
    }


    function showUpgradeStep(step) {
        upgradeStep = step;

        // Hide all steps
        document.querySelectorAll('.upgrade-step').forEach(el => {
            el.style.display = 'none';
        });

        // Show current step
        document.getElementById(`upgrade-step-${step}`).style.display = 'block';
    }

    async function handlePaymentSubmit(e) {
        e.preventDefault();

        const paymentMethod = document.getElementById('payment-method').value;
        const voucherCode = document.getElementById('voucher-code').value.trim();

        if (!paymentMethod) {
            showPaymentError('Please select a payment method');
            return;
        }

        // Disable submit button
        setLoading(true);

        try {
            if (paymentMethod === 'stripe') {
                await handleStripePayment(voucherCode);
            } else if (paymentMethod === 'paypal') {
                await handlePayPalPayment(voucherCode);
            }
        } catch (error) {
            console.error('Payment error:', error);
            showPaymentError(error.message || 'Payment processing failed. Please try again.');
            setLoading(false);
        }
    }

    async function handleStripePayment(voucherCode) {
        if (!stripe || !cardElement) {
            throw new Error('Stripe not initialized');
        }

        // First, create the subscription on backend
        const response = await fetch('/' + SITE + '/member/newsletters/process-upgrade', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                newsletter_id: selectedNewsletterId,
                plan_id: selectedPlanId,
                payment_method: 'stripe',
                voucher_code: voucherCode || null,
                setup_only: true // Flag to indicate we only want setup intent
            })
        });

        const data = await response.json();

        console.log('data', data, data.success)

        if (!data.success) {
            throw new Error(data.data.message);
        }

        // If we have a client secret, confirm the card payment
        if (data.data.client_secret) {
            const {error, paymentIntent} = await stripe.confirmCardPayment(data.client_secret, {
                payment_method: {
                    card: cardElement,
                }
            });

            if (error) {
                throw new Error(error.message);
            }

            if (paymentIntent.status === 'succeeded') {
                // Confirm the subscription on backend
                await confirmSubscription(data.subscription_id);
            }
        } else if (data.data.subscription_id) {
            // Payment already processed (e.g., free with voucher)
            showUpgradeStep('success');
            setLoading(false);
        }
    }

    async function handlePayPalPayment(voucherCode) {
        const response = await fetch('/member/newsletters/process-upgrade', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                newsletter_id: selectedNewsletterId,
                plan_id: selectedPlanId,
                payment_method: 'paypal',
                voucher_code: voucherCode || null
            })
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message);
        }

        // Redirect to PayPal
        if (data.redirect_url) {
            window.location.href = data.redirect_url;
        } else {
            showUpgradeStep('success');
            setLoading(false);
        }
    }

    async function confirmSubscription(subscriptionId) {
        const response = await fetch('/member/newsletters/confirm-upgrade', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                subscription_id: subscriptionId
            })
        });

        const data = await response.json();

        if (data.success) {
            showUpgradeStep('success');
        } else {
            throw new Error(data.message || 'Failed to confirm subscription');
        }

        setLoading(false);
    }

    function setLoading(loading) {
        const submitBtn = document.getElementById('submit-payment-btn');
        const buttonText = document.getElementById('button-text');
        const buttonSpinner = document.getElementById('button-spinner');

        if (loading) {
            submitBtn.disabled = true;
            buttonText.style.display = 'none';
            buttonSpinner.style.display = 'inline-block';
        } else {
            submitBtn.disabled = false;
            buttonText.style.display = 'inline';
            buttonSpinner.style.display = 'none';
        }
    }

    function showPaymentError(message) {
        const errorEl = document.getElementById('payment-error');
        errorEl.textContent = message;
        errorEl.style.display = 'block';

        setTimeout(() => {
            errorEl.style.display = 'none';
        }, 5000);
    }

    function goToPlansStep() {
        selectedPlanId = null;
        selectedPlanPrice = null;
        selectedPlanCurrency = null;

        // Destroy Stripe elements
        if (cardElement) {
            cardElement.destroy();
            cardElement = null;
        }

        showUpgradeStep('plans');
    }

    function closeUpgradeModal() {
        if (upgradeModal) {
            upgradeModal.style.display = 'none';
            document.body.style.overflow = '';

            // Destroy Stripe elements
            if (cardElement) {
                cardElement.destroy();
                cardElement = null;
            }

            // Reset form
            document.getElementById('upgrade-payment-form').reset();
            document.getElementById('payment-error').style.display = 'none';
            document.getElementById('stripe-card-container').style.display = 'none';

            // Reset to plans step
            showUpgradeStep('plans');
            setLoading(false);
        }
    }

    function closeUpgradeModalAndReload() {
        closeUpgradeModal();
        // Reload the page to show updated access
        window.location.reload();
    }

    // Close modal when clicking outside
    window.onclick = function (event) {
        if (event.target === upgradeModal) {
            closeUpgradeModal();
        }
    }


    function selectUpgradePlan(slug) {
        window.location.href = `/${SITE}/member/subscription-plans/${slug}`;
    }

    // Close modal on outside click
    document.getElementById('newsletterModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeNewsletterModal();
        }
    });
</script>

</body>
</html>