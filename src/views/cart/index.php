<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - YourStore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
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
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
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

        .main-nav a:hover,
        .main-nav a.active {
            color: var(--primary-color);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .icon-btn {
            position: relative;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s;
        }

        .icon-btn:hover {
            background-color: var(--bg-light);
        }

        .badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger-color);
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.375rem;
            border-radius: 1rem;
            font-weight: 600;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .breadcrumb {
            display: flex;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        /* Cart Layout */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        /* Cart Items */
        .cart-items {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .cart-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .clear-cart-btn {
            background: none;
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .clear-cart-btn:hover {
            background: var(--danger-color);
            color: white;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
        }

        .item-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .item-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            text-decoration: none;
            transition: color 0.3s;
        }

        .item-name:hover {
            color: var(--primary-color);
        }

        .item-price {
            font-size: 1rem;
            color: var(--text-secondary);
        }

        .item-price .sale-price {
            color: var(--danger-color);
            font-weight: 600;
            margin-right: 0.5rem;
        }

        .item-price .original-price {
            text-decoration: line-through;
            color: var(--text-secondary);
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .qty-btn {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 0.375rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .qty-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .qty-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .qty-input {
            width: 60px;
            height: 32px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            font-weight: 500;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-end;
        }

        .item-subtotal {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .remove-btn {
            background: none;
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: background-color 0.3s;
        }

        .remove-btn:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        /* Cart Summary */
        .cart-summary {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .cart-summary h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .summary-row.total {
            font-size: 1.25rem;
            font-weight: 700;
            padding-top: 1rem;
            border-top: 2px solid var(--border-color);
            margin-top: 1rem;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            margin-bottom: 1rem;
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

        /* Empty State */
        .empty-cart {
            background: white;
            border-radius: 0.75rem;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .empty-cart svg {
            width: 80px;
            height: 80px;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .empty-cart h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .empty-cart p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        /* Loading State */
        .loading-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem 2rem;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow-lg);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        .toast.show {
            display: flex;
        }

        .toast.success {
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            border-left: 4px solid var(--danger-color);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Footer */
        .site-footer {
            background: white;
            padding: 2rem 0;
            text-align: center;
            color: var(--text-secondary);
            margin-top: 4rem;
            box-shadow: var(--shadow);
        }

        /* Responsive */
        @media (max-width: 968px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
            }

            .cart-item {
                grid-template-columns: 100px 1fr;
                gap: 1rem;
            }

            .item-image {
                width: 100px;
                height: 100px;
            }

            .item-actions {
                grid-column: 2;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        @media (max-width: 640px) {
            .main-nav {
                display: none;
            }

            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="site-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="/">YourStore</a>
            </div>
            <nav class="main-nav">
                <a href="/">Home</a>
                <a href="/shop">Shop</a>
                <a href="/cart" class="active">Cart</a>
                <a href="/contact">Contact</a>
            </nav>
            <div class="header-actions">
                <button class="icon-btn" onclick="window.location.href='/wishlist'">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span class="badge" id="wishlist-count">0</span>
                </button>
                <button class="icon-btn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <span class="badge" id="cart-count">0</span>
                </button>
            </div>
        </div>
    </div>
</header>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1 class="page-title">Shopping Cart</h1>
        <div class="breadcrumb">
            <a href="/">Home</a>
            <span>/</span>
            <span>Cart</span>
        </div>
    </div>
</div>

<!-- Main Content -->
<!-- Main Content -->
<main>
    <div class="container">
        <div id="loading-container" class="loading-state" style="display: none;">
            <div class="spinner"></div>
            <p>Loading your cart...</p>
        </div>
        <div id="empty-container" class="empty-cart" style="display: <?php use App\Enums\Subscriptions\SubscriptionType;

        echo empty($items) ? 'block' : 'none'; ?>;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            <h3>Your cart is empty</h3>
            <p>Add some products to get started</p>
            <button class="btn btn-primary" onclick="window.location.href='/shop'"
                    style="max-width: 300px; margin: 0 auto;">
                Continue Shopping
            </button>
        </div>

        <div id="cart-container" class="cart-layout" style="display: <?php echo empty($items) ? 'none' : 'grid'; ?>;">
            <div class="cart-items">
                <div class="cart-header">
                    <h2>Cart Items (<span id="items-count"><?= count($items) ?></span>)</h2>
                    <button class="clear-cart-btn" onclick="clearCart()">Clear Cart</button>
                </div>

                <div id="alert-container"></div>

                <div id="cart-items-list">
                    <?php
                    // Group items by merchant
                    $itemsByMerchant = [];
                    foreach ($items as $item) {
                        $merchantId = $item['options']['merchant_id'] ?? 0;
                        $merchantName = $merchantId ? ($item['merchant_name'] ?? 'Merchant ' . $merchantId) : 'Direct';

                        if (!isset($itemsByMerchant[$merchantId])) {
                            $itemsByMerchant[$merchantId] = [
                                    'name' => $merchantName,
                                    'items' => []
                            ];
                        }
                        $itemsByMerchant[$merchantId]['items'][] = $item;
                    }
                    ?>


                    <?php foreach ($itemsByMerchant as $merchantId => $merchantData): ?>
                        <div class="merchant-group" style="margin-bottom: 2rem;">
                            <div class="merchant-header"
                                 style="padding: 1rem; background: #f8fafc; border-radius: 0.5rem; margin-bottom: 1rem;">
                                <h3 style="font-size: 1.125rem; font-weight: 600; color: #1e293b;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                    </svg>
                                    <?= htmlspecialchars($merchantData['name']) ?>
                                </h3>
                                <p style="font-size: 0.875rem; color: #64748b; margin-top: 0.25rem;">
                                    <?= count($merchantData['items']) ?> item(s)
                                </p>
                            </div>
                            <?php foreach ($items as $item):
                                $isFreeGift = ($item['options']['type'] ?? '') === 'free_gift'
                                        || ($item['options']['is_gift'] ?? false) === true
                                        || (float)($item['price'] ?? 0) === 0.0;

                                ?>
                                <div class="cart-item" data-item-id="<?= $item['id'] ?>">
                                    <?php if (!empty($item['subscription_plan_id'])): ?>
                                        <!-- Subscription item -->
                                        <?php
                                        $options = $item['options'];
                                        $deliveryType = $options['delivery_type'] ?? SubscriptionType::DIGITAL->value;
                                        $planName = $options['plan_name'] ?? 'Subscription';
                                        $planId = $item['subscription_plan_id'];
                                        ?>
                                        <div style="width: 120px; height: 120px; border-radius: 0.5rem; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; background: var(--bg-light);">
                                            <?php if ($isFreeGift): ?>
                                                <div style="position: relative;">
                                                    <span style="
                                                        position: absolute; top: -0.5rem; left: -0.5rem;
                                                        background: #10b981; color: white; font-size: 0.7rem;
                                                        font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 1rem;
                                                        letter-spacing: 0.05em; text-transform: uppercase; z-index: 1;
                                                    ">🎁 Free Gift</span>
                                                    <div style="width: 120px; height: 120px; border-radius: 0.5rem; border: 2px solid #10b981; display: flex; align-items: center; justify-content: center; background: var(--bg-light);">
                                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                                                             stroke="#10b981">
                                                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div style="width: 120px; height: 120px; border-radius: 0.5rem; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; background: var(--bg-light);">
                                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                                                         stroke="currentColor">
                                                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-details">
                                            <div class="item-name"><?= htmlspecialchars($planName) ?></div>
                                            <div class="item-meta">
                                                <?= ucfirst($deliveryType) ?> Delivery
                                                <?php if (isset($options['billing_period'])): ?>
                                                    • <?= htmlspecialchars($options['billing_period']) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-price">
                                                <?php if ($isFreeGift): ?>
                                                    <span style="color: #10b981; font-weight: 700; font-size: 1rem;">FREE</span>
                                                    <span style="display: inline-block; background: #d1fae5; color: #065f46; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 0.375rem; border: 1px solid #6ee7b7; margin-left: 0.5rem;">Complimentary</span>
                                                <?php else: ?>
                                                    <span class="sale-price">$<?= number_format($item['price'], 2) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="item-actions">
                                            <div class="item-subtotal">
                                                <?php if ($isFreeGift): ?>
                                                    <span style="color: #10b981; font-weight: 700;">FREE</span>
                                                <?php else: ?>
                                                    $<?= number_format($item['subtotal'], 2) ?>
                                                <?php endif; ?>
                                            </div>
                                            <button class="remove-btn" onclick="removeItem(<?= $item['id'] ?>)">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                                     stroke="currentColor">
                                                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>

                                            <?php if (!empty($startOptions[$planId])): ?>
                                                <div class="form-group" style="margin-top: 1rem;">
                                                    <label class="form-label" style="font-size: 0.875rem;">Start
                                                        Date:</label>
                                                    <select id="start-date-<?= $item['id'] ?>"
                                                            name="start_date"
                                                            class="form-select"
                                                            onchange="updateSubscriptionStartDate(<?= $item['id'] ?>, <?= $planId ?>, this.value)"
                                                            style="padding: 0.5rem; font-size: 0.875rem;">
                                                        <option value="">Select Start Date</option>
                                                        <?php foreach ($startOptions[$planId]['start_date_options'] as $startOption): ?>
                                                            <option value="<?= $startOption['start_date'] ?>">
                                                                <?= date('M j, Y', strtotime($startOption['start_date'])) ?>
                                                                (Next
                                                                billing: <?= date('M j, Y', strtotime($startOption['next_billing_date'])) ?>
                                                                )
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php if ($isFreeGift): ?>
                                            <div style="position: relative;">
                                                <span style="
                                                    position: absolute;
                                                    top: -0.5rem;
                                                    left: -0.5rem;
                                                    background: #10b981;
                                                    color: white;
                                                    font-size: 0.7rem;
                                                    font-weight: 700;
                                                    padding: 0.2rem 0.6rem;
                                                    border-radius: 1rem;
                                                    letter-spacing: 0.05em;
                                                    text-transform: uppercase;
                                                    z-index: 1;
                                                ">🎁 Free Gift</span>
                                                <img src="<?= htmlspecialchars($item['product_image'] ?? '/images/placeholder.jpg') ?>"
                                                     alt="<?= htmlspecialchars($item['product_name']) ?>"
                                                     class="item-image"
                                                     style="border: 2px solid #10b981;">
                                            </div>
                                        <?php else: ?>
                                            <img src="<?= htmlspecialchars($item['product_image'] ?? '/images/placeholder.jpg') ?>"
                                                 alt="<?= htmlspecialchars($item['product_name']) ?>"
                                                 class="item-image">
                                        <?php endif; ?>
                                        <div class="item-details">
                                            <a href="/shop/details/<?= htmlspecialchars($item['product_slug']) ?>"
                                               class="item-name">
                                                <?= htmlspecialchars($item['product_name']) ?>
                                            </a>
                                            <?php if ($isFreeGift): ?>
                                                <span style="
                                                    display: inline-block;
                                                    background: #d1fae5;
                                                    color: #065f46;
                                                    font-size: 0.75rem;
                                                    font-weight: 600;
                                                    padding: 0.2rem 0.6rem;
                                                    border-radius: 0.375rem;
                                                    border: 1px solid #6ee7b7;
                                                    margin-top: 0.25rem;
                                                ">Complimentary — No charge</span>
                                            <?php endif; ?>
                                            <!-- ADD VARIANT OPTIONS DISPLAY -->
                                            <?php if (!empty($item['variant_id']) && !empty($item['variant_options'])): ?>
                                                <div class="variant-options" style="margin-top: 0.5rem;">
                                                    <?php foreach ($item['variant_options'] as $optionName => $optionValue): ?>
                                                        <span class="variant-badge"
                                                              style="display: inline-block; background: var(--bg-light); color: var(--text-secondary); padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.875rem; margin-right: 0.5rem; border: 1px solid var(--border-color);">
                                                            <?= htmlspecialchars(ucfirst($optionName)) ?>: <strong><?= htmlspecialchars($optionValue) ?></strong>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php if (!empty($item['variant_sku'])): ?>
                                                    <div class="variant-sku"
                                                         style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                                        SKU: <?= htmlspecialchars($item['variant_sku']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <div class="item-price">
                                                <?php if ($isFreeGift): ?>
                                                    <span style="color: #10b981; font-weight: 700; font-size: 1rem;">FREE</span>
                                                <?php else: ?>
                                                    <span class="sale-price">$<?= number_format($item['price'], 2) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="quantity-controls">
                                                <button class="qty-btn"
                                                        onclick="updateQuantity(<?= $item['id'] ?>, <?= $item['quantity'] - 1 ?>)">
                                                    -
                                                </button>
                                                <input type="number" class="qty-input"
                                                       value="<?= $item['quantity'] ?>" min="1"
                                                       onchange="updateQuantity(<?= $item['id'] ?>, this.value)"/>
                                                <button class="qty-btn"
                                                        onclick="updateQuantity(<?= $item['id'] ?>, <?= $item['quantity'] + 1 ?>)">
                                                    +
                                                </button>
                                                <?php if (!empty($item['estimated_delivery'])): ?>
                                                    <div class="item-delivery"
                                                         style="font-size: 0.75rem; color: var(--success-color); margin-top: 0.25rem;">
                                                        📦 Delivery: <?= htmlspecialchars($item['estimated_delivery']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="item-actions">
                                            <div class="item-subtotal">
                                                <?php if ($isFreeGift): ?>
                                                    <span style="color: #10b981; font-weight: 700;">FREE</span>
                                                <?php else: ?>
                                                    $<?= number_format($item['subtotal'], 2) ?>
                                                <?php endif; ?>
                                            </div>
                                            <button class="remove-btn" onclick="removeItem(<?= $item['id'] ?>)">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                                     stroke="currentColor">
                                                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="cart-summary">
                <h3>Order Summary</h3>

                <div id="order-items">

                    <?php
                    // Group items by merchant
                    $itemsByMerchant = [];

                    foreach ($items as $item) {
                        $merchantId = $item['options']['merchant_id'] ?? 0;
                        $merchantName = $merchantId ? ($item['merchant_name'] ?? 'Merchant ' . $merchantId) : 'Direct';

                        if (!isset($itemsByMerchant[$merchantId])) {
                            $itemsByMerchant[$merchantId] = [
                                    'name' => $merchantName,
                                    'items' => [],
                                    'subtotal' => 0
                            ];
                        }
                        $itemsByMerchant[$merchantId]['items'][] = $item;
                        $itemsByMerchant[$merchantId]['subtotal'] += $item['subtotal'];
                    }
                    ?>

                    <?php foreach ($itemsByMerchant

                                   as $merchantId => $merchantData): ?>
                    <div class="merchant-group"
                         style="margin-bottom: 1.5rem; padding-bottom: 1.5rem;">
                        <div class="merchant-header" style="margin-bottom: 1rem;">
                            <strong style="font-size: 0.875rem; color: var(--text-primary);">
                                <?= htmlspecialchars($merchantData['name']) ?>
                            </strong>
                            <span style="font-size: 0.75rem; color: var(--text-secondary); margin-left: 0.5rem;">
                                (<?= count($merchantData['items']) ?> items)
                            </span>
                        </div>

                        <?php foreach ($items as $item): ?>
                            <div class="summary-item">
                                <img src="<?= htmlspecialchars($item['product_image'] ?? '/images/placeholder.jpg') ?>"
                                     alt="<?= htmlspecialchars($item['product_name']) ?>"
                                     class="item-image">
                                <div class="item-details">
                                    <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>

                                    <?php if (!empty($item['variant_id']) && !empty($item['variant_options'])): ?>
                                        <div class="variant-options"
                                             style="margin-top: 0.25rem; font-size: 0.75rem; color: var(--text-secondary);">
                                            <?php
                                            $variantParts = [];
                                            foreach ($item['variant_options'] as $optionName => $optionValue) {
                                                $variantParts[] = htmlspecialchars(ucfirst($optionName)) . ': <strong>' . htmlspecialchars($optionValue) . '</strong>';
                                            }
                                            echo implode(' • ', $variantParts);
                                            ?>
                                        </div>
                                        <?php if (!empty($item['variant_sku'])): ?>
                                            <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 0.125rem;">
                                                SKU: <?= htmlspecialchars($item['variant_sku']) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <div class="item-meta">Qty: <?= $item['quantity'] ?></div>
                                </div>
                                <div class="item-price">$<?= number_format($item['subtotal'], 2) ?></div>

                                <?php if (!empty($item['options']['subscription_plan_id'])): ?>
                                    <input type="hidden" name="plan_id"
                                           value="<?= $item['options']['subscription_plan_id'] ?>">
                                <?php endif; ?>
                                <?php if (!empty($item['estimated_delivery'])): ?>
                                    <div class="item-delivery"
                                         style="font-size: 0.75rem; color: var(--success-color); margin-top: 0.25rem;">
                                        📦 Delivery: <?= htmlspecialchars($item['estimated_delivery']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="voucher-section"
                     style="margin: 1.5rem 0; padding: 1.5rem 0; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
                    <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Voucher Code</h4>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="voucher-input" placeholder="Enter code"
                               style="flex: 1; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; font-size: 0.875rem;"
                               value="<?= htmlspecialchars($_SESSION['applied_voucher_code']['code'] ?? '') ?>">
                        <button onclick="applyVoucher()" class="btn btn-secondary"
                                style="width: auto; padding: 0.75rem 1.5rem; font-size: 0.875rem;">Apply
                        </button>
                    </div>
                    <div id="voucher-message" style="margin-top: 0.5rem; font-size: 0.875rem;"></div>
                    <div id="applied-voucher"
                         style="<?= !empty($_SESSION['applied_voucher_code']) ? 'display: block;' : 'display: none;' ?> margin-top: 1rem; padding: 1rem; background: #d1fae5; border-radius: 0.5rem; border: 1px solid #10b981;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong id="voucher-code-display" style="color: #065f46;"></strong>
                                <p style="font-size: 0.875rem; color: #065f46; margin: 0.25rem 0 0 0;">
                                    Discount: <span
                                            id="voucher-discount-display"><?= htmlspecialchars($_SESSION['applied_voucher_code']['discount'] ?? '') ?></span>
                                </p>
                            </div>
                            <button onclick="removeVoucher()"
                                    style="background: none; border: none; color: #065f46; cursor: pointer; padding: 0.5rem;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="summary-row" id="discount-row" style="display: none; color: var(--success-color);">
                    <span>Discount:</span>
                    <span id="discount-amount"><?= htmlspecialchars($_SESSION['applied_voucher_code']['discount'] ?? '') ?></span>
                </div>

                <?php
                $finalTotal = $subtotal + $tax + $shipping;

                if (!empty($_SESSION['applied_voucher_code'])) {
                    $finalTotal -= $_SESSION['applied_voucher_code']['discount'];
                }

                ?>

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">$<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping:</span>
                    <span id="shipping"><?= $shipping > 0 ? '$' . number_format($shipping, 2) : 0 ?></span>
                </div>
                <div class="summary-row">
                    <span>Tax (10%):</span>
                    <span id="tax">$<?= number_format($tax, 2) ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total:</span>
                    <span id="total" data-total="<?= $finalTotal ?>">$<?= number_format($finalTotal, 2) ?></span>
                </div>

                <?php if (!empty($hasPreOrders)): ?>
                    <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
                        <div style="font-weight: 600; color: #92400e; margin-bottom: 0.75rem;">
                            ⚠️ Pre-Order Items in Cart
                        </div>
                        <?php foreach ($hasPreOrders as $preOrder): ?>
                            <div style="font-size: 0.875rem; color: #78350f; margin-bottom: 0.5rem;">
                                <strong><?= htmlspecialchars($preOrder['name']) ?></strong><br>
                                <?= htmlspecialchars($preOrder['message']) ?>
                                <?php if ($preOrder['ship_date']): ?>
                                    <br>Ships: <?= htmlspecialchars($preOrder['ship_date']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <label style="display: flex; align-items: start; gap: 0.75rem; cursor: pointer; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #fbbf24;">
                            <input type="checkbox" id="accept-pre-order" name="accept_pre_order" required
                                   style="margin-top: 0.25rem; width: 18px; height: 18px; cursor: pointer; flex-shrink: 0;">
                            <span style="flex: 1; font-size: 0.875rem; color: #92400e;">
                I understand this order contains pre-order items and accept the delivery timelines shown above.
            </span>
                        </label>
                    </div>
                <?php endif; ?>

                <button class="btn btn-primary" onclick="proceedToCheckout()">
                    Proceed to Checkout
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='/shop'">
                    Continue Shopping
                </button>

                <div class="security-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span>Secure SSL encrypted checkout</span>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="site-footer">
    <div class="container">
        <p>&copy; 2025 YourStore. All rights reserved.</p>
    </div>
</footer>

<!-- Toast -->
<div id="toast" class="toast"></div>

<script>
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    const API_BASE = '/api/' + SITE;
    let cartData = null;
    let isOneTimeSubscription = false
    const subscriptionStartDates = {};
    let appliedVoucher = <?= json_encode($_SESSION['applied_voucher_code'] ?? null) ?>;

    const INITIAL_SUBTOTAL = parseFloat(document.getElementById('subtotal').textContent.replace('$', ''));
    const INITIAL_SHIPPING = parseFloat(document.getElementById('shipping').textContent.replace('$', '') || '0');
    const INITIAL_TAX = parseFloat(document.getElementById('tax').textContent.replace('$', ''));

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    function formatCurrency(amount) {
        return '$' + parseFloat(amount).toFixed(2);
    }

    async function updateSubscriptionStartDate(cartItemId, planId, startDate) {
        if (!startDate) {
            delete subscriptionStartDates[cartItemId];
            return;
        }

        subscriptionStartDates[cartItemId] = {
            planId: planId,
            startDate: startDate
        };

        try {
            const response = await fetch(`${API_BASE}/cart/${cartItemId}/update-start-date`, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    start_date: startDate,
                    plan_id: planId,
                    cart_item_id: cartItemId,
                })
            });

            const data = await response.json();

            if (data.success) {
                showToast('Start date updated');
            } else {
                showToast(data.message || 'Failed to update start date', 'error');
            }
        } catch (error) {
            console.error('Error updating start date:', error);
            showToast('Failed to update start date', 'error');
        }
    }

    async function loadCart() {
        const loading = document.getElementById('loading-container');
        const empty = document.getElementById('empty-container');
        const cartContainer = document.getElementById('cart-container');

        loading.style.display = 'flex';
        empty.style.display = 'none';
        cartContainer.style.display = 'none';

        try {
            const response = await fetch(`${API_BASE}/cart`);
            cartData = await response.json();

            if (!cartData.items || cartData.items.length === 0) {
                loading.style.display = 'none';
                empty.style.display = 'block';
                updateCartCount(0);
                return;
            }

            renderCart();
            loading.style.display = 'none';
            cartContainer.style.display = 'grid';
        } catch (error) {
            console.error('Error loading cart:', error);
            showToast('Failed to load cart', 'error');
            loading.style.display = 'none';
            empty.style.display = 'block';
        }
    }

    function renderCart() {
        console.log('data', cartData.items)
        if (!cartData || cartData.items.length === 0) {
            document.getElementById('loading-container').style.display = 'none';
            document.getElementById('empty-container').style.display = 'block';
            document.getElementById('cart-container').style.display = 'none';
            updateCartCount(0);
            return;
        }

        const itemsList = document.getElementById('cart-items-list');
        itemsList.innerHTML = cartData.items.map(item => {
            let variantHtml = '';
            if (item.variant_id && item.variant_options) {
                const badges = Object.entries(item.variant_options)
                    .map(([k, v]) => `<span class="variant-badge" style="display:inline-block;background:var(--bg-light);color:var(--text-secondary);padding:.25rem .75rem;border-radius:1rem;font-size:.875rem;margin-right:.5rem;border:1px solid var(--border-color);">${k.charAt(0).toUpperCase() + k.slice(1)}: <strong>${v}</strong></span>`)
                    .join('');
                const sku = item.variant_sku ? `<div style="font-size:.75rem;color:var(--text-secondary);margin-top:.25rem;">SKU: ${item.variant_sku}</div>` : '';
                variantHtml = `<div style="margin-top:.5rem;">${badges}</div>${sku}`;
            }
            return `
      <div class="cart-item" data-item-id="${item.id}">
        <img src="${item.product_image || '/images/placeholder.jpg'}" alt="${item.product_name}" class="item-image">
        <div class="item-details">
          <a href="/shop/details/${item.product_slug}" class="item-name">${item.product_name}</a>
          ${variantHtml}
          <div class="item-price"><span class="sale-price">${formatCurrency(item.price)}</span></div>
          <div class="quantity-controls">
            <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity - 1})">-</button>
            <input type="number" class="qty-input" value="${item.quantity}" min="1" onchange="updateQuantity(${item.id}, this.value)" />
            <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity + 1})">+</button>
          </div>
        </div>
        <div class="item-actions">
          <div class="item-subtotal">${formatCurrency(item.subtotal)}</div>
          <button class="remove-btn" onclick="removeItem(${item.id})">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
          </button>
        </div>
      </div>`;
        }).join('');

        // Re-render the summary item list
        const summaryList = document.getElementById('order-items');
        summaryList.innerHTML = cartData.items.map(item => {
            let variantHtml = '';
            if (item.variant_id && item.variant_options) {
                const parts = Object.entries(item.variant_options)
                    .map(([k, v]) => `${k.charAt(0).toUpperCase() + k.slice(1)}: <strong>${v}</strong>`)
                    .join(' • ');
                const sku = item.variant_sku ? `<div style="font-size:.7rem;color:var(--text-secondary)">SKU: ${item.variant_sku}</div>` : '';
                variantHtml = `<div style="font-size:.75rem;color:var(--text-secondary);margin-top:.25rem;">${parts}</div>${sku}`;
            }
            return `
      <div class="summary-item" style="display:flex;align-items:center;gap:.75rem;padding:.75rem 0;border-bottom:1px solid var(--border-color);">
        <img src="${item.product_image || '/images/placeholder.jpg'}" alt="${item.product_name}"
             style="width:48px;height:48px;object-fit:cover;border-radius:.375rem;border:1px solid var(--border-color);flex-shrink:0;" />
        <div style="flex:1;min-width:0;">
          <div style="font-weight:600;font-size:.875rem;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.product_name}</div>
          ${variantHtml}
          <div style="font-size:.8rem;color:var(--text-secondary);margin-top:.2rem;">Qty: ${item.quantity}</div>
        </div>
        <div style="font-weight:600;font-size:.9rem;color:var(--text-primary);white-space:nowrap;">${formatCurrency(item.subtotal)}</div>
      </div>`;
        }).join('');

        document.getElementById('items-count').textContent = cartData.items.length;
        updateCartCount(cartData.count);
        updateTotals();
    }

    function updateSummary() {
        const subtotal = cartData ? cartData.total : parseFloat(document.getElementById('subtotal').textContent.replace(/[^0-9.]/g, ''));
        const discount = appliedVoucher ? parseFloat(appliedVoucher.discount) : 0;
        const tax = (subtotal - discount) * 0.1;
        const total = subtotal - discount + tax;

        document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
        if (appliedVoucher) {
            document.getElementById('discount-row').style.display = 'flex';
            document.getElementById('discount-amount').textContent = '-$' + discount.toFixed(2);
        }
        document.getElementById('tax').textContent = '$' + tax.toFixed(2);
        document.getElementById('total').textContent = '$' + total.toFixed(2);
    }

    async function applyVoucher() {
        const voucherCode = document.getElementById('voucher-input').value.trim();
        if (!voucherCode) {
            showVoucherMessage('Please enter a voucher code', 'error');
            return;
        }

        const totalElement = document.getElementById('total')
        const totalAmount = totalElement.dataset.total;

        // Detect if this is a subscription checkout
        const isSubscription = window.location.search.includes('type=subscription') ||
            document.querySelector('[name="plan_id"]');

        const requestBody = {
            code: voucherCode,
            is_subscription: !!isSubscription,
            order_value: parseFloat(totalAmount)
        };

        // Add plan_id if this is a subscription
        if (isSubscription) {
            const planIdInput = document.querySelector('[name="plan_id"]');
            if (planIdInput) {
                requestBody.plan_id = parseInt(planIdInput.value);
            } else {
                // Try to get from cart items
                const cartItems = getCartItems(); // You'll need to implement this
                if (cartItems.length > 0 && cartItems[0].subscription_plan_id) {
                    requestBody.plan_id = cartItems[0].subscription_plan_id;
                }
            }
        }

        const messageEl = document.getElementById('voucher-message');

        try {
            const response = await fetch(`${API_BASE}/vouchers/validate`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(requestBody)
            });

            const result = await response.json();

            console.log('result', result.data)

            if (result.data.valid) {
                appliedVoucher = {
                    code: voucherCode,
                    discount: result.data.discount,
                    voucher_id: result.data.voucher_id
                };

                displayAppliedVoucher();
                document.getElementById('voucher-input').value = '';
                messageEl.textContent = '';
                showAlert('Voucher applied successfully!', 'success');
            } else {
                messageEl.textContent = data.data.message;
                messageEl.style.color = 'var(--danger-color)';
            }
        } catch (error) {
            console.error('Error:', error);
            showVoucherMessage('Error applying voucher', 'error');
        }
    }

    function displayAppliedVoucher() {
        if (appliedVoucher) {
            document.getElementById('voucher-code-display').textContent = appliedVoucher.code;
            document.getElementById('voucher-discount-display').textContent = '$' + appliedVoucher.discount.toFixed(2);
            document.getElementById('applied-voucher').style.display = 'block';
            document.getElementById('discount-row').style.display = 'flex';
            document.getElementById('discount-amount').textContent = '-$' + appliedVoucher.discount.toFixed(2);
            updateTotals();
        }
    }

    function updateTotals() {
        if (!cartData) return;

        const discount = appliedVoucher ? parseFloat(appliedVoucher.discount) : 0;

        const subtotal = cartData.total || 0;
        const shipping = cartData.shipping || 0;

        const taxRate = <?= $tax_rate ?>;
        const taxableAmount = subtotal - discount + shipping;
        const tax = taxableAmount * taxRate;
        const total = taxableAmount + tax;

        const subtotalEl = document.getElementById('subtotal');
        const shippingEl = document.getElementById('shipping');
        const taxEl = document.getElementById('tax');
        const totalEl = document.getElementById('total');

        if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
        if (shippingEl) shippingEl.textContent = shipping > 0 ? formatCurrency(shipping) : 'Free';
        if (taxEl) taxEl.textContent = formatCurrency(tax);
        if (totalEl) totalEl.textContent = formatCurrency(total);

        if (appliedVoucher) {
            const discountRow = document.getElementById('discount-row');
            if (discountRow) {
                discountRow.style.display = 'flex';
            }
            document.getElementById('discount-amount').textContent =
                '- ' + formatCurrency(discount);
        }
    }

    function showVoucherMessage(message, type) {
        const messageEl = document.getElementById('voucher-message');
        messageEl.textContent = message;
        messageEl.className = `sub-voucher-message ${type}`;
    }

    async function removeVoucher() {
        try {
            const response = await fetch(`${API_BASE}/vouchers/remove-voucher`, {
                method: 'POST'
            });

            const result = await response.json();

            if (result.success) {
                appliedVoucher = null;
                document.getElementById('applied-voucher').style.display = 'none';
                document.getElementById('discount-row').style.display = 'none';
                updateTotals();
                showAlert('Voucher removed', 'success');
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function showAlert(message, type = 'success') {
        const alertHtml = `
            <div class="alert alert-${type}">
                ${message}
            </div>
        `;
        document.getElementById('alert-container').innerHTML = alertHtml;

        // Scroll to top
        window.scrollTo({top: 0, behavior: 'smooth'});

        // Auto-remove after 5 seconds
        setTimeout(() => {
            document.getElementById('alert-container').innerHTML = '';
        }, 5000);
    }

    async function updateQuantity(itemId, quantity) {
        quantity = parseInt(quantity);
        if (quantity < 1) quantity = 1;

        try {
            const response = await fetch(`${API_BASE}/cart/${itemId}`, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({quantity})
            });

            const data = await response.json();

            if (data.success) {
                await loadCart();
                showToast('Cart updated');
            } else {
                showToast(data.message || 'Failed to update quantity', 'error');
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            showToast('Failed to update quantity', 'error');
        }
    }

    async function removeItem(itemId) {
        if (!confirm('Remove this item from cart?')) return;

        try {
            const response = await fetch(`${API_BASE}/cart/${itemId}`, {
                method: 'DELETE'
            });

            const data = await response.json();

            if (data.success) {
                await loadCart();
                showToast('Item removed from cart');
            } else {
                showToast(data.message || 'Failed to remove item', 'error');
            }
        } catch (error) {
            console.error('Error removing item:', error);
            showToast('Failed to remove item', 'error');
        }
    }

    async function clearCart() {
        if (!confirm('Clear all items from cart?')) return;

        try {
            const response = await fetch(`${API_BASE}/cart/clear`, {
                method: 'DELETE'
            });

            const data = await response.json();

            if (data.success) {
                await loadCart();
                showToast('Cart cleared');
            } else {
                showToast(data.message || 'Failed to clear cart', 'error');
            }
        } catch (error) {
            console.error('Error clearing cart:', error);
            showToast('Failed to clear cart', 'error');
        }
    }

    function updateCartCount(count) {
        document.getElementById('cart-count').textContent = count;
    }

    function proceedToCheckout() {
        if (appliedVoucher) {
            sessionStorage.setItem('appliedVoucher', JSON.stringify(appliedVoucher));
        }

        if (Object.keys(subscriptionStartDates).length > 0) {
            sessionStorage.setItem('subscriptionStartDates', JSON.stringify(subscriptionStartDates));
        }

        window.location.href = isOneTimeSubscription ? '/checkout?type=subscription' : '/checkout';
    }

    async function loadWishlistCount() {
        try {
            const response = await fetch(`${API_BASE}/wishlist`);
            const data = await response.json();
            document.getElementById('wishlist-count').textContent = data.data?.count || 0;
        } catch (error) {
            console.error('Error loading wishlist count:', error);
        }
    }

    // Initialize
    //loadCart();
    loadWishlistCount();
    checkCartForSubscription();

    if (appliedVoucher) {
        document.getElementById('voucher-code-display').textContent = appliedVoucher.code;
        document.getElementById('voucher-discount-display').textContent = '$' + parseFloat(appliedVoucher.discount).toFixed(2);
        document.getElementById('applied-voucher').style.display = 'block';
        document.getElementById('discount-row').style.display = 'flex';
        document.getElementById('discount-amount').textContent = '-$' + parseFloat(appliedVoucher.discount).toFixed(2);
        updateSummary();
    }

    function checkCartForSubscription() {
        const urlParams = new URLSearchParams(window.location.search);
        isOneTimeSubscription = urlParams.get('type') === 'subscription';
    }
</script>

</body>
</html>