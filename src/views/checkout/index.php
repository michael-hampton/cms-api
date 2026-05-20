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
$isOneTimeCart = $isOneTimeCart ?? false;
$isMixedSubscriptionCart = $isMixedSubscriptionCart ?? false;

// ── Basket type derivation ────────────────────────────────────────────────
// Inspect every cart item (subscription + product) to determine whether the
// basket contains print, digital, or a combination of both.
// Subscription items carry delivery_type in their options array.
// Non-subscription product items are assumed to be physical (print).
$hasPrint = false;
$hasDigital = false;

foreach ($items ?? [] as $item) {
    if (!empty($item['subscription_plan_id'])) {
        $deliveryType = strtolower($item['options']['delivery_type'] ?? 'print');
        if ($deliveryType === 'digital') {
            $hasDigital = true;
        } else {
            // 'print', 'print_digital', or any physical type
            $hasPrint = true;
            if (str_contains($deliveryType, 'digital')) {
                $hasDigital = true;
            }
        }
    } else {
        // Plain product — physical by nature
        $hasPrint = true;
    }
}

if ($hasPrint && $hasDigital) {
    $basketType = 'print_and_digital';
} elseif ($hasDigital) {
    $basketType = 'digital_only';
} else {
    $basketType = 'print_only';
}

// For digital-only baskets the shipping address is never needed.
// Override the controller-supplied $requiresShipping so every downstream
// component (address-lookup, validation) picks up the correct value.
if ($basketType === 'digital_only') {
    $requiresShipping = false;
}
// ─────────────────────────────────────────────────────────────────────────

$subscriptionCartSnapshot = array_values(array_map(function ($item) {
    $options = $item['options'] ?? [];
    $planId = (int)($item['subscription_plan_id'] ?? 0);
    $plan = $planId > 0 ? \App\Models\SubscriptionPlan::find($planId) : null;

    return [
            'subscription_plan_id' => $planId,
            'delivery_type' => $options['delivery_type'] ?? 'print',
            'pricing_tier_id' => $options['pricing_tier_id'] ?? null,
            'start_date' => $options['start_date'] ?? null,
            'is_one_time' => $plan?->isOneTime() ?? false,
    ];
}, $cartSubscriptionItems));

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

    /* ── Order summary ────────────────────────────────────────────── */
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
['label' => 'Home',     'href' => '/'],
['label' => 'Cart',     'href' => '/cart'],
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
            <div class="step completed" id="step-1-indicator"
                 onclick="window.location.href='/cart'" title="Return to cart">
                <div class="step-circle">✓</div>
                <div class="step-label">Cart</div>
            </div>
            <div class="step active" id="step-2-indicator">
                <div class="step-circle">2</div>
                <div class="step-label">
                    <?= $basketType === 'digital_only' ? 'Details' : 'Shipping' ?>
                </div>
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

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-error" style="margin-bottom: 1.5rem;">
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

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

            <!-- ════ STEP 2: Contact + Shipping / Details ════ -->
            <?php if ($checkoutMode === 'steps'): ?>
            <div id="step-2-section">
                <?php endif; ?>

                @include('checkout/components/form/billing-form', [
                'member' => $member ?? null,
                'requiresShipping' => $requiresShipping ?? true,
                'basketType' => $basketType,
                ])

                <!-- Gift options — shown for all basket types -->
                @include('checkout/components/form/gift-fields', [
                'basketType' => $basketType,
                'isGift' => false,
                ])

                <?php if ($checkoutMode === 'steps'): ?>
                    @include('checkout/components/form/button', [
                    'id'      => 'continue-to-payment-btn',
                    'type'    => 'button',
                    'label'   => 'Continue to Payment',
                    'variant' => 'primary',
                    'onclick' => 'window.checkoutManager.advanceToPayment()',
                    'style'   => 'margin-top: 2rem;',
                    'icon'    => '
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
                        <path d="M2 9h20"></path>
                        <rect x="5" y="12" width="3" height="2" rx="0.5"></rect>
                        <path d="M10 13h7"></path>
                    </svg>',
                    ])
                <?php endif; ?>

                <?php if ($checkoutMode === 'steps'): ?>
            </div>
        <?php endif; ?>

            <!-- ════ STEP 3: Payment ════ -->
            <?php if ($checkoutMode === 'steps'): ?>
            <div id="step-3-section" style="display: none;">
                <?php endif; ?>

                @include('checkout/components/payment-method-selector')
                @include('checkout/components/saved-cards')

                <div id="payment-request-button"></div>

                @include('checkout/components/stripe-card-element', [
                'showBackButton' => true,
                ])

                <!-- Order notes -->
                @include('checkout/components/form/form-section', ['title' => 'Order Notes (Optional)'])
                @include('checkout/components/form/form-group', [
                'name' => 'notes',
                'label' => 'Special instructions for delivery',
                'required' => false,
                'class' => 'full-width',
                'type' => 'textarea',
                'id' => 'notes',
                'value' => '',
                ])
                @include('checkout/components/form/form-section', ['close' => true])

                <?php if ($checkoutMode === 'steps'): ?>
                    @include('checkout/components/form/button', [
                    'label'   => '← Back to ' . ($basketType === 'digital_only' ? 'Details' : 'Shipping'),
                    'variant' => 'secondary',
                    'onclick' => 'window.checkoutManager.goToStep(2)',
                    'style'   => 'margin-top: 1rem;',
                    ])
                <?php endif; ?>

                <?php if ($checkoutMode === 'steps'): ?>
            </div>
        <?php endif; ?>

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
    const CHECKOUT_BOOTSTRAP = <?= json_encode([
            'apiBase' => $apiBase ?? '',
            'planCurrency' => $currency ?? '$',
            'taxRate' => (float)($tax_rate ?? 0),
            'initialSubtotal' => (float)($subtotal ?? 0),
            'initialShipping' => (float)($shipping ?? 0),
            'subscriptionCartSnapshot' => $subscriptionCartSnapshot,
    ]) ?>;

    const API_BASE = CHECKOUT_BOOTSTRAP.apiBase;
    const PLAN_CURRENCY = CHECKOUT_BOOTSTRAP.planCurrency;
    const TAX_RATE = CHECKOUT_BOOTSTRAP.taxRate;
    const INITIAL_SUBTOTAL = CHECKOUT_BOOTSTRAP.initialSubtotal;
    const INITIAL_SHIPPING = CHECKOUT_BOOTSTRAP.initialShipping;
    const SUBSCRIPTION_CART_SNAPSHOT = CHECKOUT_BOOTSTRAP.subscriptionCartSnapshot;
</script>

@js('cart-utils.js')
@js('saved-cards.js')

@include('checkout/components/scripts/payment-method-selector.js.php')
@js('checkout-auth.js')

<script>
    window.onPaymentMethodChange = function (method) {
        if (window.checkoutManager) {
            window.checkoutManager.handlePaymentMethodChange(method);
        }
    };
</script>

<script src="https://js.stripe.com/v3/"></script>

<script>
    const CHECKOUT_CONFIG = <?= json_encode([
            'site' => $site,
            'stripeKey' => $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key'),
            'checkoutMode' => $checkoutMode,
            'requiresShipping' => $requiresShipping ?? true,
            'basketType' => $basketType,
            'isMixedCart' => $isMixedCart,
            'isMixedSubscriptionCart' => $isMixedSubscriptionCart,
            'isSubscriptionCart' => $isSubscription,
            'isOneTimeCart' => $isOneTimeCart,
    ]) ?>;

    let isLoggedIn = false;
    let currentMember = null;
    let selectedAddressId = null;
    let appliedVoucher = <?= json_encode($appliedVoucher) ?>;
    const requiresShipping = <?= json_encode($requiresShipping ?? true) ?>;
    const SITE = <?= json_encode($site) ?>;

    // ── StripeService ────────────────────────────────────────────────────
    class StripeService {
        constructor(stripeKey) {
            this.stripeKey = stripeKey;
            this.stripe = null;
            this.elements = null;
            this.cardElement = null;
            this._cardComplete = false;
        }

        async init() {
            if (!this.stripeKey) return;

            this.stripe = Stripe(this.stripeKey);
            this.elements = this.stripe.elements();
            this.cardElement = this.elements.create('card', {
                hidePostalCode: true,
                style: {
                    base: {fontSize: '16px', color: '#1e293b', '::placeholder': {color: '#64748b'}},
                },
            });

            const cardContainer = document.getElementById('card-element');
            if (cardContainer) {
                this.cardElement.mount('#card-element');
                this.cardElement.on('change', (event) => {
                    document.getElementById('card-errors').textContent = event.error ? event.error.message : '';
                    this._cardComplete = event.complete;
                    window.checkoutManager?.syncPlaceOrderButton();
                });
            }

            const paymentSection = document.getElementById('payment-form-section');
            if (paymentSection) paymentSection.style.display = 'none';
        }

        isReady() {
            // If a saved card is selected, Stripe element doesn't need to be filled
            if (window.selectedCardId) return true;
            // cardElement tracks its complete state via the 'change' event
            return this._cardComplete === true;
        }

        async createPaymentMethod(billingDetails) {
            if (window.selectedCardId) return {id: window.selectedCardId};

            const {paymentMethod, error} = await this.stripe.createPaymentMethod({
                type: 'card',
                card: this.cardElement,
                billing_details: billingDetails,
            });
            if (error) throw new Error(error.message);
            return paymentMethod;
        }

        async confirmPayment(clientSecret, billingDetails) {
            const paymentResult = window.selectedCardId
                ? await this.stripe.confirmCardPayment(clientSecret, {payment_method: window.selectedCardId})
                : await this.stripe.confirmCardPayment(clientSecret, {
                    payment_method: {card: this.cardElement, billing_details: billingDetails},
                    setup_future_usage: 'off_session',
                });

            if (paymentResult.error) throw new Error(paymentResult.error.message);
            return paymentResult.paymentIntent;
        }
    }

    // ── ApiService ───────────────────────────────────────────────────────
    class ApiService {
        constructor(apiBase) {
            this.apiBase = apiBase;
        }

        async request(url, options = {}) {
            const response = await fetch(url, options);
            const contentType = response.headers.get('content-type') || '';

            if (contentType.includes('application/json')) {
                const payload = await response.json();

                if (!response.ok) {
                    const message = payload.message
                        || payload.error
                        || payload.data?.message
                        || payload.data?.error
                        || `Request failed with status ${response.status}`;
                    throw new Error(message);
                }

                return payload;
            }

            const text = (await response.text()).trim();

            if (!response.ok) {
                throw new Error(text || `Request failed with status ${response.status}`);
            }

            throw new Error(text || 'Unexpected response from checkout service.');
        }

        async createOneTimeSubscriptionCheckout(payload) {
            return this.request(`${this.apiBase}/subscriptions/onetime/checkout`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({...payload, isOneTimeSubscription: true, one_time_subscription: true}),
            });
        }

        async createRecurringSubscriptionCheckout(payload) {
            return this.request(`${this.apiBase}/subscriptions/onetime/checkout`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({...payload, isOneTimeSubscription: true}),
            });
        }

        async confirmSubscriptionPayment(payload) {
            return this.request(`${this.apiBase}/subscriptions/onetime/confirm-payment`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload),
            });
        }

        async processRegularCheckout(payload) {
            return this.request(`${this.apiBase}/checkout/process`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload),
            });
        }

        async confirmRegularCheckout(payload) {
            return this.request(`${this.apiBase}/checkout/confirm-payment`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload),
            });
        }

        async clearCart() {
            return this.request(`${this.apiBase}/cart/clear`, {method: 'DELETE'});
        }

        async addSubscriptionItem(item) {
            return this.request(`${this.apiBase}/cart/subscription`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    plan_id: item.subscription_plan_id,
                    delivery_type: item.delivery_type,
                    pricing_id: item.pricing_tier_id,
                    start_date: item.start_date,
                }),
            });
        }
    }

    // ── CartFlowService ──────────────────────────────────────────────────
    class CartFlowService {
        constructor(apiService, subscriptionItems) {
            this.api = apiService;
            this.subscriptionItems = subscriptionItems;
        }

        get oneTimeItems() {
            return this.subscriptionItems.filter(i => i.is_one_time);
        }

        get recurringItems() {
            return this.subscriptionItems.filter(i => !i.is_one_time);
        }

        async replaceCart(items) {
            const clearResult = await this.api.clearCart();
            if (!clearResult.success) throw new Error(clearResult.message || 'Failed to prepare cart for checkout.');

            for (const item of items) {
                const addResult = await this.api.addSubscriptionItem(item);
                if (!addResult.success) throw new Error(addResult.message || 'Failed to rebuild checkout cart.');
            }
        }
    }

    // ── CheckoutManager ──────────────────────────────────────────────────
    class CheckoutManager {
        constructor({stripeService, apiService, cartFlowService, config}) {
            this.stripe = stripeService;
            this.api = apiService;
            this.cartFlow = cartFlowService;
            this.config = config;
            this.state = 'idle';
            this.currentStep = 2;
            this.completedSubscriptionIds = [];
            this.pendingOneTimeStorageKey = 'checkout.pendingOneTime';
        }

        async init() {
            await this.stripe.init();
            this.bindEvents();
            this.syncSubscriptionUi();
            this.applyCheckoutRestrictions();
        }

        bindEvents() {
            document.getElementById('place-order-btn')?.addEventListener('click', () => this.process());

            // Re-evaluate button when saved card selection changes
            document.addEventListener('change', (e) => {
                if (e.target?.name === 'saved_card' || e.target?.name === 'payment_method') {
                    this.syncPlaceOrderButton();
                }
            });
        }

        syncPlaceOrderButton() {
            const btn = document.getElementById('place-order-btn');
            if (!btn) return;

            const selectedMethod = window.selectedPaymentMethod ?? 'card';

            if (selectedMethod !== 'card') {
                // PayPal / bank — Stripe element not required
                btn.disabled = false;
                btn.title = '';
                return;
            }

            const hasCard = window.selectedCardId || this.stripe._cardComplete === true;
            btn.disabled = !hasCard;
            btn.title = hasCard ? '' : 'Please enter your card details to continue';
        }

        handlePaymentMethodChange(method) {
            if (method !== 'card') return;
            if (window.savedCards && window.savedCards.length > 0
                && typeof window.displaySavedCards === 'function') {
                window.displaySavedCards();
            }
        }

        syncSubscriptionUi() {
            const block = document.getElementById('global-renewal-consent-block');
            if (block) block.style.display = this.config.isSubscriptionCart ? '' : 'none';
        }

        // ── Field validation ─────────────────────────────────────────────
        /**
         * Returns the list of required field names for the current basket type.
         *
         * Digital-only:      no address fields required.
         * Print / P+D:       address fields required (unless a saved address is selected).
         * All baskets:       first_name, last_name, email always required.
         * Gift (when shown): recipient_first_name, recipient_last_name required.
         *                    recipient_email required when basket includes digital.
         */
        _requiredFields() {
            const isDigitalOnly = this.config.basketType === 'digital_only';
            const includesDigital = ['digital_only', 'print_and_digital'].includes(this.config.basketType);
            const isGift = document.getElementById('is-gift-checkbox')?.checked ?? false;

            const base = ['first_name', 'last_name'];

            // Email is required whenever basket includes digital content
            if (includesDigital) base.push('email');

            // Address fields — only for non-digital-only baskets without a saved address
            if (!isDigitalOnly && !selectedAddressId) {
                base.push('address', 'city', 'postal_code', 'country');
            }

            // Gift recipient fields
            if (isGift) {
                base.push('recipient_first_name', 'recipient_last_name');
                // AC2 note: recipient_email validation is NOT enforced for print+digital
                // gift flow at the shipping step — it is validated at submission instead.
                if (this.config.basketType === 'digital_only') {
                    base.push('recipient_email');
                }
            }

            return base;
        }

        validateShippingFields() {
            document.querySelectorAll('.form-error').forEach(el => (el.textContent = ''));

            if (!this.config.requiresShipping && this.config.basketType === 'digital_only') {
                // For digital-only, only validate non-address fields
                return this._validateFields(['first_name', 'last_name', 'email']);
            }

            return this._validateFields(this._requiredFields());
        }

        _validateFields(fields) {
            const data = Object.fromEntries(new FormData(document.getElementById('checkout-form')));
            let hasErrors = false;

            for (const field of fields) {
                if (!data[field]?.trim()) {
                    const el = document.getElementById(`error-${field}`);
                    if (el) el.textContent = 'This field is required';
                    hasErrors = true;
                }
            }

            return !hasErrors;
        }

        // ── Step navigation ──────────────────────────────────────────────
        goToStep(step) {
            if (this.config.checkoutMode !== 'steps') return;
            if (step === this.currentStep) return;

            document.getElementById('step-2-section').style.display = step === 2 ? 'block' : 'none';
            document.getElementById('step-3-section').style.display = step === 3 ? 'block' : 'none';
            document.getElementById('progress-line').style.width = ({2: '33%', 3: '66%'}[step] ?? '33%');

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
                indicators[2].onclick = () => this.goToStep(2);
                indicators[3].classList.add('active');
            }

            this.currentStep = step;
            window.scrollTo({top: 0, behavior: 'smooth'});
        }

        advanceToPayment() {
            if (this.config.isMixedCart) {
                showAlert('Your cart contains both subscription and physical items. Please return to your cart.', 'error');
                return;
            }

            document.getElementById('alert-container').innerHTML = '';

            if (!this.validateShippingFields()) {
                showAlert('Please fill in all required fields before continuing.', 'error');
                return;
            }

            this.goToStep(3);
        }

        // ── State ────────────────────────────────────────────────────────
        setState(state) {
            this.state = state;
            const btn = document.getElementById('place-order-btn');
            const overlay = document.getElementById('loading-overlay');

            btn.disabled = state !== 'idle';

            if (state !== 'idle') {
                btn.classList.add('processing');
                const labels = {
                    processing: 'Processing…',
                    processing_recurring: 'Setting Up Subscriptions…',
                    processing_one_time: 'Confirming One-Time Payment…',
                };
                btn.textContent = labels[state] ?? 'Processing…';
                overlay.classList.add('show');
            } else {
                btn.classList.remove('processing');
                btn.textContent = 'Place Order';
                overlay.classList.remove('show');
            }
        }

        setCardError(message = '') {
            const cardErrors = document.getElementById('card-errors');
            if (cardErrors) cardErrors.textContent = message;
        }

        getBillingDetails(data) {
            return {
                name: `${data.first_name} ${data.last_name}`.trim(),
                email: data.email,
                phone: data.phone,
            };
        }

        // ── Payload builder ──────────────────────────────────────────────
        buildPayload({includeVoucher = true} = {}) {
            const formData = new FormData(document.getElementById('checkout-form'));
            const data = Object.fromEntries(formData);

            document.querySelectorAll('.form-error').forEach(el => (el.textContent = ''));
            document.getElementById('alert-container').innerHTML = '';
            this.setCardError('');

            // Pre-order acceptance
            const acceptPreOrder = document.getElementById('accept-pre-order');
            if (acceptPreOrder && !acceptPreOrder.checked) {
                throw new Error('You must accept the pre-order terms to continue.');
            }

            // Subscription consent
            const globalConsentBlock = document.getElementById('global-renewal-consent-block');
            const globalConsentCb = document.getElementById('global-renewal-consent');
            if (globalConsentCb && !globalConsentCb.checked && this.config.isSubscriptionCart) {
                globalConsentBlock?.classList.add('consent-error');
                globalConsentBlock?.scrollIntoView({behavior: 'smooth', block: 'center'});
                throw new Error('Please confirm the subscription terms before placing your order.');
            }
            globalConsentBlock?.classList.remove('consent-error');
            data.global_renewal_consent = '1';

            const usConsentBlock = document.getElementById('us-renewal-consent-block');
            if (usConsentBlock && usConsentBlock.style.display !== 'none' && this.config.isSubscriptionCart) {
                const usConsentCb = document.getElementById('us-renewal-consent');
                if (!usConsentCb?.checked) {
                    usConsentBlock.classList.add('consent-error');
                    usConsentBlock.scrollIntoView({behavior: 'smooth', block: 'center'});
                    throw new Error('Please confirm the auto-renewal terms before placing your order.');
                }
                usConsentBlock.classList.remove('consent-error');
                data.us_renewal_consent = '1';
            }

            // For digital-only baskets, strip any address keys that might have
            // leaked in from autofill so the server doesn't attempt to validate them.
            if (this.config.basketType === 'digital_only') {
                ['address', 'address2', 'city', 'state', 'postal_code', 'country'].forEach(k => delete data[k]);
            } else if (selectedAddressId) {
                data.saved_address = selectedAddressId;
                ['address', 'address2', 'city', 'state', 'postal_code', 'country'].forEach(k => delete data[k]);
            }

            // Single-page mode validation
            if (this.config.checkoutMode !== 'steps' && !this._validateFields(this._requiredFields())) {
                throw new Error('Please fill in all required fields.');
            }

            if (includeVoucher && appliedVoucher) {
                data.voucher_code = appliedVoucher.code;
                data.voucher_id = appliedVoucher.voucher_id;
                data.discount_amount = appliedVoucher.discount;
            }

            data.multi_merchant = true;
            return data;
        }

        // ── Subscription helpers ─────────────────────────────────────────
        normalizeSubscriptionIds(result) {
            return Array.isArray(result) ? result : (result ? [result] : []);
        }

        storePendingOneTimeState(items) {
            sessionStorage.setItem(this.pendingOneTimeStorageKey, JSON.stringify({
                items,
                completedSubscriptionIds: this.completedSubscriptionIds,
            }));
        }

        clearPendingOneTimeState() {
            sessionStorage.removeItem(this.pendingOneTimeStorageKey);
        }

        async confirmSubscriptionCheckout({orderId, subscriptionIds, paymentIntentId = null, paymentMethodId = null}) {
            const payload = {order_id: orderId};
            if (paymentIntentId) payload.payment_intent_id = paymentIntentId;
            if (paymentMethodId) payload.payment_method_id = paymentMethodId;
            if (subscriptionIds.length === 1) {
                payload.subscription_id = subscriptionIds[0];
            } else {
                payload.subscription_ids = subscriptionIds;
            }

            const result = await this.api.confirmSubscriptionPayment(payload);
            if (!result.success) throw new Error(result.message || 'Payment confirmation failed');

            this.completedSubscriptionIds.push(...subscriptionIds);
            this.completedSubscriptionIds = [...new Set(this.completedSubscriptionIds)];
        }

        // ── Checkout flows ───────────────────────────────────────────────
        async handleRegularCheckout(data) {
            const result = await this.api.processRegularCheckout(data);
            if (!result.success) throw new Error(result.message || 'Failed to process order');

            const contexts = result.stripe_contexts;
            const clientSecret = contexts ? contexts[Object.keys(contexts)[0]].client_secret : result.client_secret;
            const paymentIntent = await this.stripe.confirmPayment(clientSecret, this.getBillingDetails(data));

            if (paymentIntent.status === 'succeeded') {
                const confirmResult = await this.api.confirmRegularCheckout({
                    payment_intent_id: paymentIntent.id,
                    checkout_id: result.checkout_id,
                });
                if (!confirmResult.success) throw new Error(confirmResult.message || 'Payment confirmation failed');
                window.location.href = `/order-confirmation?checkout_id=${confirmResult.checkout_id}`;
            }
        }

        async handleRecurringFlow(data) {
            this.setState('processing_recurring');
            const result = await this.api.createRecurringSubscriptionCheckout(data);
            if (!result.success) throw new Error(result.message || 'Checkout failed');

            console.log('result', result)

            const subscriptionIds = this.normalizeSubscriptionIds(
                result.data.subscription_ids || result.data.subscription_id
            );
            const paymentMethod = await this.stripe.createPaymentMethod(this.getBillingDetails(data));

            await this.confirmSubscriptionCheckout({
                orderId: result.data.order_id,
                subscriptionIds,
                paymentMethodId: paymentMethod.id,
            });
        }

        async handleOneTimeFlow(data) {
            this.setState('processing_one_time');
            const result = await this.api.createOneTimeSubscriptionCheckout(data);
            if (!result.success) throw new Error(result.message || 'Checkout failed');

            const contexts = result.data.stripe_contexts;
            const clientSecret = contexts
                ? contexts[Object.keys(contexts)[0]].client_secret
                : result.data.client_secret;
            const subscriptionIds = this.normalizeSubscriptionIds(
                result.data.subscription_ids || result.data.subscription_id
            );
            const paymentIntent = await this.stripe.confirmPayment(clientSecret, this.getBillingDetails(data));

            if (paymentIntent?.status === 'succeeded') {
                await this.confirmSubscriptionCheckout({
                    orderId: result.data.order_id,
                    subscriptionIds,
                    paymentIntentId: paymentIntent.id,
                });
            }
        }

        async handleMixedSubscriptionFlow() {
            const recurringItems = this.cartFlow.recurringItems;
            const oneTimeItems = this.cartFlow.oneTimeItems;

            await this.cartFlow.replaceCart(recurringItems);
            await this.handleRecurringFlow(this.buildPayload());

            try {
                await this.cartFlow.replaceCart(oneTimeItems);
                await this.handleOneTimeFlow(this.buildPayload({includeVoucher: false}));
            } catch (error) {
                this.storePendingOneTimeState(oneTimeItems);
                await this.cartFlow.replaceCart(oneTimeItems);
                throw new Error(
                    `Recurring subscriptions were created successfully, but the one-time payment still needs to be completed. ${error.message}`
                );
            }

            this.clearPendingOneTimeState();
            window.location.href = `/subscription-confirmation?ids=${this.completedSubscriptionIds.join(',')}`;
        }

        async handleSubscriptionCheckout() {
            if (this.config.isMixedSubscriptionCart) {
                await this.handleMixedSubscriptionFlow();
                return;
            }

            if (this.config.isOneTimeCart) {
                await this.handleOneTimeFlow(this.buildPayload());
            } else {
                await this.handleRecurringFlow(this.buildPayload());
            }

            this.clearPendingOneTimeState();
            window.location.href = `/subscription-confirmation?ids=${this.completedSubscriptionIds.join(',')}`;
        }

        applyCheckoutRestrictions() {
            if (!this.config.isMixedCart) return;

            const button = this.config.checkoutMode === 'steps'
                ? document.getElementById('continue-to-payment-btn')
                : document.getElementById('place-order-btn');

            if (button) {
                button.disabled = true;
                button.title = 'Remove subscription or physical items from your cart to continue';
            }
        }

        // ── Main entry point ─────────────────────────────────────────────
        async process() {
            if (this.config.isMixedCart) {
                showAlert('Your cart contains both subscription and physical items. Please return to your cart.', 'error');
                return;
            }

            try {
                this.setState('processing');

                if (this.config.isSubscriptionCart) {
                    await this.handleSubscriptionCheckout();
                } else {
                    await this.handleRegularCheckout(this.buildPayload());
                }
            } catch (error) {
                this.setCardError(error.message || 'Payment failed');
                showAlert(error.message || 'Payment failed', 'error');
                this.state = 'failed';
            } finally {
                if (this.state !== 'complete') this.setState('idle');
            }
        }
    }

    // ── Bootstrap ────────────────────────────────────────────────────────
    const apiService = new ApiService(API_BASE);
    window.checkoutManager = new CheckoutManager({
        stripeService: new StripeService(CHECKOUT_CONFIG.stripeKey),
        apiService,
        cartFlowService: new CartFlowService(apiService, SUBSCRIPTION_CART_SNAPSHOT),
        config: CHECKOUT_CONFIG,
    });

    checkLoginStatus();
    window.checkoutManager.init().then(() => {
        window.checkoutManager.syncPlaceOrderButton();
    });

    if (appliedVoucher) {
        window.appliedVoucher = appliedVoucher;
        displayAppliedVoucher();
    }

    <?php if (($member?->country ?? '') === 'US'): ?>
    handleCountryChange('US');

    (function () {
        const btn = document.getElementById('place-order-btn');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.title = 'Please enter your card details to continue';
        }
    })();

    <?php endif; ?>
</script>
@endsection
