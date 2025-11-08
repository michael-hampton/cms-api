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
        <a href="/member/orders" class="btn btn-secondary">
            ← Back to Orders
        </a>
        <?php if ($order->canBeCancelled()): ?>
            <button onclick="cancelOrder(<?= $order->id ?>)" class="btn btn-secondary">
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
</body>
</html>