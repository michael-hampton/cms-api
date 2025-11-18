<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= htmlspecialchars($order->order_number) ?> - <?= htmlspecialchars($site->name) ?></title>
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

        .header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav {
            display: flex;
            gap: 1.5rem;
        }

        .nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav a:hover {
            color: var(--primary-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .order-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .order-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.processing {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .status-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .order-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .summary-item {
            display: flex;
            flex-direction: column;
        }

        .summary-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .summary-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
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

        .items-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .item {
            display: flex;
            gap: 1rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 0.5rem;
            background: var(--bg-light);
            object-fit: cover;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .item-price {
            text-align: right;
            font-weight: 600;
        }

        .address-block {
            margin-bottom: 1.5rem;
        }

        .address-block:last-child {
            margin-bottom: 0;
        }

        .address-title {
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .address-content {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .totals-table {
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
        }

        .totals-row.total {
            font-size: 1.25rem;
            font-weight: 700;
            padding-top: 1rem;
            margin-top: 0.5rem;
            border-top: 2px solid var(--border-color);
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

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 1rem;
            }

            .header-top {
                flex-direction: column;
                gap: 1rem;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /** Refund **/
        .refund-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
        }

        .refund-modal-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
        }

        .refund-modal-container {
            position: relative;
            background: white;
            max-width: 800px;
            margin: 2rem auto;
            border-radius: 1rem;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .refund-modal-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .refund-modal-header h2 {
            margin-bottom: 0.5rem;
        }

        .refund-modal-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .refund-close-btn {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: var(--text-secondary);
            line-height: 1;
        }

        .refund-modal-body {
            padding: 2rem;
        }

        .refund-type-section {
            margin-bottom: 2rem;
        }

        .refund-type-section label {
            display: block;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .refund-type-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .refund-type-option {
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .refund-type-option:hover {
            border-color: var(--primary-color);
        }

        .refund-type-option.active {
            border-color: var(--primary-color);
            background: #eff6ff;
        }

        .refund-type-option input {
            display: none;
        }

        .refund-type-option strong {
            display: block;
            margin-bottom: 0.25rem;
        }

        .refund-type-option p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .refund-summary {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .refund-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
        }

        .refund-amount-row {
            border-top: 2px solid var(--border-color);
            margin-top: 0.5rem;
            padding-top: 1rem;
            font-weight: 600;
            font-size: 1.25rem;
            color: var(--danger-color);
        }

        .refund-progress {
            margin: 1rem 0 0.5rem;
            height: 0.5rem;
            background: var(--border-color);
            border-radius: 0.25rem;
            overflow: hidden;
        }

        .refund-progress-bar {
            height: 100%;
            background: linear-gradient(to right, #ef4444, #dc2626);
            transition: width 0.3s;
        }

        .refund-percentage {
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .refund-items-list {
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            margin-top: 1rem;
            overflow: hidden;
        }

        .refund-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .refund-item:last-child {
            border-bottom: none;
        }

        .refund-item-info strong {
            display: block;
            margin-bottom: 0.25rem;
        }

        .refund-item-info span {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .refund-qty-input {
            width: 80px;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
        }

        .refund-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 0.5rem;
        }

        .refund-options label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }

        .refund-modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .refund-type-buttons {
                grid-template-columns: 1fr;
            }

            .refund-modal-container {
                margin: 0;
                border-radius: 0;
                max-height: 100vh;
            }
        }


        .cancel-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .cancel-modal-container {
            background: white;
            border-radius: 1rem;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border-top: 4px solid var(--danger-color);
        }

        .cancel-modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            background: linear-gradient(to bottom, #fef2f2 0%, white 100%);
        }

        .cancel-warning-icon {
            font-size: 2rem;
        }

        .cancel-modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--text-primary);
        }

        .cancel-modal-header p {
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .cancel-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0.25rem 0.5rem;
        }

        .cancel-modal-body {
            padding: 1.5rem;
        }

        .cancel-form-group {
            margin-bottom: 1rem;
        }

        .cancel-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-primary);
        }

        .cancel-form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .cancel-checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: normal !important;
            cursor: pointer;
        }

        .cancel-checkbox-label input {
            width: 18px;
            height: 18px;
        }

        .cancel-warning-box {
            padding: 1rem;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 0.5rem;
            margin-top: 1rem;
            font-size: 0.875rem;
        }

        .cancel-warning-box strong {
            color: #92400e;
            display: block;
            margin-bottom: 0.5rem;
        }

        .cancel-warning-box ul {
            margin: 0.5rem 0 0 1.25rem;
            padding: 0;
            color: #78350f;
        }

        .cancel-modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-content">
        <a href="/" class="logo"><?= htmlspecialchars($site->name) ?></a>
        <nav class="nav">
            <a href="/member/dashboard">Dashboard</a>
            <a href="/member/orders">Orders</a>
            <a href="/member/addresses">Addresses</a>
            <a href="/member/settings">Settings</a>
            <a href="/member/logout">Logout</a>
        </nav>
    </div>
</header>

<main class="container">
    <div class="page-header">
        <div class="header-top">
            <div>
                <h1 class="order-title">Order #<?= htmlspecialchars($order->order_number) ?></h1>
                <div class="order-meta">
                    Placed on <?= $order->created_at->format('F j, Y \a\t g:i A') ?>
                </div>
            </div>
            <span class="status-badge <?= strtolower($order->status) ?>">
                <?= htmlspecialchars($order->status) ?>
            </span>
        </div>

        <div class="order-summary">
            <div class="summary-item">
                <span class="summary-label">Total Amount</span>
                <span class="summary-value"><?= htmlspecialchars($order->currency) ?> <?= number_format($order->total ?? 0, 2) ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Payment Status</span>
                <span class="summary-value" style="color: <?= $order->isPaid() ? 'var(--success-color)' : 'var(--warning-color)' ?>">
                    <?= $order->isPaid() ? 'Paid' : 'Pending' ?>
                </span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Payment Method</span>
                <span class="summary-value"><?= htmlspecialchars($order->payment_method ?: 'N/A') ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Items</span>
                <span class="summary-value"><?= $order->items->count() ?></span>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <div>
            <div class="card">
                <h2 class="card-title">Order Items</h2>
                <div class="items-list">
                    <?php foreach ($order->items as $item): ?>
                        <div class="item">
                            <div class="item-details">
                                <div class="item-name"><?= htmlspecialchars($item->product_name) ?></div>
                                <div class="item-meta">
                                    Quantity: <?= $item->quantity ?> × <?= htmlspecialchars($order->currency) ?> <?= number_format($item->price ?? 0, 2) ?>
                                </div>
                            </div>
                            <div class="item-price">
                                <?= htmlspecialchars($order->currency) ?> <?= number_format($item->quantity * $item->price, 2) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="totals-table">
                    <div class="totals-row">
                        <span>Subtotal</span>
                        <span><?= htmlspecialchars($order->currency) ?> <?= number_format($order->subtotal, 2) ?></span>
                    </div>
                    <?php if ($order->shipping > 0): ?>
                        <div class="totals-row">
                            <span>Shipping</span>
                            <span><?= htmlspecialchars($order->currency) ?> <?= number_format($order->shipping, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($order->tax > 0): ?>
                        <div class="totals-row">
                            <span>Tax</span>
                            <span><?= htmlspecialchars($order->currency) ?> <?= number_format($order->tax, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($order->discount > 0): ?>
                        <div class="totals-row" style="color: var(--success-color)">
                            <span>Discount</span>
                            <span>-<?= htmlspecialchars($order->currency) ?> <?= number_format($order->discount, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="totals-row total">
                        <span>Total</span>
                        <span><?= htmlspecialchars($order->currency) ?> <?= number_format($order->total, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <h2 class="card-title">Shipping & Billing</h2>

                <?php if ($shippingAddress = $order->getShippingAddressDataAttribute()): ?>
                    <div class="address-block">
                        <div class="address-title">
                            📦 Shipping Address
                        </div>
                        <div class="address-content">
                            <?= htmlspecialchars($shippingAddress['address_line_1'] ?? '') ?><br>
                            <?php if (!empty($shippingAddress['address_line_2'])): ?>
                                <?= htmlspecialchars($shippingAddress['address_line_2']) ?><br>
                            <?php endif; ?>
                            <?= htmlspecialchars($shippingAddress['city'] ?? '') ?>, <?= htmlspecialchars($shippingAddress['state'] ?? '') ?> <?= htmlspecialchars($shippingAddress['postcode'] ?? '') ?><br>
                            <?= htmlspecialchars($shippingAddress['country'] ?? '') ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($billingAddress = $order->getBillingAddressDataAttribute()): ?>
                    <div class="address-block">
                        <div class="address-title">
                            💳 Billing Address
                        </div>
                        <div class="address-content">
                            <?= htmlspecialchars($billingAddress['address_line_1'] ?? '') ?><br>
                            <?php if (!empty($billingAddress['address_line_2'])): ?>
                                <?= htmlspecialchars($billingAddress['address_line_2']) ?><br>
                            <?php endif; ?>
                            <?= htmlspecialchars($billingAddress['city'] ?? '') ?>, <?= htmlspecialchars($billingAddress['state'] ?? '') ?> <?= htmlspecialchars($billingAddress['postcode'] ?? '') ?><br>
                            <?= htmlspecialchars($billingAddress['country'] ?? '') ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($order->customer_notes): ?>
                    <div class="address-block">
                        <div class="address-title">
                            📝 Customer Notes
                        </div>
                        <div class="address-content">
                            <?= nl2br(htmlspecialchars($order->customer_notes)) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="actions">
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/orders" class="btn btn-secondary">
            ← Back to Orders
        </a>
        <?php if ($order->canBeRefunded()): ?>
            <button onclick="openRefundModal()" class="btn btn-warning">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                Process Refund
            </button>
        <?php endif; ?>

        <?php if ($order->canBeCancelled()): ?>
            <button onclick="cancelOrder(<?= $order->id ?>)" class="btn btn-warning">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                Cancel Order
            </button>
        <?php endif; ?>
    </div>
</main>

<script>
    async function cancelOrder(orderId) {
        if (!confirm('Are you sure you want to cancel this order?')) {
            return;
        }

        try {
            const response = await fetch(`/member/orders/${orderId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                alert('Order cancelled successfully');
                window.location.href = '/member/orders';
            } else {
                alert(data.message || 'Failed to cancel order');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to cancel order');
        }
    }
</script>

<!-- Refund Modal -->
<div id="refundModal" class="refund-modal" style="display: none;">
    <div class="refund-modal-overlay" onclick="closeRefundModal()"></div>
    <div class="refund-modal-container">
        <div class="refund-modal-header">
            <h2>Process Refund</h2>
            <p class="refund-modal-subtitle">Order #<?= htmlspecialchars($order->order_number) ?></p>
            <button class="refund-close-btn" onclick="closeRefundModal()">×</button>
        </div>
        <div class="refund-modal-body">
            <div class="refund-type-section">
                <label>Refund Type</label>
                <div class="refund-type-buttons">
                    <label class="refund-type-option active">
                        <input type="radio" name="refund_type" value="full" checked onchange="updateRefundType('full')">
                        <div>
                            <strong>Full Refund</strong>
                            <p>Refund the entire order amount</p>
                        </div>
                    </label>
                    <label class="refund-type-option">
                        <input type="radio" name="refund_type" value="partial" onchange="updateRefundType('partial')">
                        <div>
                            <strong>Partial Refund</strong>
                            <p>Refund specific items or amount</p>
                        </div>
                    </label>
                </div>
            </div>

            <div class="refund-summary">
                <div class="refund-summary-row">
                    <span>Order Total:</span>
                    <span><?= htmlspecialchars($order->currency) ?> <?= number_format($order->total, 2) ?></span>
                </div>
                <div class="refund-summary-row refund-amount-row">
                    <span>Refund Amount:</span>
                    <span id="refundAmount"><?= htmlspecialchars($order->currency) ?> <?= number_format($order->total, 2) ?></span>
                </div>
                <div class="refund-progress">
                    <div id="refundProgressBar" class="refund-progress-bar" style="width: 100%"></div>
                </div>
                <div class="refund-percentage" id="refundPercentage">100% of order total</div>
            </div>

            <div id="partialRefundItems" style="display: none;">
                <label>Items to Refund</label>
                <div class="refund-items-list">
                    <?php foreach ($order->items as $item): ?>
                        <div class="refund-item">
                            <div class="refund-item-info">
                                <strong><?= htmlspecialchars($item->product_name) ?></strong>
                                <span><?= htmlspecialchars($order->currency) ?> <?= number_format($item->unit_price, 2) ?> × <?= $item->quantity ?></span>
                            </div>
                            <div class="refund-item-controls">
                                <input type="number"
                                       class="refund-qty-input"
                                       min="0"
                                       max="<?= $item->quantity ?>"
                                       value="0"
                                       data-item-id="<?= $item->id ?>"
                                       data-price="<?= $item->unit_price ?>"
                                       onchange="updatePartialRefund()">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Reason for Refund *</label>
                <select id="refundReason" required>
                    <option value="">Select a reason</option>
                    <option value="customer_request">Customer Request</option>
                    <option value="damaged_item">Damaged Item</option>
                    <option value="wrong_item">Wrong Item Sent</option>
                    <option value="not_received">Item Not Received</option>
                    <option value="quality_issue">Quality Issue</option>
                    <option value="changed_mind">Customer Changed Mind</option>
                    <option value="duplicate_order">Duplicate Order</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Internal Notes</label>
                <textarea id="refundNotes" rows="3" placeholder="Add any internal notes..."></textarea>
            </div>

            <div class="refund-options">
                <label>
                    <input type="checkbox" id="notifyCustomer" checked>
                    <span>Notify customer via email</span>
                </label>
                <label>
                    <input type="checkbox" id="restockItems" checked>
                    <span>Restock items to inventory</span>
                </label>
            </div>
        </div>
        <div class="refund-modal-footer">
            <button class="btn btn-secondary" onclick="closeRefundModal()">Cancel</button>
            <button class="btn btn-warning" onclick="processRefund()">Process Refund</button>
        </div>
    </div>
</div>

<div class="cancel-modal-overlay" style="display: none;">
    <div class="cancel-modal-container">
        <div class="cancel-modal-header">
            <div class="cancel-warning-icon">⚠️</div>
            <div>
                <h2>Cancel Order</h2>
                <p>This action will cancel the order and cannot be undone</p>
            </div>
            <button class="cancel-modal-close" onclick="this.closest('.cancel-modal-overlay').remove()">×</button>
        </div>
        <div class="cancel-modal-body">
            <div class="cancel-form-group">
                <label>Cancellation Reason *</label>
                <select id="cancelReason" class="cancel-form-control" required>
                    <option value="">Select a reason...</option>
                    <option value="customer_request">Customer Request</option>
                    <option value="out_of_stock">Out of Stock</option>
                    <option value="payment_failed">Payment Failed</option>
                    <option value="fraudulent">Fraudulent Order</option>
                    <option value="duplicate">Duplicate Order</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="cancel-form-group">
                <label class="cancel-checkbox-label">
                    <input type="checkbox" id="notifyCustomer" checked>
                    <span>Send cancellation notification to customer</span>
                </label>
            </div>
            <div class="cancel-warning-box">
                <strong>Important:</strong> Cancelling this order will:
                <ul>
                    <li>Mark the order as cancelled in the system</li>
                    <li>Release any reserved inventory</li>
                    <li>Stop any pending fulfillment processes</li>
                </ul>
            </div>
        </div>
        <div class="cancel-modal-footer">
            <input type="hidden" id="cancelOrderId" value="">
            <button class="btn btn-secondary" onclick="this.closest('.cancel-modal-overlay').style.display='none'">Keep
                Order
            </button>
            <button class="btn btn-danger" onclick="confirmCancelOrder()">Cancel Order</button>
        </div>
    </div>
</div>
</body>
</html>

<script>
    let currentRefundType = 'full';
    const orderTotal = <?= $order->total ?>;
    const orderCurrency = '<?= htmlspecialchars($order->currency) ?>';

    async function cancelOrder(orderId) {
        document.getElementById('cancelOrderId').value = orderId;
        document.querySelector('.cancel-modal-overlay').style.display = 'flex';
    }

    async function confirmCancelOrder() {
        const reason = document.getElementById('cancelReason').value;
        const notifyCustomer = document.getElementById('notifyCustomer').checked;
        const orderId = document.getElementById('cancelOrderId').value

        if (!reason) {
            alert('Please select a cancellation reason');
            return;
        }

        try {
            const response = await fetch(`/member/orders/${orderId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                alert('Order cancelled successfully');
                location.reload();
            } else {
                alert(data.message || 'Failed to cancel order');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to cancel order');
        }
    }

    function openRefundModal() {
        document.getElementById('refundModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeRefundModal() {
        document.getElementById('refundModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function updateRefundType(type) {
        currentRefundType = type;
        const partialItems = document.getElementById('partialRefundItems');

        // Update active state
        document.querySelectorAll('.refund-type-option').forEach(option => {
            option.classList.remove('active');
        });
        event.target.closest('.refund-type-option').classList.add('active');

        if (type === 'full') {
            partialItems.style.display = 'none';
            updateRefundDisplay(orderTotal);
        } else {
            partialItems.style.display = 'block';
            updatePartialRefund();
        }
    }

    function updatePartialRefund() {
        let total = 0;
        document.querySelectorAll('.refund-qty-input').forEach(input => {
            const quantity = parseInt(input.value) || 0;
            alert(quantity)
            const price = parseFloat(input.dataset.price) || 0;
            alert(price)
            total += quantity * price;
        });
        alert(total)
        updateRefundDisplay(total);
    }

    function updateRefundDisplay(amount) {
        const percentage = (amount / orderTotal) * 100;
        document.getElementById('refundAmount').textContent =
            `${orderCurrency} ${amount.toFixed(2)}`;
        document.getElementById('refundProgressBar').style.width = `${percentage}%`;
        document.getElementById('refundPercentage').textContent =
            `${percentage.toFixed(1)}% of order total`;
    }

    async function processRefund() {
        const reason = document.getElementById('refundReason').value;
        if (!reason) {
            alert('Please select a reason for the refund');
            return;
        }

        const refundData = {
            order_id: <?= $order->id ?>,
            refund_type: currentRefundType,
            refund_amount: currentRefundType === 'full' ? orderTotal : calculatePartialAmount(),
            reason: reason,
            internal_notes: document.getElementById('refundNotes').value,
            notify_customer: document.getElementById('notifyCustomer').checked,
            restock_items: document.getElementById('restockItems').checked,
            items: currentRefundType === 'partial' ? getPartialItems() : []
        };

        try {
            const response = await fetch(`/member/orders/<?= $order->id ?>/refund`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(refundData)
            });

            const data = await response.json();

            if (data.success) {
                alert('Refund processed successfully');
                window.location.reload();
            } else {
                alert(data.message || 'Failed to process refund');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to process refund');
        }
    }

    function calculatePartialAmount() {
        let total = 0;
        document.querySelectorAll('.refund-qty-input').forEach(input => {
            const quantity = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price) || 0;
            total += quantity * price;
        });
        return total;
    }

    function getPartialItems() {
        const items = [];
        document.querySelectorAll('.refund-qty-input').forEach(input => {
            const quantity = parseInt(input.value) || 0;
            if (quantity > 0) {
                items.push({
                    item_id: input.dataset.itemId,
                    quantity: quantity,
                    amount: quantity * parseFloat(input.dataset.price)
                });
            }
        });
        return items;
    }
</script>