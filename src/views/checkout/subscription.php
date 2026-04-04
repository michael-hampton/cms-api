@section('logic')
<?php
/**
 * Subscription checkout page.
 *
 * Expected view data:
 *   object      $plan             — SubscriptionPlan model
 *   object      $member           — authenticated member
 *   bool        $requiresShipping — whether a shipping address is needed
 *   bool        $isSubscription   — always true for this view
 *
 * The page reuses the shared checkout layout, components, and JS patterns
 * from checkout/index.php instead of duplicating them inline.
 */

use App\Framework\Support\SiteContext;

$site = SiteContext::slug();
$apiBase = '/api/' . $site;
$displayCurrency = strtoupper($plan->currency ?? 'GBP');

$isPreRelease = !empty($plan->release_date)
        && $plan->release_date > now_datetime()
        && !empty($plan->pre_release_enabled);

$trialDays = $plan->hasTrial() ? (int)$plan->trial_days : 0;
$planPrice = (float)$plan->price;
?>
@endsection

@extends('checkout/layout/shop')

@section('title', 'Subscribe — ' . htmlspecialchars($plan->name))

@section('styles')
<style>
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
        transition: border-color 0.2s;
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    /* ── Plan summary card (right column header) ────────────────── */
    .plan-hero {
        background: linear-gradient(135deg, #1e40af 0%, #2563eb 50%, #3b82f6 100%);
        border-radius: 0.75rem 0.75rem 0 0;
        padding: 1.5rem;
        color: white;
        text-align: center;
    }

    .plan-hero-name {
        font-size: 1.375rem;
        font-weight: 800;
        letter-spacing: -.02em;
    }

    .plan-hero-price {
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: .25rem;
        margin-top: .5rem;
    }

    .plan-hero-currency {
        font-size: 1.25rem;
        font-weight: 700;
        opacity: .85;
    }

    .plan-hero-amount {
        font-size: 2.5rem;
        font-weight: 900;
    }

    .plan-hero-period {
        font-size: 1rem;
        opacity: .8;
    }

    /* Pre-release notice */
    .prerelease-notice {
        background: #fef3c7;
        border: 2px solid #f59e0b;
        border-radius: 0.5rem;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .prerelease-notice h4 {
        color: #92400e;
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: .75rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .prerelease-notice p {
        font-size: .875rem;
        color: #78350f;
        line-height: 1.6;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1rem;
        font-size: .875rem;
    }

    .summary-row.total {
        font-size: 1.25rem;
        font-weight: 700;
        padding-top: 1rem;
        border-top: 2px solid var(--border-color);
        margin-top: 1rem;
    }

    @media (max-width: 968px) {
        .checkout-layout {
            grid-template-columns: 1fr;
        }

        .form-row {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@section('page-header')
@include('checkout/components/page-header', [
'title'       => 'Subscribe',
'breadcrumbs' => [
['label' => 'Home', 'href'  => '/'],
['label' => 'Shop', 'href'  => '/shop'],
['label' => 'Subscribe'],
],
])
@endsection

@section('content')

<div id="alert-container"></div>

<div class="checkout-layout">

    <!-- ── Left: form ──────────────────────────────────────────── -->
    <div class="checkout-form">
        <form id="subscription-form">
            <input type="hidden" name="subscription_plan_id" value="<?= (int)$plan->id ?>">

            <?php /* Contact details — member may already be filled in */ ?>
            <div class="form-section">
                <h2 class="section-title">Contact Information</h2>
                <div class="form-row">
                    @include('checkout/components/form/form-group', [
                    'name' => 'first_name',
                    'label' => 'First Name',
                    'required' => true,
                    'value' => $member?->first_name ?? '',
                    ])
                    @include('checkout/components/form/form-group', [
                    'name' => 'last_name',
                    'label' => 'Last Name',
                    'required' => true,
                    'value' => $member?->last_name ?? '',
                    ])
                </div>
                <div class="form-row">
                    @include('checkout/components/form/form-group', [
                    'name' => 'email',
                    'label' => 'Email',
                    'type' => 'email',
                    'required' => true,
                    'value' => $member?->email ?? '',
                    ])
                    @include('checkout/components/form/form-group', [
                    'name' => 'phone',
                    'label' => 'Phone',
                    'type' => 'tel',
                    ])
                </div>
            </div>

            <?php if ($requiresShipping): ?>
                <div class="form-section">
                    <h2 class="section-title">Shipping Address</h2>
                    @include('checkout/components/form/form-group', [
                    'name' => 'address',
                    'label' => 'Address',
                    'required' => true,
                    'class' => 'full-width',
                    ])
                    @include('checkout/components/form/form-group', [
                    'name' => 'address2',
                    'label' => 'Apartment, suite, etc. (optional)',
                    'class' => 'full-width',
                    ])
                    <div class="form-row">
                        @include('checkout/components/form/form-group', [
                        'name' => 'city',
                        'label' => 'City',
                        'required' => true,
                        ])
                        @include('checkout/components/form/form-group', [
                        'name' => 'state',
                        'label' => 'State / Province',
                        ])
                    </div>
                    <div class="form-row">
                        @include('checkout/components/form/form-group', [
                        'name' => 'postal_code',
                        'label' => 'Postal Code',
                        'required' => true,
                        ])
                        @include('checkout/components/form/select', [
                        'name' => 'country',
                        'id' => 'country-select',
                        'label' => 'Country',
                        'required' => true,
                        'blank' => true,
                        'blankLabel' => 'Select Country',
                        'options' => [
                        'US' => 'United States',
                        'CA' => 'Canada',
                        'GB' => 'United Kingdom',
                        'AU' => 'Australia',
                        'DE' => 'Germany',
                        'FR' => 'France',
                        ],
                        'selected' => $member?->country ?? '',
                        'onChange' => 'handleCountryChange(this.value)',
                        ])
                    </div>
                </div>
            <?php endif; ?>

            <?php /* Pre-release notice & acceptance */ ?>
            <?php if ($isPreRelease): ?>
                <div class="prerelease-notice">
                    <h4>⚠️ Pre-Release Subscription</h4>
                    <p>
                        <strong>Access begins:</strong> <?= $plan->release_date->format('F j, Y') ?><br>
                        <?php if (!empty($plan->print_shipping_required)): ?>
                            <strong>First print issue ships:</strong> <?= $plan->release_date->format('F j, Y') ?><br>
                        <?php endif; ?>
                        You will be charged today but access begins on the date above.
                    </p>
                    <div style="margin-top:1rem;">
                        @include('checkout/components/form/checkbox-control', [
                        'name' => 'accept_pre_release',
                        'id' => 'accept-pre-release',
                        'label' => 'I understand this is a pre-release subscription and access begins on <strong>' .
                            $plan->release_date->format('F j, Y') . '</strong>.',
                        'required' => true,
                        ])
                    </div>
                </div>
            <?php endif; ?>

            <?php /* Payment */ ?>
            @include('checkout/components/saved-cards')

            <div id="payment-request-button"></div>

            @include('checkout/components/stripe-card-element', [
            'sectionTitle' => 'Card Details',
            'showBackButton' => false,
            ])

            <?php /* Auto-renewal consent */ ?>
            @include('checkout/components/auto-renewal-consent', [
            'showGlobal' => true,
            'showUs' => false,
            ])
        </form>
    </div>

    <!-- ── Right: plan summary ─────────────────────────────────── -->
    <div style="height:fit-content;position:sticky;top:100px;">

        <!-- Plan hero -->
        <div style="background:white;border-radius:.75rem;box-shadow:var(--shadow);overflow:hidden;margin-bottom:1rem;">
            <div class="plan-hero">
                <div class="plan-hero-name"><?= htmlspecialchars($plan->name) ?></div>
                <?php if (!empty($plan->description)): ?>
                    <p style="font-size:.875rem;opacity:.8;margin-top:.25rem;"><?= htmlspecialchars($plan->description) ?></p>
                <?php endif; ?>
                <div class="plan-hero-price">
                    <span class="plan-hero-currency"><?= htmlspecialchars($plan->currency) ?></span>
                    <span class="plan-hero-amount"><?= number_format($planPrice, 2) ?></span>
                    <span class="plan-hero-period">/<?= htmlspecialchars($plan->billing_period === 'month' ? 'mo' : 'yr') ?></span>
                </div>
            </div>

            <div style="padding:1.5rem;">
                <?php if ($trialDays > 0): ?>
                    <div style="display:flex;align-items:flex-start;gap:.875rem;background:#f0fdf4;border:1.5px solid #6ee7b7;border-radius:.625rem;padding:1rem 1.25rem;margin-bottom:1.25rem;">
                        <span style="font-size:1.75rem;line-height:1;flex-shrink:0;margin-top:.1rem;">🎁</span>
                        <div>
                            <div style="font-weight:700;font-size:1rem;color:#065f46;"><?= $trialDays ?>-day free trial
                                included
                            </div>
                            <div style="font-size:.875rem;color:#047857;margin-top:.25rem;line-height:1.6;">
                                First charge on
                                <strong><?= (new DateTimeImmutable())->modify("+{$trialDays} days")->format('F j, Y') ?></strong>.
                                Cancel any time.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($plan->features) && count($plan->features) > 0): ?>
                    <div style="margin-bottom:1rem;">
                        <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);margin-bottom:.75rem;">
                            What's included
                        </div>
                        <ul style="list-style:none;padding:0;display:flex;flex-direction:column;gap:.5rem;">
                            <?php foreach ($plan->features as $feature): ?>
                                <li style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981"
                                         stroke-width="2.5" style="flex-shrink:0;">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <?= htmlspecialchars($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Price breakdown -->
                <div style="padding-top:1rem;border-top:1px solid var(--border-color);">
                    <div class="summary-row">
                        <span>Plan price</span>
                        <span><?= htmlspecialchars($plan->currency) ?> <?= number_format($planPrice, 2) ?></span>
                    </div>
                    <?php if ($trialDays > 0): ?>
                        <div class="summary-row" style="color:var(--success-color);">
                            <span>Today's charge</span>
                            <span>FREE</span>
                        </div>
                    <?php endif; ?>
                    <div id="discount-row" style="display:none;color:var(--success-color);" class="summary-row">
                        <span>Discount:</span>
                        <span id="discount-amount"></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total today</span>
                        <span id="total" data-total="<?= $trialDays > 0 ? '0' : $planPrice ?>">
                            <?= $trialDays > 0 ? 'FREE' : htmlspecialchars($plan->currency) . ' ' . number_format($planPrice, 2) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Voucher -->
        @include('checkout/components/voucher-section', [
        'currency' => $plan->currency ?? '$',
        'applyOnClick' => 'applyVoucher()',
        'removeOnClick' => 'removeVoucher()',
        ])

        <!-- Place order button -->
        @include('checkout/components/form/button', [
        'id' => 'subscribe-btn',
        'label' => $trialDays > 0 ? "Start {$trialDays}-Day Free Trial" : 'Subscribe Now',
        'variant' => 'primary',
        'style' => 'margin-top:1rem;',
        'type' => 'button'
        ])

        @include('checkout/components/security-badge')
    </div>

</div>

@endsection

@section('scripts')
@include('checkout/components/loading-overlay', [
'message' => 'Processing your subscription...',
'id'      => 'loading-overlay',
])

<script>
    const API_BASE = <?= json_encode($apiBase) ?>;
    const PLAN_CURRENCY = <?= json_encode($plan->currency ?? '$') ?>;
    const TAX_RATE = 0;
    const INITIAL_SUBTOTAL = <?= json_encode($planPrice) ?>;
    const INITIAL_SHIPPING = 0;
    const STRIPE_KEY = <?= json_encode($_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key')) ?>;
    const SITE = <?= json_encode($site) ?>;
    const REQUIRES_SHIPPING = <?= json_encode($requiresShipping) ?>;
    const HAS_TRIAL = <?= json_encode($trialDays > 0) ?>;

    window.appliedVoucher = null;
</script>

@js('cart-utils.js')

<script src="https://js.stripe.com/v3/"></script>

<script>
    let stripe = null, elements = null, cardElement = null;
    let selectedCardId = null;
    let clientSecret = null;
    let subscriptionId = null;
    let orderId = null;

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

    // ── Stripe init ───────────────────────────────────────────────────────
    async function initStripe() {
        if (!STRIPE_KEY) return;
        stripe = Stripe(STRIPE_KEY);
        elements = stripe.elements();

        cardElement = elements.create('card', {
            hidePostalCode: true,
            style: {base: {fontSize: '16px', color: '#1e293b', '::placeholder': {color: '#64748b'}}},
        });

        const cardContainer = document.getElementById('card-element');
        if (cardContainer) {
            cardElement.mount('#card-element');
            cardElement.on('change', e => {
                document.getElementById('card-errors').textContent = e.error ? e.error.message : '';
            });
        }
    }

    // ── Saved cards ───────────────────────────────────────────────────────
    async function loadSavedCards() {
        try {
            const res = await fetch(`${API_BASE}/member/payment-methods`);
            const data = await res.json();
            if (data.success && data.data?.payment_methods?.length) {
                displaySavedCards(data.data.payment_methods);
            }
        } catch (e) { /* non-critical */
        }
    }

    function displaySavedCards(cards) {
        const list = document.getElementById('saved-cards-list');
        const section = document.getElementById('saved-cards-section');
        const newCard = document.getElementById('new-card-section');
        if (!list || !section) return;

        list.innerHTML = cards.map(card => `
        <label class="saved-card" for="card-${card.id}">
          <input type="radio" name="saved_card" value="${card.id}" id="card-${card.id}"
                 onchange="selectSavedCard('${card.id}')">
          <div class="card-details">
            <div class="card-brand">${card.card.brand}</div>
            <div class="card-number">•••• •••• •••• ${card.card.last4}</div>
            <div class="card-expiry">Expires ${card.card.exp_month}/${card.card.exp_year}</div>
          </div>
        </label>`).join('');

        section.style.display = 'block';
        if (newCard) newCard.style.display = 'none';
    }

    function selectSavedCard(id) {
        selectedCardId = id;
        document.querySelectorAll('.saved-card').forEach(c => c.classList.remove('selected'));
        document.getElementById(`card-${id}`)?.closest('.saved-card')?.classList.add('selected');
    }

    function showNewCardForm() {
        selectedCardId = null;
        const section = document.getElementById('saved-cards-section');
        const newCard = document.getElementById('new-card-section');
        const back = document.getElementById('back-to-saved-cards-btn');
        if (section) section.style.display = 'none';
        if (newCard) newCard.style.display = 'block';
        if (back) back.style.display = 'block';
        document.querySelectorAll('[name="saved_card"]').forEach(r => r.checked = false);
    }

    function showSavedCards() {
        selectedCardId = null;
        const section = document.getElementById('saved-cards-section');
        const newCard = document.getElementById('new-card-section');
        const back = document.getElementById('back-to-saved-cards-btn');
        if (section) section.style.display = 'block';
        if (newCard) newCard.style.display = 'none';
        if (back) back.style.display = 'none';
        document.querySelectorAll('[name="saved_card"]').forEach(r => r.checked = false);
    }

    // ── Place order ───────────────────────────────────────────────────────
    function setProcessing(on) {
        const btn = document.getElementById('subscribe-btn');
        const overlay = document.getElementById('loading-overlay');
        btn.disabled = on;
        if (on) {
            btn.classList.add('processing');
            btn.textContent = 'Processing…';
            overlay.classList.add('show');
        } else {
            btn.classList.remove('processing');
            btn.textContent = <?= json_encode($trialDays > 0 ? "Start {$trialDays}-Day Free Trial" : 'Subscribe Now') ?>;
            overlay.classList.remove('show');
        }
    }

    document.getElementById('subscribe-btn').addEventListener('click', async () => {
        const form = document.getElementById('subscription-form');
        const data = Object.fromEntries(new FormData(form));

        document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
        document.getElementById('alert-container').innerHTML = '';
        document.getElementById('card-errors').textContent = '';

        // Pre-release
        const acceptEl = document.getElementById('accept-pre-release');
        if (acceptEl && !acceptEl.checked) {
            showAlert('You must accept the pre-release terms to continue.', 'error');
            return;
        }

        // Global renewal consent
        const globalCb = document.getElementById('global-renewal-consent');
        const globalBlock = document.getElementById('global-renewal-consent-block');
        if (globalCb && !globalCb.checked) {
            globalBlock?.classList.add('consent-error');
            showAlert('Please confirm the subscription terms.', 'error');
            globalBlock?.scrollIntoView({behavior: 'smooth', block: 'center'});
            return;
        }
        globalBlock?.classList.remove('consent-error');
        data.global_renewal_consent = '1';

        // US consent
        const usBlock = document.getElementById('us-renewal-consent-block');
        if (usBlock && usBlock?.style.display !== 'none') {
            const usCb = document.getElementById('us-renewal-consent');
            if (usCb && !usCb.checked) {
                usBlock.classList.add('consent-error');
                showAlert('Please confirm the auto-renewal terms.', 'error');
                usBlock.scrollIntoView({behavior: 'smooth', block: 'center'});
                return;
            }
            usBlock.classList.remove('consent-error');
            data.us_renewal_consent = '1';
        }

        if (window.appliedVoucher) {
            data.voucher_code = window.appliedVoucher.code;
            data.voucher_id = window.appliedVoucher.voucher_id;
            data.discount_amount = window.appliedVoucher.discount;
        }

        data.isOneTimeSubscription = true;

        setProcessing(true);
        try {
            await processSubscription(data);
        } finally {
            setProcessing(false);
        }
    });

    async function processSubscription(data) {
        const res = await fetch(`${API_BASE}/subscriptions/onetime/checkout`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data),
        });
        const result = await res.json();

        if (!result.success) {
            showAlert(result.message || 'Checkout failed.', 'error');
            return;
        }

        const contexts = result.data?.stripe_contexts;
        clientSecret = contexts
            ? contexts[Object.keys(contexts)[0]].client_secret
            : (result.data?.client_secret ?? null);

        subscriptionId = result.data?.subscription_ids ?? result.data?.subscription_id ?? null;
        orderId = result.data?.order_id ?? null;

        const paymentResult = selectedCardId
            ? await stripe.confirmCardPayment(clientSecret, {payment_method: selectedCardId})
            : await stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: `${data.first_name} ${data.last_name}`,
                        email: data.email,
                        phone: data.phone ?? undefined,
                    },
                },
                setup_future_usage: 'off_session',
            });

        const {error, paymentIntent} = paymentResult;

        if (error) {
            document.getElementById('card-errors').textContent = error.message;
            showAlert(error.message, 'error');
            return;
        }

        if (paymentIntent.status === 'succeeded') {
            await confirmPayment(paymentIntent.id);
        }
    }

    async function confirmPayment(intentId) {
        const body = {payment_intent_id: intentId, order_id: orderId};
        Array.isArray(subscriptionId)
            ? (body.subscription_ids = subscriptionId)
            : (body.subscription_id = subscriptionId);

        const res = await fetch(`${API_BASE}/subscriptions/onetime/confirm-payment`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body),
        });
        const result = await res.json();

        if (result.success) {
            const ids = Array.isArray(subscriptionId) ? subscriptionId : [subscriptionId];
            window.location.href = `/subscription-confirmation?ids=${ids.join(',')}`;
        } else {
            showAlert(result.message || 'Payment confirmation failed.', 'error');
        }
    }

    // ── Init ──────────────────────────────────────────────────────────────
    initStripe();
    loadSavedCards();

    <?php if (($member?->country ?? '') === 'US'): ?>
    handleCountryChange('US');
    <?php endif; ?>
</script>
@endsection