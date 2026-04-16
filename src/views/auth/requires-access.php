<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page->is_paid ? 'Purchase Article' : 'Subscription Required') ?></title>

    <?php if ($page->is_paid): ?>
        {{-- Stripe.js is loaded only when payment is actually required --}}
        <script src="https://js.stripe.com/v3/" defer></script>
    <?php endif; ?>

    <style>
        /* ── Reset & tokens ───────────────────────────────────── */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --danger: #ef4444;
            --success: #10b981;
            --border: #e2e8f0;
            --bg: #f1f5f9;
            --text: #1e293b;
            --text-muted: #64748b;
            --shadow-card: 0 20px 25px -5px rgba(0, 0, 0, .10), 0 8px 10px -6px rgba(0, 0, 0, .06);
        }

        body {
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            background-color: var(--bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--text);
        }

        /* ── Sneak-peek header ────────────────────────────────── */
        .content-header {
            width: 100%;
            max-width: 800px;
            padding: 4rem 2rem 2rem;
            text-align: center;
            mask-image: linear-gradient(to bottom, black 50%, transparent 100%);
            -webkit-mask-image: linear-gradient(to bottom, black 50%, transparent 100%);
        }

        .content-header h2 {
            font-size: 2.5rem;
            margin: 0 0 1rem;
            color: #0f172a;
        }

        .content-header p {
            font-size: 1.25rem;
            color: var(--text-muted);
            margin: 0;
        }

        /* ── Lock card ────────────────────────────────────────── */
        .lock-card {
            text-align: center;
            padding: 2.5rem;
            max-width: 420px;
            width: 90%;
            background: #fff;
            border-radius: 16px;
            box-shadow: var(--shadow-card);
            margin-top: -2rem;
            position: relative;
            z-index: 10;
            border: 1px solid var(--border);
        }
        .lock-icon {
            background: #fef2f2;
            color: var(--danger);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
        }

        .lock-card h1 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #0f172a;
        }

        .message {
            font-size: 1rem;
            color: #475569;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* ── Price badge ──────────────────────────────────────── */
        .price-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: var(--primary);
            border-radius: 2rem;
            padding: .4rem 1.1rem;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        /* ── Buttons ──────────────────────────────────────────── */
        .button-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn {
            display: block;
            padding: .8rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all .2s ease;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            width: 100%;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: #f8fafc;
            color: #0f172a;
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #f1f5f9;
        }

        .btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        /* ── Payment modal overlay ────────────────────────────── */
        #payment-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0, 0, 0, .55);
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        #payment-modal-overlay.open {
            display: flex;
        }

        .payment-modal {
            background: white;
            border-radius: 16px;
            max-width: 480px;
            width: 100%;
            box-shadow: var(--shadow-card);
            overflow: hidden;
            animation: modal-in .25s ease;
        }

        @keyframes modal-in {
            from {
                opacity: 0;
                transform: translateY(20px) scale(.97);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .payment-modal-header {
            padding: 1.5rem 1.75rem 1rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .payment-modal-header h2 {
            font-size: 1.25rem;
            color: #0f172a;
            margin-bottom: .25rem;
        }

        .payment-modal-header p {
            font-size: .875rem;
            color: var(--text-muted);
        }

        .modal-close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            line-height: 1;
            padding: 0 .25rem;
            transition: color .15s;
        }

        .modal-close-btn:hover {
            color: var(--text);
        }

        .payment-modal-body {
            padding: 1.5rem 1.75rem;
        }

        /* Email field (guests only) */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: .875rem;
            font-weight: 600;
            margin-bottom: .4rem;
            color: var(--text);
        }

        .form-input {
            width: 100%;
            padding: .7rem .875rem;
            border: 1px solid var(--border);
            border-radius: .5rem;
            font-size: .9375rem;
            color: var(--text);
            transition: border-color .15s, box-shadow .15s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
        }

        /* Stripe card element container */
        #card-element {
            padding: .75rem;
            border: 1px solid var(--border);
            border-radius: .5rem;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }

        #card-element.StripeElement--focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
        }

        #card-element.StripeElement--invalid {
            border-color: var(--danger);
        }

        #card-errors:not(:empty) {
            margin-top: .6rem;
            padding: .7rem 1rem;
            background: #fee2e2;
            border: 1px solid #ef4444;
            border-radius: .375rem;
            color: #991b1b;
            font-size: .875rem;
        }

        /* Order summary row inside modal */
        .order-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .75rem 0;
            border-bottom: 1px solid var(--border);
            font-size: .9375rem;
        }

        .order-summary-row:last-child {
            border-bottom: none;
        }

        .order-summary-row .amount {
            font-weight: 700;
            color: #0f172a;
        }

        /* Security note */
        .security-note {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .8125rem;
            color: var(--text-muted);
            margin-top: 1rem;
            justify-content: center;
        }

        /* Success state */
        .payment-success {
            text-align: center;
            padding: 2rem 1.75rem;
        }

        .payment-success .success-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #d1fae5;
            color: var(--success);
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .payment-success h3 {
            font-size: 1.25rem;
            margin-bottom: .5rem;
            color: #0f172a;
        }

        .payment-success p {
            color: var(--text-muted);
            font-size: .9375rem;
        }
    </style>
</head>
<body>

<!-- Sneak peek of the article -->
<header class="content-header">
    <h2><?= htmlspecialchars($page->title) ?></h2>
    <p><?= htmlspecialchars($page->subtitle ?? '') ?></p>
</header>

<!-- Lock card -->
<div class="lock-card">
    <div class="lock-icon">🔒</div>

    <?php if ($page->is_paid): ?>
        {{-- ── PAID ARTICLE BRANCH ───────────────────────────── --}}
        <h1>One-time purchase</h1>
        <div class="price-badge">
            <span>£</span><?= number_format($page->price / 100, 2) ?>
        </div>
        <p class="message">
            Purchase instant, permanent access to this article. No subscription required.
        </p>
        <div class="button-group">
            <button class="btn btn-primary" onclick="openPaymentModal()">
                Buy Article &mdash; £<?= number_format($page->price / 100, 2) ?>
            </button>
            <a href="/member/login" class="btn btn-secondary">Log in to existing account</a>
        </div>
    <?php else: ?>
        {{-- ── SUBSCRIPTION REQUIRED BRANCH ────────────────────── --}}
        <h1>Ready to keep reading?</h1>
        <p class="message">
            To view this page, please <strong>log in</strong> to your account or
            <strong>subscribe</strong> to one of our plans for full access.
        </p>
        <div class="button-group">
            <a class="btn btn-primary" onclick="showSubscriptionModal()">View Subscription Plans</a>
            <a href="/member/login" class="btn btn-secondary">Log In to Account</a>
        </div>
    <?php endif; ?>
</div>

<?php if (!$page->is_paid && isset($subscriptionModalData)): ?>
    @include('components/subscription-modal', ['subscriptionModalData' => $subscriptionModalData])
<?php endif; ?>

<?php if ($page->is_paid): ?>

    <?php
    // Pre-fill email for authenticated users — avoids JS manipulation of who
    // the purchase email is attributed to. Auth::user() may be null for guests.
    $authedEmail = \App\Framework\Authorization\Auth::user()?->email ?? '';
    $isAuthed = !empty($authedEmail);
    $siteSlug = \App\Framework\Support\SiteContext::slug();
    $pageId = (int)$page->id;
    $priceFormatted = '£' . number_format($page->price / 100, 2);
    ?>

    <div id="payment-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="payment-modal-title">
        <div class="payment-modal">

            <!-- Header -->
            <div class="payment-modal-header">
                <div>
                    <h2 id="payment-modal-title">Complete Purchase</h2>
                    <p><?= htmlspecialchars($page->title) ?></p>
                </div>
                <button class="modal-close-btn" onclick="closePaymentModal()" aria-label="Close">&times;</button>
            </div>

            <!-- Body — shown during payment form step -->
            <div class="payment-modal-body" id="payment-form-body">

                <!-- Order summary -->
                <div style="margin-bottom:1.25rem;">
                    <div class="order-summary-row">
                        <span><?= htmlspecialchars($page->title) ?></span>
                        <span class="amount"><?= $priceFormatted ?></span>
                    </div>
                    <div class="order-summary-row" style="font-weight:700;">
                        <span>Total</span>
                        <span class="amount"><?= $priceFormatted ?></span>
                    </div>
                </div>

                <!-- Email — hidden/pre-filled for logged-in users -->
                <?php if (!$isAuthed): ?>
                    <div class="form-group">
                        <label class="form-label" for="payment-email">Email address</label>
                        <input type="email" id="payment-email" class="form-input"
                               placeholder="you@example.com" autocomplete="email" required>
                        <span id="error-payment-email"
                              style="font-size:.8125rem;color:var(--danger);margin-top:.3rem;display:block;"></span>
                    </div>
                <?php else: ?>
                    {{-- For authenticated users, bind the purchase to their account email server-side --}}
                    <input type="hidden" id="payment-email" value="<?= htmlspecialchars($authedEmail) ?>">
                <?php endif; ?>

                <!-- Stripe card element -->
                <div class="form-group">
                    <label class="form-label">Card details</label>
                    <div id="card-element"></div>
                    <div id="card-errors" role="alert" aria-live="polite"></div>
                </div>

                <!-- Pay button -->
                <button id="pay-btn" class="btn btn-primary" onclick="handlePayment()">
                    Pay <?= $priceFormatted ?>
                </button>

                <p class="security-note">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Secured by Stripe &mdash; we never see your card number
                </p>
            </div>

            <!-- Success state — shown after payment confirmed -->
            <div class="payment-success" id="payment-success-body" style="display:none;">
                <div class="success-icon">✓</div>
                <h3>Payment successful!</h3>
                <p>You now have permanent access to this article. Redirecting you now…</p>
            </div>

        </div>
    </div>

    <script>
        (function () {
            'use strict';

            /* ── Config (server-rendered, safe to expose) ───────────── */
            const PAGE_ID = <?= $pageId ?>;
            const SITE_SLUG = '<?= htmlspecialchars($siteSlug) ?>';
            const API_URL = `/${SITE_SLUG}/api/open-collab/pages/${PAGE_ID}/purchase`;

            /* ── Stripe elements ─────────────────────────────────────── */
            let stripe, cardElement, pendingPaymentId;

            function initStripe() {
                // Stripe publishable key — safe to expose on the frontend
                stripe = Stripe('<?= htmlspecialchars(config('services.stripe.key')) ?>');
                const elements = stripe.elements();
                cardElement = elements.create('card', {
                    style: {
                        base: {
                            fontSize: '15px',
                            color: '#1e293b',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                            '::placeholder': {color: '#94a3b8'},
                        },
                        invalid: {color: '#ef4444'},
                    },
                    hidePostalCode: false,
                });
                cardElement.mount('#card-element');
                cardElement.on('change', function (event) {
                    const errEl = document.getElementById('card-errors');
                    errEl.textContent = event.error ? event.error.message : '';
                });
            }

            /* ── Modal open / close ──────────────────────────────────── */
            window.openPaymentModal = function () {
                const overlay = document.getElementById('payment-modal-overlay');
                overlay.classList.add('open');
                document.body.style.overflow = 'hidden';

                // Lazy-init Stripe only when the modal actually opens
                if (!stripe) initStripe();

                // Reset state in case modal was previously used
                resetModal();
            };

            window.closePaymentModal = function () {
                // Do not close while a payment is in-flight
                const payBtn = document.getElementById('pay-btn');
                if (payBtn && payBtn.disabled) return;

                document.getElementById('payment-modal-overlay').classList.remove('open');
                document.body.style.overflow = '';
            };

            // Close on overlay click (not on modal itself)
            document.getElementById('payment-modal-overlay').addEventListener('click', function (e) {
                if (e.target === this) window.closePaymentModal();
            });

            // Close on Escape
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') window.closePaymentModal();
            });

            /* ── Payment flow ────────────────────────────────────────── */
            window.handlePayment = async function () {
                const emailInput = document.getElementById('payment-email');
                const email = emailInput ? emailInput.value.trim() : '';
                const errEl = document.getElementById('card-errors');
                const emailErrEl = document.getElementById('error-payment-email');
                const payBtn = document.getElementById('pay-btn');

                // Clear previous errors
                errEl.textContent = '';
                if (emailErrEl) emailErrEl.textContent = '';

                // Client-side email guard (only needed for guests; authed email is hidden input)
                <?php if (!$isAuthed): ?>
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    if (emailErrEl) emailErrEl.textContent = 'Please enter a valid email address.';
                    return;
                }
                <?php endif; ?>

                payBtn.disabled = true;
                payBtn.textContent = 'Processing…';

                try {
                    /* Step 1 — create PaymentIntent on the server */
                    const initRes = await fetch(API_URL, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                        body: JSON.stringify({email}),
                    });

                    const initData = await initRes.json();

                    if (!initRes.ok) {
                        // 409 = duplicate purchase
                        if (initRes.status === 409) {
                            errEl.textContent = initData.message || 'You already have access to this article.';
                        } else {
                            errEl.textContent = initData.message || 'Could not initiate payment. Please try again.';
                        }
                        resetPayButton();
                        return;
                    }

                    pendingPaymentId = initData.payment_id;
                    const clientSecret = initData.client_secret;

                    /* Step 2 — confirm payment with Stripe.js (card data goes directly to Stripe) */
                    const {error, paymentIntent} = await stripe.confirmCardPayment(clientSecret, {
                        payment_method: {
                            card: cardElement,
                            billing_details: {email},
                        },
                    });

                    if (error) {
                        errEl.textContent = error.message;
                        resetPayButton();
                        return;
                    }

                    if (paymentIntent.status === 'succeeded') {
                        showSuccess();
                    } else {
                        // Unlikely but handle incomplete intents gracefully
                        errEl.textContent = 'Payment could not be completed. Please try again.';
                        resetPayButton();
                    }

                } catch (err) {
                    console.error('Payment error:', err);
                    errEl.textContent = 'An unexpected error occurred. Please try again.';
                    resetPayButton();
                }
            };

            /* ── Helpers ─────────────────────────────────────────────── */

            function resetPayButton() {
                const payBtn = document.getElementById('pay-btn');
                payBtn.disabled = false;
                payBtn.textContent = 'Pay <?= $priceFormatted ?>';
            }

            function resetModal() {
                document.getElementById('payment-form-body').style.display = 'block';
                document.getElementById('payment-success-body').style.display = 'none';
                document.getElementById('card-errors').textContent = '';
                const emailErr = document.getElementById('error-payment-email');
                if (emailErr) emailErr.textContent = '';
                resetPayButton();
                // Unmount and remount card element to clear any previous input
                if (cardElement) {
                    cardElement.clear();
                }
                pendingPaymentId = null;
            }

            function showSuccess() {
                document.getElementById('payment-form-body').style.display = 'none';
                document.getElementById('payment-success-body').style.display = 'block';
                // Redirect after a short delay so the user sees the confirmation.
                // Access is granted via webhook; the page will now be accessible.
                setTimeout(() => {
                    window.location.reload();
                }, 2500);
            }

        })();
    </script>
<?php endif; ?>

</body>
</html>