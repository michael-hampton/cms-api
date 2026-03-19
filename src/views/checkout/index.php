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

        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 3rem;
        }

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

        /* Payment error shown inline beneath the card element */
        #card-errors:not(:empty) {
            margin-top: 0.5rem;
            padding: 0.75rem 1rem;
            background: #fee2e2;
            border: 1px solid #ef4444;
            border-radius: 0.375rem;
            color: #991b1b;
            font-size: 0.875rem;
        }

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

        .currency-badge {
            display: inline-block;
            background: #eff6ff;
            color: var(--primary-color);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid #bfdbfe;
            margin-left: 0.5rem;
            vertical-align: middle;
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
            transform: none;
        }

        .btn-primary.processing {
            position: relative;
        }

        .btn-primary.processing::after {
            content: '';
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin-left: 0.5rem;
            vertical-align: middle;
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

        /* Auto-renewal consent blocks — global (all users) and US-specific */
        .auto-renewal-consent {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-top: 1.5rem;
        }

        .auto-renewal-consent label {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            cursor: pointer;
        }

        .auto-renewal-consent input[type="checkbox"] {
            margin-top: 0.2rem;
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .auto-renewal-consent .consent-text {
            font-size: 0.8125rem;
            color: #0c4a6e;
            line-height: 1.6;
        }

        .auto-renewal-consent .consent-text strong {
            display: block;
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
        }

        .auto-renewal-consent.consent-error {
            border-color: var(--danger-color);
            background: #fff1f2;
        }

        .auto-renewal-consent.consent-error .consent-text {
            color: #7f1d1d;
        }

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

        .site-footer {
            background: white;
            padding: 2rem 0;
            text-align: center;
            color: var(--text-secondary);
            margin-top: 4rem;
            box-shadow: var(--shadow);
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

        .saved-card {
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

        .saved-card:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .saved-card input[type="radio"] {
            flex-shrink: 0;
        }

        .saved-card .card-details {
            flex: 1;
        }

        .saved-card .card-brand {
            font-weight: 600;
            text-transform: capitalize;
        }

        .saved-card .card-number {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .saved-card .card-expiry {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .saved-card.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

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

            .progress-steps::before, .progress-line {
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
    </style>
    @css('checkout-auth.css')
</head>
<body>
<header class="site-header">
    <div class="container">
        <div class="header-content">
            <div class="logo"><a href="/">YourStore</a></div>
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

<div class="page-header">
    <div class="container">
        <h1 class="page-title">Checkout</h1>
        <div class="breadcrumb">
            <a href="/">Home</a><span>/</span>
            <a href="/cart">Cart</a><span>/</span>
            <span>Checkout</span>
        </div>
    </div>
</div>

<main>
    <div class="container">
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

        <?php
        // Detect mixed cart server-side — subscriptions and physical products cannot
        // be purchased together. Show a blocking warning before the user submits.
        $cartSubscriptionItems = array_filter($items, fn($item) => !empty($item['subscription_plan_id']));
        $cartProductItems = array_filter($items, fn($item) => empty($item['subscription_plan_id']));
        $isMixedCart = !empty($cartSubscriptionItems) && !empty($cartProductItems);
        ?>

        <?php if ($isMixedCart): ?>
            <div class="alert alert-error" id="mixed-cart-warning" style="margin-bottom:1.5rem;">
                <strong>Your cart contains both subscription and physical items.</strong>
                These cannot be purchased together in a single order. Please
                <a href="/cart" style="color:inherit;font-weight:600;text-decoration:underline;">return to your cart</a>
                and complete them as separate orders.
            </div>
        <?php endif; ?>

        <?php if (!\App\Framework\Authorization\MemberAuth::check()): ?>
        <div class="login-prompt">
            <p>Already have an account? <a href="/member/login?redirect=/checkout">Login</a> to use saved addresses</p>
        </div>
        <?php endif; ?>

        <div class="checkout-layout">
            <div class="checkout-form">
                <form id="checkout-form">
                    <!-- Contact Information -->
                    <div class="form-section">
                        <h2 class="section-title">Contact Information</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" class="form-input"
                                       value="<?= $member?->first_name ?? '' ?>" required>
                                <span class="form-error" id="error-first_name"></span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" class="form-input"
                                       value="<?= $member?->last_name ?? '' ?>" required>
                                <span class="form-error" id="error-last_name"></span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-input"
                                       value="<?= $member?->email ?? '' ?>" required>
                                <span class="form-error" id="error-email"></span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-input">
                                <span class="form-error" id="error-phone"></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($requiresShipping ?? true): ?>
                        <div class="form-section" id="saved-addresses-section" style="display: none;">
                            <h2 class="section-title">Saved Addresses</h2>
                            <div id="saved-addresses-list"></div>
                            <button type="button" onclick="showNewAddressForm()" class="btn btn-secondary">
                                Use Different Address
                            </button>
                        </div>

                        <div class="form-section" id="shipping-address-form">
                            <div class="section-header">
                                <h2 class="section-title" style="margin-bottom:0;padding-bottom:0;border:none;">Shipping
                                    Address</h2>
                                <button type="button" id="back-to-saved-btn" onclick="showSavedAddresses()"
                                        class="btn btn-secondary" style="display:none;width:auto;padding:0.5rem 1rem;">
                                    ← Back to Saved Addresses
                                </button>
                            </div>
                            <div class="form-group full-width">
                                <label class="form-label">Address <span class="required">*</span></label>
                                <input type="text" name="address" class="form-input" required>
                                <span class="form-error" id="error-address"></span>
                            </div>
                            <div class="form-group full-width">
                                <label class="form-label">Apartment, suite, etc. (optional)</label>
                                <input type="text" name="address2" class="form-input">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">City <span class="required">*</span></label>
                                    <input type="text" name="city" class="form-input" required>
                                    <span class="form-error" id="error-city"></span>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">State / Province</label>
                                    <input type="text" name="state" class="form-input">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Postal Code <span class="required">*</span></label>
                                    <input type="text" name="postal_code" class="form-input" required>
                                    <span class="form-error" id="error-postal_code"></span>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Country <span class="required">*</span></label>
                                    <select name="country" id="country-select" class="form-select"
                                            required onchange="handleCountryChange(this.value)">
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
                    <?php endif; ?>

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

                    <div class="form-section" id="saved-cards-section" style="display: none;">
                        <h2 class="section-title">Saved Payment Methods</h2>
                        <div id="saved-cards-list"></div>
                        <button type="button" onclick="showNewCardForm()" class="btn btn-secondary">
                            Use Different Card
                        </button>
                    </div>

                    <div id="payment-request-button"></div>

                    <div class="form-section" id="new-card-section">
                        <div class="section-header">
                            <h2 class="section-title">Card Details</h2>
                            <button type="button" id="back-to-saved-cards-btn" onclick="showSavedCards()"
                                    class="btn btn-secondary" style="display:none;width:auto;padding:0.5rem 1rem;">
                                ← Back to Saved Cards
                            </button>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Card Information <span class="required">*</span></label>
                            <div id="card-element"
                                 style="padding:0.75rem;border:1px solid var(--border-color);border-radius:0.5rem;"></div>
                            <!-- Inline payment error — shown immediately beneath the card element -->
                            <div id="card-errors" class="form-error" role="alert" aria-live="polite"></div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2 class="section-title">Order Notes (Optional)</h2>
                        <div class="form-group full-width">
                            <label class="form-label">Special instructions for delivery</label>
                            <textarea name="notes" class="form-textarea"
                                      placeholder="Add any special instructions..."></textarea>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h3>
                    Order Summary
                    <?php
                    // Currency is resolved server-side from the site config.
                    // It is locked to the plan/site currency and will not change
                    // if the user updates their billing country.
                    $displayCurrency = strtoupper($currency ?? 'GBP');
                    ?>
                    <span class="currency-badge"><?= htmlspecialchars($displayCurrency) ?></span>
                </h3>

                <div id="order-items">
                    <?php
                    $itemsByMerchant = [];
                    foreach ($items as $item) {
                        $merchantId = $item['options']['merchant_id'] ?? 0;
                        $merchantName = $merchantId ? ($item['merchant_name'] ?? 'Merchant ' . $merchantId) : 'Direct';
                        if (!isset($itemsByMerchant[$merchantId])) {
                            $itemsByMerchant[$merchantId] = ['name' => $merchantName, 'items' => [], 'subtotal' => 0];
                        }
                        $itemsByMerchant[$merchantId]['items'][] = $item;
                        $itemsByMerchant[$merchantId]['subtotal'] += $item['subtotal'];
                    }
                    ?>

                    <?php foreach ($itemsByMerchant as $merchantId => $merchantData): ?>
                        <div class="merchant-group" style="margin-bottom:1.5rem;padding-bottom:1.5rem;">
                            <div class="merchant-header" style="margin-bottom:1rem;">
                                <strong style="font-size:0.875rem;color:var(--text-primary);">
                                    <?= htmlspecialchars($merchantData['name']) ?>
                                </strong>
                                <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:0.5rem;">
                                (<?= count($merchantData['items']) ?> items)
                            </span>
                            </div>

                            <?php foreach ($merchantData['items'] as $item): ?>
                                <div class="summary-item">
                                    <img src="<?= htmlspecialchars($item['product_image'] ?? '/images/placeholder.jpg') ?>"
                                         alt="<?= htmlspecialchars($item['product_name']) ?>"
                                         class="item-image">
                                    <div class="item-details">
                                        <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>

                                        <?php if (!empty($item['variant_id']) && !empty($item['variant_options'])): ?>
                                            <div class="variant-options"
                                                 style="margin-top:0.25rem;font-size:0.75rem;color:var(--text-secondary);">
                                                <?php
                                                $variantParts = [];
                                                foreach ($item['variant_options'] as $optionName => $optionValue) {
                                                    $variantParts[] = htmlspecialchars(ucfirst($optionName)) . ': <strong>' . htmlspecialchars($optionValue) . '</strong>';
                                                }
                                                echo implode(' • ', $variantParts);
                                                ?>
                                            </div>
                                            <?php if (!empty($item['variant_sku'])): ?>
                                                <div style="font-size:0.7rem;color:var(--text-secondary);margin-top:0.125rem;">
                                                    SKU: <?= htmlspecialchars($item['variant_sku']) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <div class="item-meta">Qty: <?= $item['quantity'] ?></div>

                                        <?php if (!empty($item['estimated_delivery'])): ?>
                                            <div style="font-size:0.75rem;color:var(--success-color);margin-top:0.25rem;">
                                                📦 <?= htmlspecialchars($item['estimated_delivery']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-price">
                                        <?= htmlspecialchars($displayCurrency) ?> <?= number_format($item['subtotal'], 2) ?>
                                    </div>

                                    <?php if (!empty($item['options']['subscription_plan_id'])): ?>
                                        <input type="hidden" name="plan_id"
                                               value="<?= $item['options']['subscription_plan_id'] ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="voucher-section"
                         style="margin:1.5rem 0;padding:1.5rem 0;border-top:1px solid var(--border-color);border-bottom:1px solid var(--border-color);">
                        <h4 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Voucher Code</h4>
                        <div style="display:flex;gap:0.5rem;">
                            <input type="text" id="voucher-input" placeholder="Enter code"
                                   style="flex:1;padding:0.75rem;border:1px solid var(--border-color);border-radius:0.5rem;font-size:0.875rem;"
                                   value="<?= htmlspecialchars($_SESSION['applied_voucher_code']['code'] ?? '') ?>">
                            <button onclick="applyVoucher()" class="btn btn-secondary"
                                    style="width:auto;padding:0.75rem 1.5rem;font-size:0.875rem;">Apply
                            </button>
                        </div>
                        <div id="voucher-message" style="margin-top:0.5rem;font-size:0.875rem;"></div>
                        <div id="applied-voucher"
                             style="<?= !empty($_SESSION['applied_voucher_code']) ? 'display:block;' : 'display:none;' ?> margin-top:1rem;padding:1rem;background:#d1fae5;border-radius:0.5rem;border:1px solid #10b981;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <strong id="voucher-code-display" style="color:#065f46;"></strong>
                                    <p style="font-size:0.875rem;color:#065f46;margin:0.25rem 0 0 0;">
                                        Discount: <span
                                                id="voucher-discount-display"><?= htmlspecialchars($_SESSION['applied_voucher_code']['discount'] ?? '') ?></span>
                                    </p>
                                </div>
                                <button onclick="removeVoucher()"
                                        style="background:none;border:none;color:#065f46;cursor:pointer;padding:0.5rem;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="summary-row" id="discount-row"
                         style="display:none;color:var(--success-color);">
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
                        <span id="subtotal"><?= htmlspecialchars($displayCurrency) ?> <?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span id="shipping"><?= $shipping > 0 ? htmlspecialchars($displayCurrency) . ' ' . number_format($shipping, 2) : 'Free' ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax:</span>
                        <span id="tax"><?= htmlspecialchars($displayCurrency) ?> <?= number_format($tax, 2) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="total" data-total="<?= $finalTotal ?>">
                            <?= htmlspecialchars($displayCurrency) ?> <?= number_format($finalTotal, 2) ?>
                        </span>
                    </div>

                    <?php if (!empty($hasPreOrders)): ?>
                        <div style="background:#fef3c7;border:1px solid #f59e0b;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
                            <div style="font-weight:600;color:#92400e;margin-bottom:0.75rem;">⚠️ Pre-Order Items in
                                Cart
                            </div>
                            <?php foreach ($hasPreOrders as $preOrder): ?>
                                <div style="font-size:0.875rem;color:#78350f;margin-bottom:0.5rem;">
                                    <strong><?= htmlspecialchars($preOrder['name']) ?></strong><br>
                                    <?= htmlspecialchars($preOrder['message']) ?>
                                    <?php if ($preOrder['ship_date']): ?>
                                        <br>Ships: <?= htmlspecialchars($preOrder['ship_date']) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <label style="display:flex;align-items:start;gap:0.75rem;cursor:pointer;margin-top:1rem;padding-top:1rem;border-top:1px solid #fbbf24;">
                                <input type="checkbox" id="accept-pre-order" name="accept_pre_order" required
                                       style="margin-top:0.25rem;width:18px;height:18px;cursor:pointer;flex-shrink:0;">
                                <span style="flex:1;font-size:0.875rem;color:#92400e;">
                                    I understand this order contains pre-order items and accept the delivery timelines shown above.
                                </span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <!--
                        AUTO-RENEWAL CONSENT — ALL USERS
                        Shown to every user regardless of country.
                        Required before submission.
                        Covers EU/UK Consumer Rights, Australian ACCC, Canadian provincial requirements.
                        NOTE: Final wording must be reviewed and approved by Legal prior to release.
                    -->
                    <div id="global-renewal-consent-block" class="auto-renewal-consent">
                        <label>
                            <input type="checkbox" id="global-renewal-consent"
                                   name="global_renewal_consent" value="1">
                            <div class="consent-text">
                                <strong>Subscription Terms</strong>
                                I understand this is a recurring subscription that will automatically renew
                                until cancelled. I can cancel at any time via my account settings before
                                the next renewal date.
                                <em style="display:block;margin-top:0.5rem;font-size:0.75rem;opacity:0.8;">
                                    [Pending Legal review — final wording TBC]
                                </em>
                            </div>
                        </label>
                    </div>

                    <!--
                        AUTO-RENEWAL CONSENT — US USERS ONLY
                        Shown only when billing country is United States.
                        Additional explicit double opt-in required by US state legislation
                        (California ARL, New York, and others).
                        NOTE: Final wording must be reviewed and approved by Legal prior to release.
                    -->
                    <div id="us-renewal-consent-block" class="auto-renewal-consent"
                         style="display:none;margin-top:0.75rem;">
                        <label>
                            <input type="checkbox" id="us-renewal-consent"
                                   name="us_renewal_consent" value="1">
                            <div class="consent-text">
                                <strong>Auto-Renewal Notice (Required for US customers)</strong>
                                This is an automatically renewing subscription that will continue until
                                you cancel. You may cancel at any time via your account settings. Your
                                payment will be charged at the then-current rate on the same date each
                                billing period and will continue until cancelled. We may update pricing
                                with advance notice; you may cancel before any change takes effect.
                                <em style="display:block;margin-top:0.5rem;font-size:0.75rem;opacity:0.8;">
                                    [Pending Legal review — final wording TBC]
                                </em>
                            </div>
                        </label>
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
    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; 2025 YourStore. All rights reserved.</p>
    </div>
</footer>

<div id="loading-overlay" class="loading-overlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <p>Processing your order...</p>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
@js('checkout-auth.js')

<script>
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    const API_BASE = '/api/' + SITE;
    const STRIPE_KEY = '<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key') ?>';
    // Currency is locked to the site/plan — it does NOT change when billing country changes.
    const PLAN_CURRENCY = '<?= htmlspecialchars($displayCurrency) ?>';

    const requiresShipping = <?= json_encode($requiresShipping ?? true) ?>;
    const isMixedCart = <?= json_encode($isMixedCart) ?>;

    const INITIAL_SUBTOTAL = parseFloat('<?= $subtotal ?>');
    const INITIAL_SHIPPING = parseFloat('<?= $shipping ?>');
    const INITIAL_TAX = parseFloat('<?= $tax ?>');

    let isLoggedIn = false;
    let currentMember = null;
    let selectedAddressId = null;
    let appliedVoucher = <?= json_encode($_SESSION['applied_voucher_code'] ?? null) ?>;

    let stripe = null;
    let elements = null;
    let cardElement = null;
    let clientSecret = null;
    let paymentIntentId = null;
    let subscriptionId = null;
    let orderId = null;
    let isOneTimeSubscription = false;

    let savedCards = [];
    let selectedCardId = null;

    // -------------------------------------------------------------------------
    // Country / consent handling
    // -------------------------------------------------------------------------

    function handleCountryChange(countryCode) {
        const usBlock = document.getElementById('us-renewal-consent-block');
        if (!usBlock) return;

        if (countryCode === 'US') {
            usBlock.style.display = 'block';
            usBlock.classList.remove('consent-error');
        } else {
            usBlock.style.display = 'none';
            const cb = document.getElementById('us-renewal-consent');
            if (cb) cb.checked = false;
        }
    }

    // -------------------------------------------------------------------------
    // Saved cards
    // -------------------------------------------------------------------------

    function checkCartForSubscription() {
        const urlParams = new URLSearchParams(window.location.search);
        isOneTimeSubscription = urlParams.get('type') === 'subscription';

        if (!isOneTimeSubscription) {
            document.getElementById('global-renewal-consent-block').style.display = 'none';
        }
    }

    async function loadSavedCards() {
        if (!isLoggedIn || !currentMember) return;
        try {
            const response = await fetch(`/api/${SITE}/member/payment-methods`);
            const data = await response.json();
            if (data.success && data.data.payment_methods && data.data.payment_methods.length > 0) {
                savedCards = data.data.payment_methods;
                displaySavedCards();
            }
        } catch (error) {
            console.error('Failed to load saved cards:', error);
        }
    }

    function displaySavedCards() {
        const container = document.getElementById('saved-cards-list');
        const section = document.getElementById('saved-cards-section');

        container.innerHTML = savedCards.map(card => `
            <label class="saved-card ${card.id === selectedCardId ? 'selected' : ''}" for="card-${card.id}">
                <input type="radio" name="saved_card" value="${card.id}"
                       id="card-${card.id}" onchange="selectSavedCard('${card.id}')">
                <div class="card-details">
                    <div class="card-brand">${card.card.brand}</div>
                    <div class="card-number">•••• •••• •••• ${card.card.last4}</div>
                    <div class="card-expiry">Expires ${card.card.exp_month}/${card.card.exp_year}</div>
                </div>
            </label>
        `).join('');

        section.style.display = 'block';
        const newCardSection = document.getElementById('new-card-section');
        if (newCardSection) newCardSection.style.display = 'none';
    }

    function selectSavedCard(cardId) {
        selectedCardId = cardId;
        document.querySelectorAll('.saved-card').forEach(c => c.classList.remove('selected'));
        document.getElementById(`card-${cardId}`).closest('.saved-card').classList.add('selected');
    }

    function showNewCardForm() {
        selectedCardId = null;
        const savedCardsSection = document.getElementById('saved-cards-section');
        if (savedCardsSection) savedCardsSection.style.display = 'none';
        const newCardSection = document.getElementById('new-card-section');
        if (newCardSection) newCardSection.style.display = 'block';
        const backBtn = document.getElementById('back-to-saved-cards-btn');
        if (backBtn) backBtn.style.display = 'block';
        document.querySelectorAll('[name="saved_card"]').forEach(r => r.checked = false);
    }

    function showSavedCards() {
        selectedCardId = null;
        const savedCardsSection = document.getElementById('saved-cards-section');
        if (savedCardsSection) savedCardsSection.style.display = 'block';
        const newCardSection = document.getElementById('new-card-section');
        if (newCardSection) newCardSection.style.display = 'none';
        const backBtn = document.getElementById('back-to-saved-cards-btn');
        if (backBtn) backBtn.style.display = 'none';
    }

    function getCurrentOrderAmount() {
        const discount = appliedVoucher ? appliedVoucher.discount : 0;
        const taxRate = <?= $tax_rate ?>;
        const taxable = INITIAL_SUBTOTAL - discount + INITIAL_SHIPPING;
        const tax = taxable * taxRate;
        return Math.round((taxable + tax) * 100);
    }

    // -------------------------------------------------------------------------
    // Stripe init
    // -------------------------------------------------------------------------

    async function initStripe() {
        if (!STRIPE_KEY) {
            console.error('Stripe key not configured');
            return;
        }

        stripe = Stripe(STRIPE_KEY);
        elements = stripe.elements();

        cardElement = elements.create('card', {
            hidePostalCode: true,
            style: {
                base: {
                    fontSize: '16px',
                    color: '#1e293b',
                    '::placeholder': {color: '#64748b'},
                },
            },
        });

        // Apple Pay / Wallet
        const paymentRequest = stripe.paymentRequest({
            country: 'GB',
            currency: PLAN_CURRENCY.toLowerCase(),
            total: {label: 'Order Total', amount: getCurrentOrderAmount()},
            requestPayerName: true,
            requestPayerEmail: true
        });

        const prButton = elements.create('paymentRequestButton', {paymentRequest});

        paymentRequest.canMakePayment().then(result => {
            if (result) prButton.mount('#payment-request-button');
        });

        paymentRequest.on('paymentmethod', async (ev) => {
            try {
                const {error, paymentIntent} = await stripe.confirmCardPayment(
                    clientSecret,
                    {payment_method: ev.paymentMethod.id},
                    {handleActions: false}
                );

                if (error) {
                    ev.complete('fail');
                    showAlert(error.message, 'error');
                    return;
                }

                ev.complete('success');

                if (paymentIntent.status === 'requires_action') {
                    await stripe.confirmCardPayment(clientSecret);
                }
                if (paymentIntent.status === 'succeeded') {
                    await confirmPayment(paymentIntent.id);
                }
            } catch (err) {
                console.error(err);
                ev.complete('fail');
                showAlert('Payment failed', 'error');
            }
        });

        const cardContainer = document.getElementById('card-element');
        if (cardContainer) {
            cardElement.mount('#card-element');

            cardElement.on('change', (event) => {
                const displayError = document.getElementById('card-errors');
                displayError.textContent = event.error ? event.error.message : '';
            });
        }

        const paymentSection = document.getElementById('payment-form-section');
        if (paymentSection) paymentSection.style.display = 'none';
    }

    // -------------------------------------------------------------------------
    // Login / address loading
    // -------------------------------------------------------------------------

    async function checkLoginStatus() {
        try {
            const response = await fetch(`/member/me`);
            if (response.ok) {
                const data = await response.json();
                if (data.member) {
                    isLoggedIn = true;
                    currentMember = data.member;
                    if (requiresShipping) loadSavedAddresses();
                    loadSavedCards();
                }
            }
        } catch (error) { /* guest */
        }
    }

    async function loadSavedAddresses() {
        try {
            const response = await fetch(`/${SITE}/member/${currentMember.id}/addresses?type=shipping`);
            const data = await response.json();
            if (data.items && data.items.length > 0) displaySavedAddresses(data.items);
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
                ${addr.is_default ? '<span class="badge" style="position:static;background:var(--primary-color);">Default</span>' : ''}
            </label>
        `).join('');

        section.style.display = 'block';
        const shippingForm = document.getElementById('shipping-address-form');
        if (shippingForm) shippingForm.style.display = 'none';
        const backBtn = document.getElementById('back-to-saved-btn');
        if (backBtn) backBtn.style.display = 'none';
    }

    function selectAddress(addressId) {
        selectedAddressId = addressId;
        const shippingForm = document.getElementById('shipping-address-form');
        if (shippingForm) shippingForm.style.display = 'none';
    }

    function showNewAddressForm() {
        selectedAddressId = null;
        const savedAddressesSection = document.getElementById('saved-addresses-section');
        if (savedAddressesSection) savedAddressesSection.style.display = 'none';
        const shippingForm = document.getElementById('shipping-address-form');
        if (shippingForm) shippingForm.style.display = 'block';
        const backBtn = document.getElementById('back-to-saved-btn');
        if (backBtn) backBtn.style.display = 'block';
        document.querySelectorAll('[name="saved_address"]').forEach(r => r.checked = false);
    }

    function showSavedAddresses() {
        selectedAddressId = null;
        const savedAddressesSection = document.getElementById('saved-addresses-section');
        if (savedAddressesSection) savedAddressesSection.style.display = 'block';
        const shippingForm = document.getElementById('shipping-address-form');
        if (shippingForm) shippingForm.style.display = 'none';
        const backBtn = document.getElementById('back-to-saved-btn');
        if (backBtn) backBtn.style.display = 'none';
        document.querySelectorAll('[name="saved_address"]').forEach(r => r.checked = false);
    }

    // Payment method selection
    document.querySelectorAll('.payment-method').forEach(method => {
        method.addEventListener('click', function () {
            document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });

    // -------------------------------------------------------------------------
    // Place order
    // -------------------------------------------------------------------------

    function setProcessingState(processing) {
        const btn = document.getElementById('place-order-btn');
        const overlay = document.getElementById('loading-overlay');

        btn.disabled = processing;

        if (processing) {
            btn.classList.add('processing');
            btn.textContent = 'Processing…';
            overlay.classList.add('show');
        } else {
            btn.classList.remove('processing');
            btn.textContent = 'Place Order';
            overlay.classList.remove('show');
        }
    }

    document.getElementById('place-order-btn').addEventListener('click', async function () {
        if (isMixedCart) {
            showAlert('Your cart contains both subscription and physical items. Please return to your cart and complete them as separate orders.', 'error');
            return;
        }

        const form = document.getElementById('checkout-form');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        // Clear previous errors
        document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
        document.getElementById('alert-container').innerHTML = '';
        document.getElementById('card-errors').textContent = '';

        // Pre-order consent
        const acceptPreOrder = document.getElementById('accept-pre-order');
        if (acceptPreOrder && !acceptPreOrder.checked) {
            showAlert('You must accept the pre-order terms to continue.', 'error');
            return;
        }

        // Global auto-renewal consent — required for all users
        const globalConsentBlock = document.getElementById('global-renewal-consent-block');
        const globalConsentCb = document.getElementById('global-renewal-consent');
        if (!globalConsentCb.checked && isOneTimeSubscription) {
            globalConsentBlock.classList.add('consent-error');
            showAlert('Please confirm the subscription terms before placing your order.', 'error');
            globalConsentBlock.scrollIntoView({behavior: 'smooth', block: 'center'});
            return;
        }
        globalConsentBlock.classList.remove('consent-error');
        data.global_renewal_consent = '1';

        // US auto-renewal consent — additional requirement for US users
        const usConsentBlock = document.getElementById('us-renewal-consent-block');
        if (usConsentBlock && usConsentBlock.style.display !== 'none') {
            const usConsentCb = document.getElementById('us-renewal-consent');
            if (!usConsentCb.checked) {
                usConsentBlock.classList.add('consent-error');
                showAlert('Please confirm the auto-renewal terms before placing your order.', 'error');
                usConsentBlock.scrollIntoView({behavior: 'smooth', block: 'center'});
                return;
            }
            usConsentBlock.classList.remove('consent-error');
            data.us_renewal_consent = '1';
        }

        if (selectedAddressId) {
            data.saved_address = selectedAddressId;
            delete data.address;
            delete data.address2;
            delete data.city;
            delete data.state;
            delete data.postal_code;
            delete data.country;
        }

        if (requiresShipping) {
            const required = selectedAddressId
                ? ['first_name', 'last_name', 'email']
                : ['first_name', 'last_name', 'email', 'address', 'city', 'postal_code', 'country'];

            let hasErrors = false;
            for (const field of required) {
                if (!data[field] || data[field].trim() === '') {
                    const errorEl = document.getElementById(`error-${field}`);
                    if (errorEl) errorEl.textContent = 'This field is required';
                    hasErrors = true;
                }
            }
            if (hasErrors) return;
        }

        if (appliedVoucher) {
            data.voucher_code = appliedVoucher.code;
            data.voucher_id = appliedVoucher.voucher_id;
            data.discount_amount = appliedVoucher.discount;
        }

        data.multi_merchant = true;

        setProcessingState(true);

        try {
            if (isOneTimeSubscription) {
                data.isOneTimeSubscription = true;
                await handleStripeCheckout(data);
            } else {
                await handleRegularCheckout(data);
            }
        } finally {
            setProcessingState(false);
        }
    });

    // -------------------------------------------------------------------------
    // Stripe checkout flows
    // -------------------------------------------------------------------------

    async function handleStripeCheckout(data) {
        try {
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

            // ?type=subscription hits /api/{site}/subscriptions/onetime/checkout
            // which returns jsonResponse — wrapped in result.data
            const responseData = result.data;

            const contexts = responseData.stripe_contexts;
            if (contexts && Object.keys(contexts).length > 0) {
                const firstKey = Object.keys(contexts)[0];
                clientSecret = contexts[firstKey].client_secret;
            } else {
                clientSecret = responseData.client_secret;
            }

            subscriptionId = responseData.subscription_ids || responseData.subscription_id;
            orderId = responseData.order_id;

            let paymentResult;
            if (selectedCardId) {
                paymentResult = await stripe.confirmCardPayment(clientSecret, {
                    payment_method: selectedCardId,
                });
            } else {
                paymentResult = await stripe.confirmCardPayment(clientSecret, {
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
            }

            const {error, paymentIntent} = paymentResult;

            if (error) {
                // Show inline beneath card element AND as an alert
                document.getElementById('card-errors').textContent = error.message;
                showAlert(error.message, 'error');
                return;
            }

            if (paymentIntent.status === 'succeeded') {
                await confirmPayment(paymentIntent.id);
            }

        } catch (error) {
            console.error('Stripe checkout error:', error);
            showAlert('Payment failed. Please try again.', 'error');
        }
    }

    async function confirmPayment(paymentIntentId) {
        try {
            const body = {payment_intent_id: paymentIntentId, order_id: orderId};

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
                const ids = Array.isArray(subscriptionId) ? subscriptionId : [subscriptionId];
                window.location.href = `/subscription-confirmation?ids=${ids.join(',')}`;
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
            const response = await fetch(`${API_BASE}/checkout/process`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!result.success) {
                showAlert(result.message || 'Failed to process order', 'error');
                return;
            }

            // FIXED: use result.stripe_contexts and result.client_secret (not data.client_secret)
            const contexts = result.stripe_contexts;
            if (contexts && Object.keys(contexts).length > 0) {
                const firstKey = Object.keys(contexts)[0];
                clientSecret = contexts[firstKey].client_secret;
            } else {
                clientSecret = result.client_secret;
            }

            const checkoutId = result.checkout_id;

            let paymentResult;
            if (selectedCardId) {
                paymentResult = await stripe.confirmCardPayment(clientSecret, {
                    payment_method: selectedCardId,
                });
            } else {
                paymentResult = await stripe.confirmCardPayment(clientSecret, {
                    payment_method: {
                        card: cardElement,
                        billing_details: {
                            name: `${data.first_name} ${data.last_name}`,
                            email: data.email,
                            phone: data.phone,
                        },
                    },
                });
            }

            const {error, paymentIntent} = paymentResult;

            if (error) {
                // Show inline beneath card element AND as an alert so user can retry
                document.getElementById('card-errors').textContent = error.message;
                showAlert(error.message, 'error');
                return;
            }

            if (paymentIntent.status === 'succeeded') {
                const confirmResponse = await fetch(`${API_BASE}/checkout/confirm-payment`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        payment_intent_id: paymentIntent.id,
                        checkout_id: checkoutId
                    })
                });

                const confirmResult = await confirmResponse.json();

                if (confirmResult.success) {
                    window.location.href = `/order-confirmation?checkout_id=${confirmResult.checkout_id}`;
                } else {
                    showAlert(confirmResult.message || 'Payment confirmation failed', 'error');
                }
            }

        } catch (error) {
            console.error('Checkout error:', error);
            showAlert('An error occurred. Please try again.', 'error');
        }
    }

    // -------------------------------------------------------------------------
    // Vouchers
    // -------------------------------------------------------------------------

    async function applyVoucher() {
        const voucherCode = document.getElementById('voucher-input').value.trim();
        if (!voucherCode) {
            showVoucherMessage('Please enter a voucher code', 'error');
            return;
        }

        const totalAmount = document.getElementById('total').dataset.total;
        const isSubscription = window.location.search.includes('type=subscription') ||
            document.querySelector('[name="plan_id"]');

        const requestBody = {
            code: voucherCode,
            is_subscription: !!isSubscription,
            order_value: parseFloat(totalAmount)
        };

        if (isSubscription) {
            const planIdInput = document.querySelector('[name="plan_id"]');
            if (planIdInput) requestBody.plan_id = parseInt(planIdInput.value);
        }

        const messageEl = document.getElementById('voucher-message');

        try {
            const response = await fetch(`${API_BASE}/vouchers/validate`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(requestBody)
            });

            // FIXED: use `result` consistently throughout
            const result = await response.json();

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
                // FIXED: was `data.data.message` — now `result.data.message`
                messageEl.textContent = result.data.message || 'Invalid voucher code';
                messageEl.style.color = 'var(--danger-color)';
            }
        } catch (error) {
            console.error('Error:', error);
            messageEl.textContent = 'Error applying voucher';
            messageEl.style.color = 'var(--danger-color)';
        }
    }

    function displayAppliedVoucher() {
        if (!appliedVoucher) return;
        document.getElementById('voucher-code-display').textContent = appliedVoucher.code;
        document.getElementById('voucher-discount-display').textContent = PLAN_CURRENCY + ' ' + appliedVoucher.discount.toFixed(2);
        document.getElementById('applied-voucher').style.display = 'block';
        document.getElementById('discount-row').style.display = 'flex';
        document.getElementById('discount-amount').textContent = '-' + PLAN_CURRENCY + ' ' + appliedVoucher.discount.toFixed(2);
        updateTotals();
    }

    async function removeVoucher() {
        try {
            const response = await fetch(`${API_BASE}/vouchers/remove-voucher`, {method: 'POST'});
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

    function updateTotals() {
        const discount = appliedVoucher ? appliedVoucher.discount : 0;
        const taxRate = <?= $tax_rate ?>;
        const tax = (INITIAL_SUBTOTAL - discount + INITIAL_SHIPPING) * taxRate;
        const total = INITIAL_SUBTOTAL - discount + INITIAL_SHIPPING + tax;

        document.getElementById('subtotal').textContent = PLAN_CURRENCY + ' ' + INITIAL_SUBTOTAL.toFixed(2);
        if (appliedVoucher) document.getElementById('discount-amount').textContent = '-' + PLAN_CURRENCY + ' ' + discount.toFixed(2);
        document.getElementById('shipping').textContent = INITIAL_SHIPPING > 0 ? PLAN_CURRENCY + ' ' + INITIAL_SHIPPING.toFixed(2) : 'Free';
        document.getElementById('tax').textContent = PLAN_CURRENCY + ' ' + tax.toFixed(2);
        document.getElementById('total').textContent = PLAN_CURRENCY + ' ' + total.toFixed(2);
    }

    function showAlert(message, type = 'success') {
        const alertHtml = `<div class="alert alert-${type}">${message}</div>`;
        document.getElementById('alert-container').innerHTML = alertHtml;
        window.scrollTo({top: 0, behavior: 'smooth'});
        setTimeout(() => {
            document.getElementById('alert-container').innerHTML = '';
        }, 5000);
    }

    function showVoucherMessage(msg, type) {
        const el = document.getElementById('voucher-message');
        el.textContent = msg;
        el.style.color = type === 'error' ? 'var(--danger-color)' : 'var(--success-color)';
    }

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------
    checkLoginStatus();
    checkCartForSubscription();
    initStripe();

    if (isMixedCart) {
        const btn = document.getElementById('place-order-btn');
        btn.disabled = true;
        btn.title = 'Remove subscription or physical items from your cart to continue';
    }

    // If member is pre-authenticated as US, show US consent block immediately
    <?php if (($member?->country ?? '') === 'US'): ?>
    handleCountryChange('US');
    <?php endif; ?>
</script>
</body>
</html>