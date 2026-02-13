<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe - <?= htmlspecialchars($plan->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5a67d8;
            --success-color: #10b981;
            --danger-color: #ef4444;
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
            max-width: 1000px;
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

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            text-align: center;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .card {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            background: rgba(102, 126, 234, 0.05);
        }

        .plan-summary {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .plan-price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .plan-period {
            color: var(--text-secondary);
        }

        .plan-features {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }

        .plan-features li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .plan-features li:before {
            content: "✓";
            color: var(--success-color);
            font-weight: 700;
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

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
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

        @media (max-width: 968px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="/">YourStore</a>
            </div>
        </div>
    </div>
</header>

<div class="page-header">
    <div class="container">
        <h1>Complete Your Subscription</h1>
        <p>Join thousands of satisfied subscribers</p>
    </div>
</div>

<main>
    <div class="container">
        <div id="alert-container"></div>

        <div class="checkout-layout">
            <div class="card">
                <form id="subscription-form">
                    <input type="hidden" name="subscription_plan_id" value="<?= $plan->id ?>">

                    <div class="section-title">Payment Method</div>
                    <div class="payment-methods">
                        <label class="payment-method selected" data-method="stripe">
                            <input type="radio" name="payment_method" value="stripe" checked>
                            <div>
                                <div style="font-weight: 600;">Credit Card (Stripe)</div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                    Secure payment with Stripe
                                </div>
                            </div>
                        </label>
                        <label class="payment-method" data-method="paypal">
                            <input type="radio" name="payment_method" value="paypal">
                            <div>
                                <div style="font-weight: 600;">PayPal</div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                    Pay with your PayPal account
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- ADD THIS BLOCK AFTER LINE 328 (before card details) -->

                    <?php
                    // Check if any item is pre-release
                    $isPreRelease = $plan->release_date
                            && $plan->release_date > now_datetime()
                            && $plan->pre_release_enabled;
                    ?>

                    <?php if ($isPreRelease): ?>
                        <div class="card"
                             style="background: #fef3c7; border: 2px solid #f59e0b; margin-bottom: 1.5rem;">
                            <h3 style="color: #92400e; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <span>⚠️</span> Pre-Release Subscription
                            </h3>
                            <div style="color: #78350f; margin-bottom: 1rem;">
                                <p style="margin-bottom: 0.5rem;">
                                    <strong>Access begins:</strong> <?= $plan->release_date->format('F j, Y') ?>
                                </p>
                                <?php if ($plan->print_shipping_required): ?>
                                    <p style="margin-bottom: 0.5rem;">
                                        <strong>First print issue
                                            ships:</strong> <?= $plan->release_date->format('F j, Y') ?>
                                    </p>
                                <?php endif; ?>
                                <p style="margin-top: 1rem; font-size: 0.875rem;">
                                    You will be charged today, but access to this subscription will begin on the date
                                    above.
                                </p>
                            </div>
                            <label style="display: flex; align-items: start; gap: 0.75rem; cursor: pointer; padding: 1rem; background: white; border-radius: 0.5rem;">
                                <input
                                        type="checkbox"
                                        name="accept_pre_release"
                                        id="accept-pre-release"
                                        style="margin-top: 0.25rem; width: 18px; height: 18px; cursor: pointer;"
                                        required
                                >
                                <span style="flex: 1; color: #1e293b;">
            I understand this is a pre-release subscription and access will begin on
            <strong><?= $plan->release_date->format('F j, Y') ?></strong>
        </span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <div id="card-details" style="margin-top: 2rem;">
                        <div class="section-title">Card Details</div>
                        <div id="card-element" style="padding: 1rem; border: 1px solid var(--border-color);
         border-radius: 0.5rem; background: white;"></div>
                        <div id="card-errors" style="color: var(--danger-color); margin-top: 0.5rem;
         font-size: 0.875rem;"></div>
                    </div>
                </form>
            </div>
            <div>
                <div class="card">
                    <div class="plan-summary">
                        <div class="plan-name"><?= htmlspecialchars($plan->name) ?></div>
                        <?php if ($plan->description): ?>
                            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                                <?= htmlspecialchars($plan->description) ?>
                            </p>
                        <?php endif; ?>
                        <div class="plan-price">
                            <?= htmlspecialchars($plan->currency) ?>
                            <?= number_format($plan->price, 2) ?>
                        </div>
                        <div class="plan-period">
                            <?= htmlspecialchars($plan->getBillingPeriodLabel()) ?>
                        </div>
                    </div>

                    <?php if ($plan->trial_days > 0): ?>
                        <div style="background: #d1fae5; color: #065f46; padding: 1rem;
                                    border-radius: 0.5rem; margin-bottom: 1rem; text-align: center;">
                            🎉 <?= $plan->trial_days ?> Day Free Trial
                        </div>
                    <?php endif; ?>

                    <?php if ($plan->features && count($plan->features) > 0): ?>
                        <div class="section-title">What's Included</div>
                        <ul class="plan-features">
                            <?php foreach ($plan->features as $feature): ?>
                                <li><?= htmlspecialchars($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <button type="button" class="btn btn-primary" id="subscribe-btn">
                        Subscribe Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="loading-overlay" class="loading-overlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <p>Processing your subscription...</p>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    const API_BASE = '/api/' + SITE;
    const STRIPE_PUBLIC_KEY = '<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key') ?>';

    let stripe = null;
    let cardElement = null;
    let selectedPaymentMethod = 'stripe';

    // Initialize Stripe
    if (STRIPE_PUBLIC_KEY) {
        stripe = Stripe(STRIPE_PUBLIC_KEY);
        const elements = stripe.elements();

        cardElement = elements.create('card', {
            hidePostalCode: true,
            style: {
                base: {
                    fontSize: '16px',
                    color: '#1e293b',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    '::placeholder': {
                        color: '#94a3b8'
                    }
                },
                invalid: {
                    color: '#ef4444',
                    iconColor: '#ef4444'
                }
            }
        });

        cardElement.mount('#card-element');

        // Handle real-time validation errors
        cardElement.on('change', function (event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
    }

    // Payment method selection
    document.querySelectorAll('.payment-method').forEach(method => {
        method.addEventListener('click', function () {
            document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;

            selectedPaymentMethod = this.dataset.method;
            const cardDetails = document.getElementById('card-details');

            if (selectedPaymentMethod === 'stripe') {
                cardDetails.style.display = 'block';
            } else {
                cardDetails.style.display = 'none';
            }
        });
    });

    // Subscribe button
    document.getElementById('subscribe-btn').addEventListener('click', async function () {
        const form = document.getElementById('subscription-form');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        // Show loading
        document.getElementById('loading-overlay').classList.add('show');
        this.disabled = true;

        try {
            if (selectedPaymentMethod === 'stripe') {
                await processStripeSubscription(data);
            } else if (selectedPaymentMethod === 'paypal') {
                await processPayPalSubscription(data);
            }
        } catch (error) {
            console.error('Subscription error:', error);
            showAlert('An error occurred. Please try again.', 'error');
            document.getElementById('loading-overlay').classList.remove('show');
            this.disabled = false;
        }
    });

    async function processStripeSubscription(data) {
        try {
            // Step 1: Create Payment Method
            const {paymentMethod, error: pmError} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
            });

            if (pmError) {
                showAlert(pmError.message, 'error');
                document.getElementById('loading-overlay').classList.remove('show');
                document.getElementById('subscribe-btn').disabled = false;
                return;
            }

            // ADD THIS: Check pre-release acceptance
            const acceptPreRelease = document.getElementById('accept-pre-release');
            if (acceptPreRelease && !acceptPreRelease.checked) {
                showAlert('You must accept the pre-release terms to continue', 'error');
                document.getElementById('loading-overlay').classList.remove('show');
                document.getElementById('subscribe-btn').disabled = false;
                return;
            }

            // Step 2: Send to backend to create subscription
            const response = await fetch(`${API_BASE}/checkout/process`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    subscription_plan_id: data.subscription_plan_id,
                    payment_method: 'stripe',
                    payment_method_id: paymentMethod.id
                })
            });

            const result = await response.json();

            if (!result.success) {
                showAlert(result.message || 'Failed to create subscription', 'error');
                document.getElementById('loading-overlay').classList.remove('show');
                document.getElementById('subscribe-btn').disabled = false;
                return;
            }

            // Step 3: Handle 3D Secure if required
            if (result.requires_action && result.payment_intent_client_secret) {
                const {error: confirmError} = await stripe.confirmCardPayment(
                    result.payment_intent_client_secret
                );

                if (confirmError) {
                    showAlert(confirmError.message, 'error');
                    document.getElementById('loading-overlay').classList.remove('show');
                    document.getElementById('subscribe-btn').disabled = false;
                    return;
                }

                // Confirm with backend that payment succeeded
                await fetch(`${API_BASE}/checkout/confirm-payment`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        subscription_id: result.subscription_id
                    })
                });
            }

            // Success - redirect to subscriptions page
            window.location.href = '/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions?success=1';

        } catch (error) {
            console.error('Stripe error:', error);
            throw error;
        }
    }

    async function processPayPalSubscription(data) {
        try {
            const response = await fetch(`${API_BASE}/checkout/process`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success && result.redirect_url) {
                window.location.href = result.redirect_url;
            } else {
                showAlert(result.message || 'Failed to process subscription', 'error');
                document.getElementById('loading-overlay').classList.remove('show');
                document.getElementById('subscribe-btn').disabled = false;
            }
        } catch (error) {
            console.error('PayPal error:', error);
            throw error;
        }
    }

    function showAlert(message, type = 'success') {
        const alertHtml = `
            <div class="alert alert-${type}">
                ${message}
            </div>
        `;
        document.getElementById('alert-container').innerHTML = alertHtml;
        window.scrollTo({top: 0, behavior: 'smooth'});

        setTimeout(() => {
            document.getElementById('alert-container').innerHTML = '';
        }, 5000);
    }
</script>