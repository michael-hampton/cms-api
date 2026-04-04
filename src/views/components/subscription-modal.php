<?php
/**
 * Subscription modal.
 *
 * Uses shared checkout components:
 *   - checkout/components/saved-cards            (#saved-cards-section / #saved-cards-list)
 *   - checkout/components/stripe-card-element    (#card-element / #card-errors)
 *   - checkout/components/voucher-section        (#voucher-input / #applied-voucher / #discount-row)
 *   - checkout/components/auto-renewal-consent   (#global-renewal-consent-block)
 *   - checkout/components/security-badge
 *   - checkout/components/form/button
 *
 * JS dependencies (loaded by the parent layout before this partial):
 *   - cart-utils.js   (showAlert, showVoucherMessage, applyVoucher, removeVoucher,
 *                       displayAppliedVoucher, updateTotals)
 *   - saved-cards.js  (loadSavedCards, displaySavedCards, selectSavedCard,
 *                       showNewCardForm, showSavedCards)
 *   - payment-method-selector.js  (initialises .payment-method click handlers)
 *
 * Payment flow mirrors checkout/index.php:
 *   POST /api/{site}/subscriptions/onetime/checkout
 *   → stripe.confirmCardPayment(clientSecret)
 *   → POST /api/{site}/subscriptions/onetime/confirm-payment
 *   → step 4 (success)
 *
 * Globals set here for shared JS files:
 *   window.API_BASE, window.PLAN_CURRENCY, window.INITIAL_SUBTOTAL,
 *   window.INITIAL_SHIPPING, window.TAX_RATE,
 *   window.isLoggedIn, window.currentMember,
 *   window.selectedCardId, window.appliedVoucher
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

        <!-- Close button (shared component) -->
        <?= $this->partial('checkout/components/form/button', [
                'id' => 'sub-modal-close-btn',
                'label' => '✕',
                'variant' => 'secondary',
                'type' => 'button',
                'class' => 'sub-modal-close',
                'onclick' => 'closeSubscriptionModal()',
        ]) ?>

        <!-- Progress indicator -->
        <div class="sub-progress" role="list">
            <?php
            $steps = [1 => 'Choose Plan', 2 => 'Account', 3 => 'Payment', 4 => 'Complete'];
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

        <!-- ══ Step 1: Choose Plan ═══════════════════════════════════ -->
        <div class="sub-step" id="sub-step-1">
            <div class="sub-header">
                <h2 class="sub-title">Choose Your Plan</h2>
                <p class="sub-subtitle">Select the perfect plan for your needs</p>
            </div>

            <div class="sub-plans">
                <?php foreach ($plans as $plan): ?>
                    <div class="sub-plan <?= $plan->is_featured ? 'featured' : '' ?>"
                         data-plan-id="<?= (int)$plan->id ?>"
                         data-plan-slug="<?= htmlspecialchars($plan->slug) ?>"
                         data-plan-name="<?= htmlspecialchars($plan->name) ?>"
                         data-plan-price="<?= (float)$plan->price ?>"
                         data-plan-currency="<?= htmlspecialchars($plan->currency) ?>"
                         data-plan-period="<?= htmlspecialchars($plan->billing_period) ?>"
                         data-plan-trial="<?= (int)($plan->trial_days ?? 0) ?>">

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

        <!-- ══ Step 2: Account (skipped when already logged in) ══════ -->
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
                    <div class="sub-form-group">
                        <label class="sub-label" for="sub-reg-first-name">First Name *</label>
                        <input type="text" id="sub-reg-first-name" name="first_name"
                               class="sub-input" required autocomplete="given-name">
                    </div>
                    <div class="sub-form-group">
                        <label class="sub-label" for="sub-reg-last-name">Last Name *</label>
                        <input type="text" id="sub-reg-last-name" name="last_name"
                               class="sub-input" required autocomplete="family-name">
                    </div>
                </div>
                <div class="sub-form-group">
                    <label class="sub-label" for="sub-reg-email">Email *</label>
                    <input type="email" id="sub-reg-email" name="email"
                           class="sub-input" required autocomplete="email">
                </div>
                <div class="sub-form-group">
                    <label class="sub-label" for="sub-reg-password">Password *</label>
                    <input type="password" id="sub-reg-password" name="password"
                           class="sub-input" required minlength="8" autocomplete="new-password">
                    <span class="sub-hint">Minimum 8 characters</span>
                </div>
                <div class="sub-form-group">
                    <label class="sub-label" for="sub-reg-confirm">Confirm Password *</label>
                    <input type="password" id="sub-reg-confirm" name="password_confirmation"
                           class="sub-input" required autocomplete="new-password">
                </div>
                <div class="sub-form-group">
                    <label style="display: flex; align-items: flex-start; gap: .625rem; cursor: pointer;
                                  font-size: .875rem; color: var(--sub-text);">
                        <input type="checkbox" name="terms"
                               style="margin-top: .2rem; width: 16px; height: 16px; flex-shrink: 0;" required>
                        <span>
                            I agree to the
                            <a href="/terms" target="_blank" style="color: var(--sub-primary);">Terms</a>
                            and
                            <a href="/privacy" target="_blank" style="color: var(--sub-primary);">Privacy Policy</a>
                        </span>
                    </label>
                </div>
                <div class="sub-error" id="sub-register-error" role="alert"></div>
                <?= $this->partial('checkout/components/form/button', [
                        'label' => 'Create Account & Continue',
                        'variant' => 'primary',
                        'type' => 'submit',
                ]) ?>
            </form>

            <!-- Login -->
            <form id="sub-login-form" class="sub-form" style="display: none;" novalidate>
                <div class="sub-form-group">
                    <label class="sub-label" for="sub-login-email">Email *</label>
                    <input type="email" id="sub-login-email" name="email"
                           class="sub-input" required autocomplete="email">
                </div>
                <div class="sub-form-group">
                    <label class="sub-label" for="sub-login-password">Password *</label>
                    <input type="password" id="sub-login-password" name="password"
                           class="sub-input" required autocomplete="current-password">
                </div>
                <div class="sub-error" id="sub-login-error" role="alert"></div>
                <?= $this->partial('checkout/components/form/button', [
                        'label' => 'Sign In & Continue',
                        'variant' => 'primary',
                        'type' => 'submit',
                ]) ?>
                <div style="text-align: center; margin-top: .75rem; font-size: .875rem;">
                    <a href="/member/forgot-password" target="_blank" style="color: var(--sub-primary);">
                        Forgot password?
                    </a>
                </div>
            </form>

            <?= $this->partial('checkout/components/form/button', [
                    'label' => '← Back to Plans',
                    'variant' => 'secondary',
                    'type' => 'button',
                    'style' => 'margin-top: 1rem;',
                    'onclick' => 'subGoToStep(1)',
            ]) ?>
        </div>

        <!-- ══ Step 3: Payment ════════════════════════════════════════ -->
        <div class="sub-step" id="sub-step-3" style="display: none;">
            <div class="sub-header">
                <h2 class="sub-title">Payment Details</h2>
                <p class="sub-subtitle">Secure checkout powered by Stripe</p>
            </div>

            <!-- Plan summary (populated dynamically by subUpdatePaymentSummary) -->
            <div class="sub-payment-summary">
                <div class="sub-summary-row">
                    <span>Plan</span>
                    <strong id="sub-summary-plan-name">—</strong>
                </div>
                <div class="sub-summary-row">
                    <span>Billing</span>
                    <strong id="sub-summary-billing">—</strong>
                </div>
                <div class="sub-summary-row" id="sub-discount-summary-row"
                     style="display: none; color: var(--success-color);">
                    <span>Discount</span>
                    <strong id="sub-summary-discount">—</strong>
                </div>
                <div class="sub-summary-divider"></div>
                <div class="sub-summary-row sub-summary-total">
                    <span>Total today</span>
                    <strong id="sub-summary-total">—</strong>
                </div>
            </div>

            <!--
                Payment method selector.
                Uses the shared .payment-method / .payment-methods CSS from the
                layout, and payment-method-selector.js for click handling.
                window.onPaymentMethodChange (defined below) toggles sections.
            -->
            @include('checkout/components/payment-method-selector')

            <!-- Card payment section -->
            <div id="sub-card-payment-section">

                <!--
                    Saved payment methods.
                    Targets #saved-cards-section / #saved-cards-list / #new-card-section
                    and #back-to-saved-cards-btn — the same IDs saved-cards.js expects.
                    loadSavedCards() is called when step 3 becomes visible.
                -->
                <?= $this->partial('checkout/components/saved-cards', [
                        'useDifferentCardOnClick' => 'showNewCardForm()',
                ]) ?>

                <!--
                    Stripe card element.
                    Mounts into #card-element; errors written to #card-errors.
                    Back button #back-to-saved-cards-btn toggled by showNewCardForm().
                -->
                <?= $this->partial('checkout/components/stripe-card-element', [
                        'showBackButton' => true,
                        'backBtnOnClick' => 'showSavedCards()',
                ]) ?>

            </div>

            <!-- PayPal section (shown when PayPal method is selected) -->
            <div id="sub-paypal-payment-section" data-payment-section="paypal" style="display: none;">
                <p style="font-size: .875rem; color: var(--text-secondary); text-align: center; padding: 1.25rem 0;">
                    You will be redirected to PayPal to complete your payment.
                </p>
            </div>

            <!--
                Voucher section.
                applyVoucher() and removeVoucher() from cart-utils.js handle the
                full flow: API call → DOM update → total recalculation.
                They read window.appliedVoucher, PLAN_CURRENCY, and INITIAL_SUBTOTAL
                which are kept in sync when a plan is selected.
            -->
            <?= $this->partial('checkout/components/voucher-section', [
                    'currency' => '', // JS sets PLAN_CURRENCY at runtime via subReadPlanData
                    'applyOnClick' => 'applyVoucher()',
                    'removeOnClick' => 'removeVoucher()',
            ]) ?>

            <!--
                Auto-renewal consent.
                Uses a scoped checkbox ID to avoid collision if the full checkout
                page is simultaneously present in the DOM.
            -->
            <?= $this->partial('checkout/components/auto-renewal-consent', [
                    'showGlobal' => true,
                    'showUs' => false,
                    'globalConsentId' => 'sub-global-renewal-consent',
            ]) ?>

            <!-- Submit (shared button component) -->
            <?= $this->partial('checkout/components/form/button', [
                    'id' => 'sub-pay-btn',
                    'label' => 'Complete Subscription',
                    'variant' => 'primary',
                    'type' => 'button',
                    'style' => 'margin-top: 1.5rem;',
                    'onclick' => 'subProcessPayment()',
            ]) ?>

            <!-- Security badge (shared component) -->
            <?= $this->partial('checkout/components/security-badge', [
                    'label' => 'Secured by Stripe · 256-bit SSL encryption',
            ]) ?>

            <?= $this->partial('checkout/components/form/button', [
                    'label' => '← Back',
                    'variant' => 'secondary',
                    'type' => 'button',
                    'style' => 'margin-top: .75rem;',
                    'onclick' => 'subGoToStep(' . ($isLoggedIn ? 1 : 2) . ')',
            ]) ?>
        </div>

        <!-- ══ Step 4: Success ════════════════════════════════════════ -->
        <div class="sub-step" id="sub-step-4" style="display: none;">
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
                        'style' => 'margin-top: 1.5rem;',
                        'onclick' => "window.location.href='/" . htmlspecialchars($site) . "/member/dashboard'",
                ]) ?>
            </div>
        </div>

        <!-- Loading overlay (scoped inside modal container) -->
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
            opacity: 0;
        }
        to {
            opacity: 1;
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
        max-width: 860px;
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
            transform: translateY(20px) scale(.97);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* ── Close button ───────────────────────────────────────────── */
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

    /* ── Step header ────────────────────────────────────────────── */
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
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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

    /* Override shared .btn inside plan cards */
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

    /* ── Auth toggle ────────────────────────────────────────────── */
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

    /* ── Auth forms ─────────────────────────────────────────────── */
    .sub-form {
        max-width: 420px;
        margin: 0 auto;
    }

    .sub-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .875rem;
    }

    .sub-form-group {
        margin-bottom: 1rem;
    }

    .sub-label {
        display: block;
        font-size: .875rem;
        font-weight: 600;
        color: var(--sub-text);
        margin-bottom: .3125rem;
    }

    .sub-input {
        width: 100%;
        padding: .625rem .875rem;
        border: 2px solid var(--sub-border);
        border-radius: 8px;
        font-size: .9375rem;
        color: var(--sub-text);
        transition: border-color .2s, box-shadow .2s;
    }

    .sub-input:focus {
        outline: none;
        border-color: var(--sub-primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, .1);
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

    /* ── Payment summary ────────────────────────────────────────── */
    .sub-payment-summary {
        background: var(--sub-bg-alt);
        border: 1px solid var(--sub-border);
        border-radius: 10px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
    }

    .sub-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .375rem 0;
        font-size: .9rem;
        color: var(--sub-text);
    }

    .sub-summary-divider {
        height: 1px;
        background: var(--sub-border);
        margin: .25rem 0;
    }

    .sub-summary-total {
        font-size: 1rem;
        font-weight: 700;
        color: var(--sub-primary);
    }

    /* ── Payment method selector ────────────────────────────────── */
    .sub-method-section {
        margin-bottom: 1.25rem;
    }

    .sub-section-label {
        font-size: .8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--sub-muted);
        margin-bottom: .5rem;
    }

    /* The shared .payment-methods grid, scoped to the modal selector */
    #sub-payment-method-selector.payment-methods {
        grid-template-columns: 1fr 1fr;
        gap: .75rem;
    }

    #sub-payment-method-selector .payment-method {
        padding: .75rem 1rem;
    }

    /* ── Shared component max-width constraint ──────────────────── */
    .sub-modal-container #saved-cards-section,
    .sub-modal-container #new-card-section,
    .sub-modal-container .voucher-section,
    .sub-modal-container .auto-renewal-consent {
        max-width: 520px;
        margin-left: auto;
        margin-right: auto;
    }

    .sub-modal-container .security-badge {
        justify-content: center;
        margin-top: 1rem;
    }

    /* ── Success ────────────────────────────────────────────────── */
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
            opacity: 0;
        }
        60% {
            transform: scale(1.1);
        }
        100% {
            transform: scale(1);
            opacity: 1;
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

    /* ── Loading overlay ────────────────────────────────────────── */
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
            transform: rotate(360deg);
        }
    }

    .sub-loading p {
        font-size: .9375rem;
        font-weight: 600;
        color: var(--sub-text);
    }

    /* ── Responsive ─────────────────────────────────────────────── */
    @media (max-width: 640px) {
        .sub-modal-container {
            margin: 0;
            max-height: 100vh;
            border-radius: 0;
            padding: 1.375rem 1.125rem;
        }

        .sub-plans,
        #sub-payment-method-selector.payment-methods {
            grid-template-columns: 1fr;
        }

        .sub-form-row {
            grid-template-columns: 1fr;
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
    (function () {
        'use strict';

        /* ── Constants ──────────────────────────────────────────────── */
        const SITE = <?= json_encode($site) ?>;
        const SUB_STRIPE_KEY = <?= json_encode($_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key')) ?>;
        const SUB_IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
        const MODAL_COOLDOWN_MS = 24 * 60 * 60 * 1000;
        const MODAL_STORAGE_KEY = 'sub_modal_last_' + SITE;

        /*
         * Globals required by cart-utils.js and saved-cards.js.
         * Both files are loaded by the parent layout via @js() — they execute
         * after this inline script, so these assignments are in time.
         */
        window.API_BASE = <?= json_encode($apiBase) ?>;
        window.PLAN_CURRENCY = '';   // set per-plan in subReadPlanData
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

        /* ── Stripe ─────────────────────────────────────────────────── */
        let subStripe = null;
        let subCardElement = null;
        let subCardMounted = false;

        if (typeof Stripe !== 'undefined' && SUB_STRIPE_KEY) {
            subStripe = Stripe(SUB_STRIPE_KEY);
            subCardElement = subStripe.elements().create('card', {
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

        /*
         * Mount into #card-element rendered by stripe-card-element.php.
         * Called with a 50 ms delay after step 3 becomes visible so Stripe
         * can measure the element's dimensions correctly.
         */
        function subMountCard() {
            alert('mike100')
            if (subCardMounted || !subCardElement) return;
            const el = document.getElementById('card-element');
            if (!el) return;

            alert('here66')

            subCardElement.mount('#card-element');
            subCardElement.on('change', function (e) {
                const err = document.getElementById('card-errors');
                if (err) err.textContent = e.error ? e.error.message : '';
            });
            subCardMounted = true;
        }

        /* ── Modal state ────────────────────────────────────────────── */
        let subSelectedPlan = null;
        let subClientSecret = null;
        let subSubscriptionId = null;
        let subOrderId = null;

        /* ── Modal open / close ─────────────────────────────────────── */
        function showSubscriptionModal(planSlug, planId, isManual) {
            const modal = document.getElementById('subscriptionModal');
            if (!modal) return;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            if (planSlug && planId) {
                const planEl = document.querySelector('.sub-plan[data-plan-slug="' + planSlug + '"]');
                if (planEl) {
                    subReadPlanData(planEl);
                    subGoToStep(SUB_IS_LOGGED_IN ? 3 : 2);
                } else {
                    subGoToStep(1);
                }
            } else {
                subGoToStep(1);
            }

            if (!isManual) subTrackModalShown();
        }

        function closeSubscriptionModal() {
            const modal = document.getElementById('subscriptionModal');
            if (!modal) return;
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        /* ── Step navigation ────────────────────────────────────────── */
        function subGoToStep(step) {
            for (var i = 1; i <= 4; i++) {
                var el = document.getElementById('sub-step-' + i);
                var prog = document.getElementById('sub-prog-' + i);
                if (el) el.style.display = 'none';
                if (prog) {
                    prog.classList.remove('active', 'completed');
                    if (i < step) prog.classList.add('completed');
                }
            }

            var active = document.getElementById('sub-step-' + step);
            var activeProg = document.getElementById('sub-prog-' + step);
            if (active) active.style.display = 'block';
            if (activeProg) activeProg.classList.add('active');

            if (step === 3) {
                alert('here9')
                setTimeout(subMountCard, 50);
                subUpdatePaymentSummary();
                // saved-cards.js loadSavedCards() reads window.isLoggedIn / window.currentMember
                if (window.isLoggedIn && typeof window.loadSavedCards === 'function') {
                    window.loadSavedCards();
                }
            }
        }

        /* ── Plan selection ─────────────────────────────────────────── */
        function subReadPlanData(planEl) {
            subSelectedPlan = {
                id: parseInt(planEl.dataset.planId, 10),
                slug: planEl.dataset.planSlug,
                name: planEl.dataset.planName,
                price: parseFloat(planEl.dataset.planPrice),
                currency: planEl.dataset.planCurrency,
                period: planEl.dataset.planPeriod,
                trial: parseInt(planEl.dataset.planTrial, 10) || 0,
            };
            // Keep globals in sync so cart-utils voucher helpers work correctly
            window.PLAN_CURRENCY = subSelectedPlan.currency;
            window.INITIAL_SUBTOTAL = subSelectedPlan.price;
        }

        function selectPlan(slug, id) {

            const planEl = document.querySelector(`[data-plan-slug="${slug}"]`);
            subReadPlanData(planEl);
            subGoToStep(SUB_IS_LOGGED_IN ? 3 : 2);
        }

        function subUpdatePaymentSummary() {
            if (!subSelectedPlan) return;
            var p = subSelectedPlan;
            var periodLabel = p.period === 'month' ? 'monthly' : 'yearly';
            var discount = window.appliedVoucher ? parseFloat(window.appliedVoucher.discount) : 0;
            var discRow = document.getElementById('sub-discount-summary-row');

            document.getElementById('sub-summary-plan-name').textContent = p.name;
            document.getElementById('sub-summary-billing').textContent = 'Billed ' + periodLabel;

            if (discount > 0 && discRow) {
                discRow.style.display = 'flex';
                document.getElementById('sub-summary-discount').textContent =
                    '-' + p.currency + discount.toFixed(2);
            } else if (discRow) {
                discRow.style.display = 'none';
            }

            var total = p.trial > 0 ? 0 : Math.max(0, p.price - discount);
            document.getElementById('sub-summary-total').textContent = p.trial > 0
                ? 'FREE (then ' + p.currency + p.price.toFixed(2) + '/' + (p.period === 'month' ? 'mo' : 'yr') + ')'
                : p.currency + total.toFixed(2);
        }

        /*
         * payment-method-selector.js calls window.onPaymentMethodChange(method)
         * whenever the user picks a different payment method. We implement
         * that hook here to toggle the card / PayPal sections.
         */
        window.onPaymentMethodChange = function (method) {
            var cardSection = document.getElementById('sub-card-payment-section');
            var paypalSection = document.getElementById('sub-paypal-payment-section');
            if (cardSection) cardSection.style.display = method === 'card' ? 'block' : 'none';
            if (paypalSection) paypalSection.style.display = method === 'paypal' ? 'block' : 'none';
            if (method === 'card') subMountCard();
        };

        /* ── Auth: register ─────────────────────────────────────────── */
        document.getElementById('sub-register-form')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            var data = Object.fromEntries(new FormData(this));
            var errEl = document.getElementById('sub-register-error');
            errEl.textContent = '';

            if (data.password !== data.password_confirmation) {
                errEl.textContent = 'Passwords do not match.';
                return;
            }
            if (data.password.length < 8) {
                errEl.textContent = 'Password must be at least 8 characters.';
                return;
            }
            if (!data.terms) {
                errEl.textContent = 'You must agree to the Terms to continue.';
                return;
            }

            subSetLoading(true);
            try {
                var res = await fetch('/' + SITE + '/member/register', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data),
                });
                var result = await res.json();
                if (result.success) {
                    window.currentMember = result.member;
                    window.isLoggedIn = true;
                    subGoToStep(3);
                } else {
                    errEl.textContent = result.message || 'Registration failed.';
                }
            } catch (_) {
                errEl.textContent = 'An error occurred. Please try again.';
            } finally {
                subSetLoading(false);
            }
        });

        /* ── Auth: login ────────────────────────────────────────────── */
        document.getElementById('sub-login-form')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            var data = Object.fromEntries(new FormData(this));
            var errEl = document.getElementById('sub-login-error');
            errEl.textContent = '';

            subSetLoading(true);
            try {
                var res = await fetch('/' + SITE + '/member/login', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data),
                });
                var result = await res.json();
                if (result.success) {
                    window.currentMember = result.member;
                    window.isLoggedIn = true;
                    subGoToStep(3);
                } else {
                    errEl.textContent = result.message || 'Login failed.';
                }
            } catch (_) {
                errEl.textContent = 'An error occurred. Please try again.';
            } finally {
                subSetLoading(false);
            }
        });

        /* ── Auth tab toggle ────────────────────────────────────────── */
        document.querySelectorAll('.sub-auth-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.sub-auth-tab').forEach(function (t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                var target = this.dataset.tab;
                document.getElementById('sub-register-form').style.display =
                    target === 'register' ? 'block' : 'none';
                document.getElementById('sub-login-form').style.display =
                    target === 'login' ? 'block' : 'none';
            });
        });

        /* ── Process payment — mirrors checkout/index.php ───────────── */
        async function subProcessPayment() {
            var consentCb = document.getElementById('sub-global-renewal-consent');
            var consentBlock = document.getElementById('global-renewal-consent-block');

            if (consentCb && !consentCb.checked) {
                consentBlock?.classList.add('consent-error');
                subShowCardError('Please confirm the subscription terms to continue.');
                consentBlock?.scrollIntoView({behavior: 'smooth', block: 'center'});
                return;
            }
            consentBlock?.classList.remove('consent-error');

            var data = {
                subscription_plan_id: subSelectedPlan?.id,
                isOneTimeSubscription: true,
                global_renewal_consent: '1',
            };

            if (window.currentMember) data.member_id = window.currentMember.id;

            if (window.appliedVoucher) {
                data.voucher_code = window.appliedVoucher.code;
                data.voucher_id = window.appliedVoucher.voucher_id;
                data.discount_amount = window.appliedVoucher.discount;
            }

            subSetLoading(true);
            try {
                // 1. Create checkout intent
                var res = await fetch(window.API_BASE + '/subscriptions/onetime/checkout', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data),
                });
                var result = await res.json();

                if (!result.success) {
                    subShowCardError(result.message || 'Checkout failed. Please try again.');
                    return;
                }

                var contexts = result.data?.stripe_contexts;
                subClientSecret = contexts
                    ? contexts[Object.keys(contexts)[0]].client_secret
                    : (result.data?.client_secret ?? null);
                subSubscriptionId = result.data?.subscription_ids ?? result.data?.subscription_id ?? null;
                subOrderId = result.data?.order_id ?? null;

                // 2. Confirm via Stripe
                var member = window.currentMember;
                var paymentResult = window.selectedCardId
                    ? await subStripe.confirmCardPayment(subClientSecret, {
                        payment_method: window.selectedCardId,
                    })
                    : await subStripe.confirmCardPayment(subClientSecret, {
                        payment_method: {
                            card: subCardElement,
                            billing_details: {
                                name: member
                                    ? (member.first_name + ' ' + member.last_name).trim()
                                    : '',
                                email: member?.email ?? '',
                            },
                        },
                        setup_future_usage: 'off_session',
                    });

                var error = paymentResult.error;
                var paymentIntent = paymentResult.paymentIntent;

                if (error) {
                    subShowCardError(error.message);
                    return;
                }

                // 3. Server-side confirmation
                if (paymentIntent.status === 'succeeded') {
                    await subConfirmPayment(paymentIntent.id);
                }

            } catch (err) {
                console.error('Modal payment error:', err);
                subShowCardError('An unexpected error occurred. Please try again.');
            } finally {
                subSetLoading(false);
            }
        }

        async function subConfirmPayment(intentId) {
            var body = {payment_intent_id: intentId, order_id: subOrderId};
            Array.isArray(subSubscriptionId)
                ? (body.subscription_ids = subSubscriptionId)
                : (body.subscription_id = subSubscriptionId);

            var res = await fetch(window.API_BASE + '/subscriptions/onetime/confirm-payment', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body),
            });
            var result = await res.json();

            if (result.success) {
                subGoToStep(4);
            } else {
                subShowCardError(result.message || 'Payment confirmation failed.');
            }
        }

        /* ── Helpers ────────────────────────────────────────────────── */
        function subSetLoading(on) {
            var overlay = document.getElementById('sub-loading');
            var btn = document.getElementById('sub-pay-btn');
            if (overlay) overlay.classList.toggle('show', on);
            if (btn) btn.disabled = on;
        }

        function subShowCardError(msg) {
            var el = document.getElementById('card-errors');
            if (el) el.textContent = msg;
        }

        /* ── Cooldown tracking ──────────────────────────────────────── */
        function subShouldShow() {
            var last = localStorage.getItem(MODAL_STORAGE_KEY);
            return !last || (Date.now() - parseInt(last, 10)) >= MODAL_COOLDOWN_MS;
        }

        function subTrackModalShown() {
            if (!SUB_IS_LOGGED_IN) localStorage.setItem(MODAL_STORAGE_KEY, String(Date.now()));
        }

        /* ── Event listeners ────────────────────────────────────────── */
        document.querySelector('.sub-modal-overlay')?.addEventListener('click', closeSubscriptionModal);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSubscriptionModal();
        });

        /* ── Auto-show ──────────────────────────────────────────────── */
        <?php if ($subscriptionModalData['show_modal'] ?? false): ?>
        setTimeout(function () {
            if (SUB_IS_LOGGED_IN || subShouldShow()) showSubscriptionModal(null, null, false);
        }, 3000);
        <?php endif; ?>

        /* ── Public API ─────────────────────────────────────────────── */
        window.showSubscriptionModal = showSubscriptionModal;
        window.closeSubscriptionModal = closeSubscriptionModal;
        window.subGoToStep = subGoToStep;
        window.selectPlan = selectPlan;
        window.subProcessPayment = subProcessPayment;
        window.showSubscriptionModalWithPlan = function (slug, id) {
            showSubscriptionModal(slug, id, true);
        };
        window.openSubscriptionModal = function () {
            showSubscriptionModal(null, null, true);
        };

    })();
</script>