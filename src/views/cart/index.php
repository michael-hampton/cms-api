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
            to { transform: rotate(360deg); }
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
            <div id="empty-container" class="empty-cart" style="display: <?php echo empty($items) ? 'block' : 'none'; ?>;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <h3>Your cart is empty</h3>
                <p>Add some products to get started</p>
                <button class="btn btn-primary" onclick="window.location.href='/shop'" style="max-width: 300px; margin: 0 auto;">
                    Continue Shopping
                </button>
            </div>

            <div id="cart-container" class="cart-layout" style="display: <?php echo empty($items) ? 'none' : 'grid'; ?>;">
                <div class="cart-items">
                    <div class="cart-header">
                        <h2>Cart Items (<span id="items-count"><?= count($items) ?></span>)</h2>
                        <button class="clear-cart-btn" onclick="clearCart()">Clear Cart</button>
                    </div>
                    <div id="cart-items-list">
                        <?php foreach ($items as $item): ?>
                            <div class="cart-item" data-item-id="<?= $item['id'] ?>">
                                <?php if (!empty($item['subscription_plan_id'])): ?>
                                    <!-- Subscription item -->
                                    <?php
                                    $options = $item['options'];
                                    $deliveryType = $options['delivery_type'] ?? 'digital';
                                    $planName = $options['plan_name'] ?? 'Subscription';
                                    ?>
                                    <div style="width: 120px; height: 120px; border-radius: 0.5rem; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; background: var(--bg-light);">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor">
                                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                        </svg>
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
                                        <span class="sale-price">$<?= number_format($item['price'], 2) ?></span>
                                    </div>
                                </div>
                                <div class="item-actions">
                                    <div class="item-subtotal">$<?= number_format($item['subtotal'], 2) ?></div>
                                    <button class="remove-btn" onclick="removeItem(<?= $item['id'] ?>)">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                </div>
                                <?php else: ?>
                                    <!-- Regular product item - existing code -->
                                    <img src="<?= htmlspecialchars($item['product_image'] ?? '/images/placeholder.jpg') ?>"
                                         alt="<?= htmlspecialchars($item['product_name']) ?>"
                                         class="item-image">
                                    <!-- ... rest of existing product item code ... -->
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cart-summary">
                    <h3>Order Summary</h3>
                    <?php
                    $subtotal = $total;
                    $tax = $subtotal * 0.1;
                    $finalTotal = $subtotal + $tax;
                    ?>
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">$<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span id="shipping">Free</span>
                    </div>
                    <div class="summary-row">
                        <span>Tax:</span>
                        <span id="tax">$<?= number_format($tax, 2) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="total">$<?= number_format($finalTotal, 2) ?></span>
                    </div>

                    <div class="voucher-section" style="margin: 1.5rem 0; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                        <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Have a voucher code?</h4>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="voucher-input" placeholder="Enter code"
                                   style="flex: 1; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; font-size: 0.875rem;">
                            <button onclick="applyVoucher(<?= $total ?>)" class="btn btn-secondary"
                                    style="width: auto; padding: 0.75rem 1.5rem; font-size: 0.875rem;">Apply</button>
                        </div>
                        <div id="voucher-message" style="margin-top: 0.5rem; font-size: 0.875rem;"></div>
                        <div id="applied-voucher" style="display: none; margin-top: 1rem; padding: 1rem; background: #d1fae5; border-radius: 0.5rem; border: 1px solid #10b981;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong id="voucher-code-display" style="color: #065f46;"></strong>
                                    <p style="font-size: 0.875rem; color: #065f46; margin: 0.25rem 0 0 0;">
                                        Discount: <span id="voucher-discount-display"></span>
                                    </p>
                                </div>
                                <button onclick="removeVoucher()" style="background: none; border: none; color: #065f46; cursor: pointer; padding: 0.5rem;">
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
                        <span id="discount-amount">-$0.00</span>
                    </div>

                    <button class="btn btn-primary" onclick="proceedToCheckout()">
                        Proceed to Checkout
                    </button>
                    <button class="btn btn-secondary" onclick="window.location.href='/shop'">
                        Continue Shopping
                    </button>
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
    const SITE = 'test-mike';
    const API_BASE = '/api/' + SITE;
    let cartData = null;
    let appliedVoucher = null;
    let isOneTimeSubscription = false

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
        const itemsList = document.getElementById('cart-items-list');
        itemsList.innerHTML = cartData.items.map(item => `
            <div class="cart-item" data-item-id="${item.id}">
                <img src="${item.product_image || '/images/placeholder.jpg'}" alt="${item.product_name}" class="item-image">
                <div class="item-details">
                    <a href="/shop/details/${item.product_slug}" class="item-name">${item.product_name}</a>
                    <div class="item-price">
                        <span class="sale-price">${formatCurrency(item.price)}</span>
                    </div>
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
            </div>
        `).join('');

        updateSummary();
        updateCartCount(cartData.count);
        document.getElementById('items-count').textContent = cartData.items.length;
    }

    function updateSummary() {
        const subtotal = cartData.total;
        const discount = appliedVoucher ? appliedVoucher.discount : 0;
        const tax = (subtotal - discount) * 0.1;
        const total = subtotal - discount + tax;

        document.getElementById('subtotal').textContent = formatCurrency(subtotal);
        if (appliedVoucher) {
            document.getElementById('discount-amount').textContent = '-' + formatCurrency(discount);
        }
        document.getElementById('tax').textContent = formatCurrency(tax);
        document.getElementById('total').textContent = formatCurrency(total);
    }

    async function applyVoucher() {
        const code = document.getElementById('voucher-input').value.trim();
        const messageEl = document.getElementById('voucher-message');

        if (!code) {
            messageEl.textContent = 'Please enter a voucher code';
            messageEl.style.color = 'var(--danger-color)';
            return;
        }

        try {
            const response = await fetch(`${API_BASE}/vouchers/validate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    code: code,
                    order_value: cartData.total
                })
            });

            const data = await response.json();

            if (data.data.valid) {
                appliedVoucher = {
                    code: code,
                    discount: data.data.discount,
                    voucher_id: data.data.voucher_id
                };

                document.getElementById('voucher-code-display').textContent = code;
                document.getElementById('voucher-discount-display').textContent = formatCurrency(data.data.discount);
                document.getElementById('applied-voucher').style.display = 'block';
                document.getElementById('voucher-input').value = '';
                document.getElementById('discount-row').style.display = 'flex';

                messageEl.textContent = '';
                updateSummary();
                showToast('Voucher applied successfully!');
            } else {
                messageEl.textContent = data.data.message;
                messageEl.style.color = 'var(--danger-color)';
            }
        } catch (error) {
            console.error('Error applying voucher:', error);
            messageEl.textContent = 'Failed to apply voucher';
            messageEl.style.color = 'var(--danger-color)';
        }
    }

    function removeVoucher() {
        appliedVoucher = null;
        document.getElementById('applied-voucher').style.display = 'none';
        document.getElementById('discount-row').style.display = 'none';
        updateSummary();
        showToast('Voucher removed');
    }

    async function updateQuantity(itemId, quantity) {
        quantity = parseInt(quantity);
        if (quantity < 1) quantity = 1;

        try {
            const response = await fetch(`${API_BASE}/cart/${itemId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ quantity })
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
        window.location.href = isOneTimeSubscription ? '/checkout?type=subscription' : '/checkout';
    }

    async function loadWishlistCount() {
        try {
            const response = await fetch(`${API_BASE}/wishlist`);
            const data = await response.json();
            document.getElementById('wishlist-count').textContent = data.data.count || 0;
        } catch (error) {
            console.error('Error loading wishlist count:', error);
        }
    }

    // Initialize
    //loadCart();
    loadWishlistCount();
    checkCartForSubscription();

    function checkCartForSubscription() {
        const urlParams = new URLSearchParams(window.location.search);
        isOneTimeSubscription = urlParams.get('type') === 'subscription';
    }
</script>

</body>
</html>