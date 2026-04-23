<?php
/**
 * Subscription modal.
 *
 * Steps:
 *   1 — Choose Plan
 *   2 — Account  (skipped when already logged in)
 *   3a — Delivery Address  (print plans only; skipped for digital)
 *   3b — Payment
 *   4  — Complete
 *
 * Progress indicator shows 4 circles: Plan → Account → Address → Payment → Complete
 * For digital plans "Address" is shown greyed/skipped visually but not rendered.
 *
 * Uses shared checkout components:
 *   - checkout/components/form/billing-form  (step 3 — address)
 *   - checkout/components/saved-cards
 *   - checkout/components/stripe-card-element
 *   - checkout/components/voucher-section
 *   - checkout/components/auto-renewal-consent
 *   - checkout/components/security-badge
 *   - checkout/components/form/button
 *
 * JS dependencies (loaded by the parent layout before this partial):
 *   - cart-utils.js
 *   - saved-cards.js
 *
 * @var array $subscriptionModalData {
 *   bool         show_modal
 *   object[]     plans
 *   object|null  member
 * }
 */
if (!isset($subscriptionModalData)) {
    return;
}

$plans = $subscriptionModalData['plans'];
$member = $subscriptionModalData['member'];
$isLoggedIn = !empty($member);
$site = \App\Framework\Support\SiteContext::slug();
$apiBase = '/api/' . $site;
?>

<div id="subscriptionModal" class="sub-modal">
    <div class="sub-modal-overlay"></div>

    <div class="sub-modal-container">

        <!-- Close button -->
        <?= $this->partial('checkout/components/form/button', [
                'id' => 'sub-modal-close-btn',
                'label' => '✕',
                'variant' => 'secondary',
                'type' => 'button',
                'class' => 'sub-modal-close',
                'onclick' => 'closeSubscriptionModal()',
        ]) ?>

        <!-- ── Progress indicator ──────────────────────────────────────
             Steps: 1 Plan | 2 Account | 3 Address | 4 Payment | 5 Complete
             "Account" circle is hidden for logged-in users via JS class.
             "Address"  circle is marked .sub-prog-skipped for digital plans.
        ─────────────────────────────────────────────────────────────── -->
        <div class="sub-progress" role="list">
            <?php
            $steps = [
                    1 => 'Plan',
                    2 => 'Account',
                    3 => 'Address',
                    4 => 'Payment',
                    5 => 'Done',
            ];
            foreach ($steps as $n => $label): ?>
                <div class="sub-progress-step" id="sub-prog-<?= $n ?>"
                     data-step="<?= $n ?>" role="listitem">
                    <div class="sub-progress-circle"><?= $n ?></div>
                    <div class="sub-progress-label"><?= htmlspecialchars($label) ?></div>
                </div>
                <?php if ($n < count($steps)): ?>
                    <div class="sub-progress-line" aria-hidden="true"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- ══ Step 1: Choose Plan ════════════════════════════════════ -->
        <div class="sub-step" id="sub-step-1">
            <div class="sub-header">
                <h2 class="sub-title">Choose Your Plan</h2>
                <p class="sub-subtitle">Select the perfect plan for your needs</p>
            </div>

            <div class="sub-plans">
                <?php foreach ($plans as $plan):
                    $deliveryType = $plan->digital_download_url ? 'digital' : 'print';
                    ?>
                    <div class="sub-plan <?= $plan->is_featured ? 'featured' : '' ?>"
                         data-plan-id="<?= (int)$plan->id ?>"
                         data-plan-slug="<?= htmlspecialchars($plan->slug) ?>"
                         data-plan-name="<?= htmlspecialchars($plan->name) ?>"
                         data-plan-price="<?= (float)$plan->price ?>"
                         data-plan-currency="<?= htmlspecialchars($plan->currency) ?>"
                         data-plan-period="<?= htmlspecialchars($plan->billing_period) ?>"
                         data-plan-trial="<?= (int)($plan->trial_days ?? 0) ?>"
                         data-plan-delivery-type="<?= htmlspecialchars($deliveryType) ?>"
                         data-plan-one-time="<?= $plan->isOneTime() ? '1' : '0' ?>">

                        <?php if ($plan->is_featured): ?>
                            <div class="sub-plan-badge">⭐ Most Popular</div>
                        <?php endif; ?>

                        <div class="sub-plan-header">
                            <h3 class="sub-plan-name"><?= htmlspecialchars($plan->name) ?></h3>
                            <?php if ($plan->description): ?>
                                <p class="sub-plan-desc"><?= htmlspecialchars($plan->description) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="sub-plan-price">
                            <span class="sub-price-currency"><?= htmlspecialchars($plan->currency) ?></span>
                            <span class="sub-price-amount"><?= number_format((float)$plan->price, 2) ?></span>
                            <span class="sub-price-period">/<?= $plan->billing_period === 'month' ? 'mo' : 'yr' ?></span>
                        </div>

                        <?php if (($plan->trial_days ?? 0) > 0): ?>
                            <div class="sub-plan-trial">
                                ✓ <?= (int)$plan->trial_days ?> days free trial
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($plan->features)): ?>
                            <ul class="sub-plan-features">
                                <?php foreach (array_slice($plan->features, 0, 4) as $feature): ?>
                                    <li>
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                        <?= htmlspecialchars($feature) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?= $this->partial('checkout/components/form/button', [
                                'label' => 'Select Plan',
                                'variant' => 'primary',
                                'type' => 'button',
                                'class' => 'sub-plan-btn',
                                'onclick' => "selectPlan('" . htmlspecialchars($plan->slug) . "', " . $plan->id . ")",
                        ]) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ══ Step 2: Account (skipped when already logged in) ═══════ -->
        <div class="sub-step" id="sub-step-2" style="display: none;">
            <div class="sub-header">
                <h2 class="sub-title">Your Account</h2>
                <p class="sub-subtitle">Sign in or create an account to continue</p>
            </div>

            <div class="sub-auth-toggle" role="tablist">
                <button type="button" class="sub-auth-tab active" data-tab="register"
                        role="tab" aria-selected="true">Sign Up
                </button>
                <button type="button" class="sub-auth-tab" data-tab="login"
                        role="tab" aria-selected="false">Sign In
                </button>
            </div>

            <!-- Register -->
            <form id="sub-register-form" class="sub-form" novalidate>
                @csrf
                <div class="sub-form-row">
                    <?= $this->partial('checkout/components/form/form-group', [
                            'name' => 'first_name',
                            'id' => 'sub-reg-first-name',
                            'label' => 'First Name',
                            'type' => 'text',
                            'required' => true,
                            'attrs' => ['autocomplete' => 'given-name'],
                    ]) ?>
                    <?= $this->partial('checkout/components/form/form-group', [
                            'name' => 'last_name',
                            'id' => 'sub-reg-last-name',
                            'label' => 'Last Name',
                            'type' => 'text',
                            'required' => true,
                            'attrs' => ['autocomplete' => 'family-name'],
                    ]) ?>
                </div>
                <?= $this->partial('checkout/components/form/form-group', [
                        'name' => 'email',
                        'id' => 'sub-reg-email',
                        'label' => 'Email',
                        'type' => 'email',
                        'required' => true,
                        'attrs' => ['autocomplete' => 'email'],
                ]) ?>
                <?= $this->partial('checkout/components/form/form-group', [
                        'name' => 'password',
                        'id' => 'sub-reg-password',
                        'label' => 'Password',
                        'type' => 'password',
                        'required' => true,
                        'attrs' => ['autocomplete' => 'new-password', 'minlength' => '8'],
                ]) ?>
                <p class="sub-hint" style="margin-top:-.5rem;margin-bottom:.75rem;">Minimum 8 characters</p>
                <?= $this->partial('checkout/components/form/form-group', [
                        'name' => 'password_confirmation',
                        'id' => 'sub-reg-confirm',
                        'label' => 'Confirm Password',
                        'type' => 'password',
                        'required' => true,
                        'attrs' => ['autocomplete' => 'new-password'],
                ]) ?>
                <?= $this->partial('checkout/components/form/checkbox-control', [
                        'name' => 'terms',
                        'id' => 'sub-reg-terms',
                        'required' => true,
                        'label' => 'I agree to the <a href="/terms" target="_blank" style="color:var(--sub-primary);">Terms</a> and <a href="/privacy" target="_blank" style="color:var(--sub-primary);">Privacy Policy</a>',
                ]) ?>
                <div class="sub-error" id="sub-register-error" role="alert"></div>
                <?= $this->partial('checkout/components/form/button', [
                        'label' => 'Create Account & Continue',
                        'variant' => 'primary',
                        'type' => 'submit',
                ]) ?>
            </form>

            <!-- Login -->
            <form id="sub-login-form" class="sub-form" style="display:none;" novalidate>
                <?= $this->partial('checkout/components/form/form-group', [
                        'name' => 'email',
                        'id' => 'sub-login-email',
                        'label' => 'Email',
                        'type' => 'email',
                        'required' => true,
                        'attrs' => ['autocomplete' => 'email'],
                ]) ?>
                <?= $this->partial('checkout/components/form/form-group', [
                        'name' => 'password',
                        'id' => 'sub-login-password',
                        'label' => 'Password',
                        'type' => 'password',
                        'required' => true,
                        'attrs' => ['autocomplete' => 'current-password'],
                ]) ?>
                <div class="sub-error" id="sub-login-error" role="alert"></div>
                <?= $this->partial('checkout/components/form/button', [
                        'label' => 'Sign In & Continue',
                        'variant' => 'primary',
                        'type' => 'submit',
                ]) ?>
                <div style="text-align:center;margin-top:.75rem;font-size:.875rem;">
                    <a href="/member/forgot-password" target="_blank" style="color:var(--sub-primary);">
                        Forgot password?
                    </a>
                </div>
            </form>

            <?= $this->partial('checkout/components/form/button', [
                    'label' => '← Back to Plans',
                    'variant' => 'secondary',
                    'type' => 'button',
                    'style' => 'margin-top:1rem;',
                    'onclick' => 'subGoToStep(1)',
            ]) ?>
        </div>

        <!-- ══ Step 3: Delivery Address (print plans only) ════════════
             Hidden for digital plans — subGoToStep() skips it entirely.
             The <form> id is sub-address-form so JS can serialise it
             separately from the account forms above.
        ════════════════════════════════════════════════════════════════ -->
        <div class="sub-step" id="sub-step-3" style="display:none;">
            <div class="sub-header">
                <h2 class="sub-title">Delivery Address</h2>
                <p class="sub-subtitle">Where should we send your subscription?</p>
            </div>

            <form id="sub-address-form" novalidate>
                <?= $this->partial('checkout/components/form/billing-form', [
                        'member' => $member,
                        'requiresShipping' => true,
                        'checkoutMode' => 'single-page',
                ]) ?>
            </form>

            <!-- US auto-renewal consent is shown here because country is known
                 only after the address step. JS calls handleSubCountryChange()
                 via the select's onchange handler in the address form partial. -->
            <?= $this->partial('checkout/components/auto-renewal-consent', [
                    'showGlobal' => false,
                    'showUs' => true,
                    'usConsentId' => 'sub-us-renewal-consent',
            ]) ?>

            <div style="display:flex;gap:.75rem;margin-top:1.5rem;">
                <?= $this->partial('checkout/components/form/button', [
                        'label' => '← Back',
                        'variant' => 'secondary',
                        'type' => 'button',
                        'onclick' => 'subGoBack()',
                ]) ?>
                <?= $this->partial('checkout/components/form/button', [
                        'id' => 'sub-address-next-btn',
                        'label' => 'Continue to Payment →',
                        'variant' => 'primary',
                        'type' => 'button',
                        'onclick' => 'subAdvanceFromAddress()',
                ]) ?>
            </div>
        </div>

        <!-- ══ Step 4: Payment ════════════════════════════════════════ -->
        <div class="sub-step" id="sub-step-4" style="display:none;">
            <div class="sub-header sub-header--payment">
                <h2 class="sub-title sub-title--payment">Payment Details</h2>
                <p class="sub-subtitle">Secure checkout powered by Stripe</p>
            </div>

            <div class="sub-step4-layout">

                <!-- LEFT: payment inputs -->
                <div class="sub-step4-main">

                    @include('checkout/components/payment-method-selector')

                    <div id="sub-card-payment-section">
                        <?= $this->partial('checkout/components/saved-cards', [
                                'useDifferentCardOnClick' => 'showNewCardForm()',
                        ]) ?>

                        <?= $this->partial('checkout/components/stripe-card-element', [
                                'showBackButton' => true,
                                'backBtnOnClick' => 'showSavedCards()',
                        ]) ?>
                    </div>

                    <div id="sub-paypal-payment-section" data-payment-section="paypal" style="display:none;">
                        <p style="font-size:.875rem;color:var(--text-secondary);text-align:center;padding:1.25rem 0;">
                            You will be redirected to PayPal to complete your payment.
                        </p>
                    </div>

                    <?= $this->partial('checkout/components/voucher-section', [
                            'currency' => '',
                            'applyOnClick' => 'applyVoucher()',
                            'removeOnClick' => 'removeVoucher()',
                    ]) ?>

                    <!-- Global auto-renewal consent (all plans) -->
                    <?= $this->partial('checkout/components/auto-renewal-consent', [
                            'showGlobal' => true,
                            'showUs' => false,
                            'globalConsentId' => 'sub-global-renewal-consent',
                    ]) ?>

                </div>

                <!-- RIGHT: order summary sidebar -->
                <aside class="sub-step3-aside">
                    <div class="sub-aside-card">
                        <h3 class="sub-aside-heading">Order Summary</h3>
                        <div class="sub-aside-plan-name" id="sub-summary-plan-name">—</div>
                        <div class="sub-summary-row">
                            <span>Billing</span>
                            <strong id="sub-summary-billing">—</strong>
                        </div>
                        <div class="sub-summary-row" id="sub-discount-summary-row"
                             style="display:none;color:var(--success-color,#10b981);">
                            <span>Discount</span>
                            <strong id="sub-summary-discount">—</strong>
                        </div>
                        <div class="sub-summary-divider"></div>
                        <div class="sub-summary-row sub-summary-total">
                            <span>Total today</span>
                            <strong id="sub-summary-total">—</strong>
                        </div>
                        <div class="sub-aside-guarantee">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            30-day money-back guarantee
                        </div>
                    </div>

                    <?= $this->partial('checkout/components/form/button', [
                            'id' => 'sub-pay-btn',
                            'label' => 'Complete Subscription',
                            'variant' => 'primary',
                            'type' => 'button',
                            'style' => 'width:100%;margin-top:1rem;',
                            'onclick' => 'subProcessPayment()',
                    ]) ?>

                    <?= $this->partial('checkout/components/security-badge', [
                            'label' => 'Secured by Stripe · 256-bit SSL',
                    ]) ?>

                    <?= $this->partial('checkout/components/form/button', [
                            'label' => '← Back',
                            'variant' => 'secondary',
                            'type' => 'button',
                            'style' => 'width:100%;margin-top:.5rem;',
                            'onclick' => 'subGoBack()',
                    ]) ?>
                </aside>
            </div>
        </div>

        <!-- ══ Step 5: Success ════════════════════════════════════════ -->
        <div class="sub-step" id="sub-step-5" style="display:none;">
            <div class="sub-success">
                <div class="sub-success-icon" aria-hidden="true">
                    <svg width="56" height="56" viewBox="0 0 24 24" fill="none"
                         stroke="white" stroke-width="2.5" stroke-linecap="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <h2 class="sub-success-title">Welcome Aboard! 🎉</h2>
                <p class="sub-success-text">Your subscription is now active.</p>
                <?= $this->partial('checkout/components/form/button', [
                        'label' => 'Go to Dashboard',
                        'variant' => 'primary',
                        'type' => 'button',
                        'style' => 'margin-top:1.5rem;',
                        'onclick' => "window.location.href='/" . htmlspecialchars($site) . "/member/dashboard'",
                ]) ?>
            </div>
        </div>

        <!-- Loading overlay -->
        <div class="sub-loading" id="sub-loading" aria-live="polite">
            <div class="sub-spinner"></div>
            <p>Processing...</p>
        </div>

    </div><!-- /.sub-modal-container -->
</div><!-- /#subscriptionModal -->

<style>
    /* ── Root variables ─────────────────────────────────────────── */
    #subscriptionModal {
        --sub-primary: #6366f1;
        --sub-primary-dark: #4f46e5;
        --sub-text: #0f172a;
        --sub-muted: #64748b;
        --sub-border: #e2e8f0;
        --sub-bg: #ffffff;
        --sub-bg-alt: #f8fafc;
    }

    /* ── Shell ──────────────────────────────────────────────────── */
    .sub-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 99999;
    }

    .sub-modal.show {
        display: block;
        animation: subFadeIn .2s ease-out;
    }

    @keyframes subFadeIn {
        from {
            opacity: 0
        }
        to {
            opacity: 1
        }
    }

    .sub-modal-overlay {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .72);
        backdrop-filter: blur(6px);
    }

    .sub-modal-container {
        position: relative;
        width: 100%;
        max-width: 1200px;
        max-height: 92vh;
        margin: 4vh auto;
        background: var(--sub-bg);
        border-radius: 20px;
        padding: 2.5rem;
        overflow-y: auto;
        box-shadow: 0 25px 60px rgba(0, 0, 0, .22);
        animation: subSlideUp .35s cubic-bezier(.34, 1.56, .64, 1);
    }

    @keyframes subSlideUp {
        from {
            opacity: 0;
            transform: translateY(20px) scale(.97)
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1)
        }
    }

    /* ── Close ──────────────────────────────────────────────────── */
    .sub-modal-container .sub-modal-close.btn {
        position: absolute;
        top: 1.125rem;
        right: 1.125rem;
        width: 36px !important;
        height: 36px;
        padding: 0 !important;
        border-radius: 50% !important;
        background: var(--sub-bg-alt) !important;
        border: 1px solid var(--sub-border) !important;
        color: var(--sub-muted) !important;
        font-size: 1rem;
        line-height: 1;
        margin: 0 !important;
        z-index: 10;
        transition: transform .2s, background .2s;
    }

    .sub-modal-container .sub-modal-close.btn:hover {
        background: var(--sub-border) !important;
        transform: rotate(90deg);
    }

    /* ── Progress ───────────────────────────────────────────────── */
    .sub-progress {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 2rem;
    }

    .sub-progress-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .375rem;
    }

    .sub-progress-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--sub-bg-alt);
        border: 2px solid var(--sub-border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        color: var(--sub-muted);
        transition: all .3s;
    }

    .sub-progress-step.active .sub-progress-circle {
        background: var(--sub-primary);
        border-color: var(--sub-primary);
        color: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, .35);
    }

    .sub-progress-step.completed .sub-progress-circle {
        background: var(--success-color, #10b981);
        border-color: var(--success-color, #10b981);
        color: white;
    }

    /* Skipped steps (address step for digital plans) */
    .sub-progress-step.skipped .sub-progress-circle {
        opacity: .35;
    }

    .sub-progress-step.skipped .sub-progress-label {
        opacity: .35;
    }

    .sub-progress-label {
        font-size: .7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--sub-muted);
    }

    .sub-progress-step.active .sub-progress-label {
        color: var(--sub-primary);
    }

    .sub-progress-step.completed .sub-progress-label {
        color: var(--success-color, #10b981);
    }

    .sub-progress-line {
        width: 60px;
        height: 2px;
        background: var(--sub-border);
        margin: 0 .25rem 1.25rem;
        flex-shrink: 0;
    }

    /* ── Step header ─────────────────────────────────────────────── */
    .sub-header {
        text-align: center;
        margin-bottom: 1.75rem;
    }

    .sub-title {
        font-size: 1.625rem;
        font-weight: 800;
        color: var(--sub-text);
        margin-bottom: .375rem;
        letter-spacing: -.02em;
    }

    .sub-subtitle {
        font-size: .9375rem;
        color: var(--sub-muted);
    }

    /* ── Plans grid ─────────────────────────────────────────────── */
    .sub-plans {
        display: grid;
        grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.25rem;
    }

    .sub-plan {
        position: relative;
        background: var(--sub-bg);
        border: 2px solid var(--sub-border);
        border-radius: 14px;
        padding: 1.625rem 1.375rem;
        transition: border-color .2s, transform .2s, box-shadow .2s;
        cursor: pointer;
    }

    .sub-plan:hover {
        border-color: var(--sub-primary);
        transform: translateY(-3px);
        box-shadow: 0 10px 24px rgba(99, 102, 241, .12);
    }

    .sub-plan.featured {
        border-color: var(--sub-primary);
        background: linear-gradient(135deg, rgba(99, 102, 241, .04), rgba(139, 92, 246, .04));
    }

    .sub-plan-badge {
        position: absolute;
        top: -11px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, var(--sub-primary), var(--sub-primary-dark));
        color: white;
        padding: .25rem .875rem;
        border-radius: 20px;
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        white-space: nowrap;
    }

    .sub-plan-name {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--sub-text);
        margin-bottom: .25rem;
    }

    .sub-plan-desc {
        font-size: .8125rem;
        color: var(--sub-muted);
        margin-bottom: .875rem;
    }

    .sub-plan-price {
        display: flex;
        align-items: baseline;
        gap: .125rem;
        margin-bottom: .875rem;
        padding-bottom: .875rem;
        border-bottom: 1px solid var(--sub-border);
    }

    .sub-price-currency {
        font-size: .9375rem;
        color: var(--sub-muted);
        font-weight: 600;
    }

    .sub-price-amount {
        font-size: 2.125rem;
        color: var(--sub-primary);
        font-weight: 900;
        letter-spacing: -1px;
    }

    .sub-price-period {
        font-size: .875rem;
        color: var(--sub-muted);
        font-weight: 600;
    }

    .sub-plan-trial {
        display: flex;
        align-items: center;
        gap: .5rem;
        background: #fef3c7;
        color: #92400e;
        padding: .4rem .75rem;
        border-radius: 8px;
        font-size: .8125rem;
        font-weight: 600;
        margin-bottom: .875rem;
    }

    .sub-plan-features {
        list-style: none;
        padding: 0;
        margin: 0 0 1.125rem;
        display: flex;
        flex-direction: column;
        gap: .4rem;
    }

    .sub-plan-features li {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .8125rem;
        color: var(--sub-text);
    }

    .sub-plan-features svg {
        color: var(--success-color, #10b981);
        flex-shrink: 0;
    }

    .sub-modal-container .sub-plan-btn.btn {
        background: var(--sub-primary) !important;
        border: none !important;
        color: white !important;
        margin-bottom: 0 !important;
    }

    .sub-modal-container .sub-plan-btn.btn:hover:not(:disabled) {
        background: var(--sub-primary-dark) !important;
        box-shadow: 0 6px 16px rgba(99, 102, 241, .28);
    }

    /* ── Auth toggle ─────────────────────────────────────────────── */
    .sub-auth-toggle {
        display: flex;
        background: var(--sub-bg-alt);
        border-radius: 10px;
        padding: .25rem;
        margin: 0 auto 1.5rem;
        max-width: 360px;
    }

    .sub-auth-tab {
        flex: 1;
        padding: .5rem 1rem;
        background: transparent;
        border: none;
        border-radius: 7px;
        font-size: .9375rem;
        font-weight: 600;
        color: var(--sub-muted);
        cursor: pointer;
        transition: all .2s;
    }

    .sub-auth-tab.active {
        background: white;
        color: var(--sub-primary);
        box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
    }

    /* ── Auth forms ──────────────────────────────────────────────── */
    .sub-form {
        max-width: 420px;
        margin: 0 auto;
    }

    .sub-form-row {
        display: grid;
        grid-template-columns:1fr 1fr;
        gap: .875rem;
    }

    .sub-hint {
        font-size: .75rem;
        color: var(--sub-muted);
        display: block;
        margin-top: .25rem;
    }

    .sub-error {
        padding: .625rem .875rem;
        background: #fee2e2;
        color: #991b1b;
        border-radius: 8px;
        font-size: .875rem;
        margin-bottom: .875rem;
        display: none;
    }

    .sub-error:not(:empty) {
        display: block;
    }

    /* ── Address step ─────────────────────────────────────────────
       The form inside #sub-step-3 uses the standard .form-row /
       .form-group / .form-input classes already in the layout CSS.
       We just need spacing and a max-width constraint.
    ──────────────────────────────────────────────────────────────── */
    #sub-step-3 .form-section {
        margin-bottom: 1.5rem;
    }

    #sub-step-3 .form-row {
        display: grid;
        grid-template-columns:1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    #sub-step-3 .form-group {
        display: flex;
        flex-direction: column;
        gap: .25rem;
    }

    #sub-step-3 .form-group.full-width {
        grid-column: 1/-1;
    }

    #sub-step-3 .section-title {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: .875rem;
        padding-bottom: .625rem;
        border-bottom: 2px solid var(--sub-border);
    }

    /* US consent block sits just below the address form — hide until JS reveals */
    #sub-step-3 .auto-renewal-consent {
        display: none;
        margin-top: 1rem;
    }

    /* ── Step 4 two-column layout ────────────────────────────────── */
    .sub-step4-layout {
        display: grid;
        grid-template-columns:1fr 280px;
        gap: 2rem;
        align-items: start;
    }

    .sub-step3-aside {
        position: sticky;
        top: 1rem;
    }

    /* reuse existing aside class */
    .sub-aside-card {
        background: var(--sub-bg-alt);
        border: 1px solid var(--sub-border);
        border-radius: 14px;
        padding: 1.25rem;
    }

    .sub-aside-heading {
        font-size: .8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--sub-muted);
        margin-bottom: .875rem;
    }

    .sub-aside-plan-name {
        font-size: 1rem;
        font-weight: 700;
        color: var(--sub-text);
        margin-bottom: .75rem;
        padding-bottom: .75rem;
        border-bottom: 1px solid var(--sub-border);
    }

    .sub-aside-guarantee {
        display: flex;
        align-items: center;
        gap: .375rem;
        font-size: .75rem;
        color: var(--sub-muted);
        margin-top: .875rem;
        padding-top: .875rem;
        border-top: 1px solid var(--sub-border);
    }

    .sub-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .3rem 0;
        font-size: .875rem;
        color: var(--sub-text);
    }

    .sub-summary-divider {
        height: 1px;
        background: var(--sub-border);
        margin: .375rem 0;
    }

    .sub-summary-total {
        font-size: .9375rem;
        font-weight: 700;
        color: var(--sub-primary);
    }

    /* ── Loading overlay ─────────────────────────────────────────── */
    .sub-loading {
        display: none;
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, .9);
        backdrop-filter: blur(3px);
        border-radius: 20px;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        z-index: 50;
    }

    .sub-loading.show {
        display: flex;
    }

    .sub-spinner {
        width: 42px;
        height: 42px;
        border: 4px solid var(--border-color, #e2e8f0);
        border-top-color: var(--sub-primary);
        border-radius: 50%;
        animation: subSpin .7s linear infinite;
    }

    @keyframes subSpin {
        to {
            transform: rotate(360deg)
        }
    }

    .sub-loading p {
        font-size: .9375rem;
        font-weight: 600;
        color: var(--sub-text);
    }

    /* ── Success ─────────────────────────────────────────────────── */
    .sub-success {
        text-align: center;
        padding: 2.5rem 1.5rem;
        max-width: 420px;
        margin: 0 auto;
    }

    .sub-success-icon {
        width: 84px;
        height: 84px;
        background: linear-gradient(135deg, var(--success-color, #10b981), #059669);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.375rem;
        animation: subSuccessPop .45s cubic-bezier(.34, 1.56, .64, 1);
    }

    @keyframes subSuccessPop {
        0% {
            transform: scale(0);
            opacity: 0
        }
        60% {
            transform: scale(1.1)
        }
        100% {
            transform: scale(1);
            opacity: 1
        }
    }

    .sub-success-title {
        font-size: 1.625rem;
        font-weight: 800;
        color: var(--sub-text);
        margin-bottom: .625rem;
    }

    .sub-success-text {
        font-size: .9375rem;
        color: var(--sub-muted);
        line-height: 1.6;
    }

    /* ── Step 4 payment header overrides ────────────────────────── */
    .sub-header--payment {
        text-align: left;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--sub-border);
    }

    .sub-title--payment {
        font-size: 1.125rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .sub-header--payment .sub-subtitle {
        font-size: .8125rem;
        margin-top: .2rem;
    }

    /* ── Payment method selector spacing & separation ────────────── */
    #sub-step-4 .payment-method-selector,
    #sub-step-4 [class*="payment-method"] {
        margin-bottom: 1.25rem;
    }

    /* Give each payment option card a visible border + breathing room */
    #sub-step-4 .payment-option,
    #sub-step-4 [class*="payment-option"],
    #sub-step-4 label[class*="method"] {
        border: 1.5px solid var(--sub-border);
        border-radius: 10px;
        padding: .875rem 1rem;
        margin-bottom: .625rem;
        transition: border-color .2s, background .2s;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: .75rem;
    }

    #sub-step-4 .payment-option:last-child,
    #sub-step-4 [class*="payment-option"]:last-child {
        margin-bottom: 0;
    }

    #sub-step-4 .payment-option.selected,
    #sub-step-4 .payment-option:has(input:checked) {
        border-color: var(--sub-primary);
        background: rgba(99, 102, 241, .04);
    }

    /* Section label above saved cards */
    #sub-card-payment-section {
        margin-top: 1.25rem;
    }

    /* Separator between payment method selector and card section */
    #sub-card-payment-section::before {
        content: 'Card Details';
        display: block;
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--sub-muted);
        margin-bottom: .75rem;
        padding-bottom: .5rem;
        border-bottom: 1px solid var(--sub-border);
    }

    /* Saved card items — add card to each option */
    #sub-step-4 .saved-card,
    #sub-step-4 [class*="saved-card"] {
        border: 1.5px solid var(--sub-border);
        border-radius: 10px;
        padding: .75rem 1rem;
        margin-bottom: .5rem;
        transition: border-color .2s;
        cursor: pointer;
    }

    #sub-step-4 .saved-card.selected,
    #sub-step-4 [class*="saved-card"].selected,
    #sub-step-4 .saved-card:has(input:checked) {
        border-color: var(--sub-primary);
        background: rgba(99, 102, 241, .04);
    }

    /* PayPal section spacing */
    #sub-paypal-payment-section {
        margin-top: 1rem;
        padding: 1.5rem;
        border: 1.5px dashed var(--sub-border);
        border-radius: 10px;
    }

    @media (max-width: 640px) {
        .sub-modal-container {
            margin: 0;
            max-height: 100vh;
            border-radius: 0;
            padding: 1.375rem 1.125rem;
        }

        .sub-plans, .sub-step4-layout {
            grid-template-columns:1fr;
        }

        .sub-step3-aside {
            position: static;
            order: -1;
        }

        .sub-form-row, #sub-step-3 .form-row {
            grid-template-columns:1fr;
        }

        .sub-progress-label {
            display: none;
        }

        .sub-progress-line {
            width: 28px;
        }

        .sub-title {
            font-size: 1.375rem;
        }
    }
</style>

<script src="https://js.stripe.com/v3/"></script>

@js('saved-cards.js')
@js('cart-utils.js')

<script>
    /* ── Constants ──────────────────────────────────────────────── */
    const SITE = <?= json_encode(\App\Framework\Support\SiteContext::slug()) ?>;
    const SUB_STRIPE_KEY = <?= json_encode($_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key')) ?>;
    const SUB_IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
    const MODAL_COOLDOWN_MS = 24 * 60 * 60 * 1000;
    const MODAL_STORAGE_KEY = 'sub_modal_last_' + SITE;
    (function () {
        'use strict';

        /*
         * Globals required by cart-utils.js and saved-cards.js.
         */
        window.API_BASE = <?= json_encode($apiBase) ?>;
        window.PLAN_CURRENCY = '';
        window.INITIAL_SUBTOTAL = 0;
        window.INITIAL_SHIPPING = 0;
        window.TAX_RATE = 0;
        window.isLoggedIn = SUB_IS_LOGGED_IN;
        window.currentMember = <?= $isLoggedIn
                ? json_encode([
                        'id' => $member->id,
                        'email' => $member->email,
                        'first_name' => $member->first_name ?? '',
                        'last_name' => $member->last_name ?? '',
                ])
                : 'null' ?>;
        window.appliedVoucher = null;
        window.selectedCardId = null;
        window.requiresShipping = true;

        class ModalStripeService {
            constructor(stripeKey) {
                this.stripeKey = stripeKey;
                this.stripe = null;
                this.cardElement = null;
                this.cardMounted = false;
            }

            init() {
                if (typeof Stripe === 'undefined' || !this.stripeKey) return;

                this.stripe = Stripe(this.stripeKey);
                this.cardElement = this.stripe.elements().create('card', {
                    hidePostalCode: true,
                    style: {
                        base: {
                            fontSize: '15px',
                            color: '#0f172a',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                            '::placeholder': {color: '#94a3b8'},
                        },
                        invalid: {color: '#ef4444', iconColor: '#ef4444'},
                    },
                });
            }

            mountCard() {
                if (this.cardMounted || !this.cardElement) return;
                const element = document.getElementById('card-element');
                if (!element) return;

                this.cardElement.mount('#card-element');
                this.cardElement.on('change', (event) => {
                    const errorElement = document.getElementById('card-errors');
                    if (errorElement) errorElement.textContent = event.error ? event.error.message : '';
                });
                this.cardMounted = true;
            }

            async createPaymentMethod(billingDetails) {
                if (window.selectedCardId) return {id: window.selectedCardId};
                if (!this.stripe || !this.cardElement) {
                    throw new Error('Payment form not ready. Please refresh and try again.');
                }

                const {paymentMethod, error} = await this.stripe.createPaymentMethod({
                    type: 'card',
                    card: this.cardElement,
                    billing_details: billingDetails,
                });

                if (error) throw new Error(error.message);
                return paymentMethod;
            }

            async confirmOneTimePayment(clientSecret, billingDetails) {
                if (!this.stripe) {
                    throw new Error('Payment form not ready. Please refresh and try again.');
                }

                const result = window.selectedCardId
                    ? await this.stripe.confirmCardPayment(clientSecret, {payment_method: window.selectedCardId})
                    : await this.stripe.confirmCardPayment(clientSecret, {
                        payment_method: {
                            card: this.cardElement,
                            billing_details: billingDetails,
                        },
                        setup_future_usage: 'off_session',
                    });

                if (result.error) throw new Error(result.error.message);
                return result.paymentIntent;
            }
        }

        class ModalApiService {
            constructor(apiBase, site) {
                this.apiBase = apiBase;
                this.site = site;
            }

            async request(url, options = {}) {
                const response = await fetch(url, options);
                return response.json();
            }

            async register(payload) {
                return this.request('/' + this.site + '/member/register', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload),
                });
            }

            async login(payload) {
                return this.request('/' + this.site + '/member/login', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload),
                });
            }

            async markShown() {
                return this.request('/' + this.site + '/api/subscription-modal/mark-shown', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                });
            }

            async addPlanToCart(plan) {
                return this.request(this.apiBase + '/cart/subscription', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        plan_id: plan.id,
                        delivery_type: plan.deliveryType,
                    }),
                });
            }

            async createSubscriptionCheckout(payload) {
                return this.request(this.apiBase + '/subscriptions/onetime/checkout', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload),
                });
            }

            async confirmSubscriptionPayment(payload) {
                return this.request(this.apiBase + '/subscriptions/onetime/confirm-payment', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload),
                });
            }

            async rollbackCartItem(cartItemId) {
                if (!cartItemId) return;
                try {
                    await fetch(this.apiBase + '/cart/' + cartItemId, {method: 'DELETE'});
                } catch (_) {
                    // intentionally ignored
                }
            }
        }

        class SubscriptionModalManager {
            constructor({stripeService, apiService}) {
                this.stripe = stripeService;
                this.api = apiService;
                this.selectedPlan = null;
                this.clientSecret = null;
                this.subscriptionId = null;
                this.orderId = null;
                this.stepCount = 5;
            }

            init() {
                this.stripe.init();
                this.bindEvents();
                window.onPaymentMethodChange = (method) => this.handlePaymentMethodChange(method);
            }

            bindEvents() {
                document.querySelector('.sub-modal-overlay')?.addEventListener('click', () => this.close());
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') this.close();
                });

                document.getElementById('sub-register-form')?.addEventListener('submit', (event) => this.handleRegister(event));
                document.getElementById('sub-login-form')?.addEventListener('submit', (event) => this.handleLogin(event));

                document.querySelectorAll('.sub-auth-tab').forEach((tab) => {
                    tab.addEventListener('click', () => this.switchAuthTab(tab.dataset.tab));
                });
            }

            needsAccount() {
                return !window.isLoggedIn;
            }

            needsAddress() {
                return this.selectedPlan && this.selectedPlan.deliveryType === 'print';
            }

            nextStep(current) {
                let next = current + 1;
                if (next === 2 && !this.needsAccount()) next++;
                if (next === 3 && !this.needsAddress()) next++;
                return Math.min(next, this.stepCount);
            }

            prevStep(current) {
                let previous = current - 1;
                if (previous === 3 && !this.needsAddress()) previous--;
                if (previous === 2 && !this.needsAccount()) previous--;
                return Math.max(previous, 1);
            }

            currentStep() {
                for (let i = 1; i <= this.stepCount; i++) {
                    const element = document.getElementById(`sub-step-${i}`);
                    if (element && element.style.display !== 'none') return i;
                }
                return 1;
            }

            show(planSlug, planId, isManual) {
                const modal = document.getElementById('subscriptionModal');
                if (!modal) return;

                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
                checkLoginStatus();

                if (planSlug && planId) {
                    const planElement = document.querySelector(`.sub-plan[data-plan-slug="${planSlug}"]`);
                    if (planElement) {
                        this.readPlanData(planElement);
                        this.goToStep(this.nextStep(1));
                    } else {
                        this.goToStep(1);
                    }
                } else {
                    this.goToStep(1);
                }

                if (!isManual) {
                    this.api.markShown();
                    this.trackModalShown();
                }
            }

            close() {
                const modal = document.getElementById('subscriptionModal');
                if (!modal) return;
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }

            goToStep(step) {
                for (let i = 1; i <= this.stepCount; i++) {
                    const section = document.getElementById(`sub-step-${i}`);
                    const progress = document.getElementById(`sub-prog-${i}`);
                    if (section) section.style.display = 'none';
                    if (progress) {
                        progress.classList.remove('active', 'completed', 'skipped');
                        if (i < step) {
                            if (i === 3 && !this.needsAddress()) {
                                progress.classList.add('skipped');
                            } else if (i === 2 && !this.needsAccount()) {
                                progress.classList.add('skipped');
                            } else {
                                progress.classList.add('completed');
                            }
                        }
                    }
                }

                const active = document.getElementById(`sub-step-${step}`);
                const progress = document.getElementById(`sub-prog-${step}`);
                if (active) active.style.display = 'block';
                if (progress) progress.classList.add('active');

                if (step === 4) {
                    setTimeout(() => this.stripe.mountCard(), 50);
                    this.updatePaymentSummary();
                    if (window.isLoggedIn && typeof window.loadSavedCards === 'function') {
                        window.loadSavedCards();
                    }
                }
            }

            goBack() {
                this.goToStep(this.prevStep(this.currentStep()));
            }

            readPlanData(planElement) {
                this.selectedPlan = {
                    id: parseInt(planElement.dataset.planId, 10),
                    slug: planElement.dataset.planSlug,
                    name: planElement.dataset.planName,
                    price: parseFloat(planElement.dataset.planPrice),
                    currency: planElement.dataset.planCurrency,
                    period: planElement.dataset.planPeriod,
                    trial: parseInt(planElement.dataset.planTrial, 10) || 0,
                    deliveryType: planElement.dataset.planDeliveryType,
                    isOneTime: planElement.dataset.planOneTime === '1',
                };

                window.PLAN_CURRENCY = this.selectedPlan.currency;
                window.INITIAL_SUBTOTAL = this.selectedPlan.price;
            }

            selectPlan(slug) {
                const planElement = document.querySelector(`[data-plan-slug="${slug}"]`);
                if (!planElement) return;
                this.readPlanData(planElement);
                this.goToStep(this.nextStep(1));

                alert(this.selectedPlan.id + ' ' + this.selectedPlan.isOneTime)
            }

            updatePaymentSummary() {
                if (!this.selectedPlan) return;
                const plan = this.selectedPlan;
                const periodLabel = plan.period === 'month' ? 'monthly' : 'yearly';
                const discount = window.appliedVoucher ? parseFloat(window.appliedVoucher.discount) : 0;
                const discountRow = document.getElementById('sub-discount-summary-row');

                document.getElementById('sub-summary-plan-name').textContent = plan.name;
                document.getElementById('sub-summary-billing').textContent = 'Billed ' + periodLabel;

                if (discount > 0 && discountRow) {
                    discountRow.style.display = 'flex';
                    document.getElementById('sub-summary-discount').textContent = '-' + plan.currency + discount.toFixed(2);
                } else if (discountRow) {
                    discountRow.style.display = 'none';
                }

                const total = plan.trial > 0 ? 0 : Math.max(0, plan.price - discount);
                document.getElementById('sub-summary-total').textContent = plan.trial > 0
                    ? 'FREE (then ' + plan.currency + plan.price.toFixed(2) + '/' + (plan.period === 'month' ? 'mo' : 'yr') + ')'
                    : plan.currency + total.toFixed(2);
            }

            handlePaymentMethodChange(method) {
                const cardSection = document.getElementById('sub-card-payment-section');
                const paypalSection = document.getElementById('sub-paypal-payment-section');
                if (cardSection) cardSection.style.display = method === 'card' ? 'block' : 'none';
                if (paypalSection) paypalSection.style.display = method === 'paypal' ? 'block' : 'none';

                if (method === 'card') {
                    if (window.savedCards && window.savedCards.length > 0
                        && typeof window.displaySavedCards === 'function') {
                        window.displaySavedCards();
                    } else {
                        this.stripe.mountCard();
                    }
                }
            }

            setLoading(isLoading, message = 'Processing...') {
                const overlay = document.getElementById('sub-loading');
                const button = document.getElementById('sub-pay-btn');
                const nextButton = document.getElementById('sub-address-next-btn');
                const loadingText = overlay?.querySelector('p');

                if (loadingText) loadingText.textContent = message;
                if (overlay) overlay.classList.toggle('show', isLoading);
                if (button) button.disabled = isLoading;
                if (nextButton) nextButton.disabled = isLoading;
            }

            setCardError(message) {
                const element = document.getElementById('card-errors');
                if (element) element.textContent = message;
            }

            setFormError(id, message) {
                const element = document.getElementById(id);
                if (element) element.textContent = message;
            }

            clearFormError(id) {
                this.setFormError(id, '');
            }

            switchAuthTab(target) {
                document.querySelectorAll('.sub-auth-tab').forEach((tab) => {
                    tab.classList.remove('active');
                    tab.setAttribute('aria-selected', 'false');
                });

                const activeTab = document.querySelector(`.sub-auth-tab[data-tab="${target}"]`);
                activeTab?.classList.add('active');
                activeTab?.setAttribute('aria-selected', 'true');

                document.getElementById('sub-register-form').style.display = target === 'register' ? 'block' : 'none';
                document.getElementById('sub-login-form').style.display = target === 'login' ? 'block' : 'none';
            }

            async handleRegister(event) {
                event.preventDefault();
                const data = Object.fromEntries(new FormData(event.currentTarget));
                this.clearFormError('sub-register-error');

                if (data.password !== data.password_confirmation) {
                    this.setFormError('sub-register-error', 'Passwords do not match.');
                    return;
                }
                if (data.password.length < 8) {
                    this.setFormError('sub-register-error', 'Password must be at least 8 characters.');
                    return;
                }
                if (!data.terms) {
                    this.setFormError('sub-register-error', 'You must agree to the Terms to continue.');
                    return;
                }

                this.setLoading(true, 'Creating your account...');
                try {
                    const result = await this.api.register(data);
                    if (result.success) {
                        window.currentMember = result.member;
                        window.isLoggedIn = true;
                        this.goToStep(this.nextStep(2));
                    } else {
                        this.setFormError('sub-register-error', result.message || 'Registration failed.');
                    }
                } catch (_) {
                    this.setFormError('sub-register-error', 'An error occurred. Please try again.');
                } finally {
                    this.setLoading(false);
                }
            }

            async handleLogin(event) {
                event.preventDefault();
                const data = Object.fromEntries(new FormData(event.currentTarget));
                this.clearFormError('sub-login-error');

                this.setLoading(true, 'Signing you in...');
                try {
                    const result = await this.api.login(data);
                    if (result.success) {
                        window.currentMember = result.member;
                        window.isLoggedIn = true;
                        this.goToStep(this.nextStep(2));
                    } else {
                        this.setFormError('sub-login-error', result.message || 'Login failed.');
                    }
                } catch (_) {
                    this.setFormError('sub-login-error', 'An error occurred. Please try again.');
                } finally {
                    this.setLoading(false);
                }
            }

            validateAddressForm() {
                const form = document.getElementById('sub-address-form');
                const errors = form.querySelectorAll('.form-error');
                errors.forEach((element) => {
                    element.textContent = '';
                });

                const required = typeof selectedAddressId !== 'undefined' && selectedAddressId
                    ? ['first_name', 'last_name', 'email']
                    : ['first_name', 'last_name', 'email', 'address', 'city', 'postal_code', 'country'];

                const data = Object.fromEntries(new FormData(form));
                let hasErrors = false;

                for (const field of required) {
                    if (!data[field]?.trim()) {
                        const errorElement = form.querySelector(`#error-${field}`);
                        if (errorElement) errorElement.textContent = 'This field is required';
                        hasErrors = true;
                    }
                }

                if (hasErrors) return false;

                const usBlock = document.getElementById('us-renewal-consent-block');
                const usConsent = document.getElementById('sub-us-renewal-consent');
                if (data.country === 'US' && usConsent && !usConsent.checked) {
                    usBlock?.classList.add('consent-error');
                    return false;
                }

                usBlock?.classList.remove('consent-error');
                return true;
            }

            advanceFromAddress() {
                if (!this.validateAddressForm()) return;
                this.setLoading(true, 'Preparing payment step...');
                setTimeout(() => {
                    this.goToStep(this.nextStep(3));
                    this.setLoading(false);
                }, 150);
            }

            handleCountryChange(code) {
                const usBlock = document.getElementById('us-renewal-consent-block');
                if (!usBlock) return;

                if (code === 'US') {
                    usBlock.style.display = 'block';
                    usBlock.classList.remove('consent-error');
                } else {
                    usBlock.style.display = 'none';
                    const checkbox = document.getElementById('sub-us-renewal-consent');
                    if (checkbox) checkbox.checked = false;
                }
            }

            getBillingDetails() {
                const member = window.currentMember;
                return {
                    name: member ? (member.first_name + ' ' + member.last_name).trim() : '',
                    email: member?.email ?? '',
                };
            }

            buildCheckoutPayload() {
                const globalConsent = document.getElementById('sub-global-renewal-consent');
                const globalBlock = document.getElementById('global-renewal-consent-block');

                if (globalConsent && !globalConsent.checked) {
                    globalBlock?.classList.add('consent-error');
                    globalBlock?.scrollIntoView({behavior: 'smooth', block: 'center'});
                    throw new Error('Please confirm the subscription terms to continue.');
                }
                globalBlock?.classList.remove('consent-error');

                if (!this.selectedPlan) {
                    throw new Error('No plan selected. Please go back and choose a plan.');
                }

                const data = {
                    isOneTimeSubscription: true,
                    global_renewal_consent: '1',
                    one_time_subscription: this.selectedPlan.isOneTime,
                };

                if (window.currentMember) data.member_id = window.currentMember.id;

                if (this.needsAddress()) {
                    const addressForm = document.getElementById('sub-address-form');
                    if (addressForm) Object.assign(data, Object.fromEntries(new FormData(addressForm)));
                }

                if (window.appliedVoucher) {
                    data.voucher_code = window.appliedVoucher.code;
                    data.voucher_id = window.appliedVoucher.voucher_id;
                    data.discount_amount = window.appliedVoucher.discount;
                }

                return data;
            }

            normalizeSubscriptionIds(value) {
                return Array.isArray(value) ? value : [value];
            }

            async confirmPayment(intentId, paymentMethodId) {
                const payload = {order_id: this.orderId};
                if (intentId) payload.payment_intent_id = intentId;
                if (paymentMethodId) payload.payment_method_id = paymentMethodId;

                const subscriptionIds = this.normalizeSubscriptionIds(this.subscriptionId);
                if (subscriptionIds.length === 1) {
                    payload.subscription_id = subscriptionIds[0];
                } else {
                    payload.subscription_ids = subscriptionIds;
                }

                const result = await this.api.confirmSubscriptionPayment(payload);
                if (!result.success) {
                    throw new Error(result.message || 'Payment confirmation failed.');
                }

                this.goToStep(5);
            }

            async processPayment() {
                this.setCardError('');

                let cartItemId = null;

                try {
                    const checkoutData = this.buildCheckoutPayload();

                    this.setLoading(true, 'Adding plan to cart...');
                    const cartResult = await this.api.addPlanToCart(this.selectedPlan);
                    if (!cartResult.success) {
                        throw new Error(cartResult.message || 'Could not add plan to cart. Please try again.');
                    }
                    cartItemId = cartResult.item?.id ?? cartResult.id ?? null;

                    this.setLoading(true, 'Creating your subscription...');
                    const result = await this.api.createSubscriptionCheckout(checkoutData);
                    if (!result.success) {
                        await this.api.rollbackCartItem(cartItemId);
                        throw new Error(result.message || 'Checkout failed. Please try again.');
                    }

                    const contexts = result.data?.stripe_contexts;
                    this.clientSecret = contexts
                        ? contexts[Object.keys(contexts)[0]].client_secret
                        : (result.data?.client_secret ?? null);
                    this.subscriptionId = result.data?.subscription_ids ?? result.data?.subscription_id ?? null;
                    this.orderId = result.data?.order_id ?? null;

                    if (this.selectedPlan.isOneTime) {
                        this.setLoading(true, 'Confirming your payment...');
                        const paymentIntent = await this.stripe.confirmOneTimePayment(
                            this.clientSecret,
                            this.getBillingDetails()
                        );

                        if (paymentIntent?.status === 'succeeded') {
                            this.setLoading(true, 'Finalising your subscription...');
                            await this.confirmPayment(paymentIntent.id, null);
                        }
                        return;
                    }

                    this.setLoading(true, 'Saving your payment method...');
                    const paymentMethod = await this.stripe.createPaymentMethod(this.getBillingDetails());
                    this.setLoading(true, 'Activating your subscription...');
                    await this.confirmPayment(null, paymentMethod.id);
                } catch (error) {
                    console.error('Modal payment error:', error);
                    this.setCardError(error.message || 'An unexpected error occurred. Please try again.');
                } finally {
                    this.setLoading(false);
                }
            }

            shouldShow() {
                const last = localStorage.getItem(MODAL_STORAGE_KEY);
                return !last || (Date.now() - parseInt(last, 10)) >= MODAL_COOLDOWN_MS;
            }

            trackModalShown() {
                if (!SUB_IS_LOGGED_IN) localStorage.setItem(MODAL_STORAGE_KEY, String(Date.now()));
            }
        }

        window.subscriptionModalManager = new SubscriptionModalManager({
            stripeService: new ModalStripeService(SUB_STRIPE_KEY),
            apiService: new ModalApiService(window.API_BASE, SITE),
        });
        window.subscriptionModalManager.init();

        <?php if ($subscriptionModalData['show_modal'] ?? false): ?>
        setTimeout(() => {
            if (SUB_IS_LOGGED_IN || window.subscriptionModalManager.shouldShow()) {
                window.subscriptionModalManager.show(null, null, false);
            }
        }, 3000);
        <?php endif; ?>

        window.showSubscriptionModal = (planSlug, planId, isManual) =>
            window.subscriptionModalManager.show(planSlug, planId, isManual);
        window.closeSubscriptionModal = () => window.subscriptionModalManager.close();
        window.subGoToStep = (step) => window.subscriptionModalManager.goToStep(step);
        window.subGoBack = () => window.subscriptionModalManager.goBack();
        window.selectPlan = (slug) => window.subscriptionModalManager.selectPlan(slug);
        window.subProcessPayment = () => window.subscriptionModalManager.processPayment();
        window.subAdvanceFromAddress = () => window.subscriptionModalManager.advanceFromAddress();
        window.handleSubCountryChange = (code) => window.subscriptionModalManager.handleCountryChange(code);
        window.showSubscriptionModalWithPlan = (slug, id) => window.subscriptionModalManager.show(slug, id, true);
        window.openSubscriptionModal = () => window.subscriptionModalManager.show(null, null, true);

    })();
</script>
