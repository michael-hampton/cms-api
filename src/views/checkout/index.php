@section('logic')
<?php
/**
 * Checkout page
 *
 * Expected view data:
 *   array[]      $items            — flat cart items
 *   float        $subtotal
 *   float        $shipping
 *   float        $tax
 *   float        $tax_rate         — decimal
 *   string       $currency         — display symbol/code
 *   int          $count            — cart item count (badge)
 *   object|null  $member           — logged-in member or null
 *   bool         $requiresShipping — show shipping address fields
 *   array[]      $hasPreOrders     — pre-order warning entries
 *   string       $checkoutMode     — 'steps' | 'single-page'
 *
 * Session:
 *   $_SESSION['applied_voucher_code'] — ['code','discount','voucher_id'] or null
 */

use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;

$checkoutMode = $checkoutMode ?? 'steps';
$appliedVoucher = $_SESSION['applied_voucher_code'] ?? null;
$displayCurrency = strtoupper($currency ?? 'GBP');

$finalTotal = (float)($subtotal ?? 0)
        + (float)($tax ?? 0)
        + (float)($shipping ?? 0)
        - (float)($appliedVoucher['discount'] ?? 0);

$cartSubscriptionItems = array_filter($items ?? [], fn($i) => !empty($i['subscription_plan_id']));
$cartProductItems = array_filter($items ?? [], fn($i) => empty($i['subscription_plan_id']));
$isMixedCart = !empty($cartSubscriptionItems) && !empty($cartProductItems);
$isSubscription = !empty($cartSubscriptionItems);

$site = SiteContext::slug();
$apiBase = '/api/' . $site;
?>
@endsection


@extends('checkout/layout/shop')

@section('title', 'Checkout - YourStore')

@section('styles')
@css('checkout-auth.css')
<style>
    /* ── Checkout layout ──────────────────────────────────────────── */
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

    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        font-size: 1rem;
        transition: all 0.3s;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
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
        display: block;
        margin-top: 0.25rem;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        display: block;
        margin-bottom: 0.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    /* ── Progress stepper ─────────────────────────────────────────── */
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
        transition: width 0.4s ease;
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

    .step.completed {
        cursor: pointer;
    }

    .step.completed:hover .step-circle {
        opacity: 0.8;
        transform: scale(1.08);
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

    .step.completed .step-label {
        color: var(--success-color);
    }

    /* ── Order summary overrides ──────────────────────────────────── */
    .order-summary {
        position: sticky;
        top: 100px;
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

    .login-prompt {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
        font-size: 0.875rem;
        color: var(--primary-color);
    }

    .login-prompt a {
        font-weight: 600;
        color: var(--primary-color);
    }

    @media (max-width: 968px) {
        .checkout-layout {
            grid-template-columns: 1fr;
        }

        .order-summary {
            position: static;
        }

        .form-row {
            grid-template-columns: 1fr !important;
        }

        .progress-steps {
            flex-direction: column;
            gap: 1rem;
        }

        .progress-steps::before, .progress-line {
            display: none;
        }
    }
</style>
@endsection

@section('page-header')
@include('checkout/components/page-header', [
'title'       => 'Checkout',
'breadcrumbs' => [
['label' => 'Home',  'href' => '/'],
['label' => 'Cart',  'href' => '/cart'],
['label' => 'Checkout'],
],
])
@endsection

@section('content')
<!-- ── Progress stepper (steps mode only) ───────────────────────── -->
<?php if ($checkoutMode === 'steps'): ?>
    <div class="checkout-progress">
        <div class="progress-steps">
            <div class="progress-line" id="progress-line" style="width: 33%;"></div>
            <div class="step completed" id="step-1-indicator" onclick="window.location.href='/cart'"
                 title="Return to cart">
                <div class="step-circle">✓</div>
                <div class="step-label">Cart</div>
            </div>
            <div class="step active" id="step-2-indicator">
                <div class="step-circle">2</div>
                <div class="step-label">Shipping</div>
            </div>
            <div class="step" id="step-3-indicator">
                <div class="step-circle">3</div>
                <div class="step-label">Payment</div>
            </div>
            <div class="step" id="step-4-indicator">
                <div class="step-circle">4</div>
                <div class="step-label">Confirmation</div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="alert-container"></div>

<!-- ── Mixed-cart warning ───────────────────────────────────────── -->
<?php if ($isMixedCart): ?>
    <div class="alert alert-error" style="margin-bottom: 1.5rem;">
        <strong>Your cart contains both subscription and physical items.</strong>
        These cannot be purchased together. Please
        <a href="/cart" style="color:inherit;font-weight:600;text-decoration:underline;">return to your cart</a>
        and complete them as separate orders.
    </div>
<?php endif; ?>

<!-- ── Guest login prompt ───────────────────────────────────────── -->
<?php if (!MemberAuth::check()): ?>
    <div class="login-prompt">
        Already have an account?
        <a href="/member/login?redirect=/checkout">Login</a> to use saved addresses.
    </div>
<?php endif; ?>

<div class="checkout-layout">
    <!-- ── Left: form ─────────────────────────────────────────── -->
    <div class="checkout-form">
        <form id="checkout-form">

            <!-- ════ STEP 2: Contact + Shipping ════ -->
            <?php if ($checkoutMode === 'steps'): ?>
            <div id="step-2-section"><?php endif; ?>

                @include('checkout/components/form/billing-form', [
                'member' => $member ?? null,
                'requiresShipping' => $requiresShipping ?? true,
                'showSavedAddresses' => true,
                ])

                <?php if ($checkoutMode === 'steps'): ?>
                    @include('checkout/components/form/button', [
                    'id'      => 'continue-to-payment-btn',
                    'type' => 'button',
                    'label'   => 'Continue to Payment',
                    'variant' => 'primary',
                    'onclick' => 'advanceToPayment()',
                    'style'   => 'margin-top: 2rem;',
                    ])
                <?php endif; ?>

                <?php if ($checkoutMode === 'steps'): ?></div><?php endif; ?>

            <!-- ════ STEP 3: Payment ════ -->
            <?php if ($checkoutMode === 'steps'): ?>
            <div id="step-3-section" style="display: none;"><?php endif; ?>

                @include('checkout/components/payment-method-selector')

                @include('checkout/components/saved-cards')

                <div id="payment-request-button"></div>

                @include('checkout/components/stripe-card-element', [
                'showBackButton' => true,
                ])

                <!-- Order notes -->
                <div class="form-section">
                    <h2 class="section-title">Order Notes (Optional)</h2>
                    <div class="form-group full-width">
                        <label class="form-label">Special instructions for delivery</label>
                        <textarea name="notes" class="form-textarea"
                                  placeholder="Add any special instructions..."></textarea>
                    </div>
                </div>

                <?php if ($checkoutMode === 'steps'): ?>
                    @include('checkout/components/form/button', [
                    'label'   => '← Back to Shipping',
                    'variant' => 'secondary',
                    'onclick' => 'goToStep(2)',
                    'style'   => 'margin-top: 1rem;',
                    ])
                <?php endif; ?>

                <?php if ($checkoutMode === 'steps'): ?></div><?php endif; ?>
        </form>
    </div>

    <!-- ── Right: order summary sidebar ──────────────────────── -->
    @include('checkout/components/order-summary-sidebar', [
    'items' => $items ?? [],
    'subtotal' => $subtotal ?? 0,
    'shipping' => $shipping ?? 0,
    'tax' => $tax ?? 0,
    'finalTotal' => $finalTotal,
    'taxRate' => $tax_rate ?? 0,
    'currency' => $displayCurrency,
    'displayCurrency' => $displayCurrency,
    'apiBase' => $apiBase,
    'appliedVoucher' => $appliedVoucher,
    'hasPreOrders' => $hasPreOrders ?? [],
    'isSubscription' => $isSubscription,
    'submitBtnId' => 'place-order-btn',
    'submitBtnLabel' => 'Place Order',
    'backUrl' => '/cart',
    ])
</div>
@endsection

@section('scripts')
@include('checkout/components/loading-overlay', ['message' => 'Processing your order...', 'id' => 'loading-overlay'])

<script>
    const API_BASE = <?= json_encode($apiBase ?? '') ?>;
    const PLAN_CURRENCY = <?= json_encode($currency ?? '$') ?>;
    const TAX_RATE = <?= json_encode((float)($tax_rate ?? 0)) ?>;
    const INITIAL_SUBTOTAL = <?= json_encode((float)($subtotal ?? 0)) ?>;
    const INITIAL_SHIPPING = <?= json_encode((float)($shipping ?? 0)) ?>;
</script>

@js('cart-utils.js')
@js('saved-cards.js')

@include('checkout/components/scripts/payment-method-selector.js.php')
@js('checkout-auth.js')
<script src="https://js.stripe.com/v3/"></script>

<script>
    const SITE = <?= json_encode($site) ?>;
    const STRIPE_KEY = <?= json_encode($_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key')) ?>;
    const CHECKOUT_MODE = <?= json_encode($checkoutMode) ?>;
    const requiresShipping = <?= json_encode($requiresShipping ?? true) ?>;
    const isMixedCart = <?= json_encode($isMixedCart) ?>;

    let isLoggedIn = false;
    let currentMember = null;
    let selectedAddressId = null;
    let appliedVoucher = <?= json_encode($appliedVoucher) ?>;
    let stripe = null, elements = null, cardElement = null;
    let clientSecret = null, paymentIntentId = null;
    let subscriptionId = null, orderId = null;
    let isOneTimeSubscription = false;

    // ── Country / US consent ─────────────────────────────────────────────
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

    function checkCartForSubscription() {
        isOneTimeSubscription = new URLSearchParams(window.location.search).get('type') === 'subscription';
        if (!isOneTimeSubscription) {
            const block = document.getElementById('global-renewal-consent-block');
            if (block) block.style.display = 'none';
        }
    }

    // ── Shipping validation ───────────────────────────────────────────────
    function validateShippingFields() {
        document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
        if (!requiresShipping) return true;
        const data = Object.fromEntries(new FormData(document.getElementById('checkout-form')));
        const required = selectedAddressId
            ? ['first_name', 'last_name', 'email']
            : ['first_name', 'last_name', 'email', 'address', 'city', 'postal_code', 'country'];
        let hasErrors = false;
        for (const field of required) {
            if (!data[field]?.trim()) {
                const el = document.getElementById(`error-${field}`);
                if (el) el.textContent = 'This field is required';
                hasErrors = true;
            }
        }
        return !hasErrors;
    }

    <?php if ($checkoutMode === 'steps'): ?>
    // ── Step navigation ───────────────────────────────────────────────────
    let currentStep = 2;
    const STEP_LINE_WIDTH = {2: '33%', 3: '66%'};

    function goToStep(step) {
        if (step === currentStep) return;
        document.getElementById('step-2-section').style.display = step === 2 ? 'block' : 'none';
        document.getElementById('step-3-section').style.display = step === 3 ? 'block' : 'none';
        document.getElementById('progress-line').style.width = STEP_LINE_WIDTH[step] ?? '33%';

        const indicators = {
            2: document.getElementById('step-2-indicator'),
            3: document.getElementById('step-3-indicator'),
            4: document.getElementById('step-4-indicator'),
        };
        Object.values(indicators).forEach(el => {
            el.classList.remove('active', 'completed');
            el.style.cursor = '';
            el.onclick = null;
        });
        if (step === 2) {
            indicators[2].classList.add('active');
        } else if (step === 3) {
            indicators[2].classList.add('completed');
            indicators[2].style.cursor = 'pointer';
            indicators[2].onclick = () => goToStep(2);
            indicators[3].classList.add('active');
        }
        currentStep = step;
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function advanceToPayment() {
        alert('here5')
        if (isMixedCart) {
            showAlert('Your cart contains both subscription and physical items. Please return to your cart.', 'error');
            return;
        }
        document.getElementById('alert-container').innerHTML = '';
        if (!validateShippingFields()) {
            showAlert('Please fill in all required fields before continuing.', 'error');
            return;
        }
        goToStep(3);
    }
    <?php endif; ?>

    // ── Stripe init ───────────────────────────────────────────────────────
    async function initStripe() {
        if (!STRIPE_KEY) return;
        stripe = Stripe(STRIPE_KEY);
        elements = stripe.elements();

        cardElement = elements.create('card', {
            hidePostalCode: true,
            style: {
                base: {fontSize: '16px', color: '#1e293b', '::placeholder': {color: '#64748b'}},
            },
        });

        // Apple Pay / Wallet
        const paymentRequest = stripe.paymentRequest({
            country: 'GB', currency: PLAN_CURRENCY.toLowerCase(),
            total: {label: 'Order Total', amount: getCurrentOrderAmount()},
            requestPayerName: true, requestPayerEmail: true,
        });
        const prButton = elements.create('paymentRequestButton', {paymentRequest});
        paymentRequest.canMakePayment().then(r => {
            if (r) prButton.mount('#payment-request-button');
        });
        paymentRequest.on('paymentmethod', async (ev) => {
            try {
                const {error, paymentIntent} = await stripe.confirmCardPayment(
                    clientSecret, {payment_method: ev.paymentMethod.id}, {handleActions: false}
                );
                if (error) {
                    ev.complete('fail');
                    showAlert(error.message, 'error');
                    return;
                }
                ev.complete('success');
                if (paymentIntent.status === 'requires_action') await stripe.confirmCardPayment(clientSecret);
                if (paymentIntent.status === 'succeeded') await confirmPayment(paymentIntent.id);
            } catch (err) {
                ev.complete('fail');
                showAlert('Payment failed', 'error');
            }
        });

        const cardContainer = document.getElementById('card-element');
        if (cardContainer) {
            cardElement.mount('#card-element');
            cardElement.on('change', e => {
                document.getElementById('card-errors').textContent = e.error ? e.error.message : '';
            });
        }

        const paymentSection = document.getElementById('payment-form-section');
        if (paymentSection) paymentSection.style.display = 'none';
    }

    function getCurrentOrderAmount() {
        const discount = appliedVoucher ? appliedVoucher.discount : 0;
        const taxRate = <?= $tax_rate ?? 0 ?>;
        const taxable = <?= (float)($subtotal ?? 0) ?> - discount + <?= (float)($shipping ?? 0) ?>;
        return Math.round((taxable + taxable * taxRate) * 100);
    }

    // ── Login status / saved addresses ───────────────────────────────────
    async function checkLoginStatus() {
        try {
            const res = await fetch('/member/me');
            if (res.ok) {
                const data = await res.json();
                if (data.member) {
                    window.isLoggedIn = true;
                    window.currentMember = data.member;
                    if (requiresShipping) loadSavedAddresses();
                    loadSavedCards();
                }
            }
        } catch (e) { /* guest */
        }
    }

    async function loadSavedAddresses() {
        try {
            const res = await fetch(`/${SITE}/member/${window.currentMember.id}/addresses?type=shipping`);
            const data = await res.json();
            if (data.items?.length) displaySavedAddresses(data.items);
        } catch (e) {
            console.error(e);
        }
    }

    function displaySavedAddresses(addresses) {
        const container = document.getElementById('saved-addresses-list');
        const section = document.getElementById('saved-addresses-section');
        if (!container || !section) return;
        container.innerHTML = addresses.map(addr => `
        <label class="saved-address-card" for="addr-${addr.id}">
            <input type="radio" name="saved_address" value="${addr.id}" id="addr-${addr.id}"
                   onchange="selectAddress(${addr.id})">
            <div class="address-details">
                <strong>${addr.label || 'Address'}</strong>
                <p>${addr.formatted}</p>
            </div>
            ${addr.is_default ? '<span class="badge" style="position:static;background:var(--primary-color);">Default</span>' : ''}
        </label>`).join('');
        section.style.display = 'block';
        const shippingForm = document.getElementById('shipping-address-form');
        if (shippingForm) shippingForm.style.display = 'none';
    }

    function selectAddress(id) {
        selectedAddressId = id;
        const form = document.getElementById('shipping-address-form');
        if (form) form.style.display = 'none';
    }
    function showNewAddressForm() {
        selectedAddressId = null;
        document.getElementById('saved-addresses-section').style.display = 'none';
        document.getElementById('shipping-address-form').style.display = 'block';
        const btn = document.getElementById('back-to-saved-btn');
        if (btn) btn.style.display = 'block';
        document.querySelectorAll('[name="saved_address"]').forEach(r => r.checked = false);
    }
    function showSavedAddresses() {
        selectedAddressId = null;
        document.getElementById('saved-addresses-section').style.display = 'block';
        document.getElementById('shipping-address-form').style.display = 'none';
        const btn = document.getElementById('back-to-saved-btn');
        if (btn) btn.style.display = 'none';
        document.querySelectorAll('[name="saved_address"]').forEach(r => r.checked = false);
    }

    // ── Processing state ──────────────────────────────────────────────────
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

    // ── Place order ───────────────────────────────────────────────────────
    document.getElementById('place-order-btn').addEventListener('click', async function () {
        if (isMixedCart) {
            showAlert('Your cart contains both subscription and physical items. Please return to your cart.', 'error');
            return;
        }

        const formData = new FormData(document.getElementById('checkout-form'));
        const data = Object.fromEntries(formData);

        document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
        document.getElementById('alert-container').innerHTML = '';
        document.getElementById('card-errors').textContent = '';

        // Pre-order consent
        const acceptPreOrder = document.getElementById('accept-pre-order');
        if (acceptPreOrder && !acceptPreOrder.checked) {
            showAlert('You must accept the pre-order terms to continue.', 'error');
            return;
        }

        // Global renewal consent
        const globalConsentBlock = document.getElementById('global-renewal-consent-block');
        const globalConsentCb = document.getElementById('global-renewal-consent');
        if (globalConsentCb && !globalConsentCb.checked && isOneTimeSubscription) {
            globalConsentBlock?.classList.add('consent-error');
            showAlert('Please confirm the subscription terms before placing your order.', 'error');
            globalConsentBlock?.scrollIntoView({behavior: 'smooth', block: 'center'});
            return;
        }
        globalConsentBlock?.classList.remove('consent-error');
        data.global_renewal_consent = '1';

        // US consent
        const usConsentBlock = document.getElementById('us-renewal-consent-block');
        if (usConsentBlock?.style.display !== 'none' && isOneTimeSubscription) {
            const usConsentCb = document.getElementById('us-renewal-consent');
            if (!usConsentCb?.checked) {
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
            ['address', 'address2', 'city', 'state', 'postal_code', 'country'].forEach(k => delete data[k]);
        }

        <?php if ($checkoutMode !== 'steps'): ?>
        if (requiresShipping && !validateShippingFields()) {
            showAlert('Please fill in all required fields.', 'error');
            return;
        }
        <?php endif; ?>

        if (appliedVoucher) {
            data.voucher_code = appliedVoucher.code;
            data.voucher_id = appliedVoucher.voucher_id;
            data.discount_amount = appliedVoucher.discount;
        }
        data.multi_merchant = true;

        setProcessingState(true);
        try {
            isOneTimeSubscription
                ? await handleStripeCheckout(data)
                : await handleRegularCheckout(data);
        } finally {
            setProcessingState(false);
        }
    });

    // ── Stripe checkout ───────────────────────────────────────────────────
    async function handleStripeCheckout(data) {
        const res = await fetch(`${API_BASE}/subscriptions/onetime/checkout`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({...data, isOneTimeSubscription: true}),
        });
        const result = await res.json();
        if (!result.success) {
            showAlert(result.message || 'Checkout failed', 'error');
            return;
        }

        const contexts = result.data.stripe_contexts;
        clientSecret = contexts ? contexts[Object.keys(contexts)[0]].client_secret : result.data.client_secret;
        subscriptionId = result.data.subscription_ids || result.data.subscription_id;
        orderId = result.data.order_id;

        const paymentResult = window.selectedCardId
            ? await stripe.confirmCardPayment(clientSecret, {payment_method: window.selectedCardId})
            : await stripe.confirmCardPayment(clientSecret, {
                payment_method: {card: cardElement,
                    billing_details: {
                        name: `${data.first_name} ${data.last_name}`,
                        email: data.email,
                        phone: data.phone
                    }
                },
                setup_future_usage: 'off_session',
            });

        const {error, paymentIntent} = paymentResult;
        if (error) {
            document.getElementById('card-errors').textContent = error.message;
            showAlert(error.message, 'error');
            return;
        }
        if (paymentIntent.status === 'succeeded') await confirmPayment(paymentIntent.id);
    }

    async function confirmPayment(intentId) {
        const body = {payment_intent_id: intentId, order_id: orderId};
        Array.isArray(subscriptionId) ? (body.subscription_ids = subscriptionId) : (body.subscription_id = subscriptionId);
        const res = await fetch(`${API_BASE}/subscriptions/onetime/confirm-payment`, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body),
        });
        const result = await res.json();
        if (result.success) {
            const ids = Array.isArray(subscriptionId) ? subscriptionId : [subscriptionId];
            window.location.href = `/subscription-confirmation?ids=${ids.join(',')}`;
        } else {
            showAlert(result.message || 'Payment confirmation failed', 'error');
        }
    }

    async function handleRegularCheckout(data) {
        const res = await fetch(`${API_BASE}/checkout/process`, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data),
        });
        const result = await res.json();
        if (!result.success) {
            showAlert(result.message || 'Failed to process order', 'error');
            return;
        }

        const contexts = result.stripe_contexts;
        clientSecret = contexts ? contexts[Object.keys(contexts)[0]].client_secret : result.client_secret;
        const checkoutId = result.checkout_id;

        const paymentResult = window.selectedCardId
            ? await stripe.confirmCardPayment(clientSecret, {payment_method: window.selectedCardId})
            : await stripe.confirmCardPayment(clientSecret, {
                payment_method: {card: cardElement,
                    billing_details: {
                        name: `${data.first_name} ${data.last_name}`,
                        email: data.email,
                        phone: data.phone
                    }
                },
            });

        const {error, paymentIntent} = paymentResult;
        if (error) {
            document.getElementById('card-errors').textContent = error.message;
            showAlert(error.message, 'error');
            return;
        }

        if (paymentIntent.status === 'succeeded') {
            const confirmRes = await fetch(`${API_BASE}/checkout/confirm-payment`, {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({payment_intent_id: paymentIntent.id, checkout_id: checkoutId}),
            });
            const confirmResult = await confirmRes.json();
            confirmResult.success
                ? window.location.href = `/order-confirmation?checkout_id=${confirmResult.checkout_id}`
                : showAlert(confirmResult.message || 'Payment confirmation failed', 'error');
        }
    }

    // ── Init ──────────────────────────────────────────────────────────────
    checkLoginStatus();
    checkCartForSubscription();
    initStripe();

    if (appliedVoucher) {
        window.appliedVoucher = appliedVoucher;
        displayAppliedVoucher();
    }

    <?php if (($member?->country ?? '') === 'US'): ?>
    handleCountryChange('US');
    <?php endif; ?>

    if (isMixedCart) {
        <?php if ($checkoutMode === 'steps'): ?>
        const mixedBtn = document.getElementById('continue-to-payment-btn');
        <?php else: ?>
        const mixedBtn = document.getElementById('place-order-btn');
        <?php endif; ?>
        if (mixedBtn) {
            mixedBtn.disabled = true;
            mixedBtn.title = 'Remove subscription or physical items from your cart to continue';
        }
    }
</script>
@endsection