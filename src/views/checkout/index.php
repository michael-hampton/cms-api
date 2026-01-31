<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - YourStore</title>
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

        /* Progress Steps */
        .checkout-progress {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border-color);
            z-index: 1;
        }

        .progress-line {
            position: absolute;
            top: 20px;
            left: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width 0.3s;
            z-index: 2;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 3;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            transition: all 0.3s;
        }

        .step.active .step-circle {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .step.completed .step-circle {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }

        .step-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .step.active .step-label {
            color: var(--primary-color);
        }

        /* Checkout Layout */
        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        /* Checkout Form */
        .checkout-form {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-label .required {
            color: var(--danger-color);
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-error {
            color: var(--danger-color);
            font-size: 0.875rem;
        }

        /* Payment Methods */
        .payment-methods {
            display: grid;
            gap: 1rem;
        }

        .payment-method {
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .payment-method:hover {
            border-color: var(--primary-color);
        }

        .payment-method.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .payment-radio {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .payment-info {
            flex: 1;
        }

        .payment-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .payment-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Order Summary */
        .order-summary {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .order-summary h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .summary-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.375rem;
            border: 1px solid var(--border-color);
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 500;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .item-meta {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .item-price {
            font-weight: 600;
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
            margin-top: 1.5rem;
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            margin-bottom: 1rem;
        }

        .btn-secondary:hover {
            background: var(--bg-light);
        }

        /* Loading State */
        .loading-overlay {
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
        }

        .loading-overlay.show {
            display: flex;
        }

        .loading-content {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            text-align: center;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        /* Security Badge */
        .security-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 0.5rem;
            margin-top: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
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
            .checkout-layout {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: static;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .progress-steps {
                flex-direction: column;
                gap: 1rem;
            }

            .progress-steps::before {
                display: none;
            }

            .progress-line {
                display: none;
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

        .saved-address-card {
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .saved-address-card:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .saved-address-card input[type="radio"] {
            flex-shrink: 0;
        }

        .saved-address-card .address-details {
            flex: 1;
        }

        .saved-address-card .address-details strong {
            display: block;
            margin-bottom: 0.25rem;
        }

        .saved-address-card .badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .section-header .section-title {
            margin-bottom: 0;
            padding-bottom: 0;
            border: none;
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
                <a href="/cart">Cart</a>
                <a href="/contact">Contact</a>
            </nav>
            <div class="header-actions">
                <button class="icon-btn" onclick="window.location.href='/wishlist'">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span class="badge" id="wishlist-count">0</span>
                </button>
                <button class="icon-btn" onclick="window.location.href='/cart'">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <span class="badge" id="cart-count"><?= $count ?></span>
                </button>
            </div>
        </div>
    </div>
</header>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1 class="page-title">Checkout</h1>
        <div class="breadcrumb">
            <a href="/">Home</a>
            <span>/</span>
            <a href="/cart">Cart</a>
            <span>/</span>
            <span>Checkout</span>
        </div>
    </div>
</div>


<!-- Main Content -->
<main>
    <div class="container">
        <!-- Progress Steps -->
        <div class="checkout-progress">
            <div class="progress-steps">
                <div class="progress-line" id="progress-line"></div>
                <div class="step completed">
                    <div class="step-circle">✓</div>
                    <div class="step-label">Cart</div>
                </div>
                <div class="step active">
                    <div class="step-circle">2</div>
                    <div class="step-label">Shipping</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Payment</div>
                </div>
                <div class="step">
                    <div class="step-circle">4</div>
                    <div class="step-label">Confirmation</div>
                </div>
            </div>
        </div>

        <div id="alert-container"></div>

        <div class="login-prompt">
            <p>Already have an account? <a href="/member/login?redirect=/checkout">Login</a> to use saved addresses</p>
        </div>

        <div class="checkout-layout">
            <!-- Checkout Form -->
            <div class="checkout-form">
                <form id="checkout-form">
                    <!-- Contact Information -->
                    <div class="form-section">
                        <h2 class="section-title">Contact Information</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    First Name <span class="required">*</span>
                                </label>
                                <input type="text" name="first_name" class="form-input" required>
                                <span class="form-error" id="error-first_name"></span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    Last Name <span class="required">*</span>
                                </label>
                                <input type="text" name="last_name" class="form-input" required>
                                <span class="form-error" id="error-last_name"></span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    Email <span class="required">*</span>
                                </label>
                                <input type="email" name="email" class="form-input" required>
                                <span class="form-error" id="error-email"></span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    Phone <span class="required">*</span>
                                </label>
                                <input type="tel" name="phone" class="form-input" required>
                                <span class="form-error" id="error-phone"></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-section" id="saved-addresses-section" style="display: none;">
                        <h2 class="section-title">Saved Addresses</h2>
                        <div id="saved-addresses-list"></div>
                        <button type="button" onclick="showNewAddressForm()" class="btn btn-secondary">
                            Use Different Address
                        </button>
                    </div>

                    <!-- Shipping Address -->
                    <div class="form-section" id="shipping-address-form">
                        <div class="section-header"
                             style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h2 class="section-title" style="margin-bottom: 0; padding-bottom: 0; border: none;">
                                Shipping Address</h2>
                            <button type="button" id="back-to-saved-btn" onclick="showSavedAddresses()"
                                    class="btn btn-secondary" style="display: none; width: auto; padding: 0.5rem 1rem;">
                                ← Back to Saved Addresses
                            </button>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">
                                Address <span class="required">*</span>
                            </label>
                            <input type="text" name="address" class="form-input" required>
                            <span class="form-error" id="error-address"></span>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">
                                Apartment, suite, etc. (optional)
                            </label>
                            <input type="text" name="address2" class="form-input">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    City <span class="required">*</span>
                                </label>
                                <input type="text" name="city" class="form-input" required>
                                <span class="form-error" id="error-city"></span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    State / Province
                                </label>
                                <input type="text" name="state" class="form-input">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    Postal Code <span class="required">*</span>
                                </label>
                                <input type="text" name="postal_code" class="form-input" required>
                                <span class="form-error" id="error-postal_code"></span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    Country <span class="required">*</span>
                                </label>
                                <select name="country" class="form-select" required>
                                    <option value="">Select Country</option>
                                    <option value="US">United States</option>
                                    <option value="CA">Canada</option>
                                    <option value="GB">United Kingdom</option>
                                    <option value="AU">Australia</option>
                                    <option value="DE">Germany</option>
                                    <option value="FR">France</option>
                                </select>
                                <span class="form-error" id="error-country"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-section" id="payment-form-section">
                        <h2 class="section-title">Payment Method</h2>
                        <div class="payment-methods">
                            <label class="payment-method selected" data-method="card">
                                <input type="radio" name="payment_method" value="card" class="payment-radio" checked>
                                <div class="payment-info">
                                    <div class="payment-name">Credit / Debit Card</div>
                                    <div class="payment-description">Visa, Mastercard, American Express</div>
                                </div>
                            </label>
                            <label class="payment-method" data-method="paypal">
                                <input type="radio" name="payment_method" value="paypal" class="payment-radio">
                                <div class="payment-info">
                                    <div class="payment-name">PayPal</div>
                                    <div class="payment-description">Pay securely with your PayPal account</div>
                                </div>
                            </label>
                            <label class="payment-method" data-method="bank">
                                <input type="radio" name="payment_method" value="bank" class="payment-radio">
                                <div class="payment-info">
                                    <div class="payment-name">Bank Transfer</div>
                                    <div class="payment-description">Direct bank transfer</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <h2 class="section-title">Card Details</h2>
                    <div class="form-group full-width">
                        <label class="form-label">Card Information <span class="required">*</span></label>
                        <div id="card-element"
                             style="padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem;"></div>
                        <div id="card-errors" class="form-error" role="alert"></div>
                    </div>

                    <!-- Card Details (shown when card is selected) -->
                    <div class="form-section" id="card-details">
                        <h2 class="section-title">Card Details</h2>
                        <div class="form-group full-width">
                            <label class="form-label">
                                Card Number <span class="required">*</span>
                            </label>
                            <input type="text" name="card_number" class="form-input" placeholder="1234 5678 9012 3456"
                                   maxlength="19">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    Expiry Date <span class="required">*</span>
                                </label>
                                <input type="text" name="card_expiry" class="form-input" placeholder="MM/YY"
                                       maxlength="5">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    CVV <span class="required">*</span>
                                </label>
                                <input type="text" name="card_cvv" class="form-input" placeholder="123" maxlength="4">
                            </div>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="form-section">
                        <h2 class="section-title">Order Notes (Optional)</h2>
                        <div class="form-group full-width">
                            <label class="form-label">
                                Special instructions for delivery
                            </label>
                            <textarea name="notes" class="form-textarea"
                                      placeholder="Add any special instructions..."></textarea>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
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
                         style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);">
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
                                    <div class="item-meta">Qty: <?= $item['quantity'] ?></div>
                                </div>
                                <div class="item-price">$<?= number_format($item['subtotal'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>

                <div class="voucher-section"
                     style="margin: 1.5rem 0; padding: 1.5rem 0; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
                    <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Voucher Code</h4>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="voucher-input" placeholder="Enter code"
                               style="flex: 1; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; font-size: 0.875rem;">
                        <button onclick="applyVoucher()" class="btn btn-secondary"
                                style="width: auto; padding: 0.75rem 1.5rem; font-size: 0.875rem;">Apply
                        </button>
                    </div>
                    <div id="voucher-message" style="margin-top: 0.5rem; font-size: 0.875rem;"></div>
                    <div id="applied-voucher"
                         style="display: none; margin-top: 1rem; padding: 1rem; background: #d1fae5; border-radius: 0.5rem; border: 1px solid #10b981;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong id="voucher-code-display" style="color: #065f46;"></strong>
                                <p style="font-size: 0.875rem; color: #065f46; margin: 0.25rem 0 0 0;">
                                    Discount: <span id="voucher-discount-display"></span>
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
                    <span id="discount-amount">-$0.00</span>
                </div>

                <?php
                $subtotal = $total;
                $tax = $subtotal * 0.1;
                $shipping = $subtotal >= 100 ? 0 : 10;
                $finalTotal = $subtotal + $tax + $shipping;
                ?>

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">$<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping:</span>
                    <span id="shipping"><?= $shipping > 0 ? '$' . number_format($shipping, 2) : 'Free' ?></span>
                </div>
                <div class="summary-row">
                    <span>Tax (10%):</span>
                    <span id="tax">$<?= number_format($tax, 2) ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total:</span>
                    <span id="total">$<?= number_format($finalTotal, 2) ?></span>
                </div>

                <button type="button" class="btn btn-primary" id="place-order-btn">
                    Place Order
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='/cart'">
                    Return to Cart
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

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <p>Processing your order...</p>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>

<script>
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    const API_BASE = '/api/' + SITE;
    const STRIPE_KEY = '<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key') ?>';

    const INITIAL_SUBTOTAL = parseFloat(document.getElementById('subtotal').textContent.replace('$', ''));
    const INITIAL_SHIPPING = parseFloat(document.getElementById('shipping').textContent.replace('$', '') || '0');
    const INITIAL_TAX = parseFloat(document.getElementById('tax').textContent.replace('$', ''));

    let isLoggedIn = false;
    let currentMember = null;
    let selectedAddressId = null;
    let appliedVoucher = null;

    let stripe = null;
    let elements = null;
    let cardElement = null;
    let clientSecret = null;
    let paymentIntentId = null;
    let subscriptionId = null;
    let orderId = null;
    let isOneTimeSubscription = false;

    // Check if cart has one-time subscription
    function checkCartForSubscription() {
        const urlParams = new URLSearchParams(window.location.search);
        isOneTimeSubscription = urlParams.get('type') === 'subscription';
    }

    // Initialize Stripe
    async function initStripe() {
        if (!STRIPE_KEY) {
            console.error('Stripe key not configured');
            return;
        }

        stripe = Stripe(STRIPE_KEY);
        elements = stripe.elements();

        // Create card element
        cardElement = elements.create('card', {
            hidePostalCode: true,
            style: {
                base: {
                    fontSize: '16px',
                    color: '#1e293b',
                    '::placeholder': {
                        color: '#64748b',
                    },
                },
            },
        });

        // Mount card element
        const cardContainer = document.getElementById('card-element');
        if (cardContainer) {
            cardElement.mount('#card-element');

            // Handle validation errors
            cardElement.on('change', (event) => {
                const displayError = document.getElementById('card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });
        }

        // Hide old card fields
        const oldCardDetails = document.getElementById('card-details');
        if (oldCardDetails) {
            oldCardDetails.style.display = 'none';
        }

        // Hide payment method selection
        const paymentSection = document.getElementById('payment-form-section');
        if (paymentSection) {
            paymentSection.style.display = 'none';
        }
    }

    async function checkLoginStatus() {
        try {
            const response = await fetch(`/member/me`);
            if (response.ok) {
                const data = await response.json();
                if (data.member) {
                    isLoggedIn = true;
                    currentMember = data.member;
                    loadSavedAddresses();
                }
            }
        } catch (error) {
            console.log('Not logged in');
        }
    }

    async function loadSavedAddresses() {
        try {
            const response = await fetch(`/${SITE}/member/${currentMember.id}/addresses?type=shipping`);
            const data = await response.json();

            if (data.items && data.items.length > 0) {
                displaySavedAddresses(data.items);
            }
        } catch (error) {
            console.error('Failed to load saved addresses:', error);
        }
    }

    function displaySavedAddresses(addresses) {
        const container = document.getElementById('saved-addresses-list');
        const section = document.getElementById('saved-addresses-section');

        container.innerHTML = addresses.map(addr => `
        <label class="saved-address-card" for="addr-${addr.id}">
            <input type="radio" name="saved_address" value="${addr.id}" id="addr-${addr.id}"
                   onchange="selectAddress(${addr.id})">
            <div class="address-details">
                <strong>${addr.label || 'Address'}</strong>
                <p>${addr.formatted}</p>
            </div>
            ${addr.is_default ? '<span class="badge">Default</span>' : ''}
        </label>
    `).join('');

        section.style.display = 'block';

        // Hide manual form initially when saved addresses are loaded
        const shippingForm = document.getElementById('shipping-address-form');
        if (shippingForm) {
            shippingForm.style.display = 'none';
        }

        // Ensure back button is hidden
        const backBtn = document.getElementById('back-to-saved-btn');
        if (backBtn) {
            backBtn.style.display = 'none';
        }
    }

    function selectAddress(addressId) {
        selectedAddressId = addressId;
        const shippingForm = document.getElementById('shipping-address-form');

        if (shippingForm) {
            shippingForm.style.display = 'none';
        }
    }

    function showNewAddressForm() {
        selectedAddressId = null;

        const savedAddressesSection = document.getElementById('saved-addresses-section');
        if (savedAddressesSection) {
            savedAddressesSection.style.display = 'none';
        }

        const shippingForm = document.getElementById('shipping-address-form');
        if (shippingForm) {
            shippingForm.style.display = 'block';
        }

        const backBtn = document.getElementById('back-to-saved-btn');
        if (backBtn) {
            backBtn.style.display = 'block';
        }

        document.querySelectorAll('[name="saved_address"]').forEach(radio => radio.checked = false);
    }

    function showSavedAddresses() {
        selectedAddressId = null;

        // Show the saved addresses section
        const savedAddressesSection = document.getElementById('saved-addresses-section');
        if (savedAddressesSection) {
            savedAddressesSection.style.display = 'block';
        }

        // Hide the manual shipping address form
        const shippingForm = document.getElementById('shipping-address-form');
        if (shippingForm) {
            shippingForm.style.display = 'none';
        }

        // Hide the back button
        const backBtn = document.getElementById('back-to-saved-btn');
        if (backBtn) {
            backBtn.style.display = 'none';
        }

        // Uncheck all saved address radios
        document.querySelectorAll('[name="saved_address"]').forEach(radio => radio.checked = false);
    }

    // Payment method selection
    document.querySelectorAll('.payment-method').forEach(method => {
        method.addEventListener('click', function () {
            document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;

            // Show/hide card details
            const cardDetails = document.getElementById('card-details');
            if (this.dataset.method === 'card') {
                cardDetails.style.display = 'block';
            } else {
                cardDetails.style.display = 'none';
            }
        });
    });

    // Card number formatting
    const cardNumberInput = document.querySelector('input[name="card_number"]');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });
    }

    // Card expiry formatting
    const cardExpiryInput = document.querySelector('input[name="card_expiry"]');
    if (cardExpiryInput) {
        cardExpiryInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    }

    // Place order
    document.getElementById('place-order-btn').addEventListener('click', async function () {
        const form = document.getElementById('checkout-form');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        // Clear previous errors
        document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
        document.getElementById('alert-container').innerHTML = '';

        if (selectedAddressId) {
            data.saved_address = selectedAddressId;
            delete data.address;
            delete data.address2;
            delete data.city;
            delete data.state;
            delete data.postal_code;
            delete data.country;
        }

        const required = selectedAddressId
            ? ['first_name', 'last_name', 'email', 'phone']
            : ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'postal_code', 'country'];

        let hasErrors = false;

        for (const field of required) {
            if (!data[field] || data[field].trim() === '') {
                const errorEl = document.getElementById(`error-${field}`);
                if (errorEl) {
                    errorEl.textContent = 'This field is required';
                }
                hasErrors = true;
            }
        }

        if (hasErrors) {
            return;
        }

        if (appliedVoucher) {
            data.voucher_code = appliedVoucher.code;
            data.voucher_id = appliedVoucher.voucher_id;
            data.discount_amount = appliedVoucher.discount;
        }

        // Show loading
        document.getElementById('loading-overlay').classList.add('show');
        this.disabled = true;

        // Both flows now use Stripe
        if (isOneTimeSubscription) {
            await handleStripeCheckout(data);
        } else {
            await handleRegularCheckout(data);
        }
    });

    async function handleStripeCheckout(data) {
        document.getElementById('loading-overlay').classList.add('show');
        document.getElementById('place-order-btn').disabled = true;

        try {
            // Process checkout to get payment intent
            const response = await fetch(`${API_BASE}/subscriptions/onetime/checkout`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!result.success) {
                showAlert(result.message || 'Checkout failed', 'error');
                return;
            }

            const responseData = result.data;

            clientSecret = responseData.client_secret;
            subscriptionId = responseData.subscription_ids || responseData.subscription_id; // Handle both
            orderId = responseData.order_id;

            // Confirm payment with Stripe
            const {error, paymentIntent} = await stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: `${data.first_name} ${data.last_name}`,
                        email: data.email,
                        phone: data.phone,
                    },
                },
                setup_future_usage: 'off_session',
            });

            if (error) {
                showAlert(error.message, 'error');
                return;
            }

            if (paymentIntent.status === 'succeeded') {
                // Confirm payment on backend
                await confirmPayment(paymentIntent.id);
            }

        } catch (error) {
            console.error('Stripe checkout error:', error);
            showAlert('Payment failed. Please try again.', 'error');
        } finally {
            document.getElementById('loading-overlay').classList.remove('show');
            document.getElementById('place-order-btn').disabled = false;
        }
    }

    async function confirmPayment(paymentIntentId) {
        try {
            const body = {
                payment_intent_id: paymentIntentId,
                order_id: orderId
            };

            // Handle both single and multiple subscriptions
            if (Array.isArray(subscriptionId)) {
                body.subscription_ids = subscriptionId;
            } else if (subscriptionId) {
                body.subscription_id = subscriptionId;
            }

            const response = await fetch(`${API_BASE}/subscriptions/onetime/confirm-payment`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body)
            });

            const result = await response.json();

            if (result.success) {
                // Handle redirect for single or multiple subscriptions
                if (Array.isArray(subscriptionId) && subscriptionId.length > 1) {
                    window.location.href = `/subscriptions/onetime/multiple?ids=${subscriptionId.join(',')}`;
                } else {
                    const singleId = Array.isArray(subscriptionId) ? subscriptionId[0] : subscriptionId;
                    window.location.href = `/subscriptions/onetime/${singleId}`;
                }
            } else {
                showAlert(result.message || 'Payment confirmation failed', 'error');
            }
        } catch (error) {
            console.error('Payment confirmation error:', error);
            showAlert('Failed to confirm payment', 'error');
        }
    }

    async function handleRegularCheckout(data) {
        try {
            // First create the order and get payment intent
            const response = await fetch(`${API_BASE}/checkout/process`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!result.success) {
                showAlert(result.message || 'Failed to process order', 'error');
                return;
            }

            const clientSecret = result.client_secret;
            const orderId = result.order_internal_id;

            // Confirm payment with Stripe
            const {error, paymentIntent} = await stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: `${data.first_name} ${data.last_name}`,
                        email: data.email,
                        phone: data.phone,
                    },
                },
            });

            if (error) {
                showAlert(error.message, 'error');
                return;
            }

            if (paymentIntent.status === 'succeeded') {
                // Confirm payment on backend
                const confirmResponse = await fetch(`${API_BASE}/checkout/confirm-payment`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        payment_intent_id: paymentIntent.id,
                        order_id: orderId
                    })
                });

                const confirmResult = await confirmResponse.json();

                if (confirmResult.success) {
                    window.location.href = `/order-confirmation?order_id=${result.order_id}`;
                } else {
                    showAlert(confirmResult.message || 'Payment confirmation failed', 'error');
                }
            }

        } catch (error) {
            console.error('Checkout error:', error);
            showAlert('An error occurred. Please try again.', 'error');
        } finally {
            document.getElementById('loading-overlay').classList.remove('show');
            document.getElementById('place-order-btn').disabled = false;
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

    // Update cart count
    async function loadCartCount() {
        try {
            const response = await fetch(`${API_BASE}/cart`);
            const data = await response.json();
            document.getElementById('cart-count').textContent = data.count || 0;
        } catch (error) {
            console.error('Error loading cart count:', error);
        }
    }

    // Load wishlist count
    async function loadWishlistCount() {
        try {
            const response = await fetch(`${API_BASE}/wishlist`);
            const data = await response.json();
            document.getElementById('wishlist-count').textContent = data.data.count || 0;
        } catch (error) {
            console.error('Error loading wishlist count:', error);
        }
    }

    function checkForAppliedVoucher() {
        const savedVoucher = sessionStorage.getItem('appliedVoucher');
        if (savedVoucher) {
            appliedVoucher = JSON.parse(savedVoucher);
            displayAppliedVoucher();
            sessionStorage.removeItem('appliedVoucher');
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
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    code: code,
                    order_value: INITIAL_SUBTOTAL
                })
            });

            const data = await response.json();

            if (data.data.valid) {
                appliedVoucher = {
                    code: code,
                    discount: data.data.discount,
                    voucher_id: data.data.voucher_id
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
            console.error('Error applying voucher:', error);
            messageEl.textContent = 'Failed to apply voucher';
            messageEl.style.color = 'var(--danger-color)';
        }
    }

    function removeVoucher() {
        appliedVoucher = null;
        document.getElementById('applied-voucher').style.display = 'none';
        document.getElementById('discount-row').style.display = 'none';
        updateTotals();
        showAlert('Voucher removed', 'success');
    }

    function updateTotals() {
        const discount = appliedVoucher ? appliedVoucher.discount : 0;
        const tax = (INITIAL_SUBTOTAL - discount + INITIAL_SHIPPING) * 0.1;
        const total = INITIAL_SUBTOTAL - discount + INITIAL_SHIPPING + tax;

        document.getElementById('subtotal').textContent = '$' + INITIAL_SUBTOTAL.toFixed(2);
        if (appliedVoucher) {
            document.getElementById('discount-amount').textContent = '-$' + discount.toFixed(2);
        }
        document.getElementById('shipping').textContent = INITIAL_SHIPPING > 0 ? '$' + INITIAL_SHIPPING.toFixed(2) : 'Free';
        document.getElementById('tax').textContent = '$' + tax.toFixed(2);
        document.getElementById('total').textContent = '$' + total.toFixed(2);
    }

    // Update the place order button click handler to include voucher
    // Modify the existing place order handler to add:
    if (appliedVoucher) {
        data.voucher_code = appliedVoucher.code;
        data.voucher_id = appliedVoucher.voucher_id;
        data.discount_amount = appliedVoucher.discount;
    }

    checkLoginStatus();
    checkForAppliedVoucher();
    loadWishlistCount();
    checkCartForSubscription();
    initStripe();
</script>
</body>
</html>