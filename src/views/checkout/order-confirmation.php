<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - YourStore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --success-light: #d1fae5;
            --success-dark: #065f46;
            --secondary-color: #64748b;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: var(--bg-light);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .site-header {
            background: white;
            box-shadow: var(--shadow);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo a {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .main-nav {
            display: flex;
            gap: 2rem;
        }

        .main-nav a {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .main-nav a:hover {
            color: var(--primary-color);
        }

        /* Success Banner */
        .success-banner {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
            text-align: center;
            padding: 3rem 2rem;
            border-radius: 1rem;
            margin: 0 -20px 2rem -20px; /* Negative margin to counteract container padding */
            box-shadow: var(--shadow-lg);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: scaleIn 0.5s ease-out;
        }

        .success-icon svg {
            width: 48px;
            height: 48px;
            stroke: var(--success-color);
            stroke-width: 3;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-banner h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .success-banner p {
            font-size: 1.125rem;
            opacity: 0.95;
        }

        .order-number {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
            font-weight: 600;
            font-size: 1.125rem;
        }

        /* Content Grid */
        .confirmation-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .card {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .card-header svg {
            width: 24px;
            height: 24px;
            stroke: var(--primary-color);
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        /* Order Items */
        .order-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .item-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .item-quantity {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .item-price {
            text-align: right;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Order Summary */
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
        }

        .summary-row.subtotal {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .summary-row.total {
            font-size: 1.5rem;
            font-weight: 700;
            padding-top: 1rem;
            margin-top: 1rem;
            border-top: 2px solid var(--border-color);
            color: var(--primary-color);
        }

        /* Info Sections */
        .info-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .info-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .info-value {
            color: var(--text-primary);
            line-height: 1.5;
        }

        .address-line {
            display: block;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
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
            background: var(--success-light);
            color: var(--success-dark);
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--bg-light);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            background: var(--bg-light);
        }

        /* Email Confirmation Notice */
        .email-notice {
            background: var(--bg-light);
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 2rem;
            display: flex;
            align-items: start;
            gap: 1rem;
        }

        .email-notice svg {
            width: 24px;
            height: 24px;
            stroke: var(--primary-color);
            flex-shrink: 0;
            margin-top: 2px;
        }

        .email-notice-content h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .email-notice-content p {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Print Styles */
        @media print {
            .site-header,
            .action-buttons,
            .email-notice,
            .main-nav {
                display: none !important;
            }

            body {
                background: white;
            }

            .card {
                box-shadow: none;
                border: 1px solid var(--border-color);
            }
        }


        @media (max-width: 968px) {
            .confirmation-content {
                grid-template-columns: 1fr;
            }

            .success-banner h1 {
                font-size: 1.5rem;
            }

            .success-banner p {
                font-size: 1rem;
            }

            .order-item {
                flex-direction: column;
            }

            .item-price {
                text-align: left;
            }

            /* Adjust negative margins for mobile */
            .success-banner,
            .email-notice {
                margin-left: -20px;
                margin-right: -20px;
                border-radius: 0;
            }
        }

        @media (max-width: 640px) {
            .main-nav {
                display: none;
            }

            .card {
                padding: 1.5rem;
            }

            .success-banner {
                padding: 2rem 1.5rem;
                margin-left: -20px;
                margin-right: -20px;
                border-radius: 0;
            }

            .email-notice {
                padding: 1rem;
                margin-left: -20px;
                margin-right: -20px;
                border-radius: 0;
                flex-direction: column;
            }

            .email-notice > *,
            .email-notice svg {
                margin-left: 1rem;
            }

            .summary-row.total {
                font-size: 1.25rem;
            }

            /* Reduce container padding on very small screens */
            .container {
                padding: 0 15px;
            }

            .success-banner,
            .email-notice {
                margin-left: -15px;
                margin-right: -15px;
            }
        }

        /* Extra small screens */
        @media (max-width: 375px) {
            .container {
                padding: 0 10px;
            }

            .success-banner,
            .email-notice {
                margin-left: -10px;
                margin-right: -10px;
            }

            .card {
                padding: 1rem;
            }

            .success-banner {
                padding: 1.5rem 1rem;
            }

            .success-icon {
                width: 60px;
                height: 60px;
            }

            .success-icon svg {
                width: 36px;
                height: 36px;
            }

            .order-number {
                font-size: 1rem;
                padding: 0.4rem 0.8rem;
            }
        }


        /* Loading Animation */
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Add this media query specifically for iPhone 12 Pro and similar devices */
        @media (max-width: 390px) {
            body {
                overflow-x: hidden;
            }

            .container {
                padding: 0;
                max-width: 100%;
            }

            .success-banner {
                margin: 0;
                border-radius: 0;
                padding: 2rem 1rem;
                width: 100%;
                box-sizing: border-box;
            }

            .email-notice {
                margin: 0 0 2rem 0;
                border-radius: 0;
                padding: 1rem;
                width: 100%;
                box-sizing: border-box;
            }

            .card {
                margin: 0 0 1rem 0;
                border-radius: 0;
                padding: 1.5rem 1rem;
                width: 100%;
                box-sizing: border-box;
            }

            .confirmation-content {
                gap: 1rem;
                padding: 0;
            }

            .site-header .container {
                padding: 0 1rem;
            }

            main.container {
                padding: 0;
            }

            /* Ensure no horizontal scroll */
            * {
                max-width: 100%;
            }

            .order-number {
                font-size: 0.9rem;
                padding: 0.4rem 0.8rem;
                word-break: break-all;
            }

            .success-icon {
                width: 60px;
                height: 60px;
            }

            .success-icon svg {
                width: 36px;
                height: 36px;
            }

            .success-banner h1 {
                font-size: 1.5rem;
            }

            .success-banner p {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<header class="site-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="/">YourStore</a>
            </div>
            <nav class="main-nav">
                <a href="/">Home</a>
                <a href="/shop">Shop</a>
                <a href="/cart">Cart</a>
                <a href="/contact">Contact</a>
            </nav>
        </div>
    </div>
</header>

<main class="container">

    <!-- Success Banner -->
    <div class="success-banner fade-in">
        <div class="success-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>

        <h1>Order Confirmed!</h1>
        <p>Thank you for your purchase. Your order has been received.</p>

        <div class="order-number">
            <?php if ($orders->count() === 1): ?>
                Order #<?= htmlspecialchars($orders->first()->order_number) ?>
            <?php else: ?>
                <?= $orders->count() ?> Orders Placed
            <?php endif; ?>
        </div>
    </div>

    <!-- Email Notice -->
    <div class="email-notice fade-in">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
        </svg>
        <div class="email-notice-content">
            <h3>Confirmation Email Sent</h3>
            <p>
                We've sent a confirmation email to
                <strong><?= htmlspecialchars($orders->first()->user->email ?? 'N/A') ?></strong>
                with your order details and tracking information.
            </p>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="confirmation-content">

        <?php foreach ($orders

        as $order): ?>

        <div class="card fade-in" style="margin-bottom: 2rem;">

            <!-- Order Header -->
            <div class="card-header">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <h2>Order #<?= htmlspecialchars($order->order_number) ?></h2>
            </div>

            <!-- Order Items -->
            <?php foreach ($order->items as $item): ?>
                <div class="order-item">
                    <img src="<?= htmlspecialchars($item->product?->image ?? '/images/placeholder.jpg') ?>"
                         alt="<?= htmlspecialchars($item->product_name) ?>"
                         class="item-image">

                    <div class="item-details">
                        <div class="item-name"><?= htmlspecialchars($item->product_name) ?></div>

                        <?php if ($item->product_sku): ?>
                            <div class="item-meta">
                                SKU: <?= htmlspecialchars($item->product_sku) ?>
                            </div>
                        <?php endif; ?>

                        <div class="item-quantity">
                            Quantity: <?= $item->quantity ?> × $<?= number_format($item->unit_price, 2) ?>
                        </div>
                    </div>

                    <div class="item-price">
                        $<?= number_format($item->total, 2) ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Bottom Grid -->
            <div style="display: flex; flex-direction: column; gap: 2rem; margin-top: 2rem;">
                <!-- Shipping & Payment Info -->
                <div>
                    <div class="card" style="box-shadow:none;">
                        <div class="card-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            <h2>Order Information</h2>
                        </div>

                        <?php $shippingAddress = $order->shippingAddress; ?>

                        <div class="info-section">
                            <div class="info-label">Shipping Address</div>
                            <div class="info-value">

                                <?php if ($shippingAddress): ?>

                                    <span class="address-line">
                                            <?= htmlspecialchars($shippingAddress->address_line_1) ?>
                                        </span>

                                    <?php if (!empty($shippingAddress->address_line_2)): ?>
                                        <span class="address-line">
                                                <?= htmlspecialchars($shippingAddress->address_line_2) ?>
                                            </span>
                                    <?php endif; ?>

                                    <span class="address-line">
                                            <?= htmlspecialchars($shippingAddress->city) ?>
                                            <?= $shippingAddress->state ? ', ' . htmlspecialchars($shippingAddress->state) : '' ?>
                                            <?= htmlspecialchars($shippingAddress->postcode) ?>
                                        </span>

                                    <span class="address-line">
                                            <?= htmlspecialchars($shippingAddress->country) ?>
                                        </span>

                                <?php else: ?>
                                    <span class="address-line">No shipping address found</span>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>

                    <div class="info-section">
                        <div class="info-label">Payment Method</div>
                        <div class="info-value">
                            <?php
                            $paymentLabels = [
                                    'card' => 'Credit / Debit Card',
                                    'paypal' => 'PayPal',
                                    'bank' => 'Bank Transfer'
                            ];

                            $method = $order->payment_method ?? null;

                            echo htmlspecialchars(
                                    $paymentLabels[$method] ?? ($method ? ucfirst($method) : 'N/A')
                            );
                            ?>
                        </div>
                    </div>

                    <div class="info-section">
                        <div class="info-label">Order Status</div>
                        <div class="info-value">
                                    <span class="status-badge <?= htmlspecialchars($order->status) ?>">
                                        <?= htmlspecialchars(ucfirst($order->status)) ?>
                                    </span>
                        </div>
                    </div>

                    <div class="info-section">
                        <div class="info-label">Order Date</div>
                        <div class="info-value">
                            <?= $order->created_at->format('F j, Y \a\t g:i A') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div>
                <div class="card" style="box-shadow:none;">
                    <div class="card-header">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                        <h2>Order Summary</h2>
                    </div>

                    <div class="summary-row subtotal">
                        <span>Subtotal:</span>
                        <span>$<?= number_format($order->subtotal, 2) ?></span>
                    </div>

                    <div class="summary-row subtotal">
                        <span>Shipping:</span>
                        <span><?= $order->shipping > 0 ? '$' . number_format($order->shipping, 2) : 'Free' ?></span>
                    </div>

                    <div class="summary-row subtotal">
                        <span>Tax:</span>
                        <span>$<?= number_format($order->tax, 2) ?></span>
                    </div>

                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>$<?= number_format($order->total, 2) ?></span>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <?php endforeach; ?>

    <!-- Actions -->
    <div class="card fade-in">
        <div class="action-buttons">
            <a href="/" class="btn btn-primary">Continue Shopping</a>
            <button onclick="window.print()" class="btn btn-outline">Print Receipt</button>
            <a href="/account/orders" class="btn btn-secondary">View All Orders</a>
        </div>
    </div>

    <!-- Help Section -->
    <div class="card fade-in" style="margin-top: 2rem;">
        <div class="card-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <h2>Need Help?</h2>
        </div>
        <div class="info-value" style="line-height: 1.8;">
            <p style="margin-bottom: 1rem;">
                If you have any questions about your order, please don't hesitate to contact us.
            </p>
            <a href="/contact" class="btn btn-outline" style="width: 100%;">Contact Support</a>
        </div>
    </div>

    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const elements = document.querySelectorAll('.fade-in');
        elements.forEach((el, index) => {
            el.style.animationDelay = `${index * 0.1}s`;
        });
    });

    console.log('Orders viewed:', <?= json_encode($orders->pluck('order_number')) ?>);
</script>
</html>