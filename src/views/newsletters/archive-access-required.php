<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Access Required - <?= htmlspecialchars($site->name) ?></title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .access-card {
            background: white;
            border-radius: 16px;
            padding: 48px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .icon {
            font-size: 64px;
            margin-bottom: 24px;
        }

        h1 {
            font-size: 32px;
            color: #1a202c;
            margin-bottom: 16px;
        }

        .newsletter-title {
            color: #667eea;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .message {
            font-size: 16px;
            color: #718096;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #2c3e50;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .features {
            margin-top: 32px;
            padding-top: 32px;
            border-top: 2px solid #e2e8f0;
            text-align: left;
        }

        .features h3 {
            font-size: 18px;
            margin-bottom: 16px;
            color: #1a202c;
        }

        .feature-list {
            list-style: none;
        }

        .feature-list li {
            padding: 8px 0;
            color: #718096;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .feature-list li::before {
            content: "✓";
            color: #667eea;
            font-weight: bold;
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .access-card {
                padding: 32px 24px;
            }

            h1 {
                font-size: 24px;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        .single-purchase-section {
            margin: 40px 0;
            padding: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            color: white;
        }

        .purchase-card {
            background: white;
            color: #2c3e50;
            border-radius: 12px;
            padding: 32px;
            max-width: 500px;
            margin: 0 auto;
        }

        .purchase-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .purchase-header h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }

        .price {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
        }

        .purchase-benefits {
            list-style: none;
            padding: 0;
            margin: 24px 0;
        }

        .purchase-benefits li {
            padding: 8px 0;
            font-size: 16px;
        }

        .btn-purchase {
            width: 100%;
            padding: 16px 32px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-purchase:hover:not(:disabled) {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }

        .btn-purchase:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .secure-note {
            text-align: center;
            margin-top: 16px;
            font-size: 14px;
            color: #718096;
        }

        /* Payment Element Styles */
        #payment-element {
            margin: 24px 0;
        }

        .payment-section {
            display: none;
        }

        .payment-section.active {
            display: block;
        }

        .error-message {
            color: #e53e3e;
            font-size: 14px;
            margin-top: 12px;
            text-align: center;
        }

        .success-message {
            color: #38a169;
            font-size: 14px;
            margin-top: 12px;
            text-align: center;
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
<div class="access-card">
    <?php
    $icon = match ($access_type) {
        'anonymous' => '🔒',
        'lapsed_subscriber' => '⏰',
        'non_subscriber' => '⭐',
        default => '🔒'
    };
    ?>

    <div class="icon"><?= $icon ?></div>

    <h1>
        <?php
        echo match ($access_type) {
            'anonymous' => $is_logged_in ? 'Create an Account' : 'Sign In Required',
            'lapsed_subscriber' => 'Subscription Ended',
            'non_subscriber' => 'Subscribers Only',
            default => 'Access Required'
        };
        ?>
    </h1>

    <div class="newsletter-title"><?= htmlspecialchars($newsletter->title) ?> Archive</div>

    <p class="message"><?= htmlspecialchars($message) ?></p>

    <div class="actions">
        <?php if ($access_type === 'anonymous'): ?>
            <?php if ($is_logged_in): ?>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/account-setup"
                   class="btn btn-primary">
                    Complete Account Setup
                </a>
            <?php else: ?>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/login?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                   class="btn btn-primary">
                    Sign In
                </a>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/register" class="btn btn-secondary">
                    Create Account
                </a>
            <?php endif; ?>

        <?php elseif ($access_type === 'lapsed_subscriber'): ?>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions" class="btn btn-primary">
                Renew Subscription
            </a>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/dashboard" class="btn btn-secondary">
                My Account
            </a>

        <?php else: ?>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions" class="btn btn-primary">
                View Subscription Plans
            </a>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters" class="btn btn-secondary">
                Browse All Newsletters
            </a>
        <?php endif; ?>
    </div>

    <?php if ($access_type === 'non_subscriber'): ?>
        <div class="features">
            <h3>What you'll get with a subscription:</h3>
            <ul class="feature-list">
                <li>Full access to all newsletter archives</li>
                <li>Exclusive subscriber-only content</li>
                <li>Early access to new editions</li>
                <li>Ad-free reading experience</li>
                <li>Support independent journalism</li>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($single_access_available ?? false): ?>
        <!-- SINGLE PURCHASE OPTION -->
        <div class="single-purchase-section">
            <div class="purchase-card">
                <div id="purchase-view">
                    <div class="purchase-header">
                        <h3>🎟️ One-Time Access</h3>
                        <div class="price">$<?= number_format($newsletter->single_purchase_price ?? 2.99, 2) ?></div>
                    </div>

                    <ul class="purchase-benefits">
                        <li>✓ Access this newsletter's complete archive</li>
                        <li>✓ Valid for 90 days</li>
                        <li>✓ Download all editions as PDF</li>
                        <li>✓ No recurring charges</li>
                    </ul>

                    <button
                            class="btn-purchase"
                            id="purchase-button"
                            onclick="initiatePurchase()">
                        Purchase Access Now
                    </button>

                    <p class="secure-note">🔒 Secure payment via Stripe</p>
                </div>

                <!-- Payment Form (Hidden Initially) -->
                <div id="payment-view" class="payment-section">
                    <div class="purchase-header">
                        <h3>Complete Payment</h3>
                        <div class="price">$<?= number_format($newsletter->single_purchase_price ?? 2.99, 2) ?></div>
                    </div>

                    <form id="payment-form">
                        <div id="payment-element"></div>

                        <div id="payment-message" class="error-message"></div>

                        <button
                                type="submit"
                                id="submit-button"
                                class="btn-purchase"
                                style="margin-top: 24px;">
                            <span id="button-text">Complete Purchase</span>
                        </button>
                    </form>

                    <p class="secure-note">🔒 Your payment is secured by Stripe</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    const STRIPE_KEY = '<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key') ?>';

    let stripe = null;
    let elements = null;
    let clientSecret = null;

    // Initialize Stripe (lazy load)
    function initStripe() {
        if (!stripe) {
            if (!STRIPE_KEY) {
                console.error('Stripe key not configured');
                showError('Payment system not configured. Please contact support.');
                return false;
            }
            stripe = Stripe(STRIPE_KEY);
        }
        return true;
    }

    async function initiatePurchase() {
        const button = document.getElementById('purchase-button');
        button.disabled = true;
        button.innerHTML = '<span class="loading"></span> Processing...';

        try {
            // Initialize Stripe first
            if (!initStripe()) {
                button.disabled = false;
                button.textContent = 'Purchase Access Now';
                return;
            }

            const response = await fetch('/<?= \App\Framework\Support\SiteContext::slug()?>/member/single-access/purchase', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    content_id: <?= $newsletter->id ?>,
                    content_type: 'newsletter'
                })
            });

            const data = await response.json();

            if (data.success && data.client_secret) {
                // Store client secret and show payment form
                clientSecret = data.client_secret;
                showPaymentForm();
            } else {
                showError(data.message || 'Unable to process purchase');
                button.disabled = false;
                button.textContent = 'Purchase Access Now';
            }
        } catch (error) {
            console.error('Purchase error:', error);
            showError('An error occurred. Please try again.');
            button.disabled = false;
            button.textContent = 'Purchase Access Now';
        }
    }

    function showPaymentForm() {
        // Hide purchase view, show payment view
        document.getElementById('purchase-view').style.display = 'none';
        document.getElementById('payment-view').classList.add('active');

        // Initialize Stripe Elements
        const appearance = {
            theme: 'stripe',
            variables: {
                colorPrimary: '#667eea',
            }
        };

        elements = stripe.elements({
            clientSecret,
            appearance,
            fields: {
                billingDetails: {
                    address: {
                        postalCode: 'auto', // 'never' is NOT supported here
                    },
                },
            },
        });
        const paymentElement = elements.create('payment', {hidePostalCode: true});
        paymentElement.mount('#payment-element');

        // Handle form submission
        const form = document.getElementById('payment-form');
        form.addEventListener('submit', handlePaymentSubmit);
    }

    async function handlePaymentSubmit(event) {
        event.preventDefault();

        const submitButton = document.getElementById('submit-button');
        submitButton.disabled = true;
        document.getElementById('button-text').innerHTML = '<span class="loading"></span> Processing...';

        try {
            const {error, paymentIntent} = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: window.location.href, // Fallback, but we'll handle success manually
                },
                redirect: 'if_required'
            });

            if (error) {
                showError(error.message);
                submitButton.disabled = false;
                document.getElementById('button-text').textContent = 'Complete Purchase';
            } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                // Payment succeeded, complete the purchase
                await completePurchase(paymentIntent.id);
            }
        } catch (err) {
            console.error('Payment error:', err);
            showError('An unexpected error occurred.');
            submitButton.disabled = false;
            document.getElementById('button-text').textContent = 'Complete Purchase';
        }
    }

    async function completePurchase(paymentIntentId) {
        try {
            const response = await fetch('/<?= \App\Framework\Support\SiteContext::slug()?>/member/single-access/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_intent_id: paymentIntentId
                })
            });

            const data = await response.json();

            if (data.success) {
                // Show success message and redirect
                showSuccess('Payment successful! Redirecting...');
                setTimeout(() => {
                    window.location.href = '/<?= \App\Framework\Support\SiteContext::slug()?>/newsletters/<?= $newsletter->id ?>?token=' + data.access_token;
                }, 1500);
            } else {
                showError(data.message || 'Failed to complete purchase');
            }
        } catch (error) {
            console.error('Complete purchase error:', error);
            showError('Failed to complete purchase. Please contact support.');
        }
    }

    function showError(message) {
        const messageDiv = document.getElementById('payment-message');
        messageDiv.textContent = message;
        messageDiv.className = 'error-message';
    }

    function showSuccess(message) {
        const messageDiv = document.getElementById('payment-message');
        messageDiv.textContent = message;
        messageDiv.className = 'success-message';
    }
</script>
</body>
</html>