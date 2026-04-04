<?php
// views/components/subscription-modal.php
/**
 * Subscription modal.
 *
 * Uses shared checkout components wherever possible:
 *   - checkout/components/voucher-section        (voucher input + applied display)
 *   - checkout/components/stripe-card-element    (Stripe card mount point)
 *   - checkout/components/saved-cards            (saved payment methods list)
 *   - checkout/components/auto-renewal-consent   (auto-renewal checkbox block)
 *   - checkout/components/security-badge         (SSL badge)
 *   - checkout/components/form/button            (all CTAs)
 *
 * @var array $subscriptionModalData
 */
if (!isset($subscriptionModalData) || (!$subscriptionModalData['show_modal'] && ($subscriptionModalData['is_direct'] ?? false) || false !== true)) {
    //return;
}

$plans = $subscriptionModalData['plans'];
$member = $subscriptionModalData['member'];
$isLoggedIn = !empty($member);
?>

<div id="subscriptionModal" class="sub-modal hide">
    <div class="sub-modal-overlay"></div>
    <div class="sub-modal-container">
        <button class="sub-modal-close" onclick="closeSubscriptionModal()" aria-label="Close">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>

        <!-- Progress Indicator -->
        <div class="sub-progress">
            <div class="sub-progress-step active" data-step="1">
                <div class="sub-progress-circle">1</div>
                <div class="sub-progress-label">Choose Plan</div>
            </div>
            <div class="sub-progress-line"></div>
            <div class="sub-progress-step" data-step="2">
                <div class="sub-progress-circle">2</div>
                <div class="sub-progress-label">Account</div>
            </div>
            <div class="sub-progress-line"></div>
            <div class="sub-progress-step" data-step="3">
                <div class="sub-progress-circle">3</div>
                <div class="sub-progress-label">Payment</div>
            </div>
            <div class="sub-progress-line"></div>
            <div class="sub-progress-step" data-step="4">
                <div class="sub-progress-circle">4</div>
                <div class="sub-progress-label">Complete</div>
            </div>
        </div>

        <!-- ══ Step 1: Choose Plan ══════════════════════════════════════ -->
        <div class="sub-step" id="step-1" style="display: block;">
            <div class="sub-header">
                <h2 class="sub-title">Choose Your Plan</h2>
                <p class="sub-subtitle">Select the perfect plan for your needs</p>
            </div>

            <div class="sub-plans">
                <?php foreach ($plans as $plan): ?>
                    <div class="sub-plan <?= $plan->is_featured ? 'featured' : '' ?>"
                         data-plan-id="<?= $plan->id ?>"
                         data-plan-slug="<?= htmlspecialchars($plan->slug) ?>">

                        <?php if ($plan->is_featured): ?>
                            <div class="sub-plan-badge">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                Most Popular
                            </div>
                        <?php endif; ?>

                        <div class="sub-plan-header">
                            <h3 class="sub-plan-name"><?= htmlspecialchars($plan->name) ?></h3>
                            <?php if ($plan->description): ?>
                                <p class="sub-plan-desc"><?= htmlspecialchars($plan->description) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="sub-plan-price">
                            <span class="sub-price-currency"><?= htmlspecialchars($plan->currency) ?></span>
                            <span class="sub-price-amount"><?= round($plan->price, 2) ?></span>
                            <span class="sub-price-period">/<?= htmlspecialchars($plan->billing_period === 'month' ? 'mo' : 'yr') ?></span>
                        </div>

                        <?php if ($plan->trial_days > 0): ?>
                            <div class="sub-plan-trial">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                <?= $plan->trial_days ?> days free trial
                            </div>
                        <?php endif; ?>

                        <?php if ($plan->features && count($plan->features) > 0): ?>
                            <ul class="sub-plan-features">
                                <?php foreach (array_slice($plan->features, 0, 4) as $feature): ?>
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="2.5">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                        <?= htmlspecialchars($feature) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <!-- Uses shared button component -->
                        @include('checkout/components/form/button', [
                        'label' => 'Select Plan',
                        'variant' => 'primary',
                        'type' => 'button',
                        'class' => 'sub-plan-btn',
                        'onclick' => "selectPlan('" . htmlspecialchars($plan->slug) . "', " . $plan->id . ")",
                        ])
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ══ Step 2: Account (Register / Login) ═══════════════════════ -->
        <div class="sub-step" id="step-2" style="display: none;">
            <div class="sub-header">
                <h2 class="sub-title">Create Your Account</h2>
                <p class="sub-subtitle">Join our community to continue</p>
            </div>

            <div class="sub-auth-toggle">
                <button class="sub-auth-tab active" data-tab="register">Sign Up</button>
                <button class="sub-auth-tab" data-tab="login">Sign In</button>
            </div>

            <!-- Register Form -->
            <form id="register-form" class="sub-form" style="display: block;">
                @csrf
                <div class="sub-form-row">
                    <div class="sub-form-group">
                        <label class="sub-label">First Name</label>
                        <input type="text" name="first_name" class="sub-input" required>
                    </div>
                    <div class="sub-form-group">
                        <label class="sub-label">Last Name</label>
                        <input type="text" name="last_name" class="sub-input" required>
                    </div>
                </div>
                <div class="sub-form-group">
                    <label class="sub-label">Email</label>
                    <input type="email" name="email" class="sub-input" required>
                </div>
                <div class="sub-form-group">
                    <label class="sub-label">Password</label>
                    <input type="password" name="password" class="sub-input" required minlength="8">
                    <span class="sub-hint">Minimum 8 characters</span>
                </div>
                <div class="sub-form-group">
                    <label class="sub-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="sub-input" required>
                </div>
                <div class="sub-form-group">
                    <label class="sub-checkbox">
                        <input type="checkbox" name="terms" required>
                        <span>I agree to the <a href="/terms" target="_blank">Terms</a> and
                              <a href="/privacy" target="_blank">Privacy Policy</a></span>
                    </label>
                </div>
                <div class="sub-error" id="register-error"></div>
                @include('checkout/components/form/button', [
                'label' => 'Create Account & Continue',
                'variant' => 'primary',
                'type' => 'submit',
                'class' => 'sub-btn-primary',
                ])
            </form>

            <!-- Login Form -->
            <form id="login-form" class="sub-form" style="display: none;">
                @csrf
                <div class="sub-form-group">
                    <label class="sub-label">Email</label>
                    <input type="email" name="email" class="sub-input" required>
                </div>
                <div class="sub-form-group">
                    <label class="sub-label">Password</label>
                    <input type="password" name="password" class="sub-input" required>
                </div>
                <div class="sub-error" id="login-error"></div>
                @include('checkout/components/form/button', [
                'label' => 'Sign In & Continue',
                'variant' => 'primary',
                'type' => 'submit',
                'class' => 'sub-btn-primary',
                ])
                <div class="sub-form-footer">
                    <a href="/member/forgot-password" target="_blank">Forgot password?</a>
                </div>
            </form>

            @include('checkout/components/form/button', [
            'label' => '← Back to Plans',
            'variant' => 'secondary',
            'type' => 'button',
            'class' => 'sub-btn-back',
            'onclick' => 'goToStep(1)',
            ])
        </div>

        <!-- ══ Step 3: Payment ══════════════════════════════════════════ -->
        <div class="sub-step" id="step-3" style="display: none;">
            <div class="sub-header">
                <h2 class="sub-title">Payment Details</h2>
                <p class="sub-subtitle">Secure checkout with Stripe</p>
            </div>

            <!-- Plan summary -->
            <div class="sub-payment-summary">
                <div class="sub-summary-row">
                    <span>Plan</span>
                    <strong id="summary-plan-name">-</strong>
                </div>
                <div class="sub-summary-row">
                    <span>Billing</span>
                    <strong id="summary-billing">-</strong>
                </div>
                <div class="sub-summary-divider"></div>
                <div class="sub-summary-row sub-summary-total">
                    <span>Total</span>
                    <strong id="summary-total">-</strong>
                </div>
            </div>

            <!-- ── Shared: Saved payment methods ─────────────────────── -->
            <!--
                saved-cards.php renders the section hidden; saved-cards.js
                populates and reveals it when the member has saved cards.
                We override the section styling inside .sub-modal-container
                via the scoped CSS block below.
            -->
            @include('checkout/components/saved-cards', [
            'sectionId' => 'sub-saved-cards-section',
            'useDifferentCardOnClick' => 'subShowNewCardForm()',
            ])

            <!-- ── Shared: Stripe card element ───────────────────────── -->
            <!--
                stripe-card-element.php renders #card-element + #card-errors.
                The modal mounts Stripe into this element on step-3 open.
                We pass showBackButton=false because the modal has its own
                back button below.
            -->
            @include('checkout/components/stripe-card-element', [
            'sectionId' => 'sub-new-card-section',
            'sectionTitle' => 'Card Information',
            'showBackButton' => false,
            ])

            <!-- ── Shared: Voucher section ────────────────────────────── -->
            <!--
                voucher-section.php renders #voucher-input, #voucher-message,
                #applied-voucher, #discount-row.
                The modal's applyVoucher() / removeVoucher() functions call the
                same API endpoints as the checkout page; the component's default
                onclick handlers (applyVoucher / removeVoucher) match.
                We override colours via scoped CSS below.
            -->
            @include('checkout/components/voucher-section', [
            'currency' => '', // JS fills in the correct symbol at runtime
            'applyOnClick' => 'subApplyVoucher()',
            'removeOnClick' => 'subRemoveVoucher()',
            ])

            <!-- ── Shared: Auto-renewal consent ─────────────────────── -->
            @include('checkout/components/auto-renewal-consent', [
            'showGlobal' => true,
            'showUs' => false,
            'globalConsentId'=> 'sub-global-renewal-consent',
            ])

            <!-- Payment submit -->
            <form id="payment-form" class="sub-form" style="margin-top: 1.5rem;">
                @include('checkout/components/form/button', [
                'id' => 'submit-payment',
                'label' => 'Complete Subscription',
                'variant' => 'primary',
                'type' => 'submit',
                'class' => 'sub-btn-primary',
                ])
            </form>

            <!-- ── Shared: Security badge ─────────────────────────────── -->
            @include('checkout/components/security-badge', [
            'label' => 'Secured by Stripe • 256-bit SSL encryption',
            ])

            @include('checkout/components/form/button', [
            'label' => '← Back',
            'variant' => 'secondary',
            'type' => 'button',
            'class' => 'sub-btn-back',
            'onclick' => 'goToStep(' . ($isLoggedIn ? 1 : 2) . ')',
            ])
        </div>

        <!-- ══ Step 4: Success ══════════════════════════════════════════ -->
        <div class="sub-step" id="step-4" style="display: none;">
            <div class="sub-success">
                <div class="sub-success-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <h2 class="sub-success-title">Welcome Aboard! 🎉</h2>
                <p class="sub-success-text">Your subscription is now active. Get ready to unlock premium content!</p>

                @include('checkout/components/form/button', [
                'label' => 'Go to Dashboard',
                'variant' => 'primary',
                'type' => 'button',
                'class' => 'sub-btn-primary',
                'onclick' => "window.location.href='/" . \App\Framework\Support\SiteContext::slug() .
                "/member/dashboard'",
                ])
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="sub-loading" id="sub-loading">
            <div class="sub-spinner"></div>
            <p>Processing...</p>
        </div>
    </div>
</div>

<style>
    /* ── Modal root variables ─────────────────────────────────────────── */
    :root {
        --sub-primary: #6366f1;
        --sub-primary-dark: #4f46e5;
        --sub-success: #10b981;
        --sub-error: #ef4444;
        --sub-text: #0f172a;
        --sub-text-light: #64748b;
        --sub-border: #e2e8f0;
        --sub-bg: #ffffff;
        --sub-bg-alt: #f8fafc;
    }

    /* ── Modal shell ──────────────────────────────────────────────────── */
    .sub-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 99999;
        animation: subFadeIn .3s ease-out;
    }

    .sub-modal.show {
        display: block;
    }

    @keyframes subFadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes subSlideUp {
        from {
            opacity: 0;
            transform: translateY(30px) scale(.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .sub-modal-overlay {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .7);
        backdrop-filter: blur(8px);
    }
    .sub-modal-container {
        position: relative;
        width: 100%;
        max-width: 1000px;
        max-height: 90vh;
        margin: 5vh auto;
        background: var(--sub-bg);
        border-radius: 24px;
        padding: 48px;
        overflow-y: auto;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .25);
        animation: subSlideUp .5s cubic-bezier(.34, 1.56, .64, 1);
    }

    .sub-modal-close {
        position: absolute;
        top: 24px;
        right: 24px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--sub-bg-alt);
        border: none;
        border-radius: 50%;
        color: var(--sub-text-light);
        cursor: pointer;
        transition: all .2s;
        z-index: 10;
    }

    .sub-modal-close:hover {
        background: var(--sub-border);
        color: var(--sub-text);
        transform: rotate(90deg);
    }

    /* ── Progress ─────────────────────────────────────────────────────── */
    .sub-progress {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 48px;
    }

    .sub-progress-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
    .sub-progress-circle {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--sub-bg-alt);
        border: 2px solid var(--sub-border);
        color: var(--sub-text-light);
        font-weight: 700;
        font-size: 18px;
        transition: all .3s;
    }

    .sub-progress-step.active .sub-progress-circle {
        background: var(--sub-primary);
        border-color: var(--sub-primary);
        color: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, .4);
    }

    .sub-progress-step.completed .sub-progress-circle {
        background: var(--sub-success);
        border-color: var(--sub-success);
        color: white;
    }

    .sub-progress-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--sub-text-light);
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .sub-progress-step.active .sub-progress-label {
        color: var(--sub-primary);
    }

    .sub-progress-line {
        width: 80px;
        height: 2px;
        background: var(--sub-border);
        margin: 0 4px 24px 4px;
    }

    /* ── Header ───────────────────────────────────────────────────────── */
    .sub-header {
        text-align: center;
        margin-bottom: 40px;
    }

    .sub-title {
        font-size: 36px;
        font-weight: 800;
        color: var(--sub-text);
        margin-bottom: 8px;
        letter-spacing: -.5px;
    }

    .sub-subtitle {
        font-size: 16px;
        color: var(--sub-text-light);
    }

    /* ── Plans ────────────────────────────────────────────────────────── */
    .sub-plans {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
    }
    .sub-plan {
        position: relative;
        background: var(--sub-bg);
        border: 2px solid var(--sub-border);
        border-radius: 16px;
        padding: 32px 24px;
        transition: all .3s cubic-bezier(.34, 1.56, .64, 1);
        cursor: pointer;
    }

    .sub-plan:hover {
        border-color: var(--sub-primary);
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(99, 102, 241, .15);
    }

    .sub-plan.featured {
        border-color: var(--sub-primary);
        background: linear-gradient(135deg, rgba(99, 102, 241, .05), rgba(139, 92, 246, .05));
        transform: scale(1.05);
    }

    .sub-plan.featured:hover {
        transform: translateY(-4px) scale(1.05);
        box-shadow: 0 20px 40px rgba(99, 102, 241, .2);
    }
    .sub-plan-badge {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, var(--sub-primary), var(--sub-primary-dark));
        color: white;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        box-shadow: 0 4px 12px rgba(99, 102, 241, .4);
    }

    .sub-plan-header {
        margin-bottom: 24px;
    }

    .sub-plan-name {
        font-size: 24px;
        font-weight: 700;
        color: var(--sub-text);
        margin-bottom: 8px;
    }

    .sub-plan-desc {
        font-size: 14px;
        color: var(--sub-text-light);
        line-height: 1.5;
    }

    .sub-plan-price {
        display: flex;
        align-items: baseline;
        justify-content: center;
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--sub-border);
    }

    .sub-price-currency {
        font-size: 20px;
        font-weight: 700;
        color: var(--sub-text-light);
    }

    .sub-price-amount {
        font-size: 48px;
        font-weight: 900;
        color: var(--sub-primary);
        letter-spacing: -2px;
        margin: 0 4px;
    }

    .sub-price-period {
        font-size: 16px;
        color: var(--sub-text-light);
        font-weight: 600;
    }
    .sub-plan-trial {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 20px;
    }

    .sub-plan-features {
        list-style: none;
        padding: 0;
        margin: 0 0 24px 0;
    }

    .sub-plan-features li {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        font-size: 14px;
        color: var(--sub-text);
        font-weight: 500;
    }

    .sub-plan-features svg {
        color: var(--sub-success);
        flex-shrink: 0;
    }

    /* Override shared btn styles within the modal */
    .sub-modal-container .sub-plan-btn,
    .sub-modal-container .btn.sub-plan-btn {
        background: var(--sub-primary) !important;
        padding: 14px 24px;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 0;
    }

    .sub-modal-container .sub-plan-btn:hover {
        background: var(--sub-primary-dark) !important;
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(99, 102, 241, .3);
    }

    .sub-plan.featured .sub-plan-btn {
        background: linear-gradient(135deg, var(--sub-primary), #8b5cf6) !important;
        box-shadow: 0 4px 12px rgba(99, 102, 241, .4);
    }

    /* ── Auth toggle ──────────────────────────────────────────────────── */
    .sub-auth-toggle {
        display: flex;
        background: var(--sub-bg-alt);
        border-radius: 12px;
        padding: 4px;
        margin-bottom: 32px;
    }

    .sub-auth-tab {
        flex: 1;
        padding: 12px 24px;
        background: transparent;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        color: var(--sub-text-light);
        cursor: pointer;
        transition: all .3s;
    }

    .sub-auth-tab.active {
        background: white;
        color: var(--sub-primary);
        box-shadow: 0 2px 4px rgba(0, 0, 0, .05);
    }

    /* ── Forms ────────────────────────────────────────────────────────── */
    .sub-form {
        max-width: 500px;
        margin: 0 auto;
    }

    .sub-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .sub-form-group {
        margin-bottom: 20px;
    }

    .sub-label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: var(--sub-text);
        margin-bottom: 8px;
    }

    .sub-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--sub-border);
        border-radius: 10px;
        font-size: 15px;
        color: var(--sub-text);
        transition: all .3s;
    }

    .sub-input:focus {
        outline: none;
        border-color: var(--sub-primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, .1);
    }

    .sub-hint {
        display: block;
        font-size: 12px;
        color: var(--sub-text-light);
        margin-top: 6px;
    }

    .sub-checkbox {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        cursor: pointer;
        font-size: 14px;
        color: var(--sub-text);
    }

    .sub-checkbox input[type="checkbox"] {
        margin-top: 2px;
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .sub-checkbox a {
        color: var(--sub-primary);
        text-decoration: none;
        font-weight: 600;
    }

    .sub-error {
        padding: 12px 16px;
        background: #fee2e2;
        color: #991b1b;
        border-radius: 8px;
        font-size: 14px;
        margin-bottom: 16px;
        display: none;
    }

    .sub-error:not(:empty) {
        display: block;
    }

    /* Override shared btn styles for primary actions inside modal */
    .sub-modal-container .sub-btn-primary,
    .sub-modal-container .btn.sub-btn-primary {
        background: linear-gradient(135deg, var(--sub-primary), var(--sub-primary-dark)) !important;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-bottom: 0;
    }

    .sub-modal-container .sub-btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(99, 102, 241, .3);
    }

    .sub-form-footer {
        text-align: center;
        margin-top: 16px;
    }

    .sub-form-footer a {
        color: var(--sub-primary);
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
    }

    .sub-modal-container .sub-btn-back,
    .sub-modal-container .btn.sub-btn-back {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: transparent !important;
        border: 2px solid var(--sub-border) !important;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        color: var(--sub-text-light) !important;
        margin-top: 16px;
    }

    .sub-modal-container .sub-btn-back:hover {
        border-color: var(--sub-primary) !important;
        color: var(--sub-primary) !important;
        background: rgba(99, 102, 241, .05) !important;
    }

    /* ── Payment summary box ──────────────────────────────────────────── */
    .sub-payment-summary {
        background: var(--sub-bg-alt);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 32px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }

    .sub-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        font-size: 15px;
        color: var(--sub-text);
    }

    .sub-summary-divider {
        height: 1px;
        background: var(--sub-border);
        margin: 8px 0;
    }

    .sub-summary-total {
        font-size: 18px;
        font-weight: 700;
        color: var(--sub-primary);
    }

    /* ── Stripe card element (scoped to modal) ────────────────────────── */
    /* stripe-card-element.php uses #card-element which we re-style here */
    .sub-modal-container #card-element {
        padding: 14px 16px;
        border: 2px solid var(--sub-border);
        border-radius: 10px;
        background: white;
        transition: all .3s;
    }

    .sub-modal-container #card-element:focus-within {
        border-color: var(--sub-primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, .1);
    }

    /* ── Voucher (scoped overrides) ───────────────────────────────────── */
    /* voucher-section.php uses its own IDs; we just make sure the section
       flows correctly inside the modal layout */
    .sub-modal-container .voucher-section {
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }

    /* ── Auto-renewal consent (scoped) ───────────────────────────────── */
    .sub-modal-container .auto-renewal-consent {
        max-width: 500px;
        margin: 1rem auto;
    }

    /* ── Saved cards (scoped) ─────────────────────────────────────────── */
    .sub-modal-container #sub-saved-cards-section {
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }

    /* ── Security badge (scoped) ─────────────────────────────────────── */
    .sub-modal-container .security-badge {
        justify-content: center;
        margin-top: 24px;
    }

    /* ── Success state ────────────────────────────────────────────────── */
    .sub-success {
        text-align: center;
        padding: 48px 24px;
        max-width: 500px;
        margin: 0 auto;
    }
    .sub-success-icon {
        width: 96px;
        height: 96px;
        background: linear-gradient(135deg, var(--sub-success), #059669);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
        animation: successPop .6s cubic-bezier(.34, 1.56, .64, 1);
    }

    @keyframes successPop {
        0% {
            transform: scale(0);
            opacity: 0;
        }
        50% {
            transform: scale(1.1);
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .sub-success-icon svg {
        color: white;
    }

    .sub-success-title {
        font-size: 32px;
        font-weight: 800;
        color: var(--sub-text);
        margin-bottom: 12px;
    }

    .sub-success-text {
        font-size: 16px;
        color: var(--sub-text-light);
        margin-bottom: 32px;
        line-height: 1.6;
    }

    /* ── Loading overlay ─────────────────────────────────────────────── */
    .sub-loading {
        display: none;
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, .95);
        backdrop-filter: blur(4px);
        border-radius: 24px;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 16px;
        z-index: 100;
    }

    .sub-loading.show {
        display: flex;
    }

    .sub-spinner {
        width: 48px;
        height: 48px;
        border: 4px solid var(--sub-border);
        border-top-color: var(--sub-primary);
        border-radius: 50%;
        animation: subSpin .8s linear infinite;
    }

    @keyframes subSpin {
        to {
            transform: rotate(360deg);
        }
    }

    .sub-loading p {
        font-size: 16px;
        font-weight: 600;
        color: var(--sub-text);
    }

    /* ── Responsive ──────────────────────────────────────────────────── */
    @media (max-width: 768px) {
        .sub-modal-container {
            margin: 0;
            max-height: 100vh;
            border-radius: 0;
            padding: 32px 24px;
        }

        .sub-title {
            font-size: 28px;
        }

        .sub-plans {
            grid-template-columns: 1fr;
        }

        .sub-plan.featured {
            transform: scale(1);
        }

        .sub-form-row {
            grid-template-columns: 1fr;
        }

        .sub-progress-label {
            display: none;
        }

        .sub-progress-line {
            width: 40px;
            margin-bottom: 0;
        }
    }

    /* ── Single-plan centering ───────────────────────────────────────── */
    .sub-plans:has(.sub-plan:only-child) {
        display: flex;
        justify-content: center;
        padding: 2rem 0;
    }

    .sub-plans:has(.sub-plan:only-child) .sub-plan {
        max-width: 500px;
        width: 100%;
    }

    .sub-plan:only-child {
        transform: scale(1) !important;
    }

    .sub-plan:only-child:hover {
        transform: scale(1.02) !important;
    }
    .sub-plan:only-child .sub-plan-header,
    .sub-plan:only-child .sub-plan-price,
    .sub-plan:only-child .sub-plan-features {
        text-align: center;
    }

    .sub-plan:only-child .sub-plan-features {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .sub-plan:only-child .sub-plan-features li {
        text-align: left;
        max-width: 80%;
    }
</style>

<script src="https://js.stripe.com/v3/"></script>
<script>
    /* ── Constants ──────────────────────────────────────────────────── */
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    const API_BASE_MODAL = '/api/' + SITE;          // avoid collision with checkout-page API_BASE
    const STRIPE_KEY_MODAL = '<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? '' ?>';
    const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
    const MODAL_STORAGE_KEY = 'subscription_modal_last_shown_' + SITE;
    const MODAL_COOLDOWN_HOURS = 24;
    let forceVerification = false;

    /* ── State ──────────────────────────────────────────────────────── */
    let subStripe = null;
    let subCardElement = null;
    let subElements = null;
    let selectedPlanId = null;
    let selectedPlanSlug = null;
    let selectedPlanData = null;
    let subAppliedVoucher = null;
    let subCurrentMember = null;

    /* ── Stripe init ────────────────────────────────────────────────── */
    if (STRIPE_KEY_MODAL) {
        alert('yes')
        subStripe = Stripe(STRIPE_KEY_MODAL);
        subElements = subStripe.elements();
        subCardElement = subElements.create('card', {
            hidePostalCode: true,
            style: {
                base: {
                    fontSize: '15px',
                    color: '#0f172a',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                    '::placeholder': {color: '#94a3b8'}
                },
                invalid: {color: '#ef4444', iconColor: '#ef4444'},
            },
        });
    }

    /* ── Saved cards (uses the page-level saved-cards.js functions) ─── */
    /*
     * saved-cards.js exposes loadSavedCards(), displaySavedCards(),
     * selectSavedCard(), showNewCardForm(), showSavedCards().
     * Those functions reference #saved-cards-section and #saved-cards-list.
     * Because we used a custom sectionId ('sub-saved-cards-section') we need
     * thin wrappers that re-target the modal-specific IDs.
     */
    function subLoadSavedCards() {
        if (!IS_LOGGED_IN || !subCurrentMember) return;
        fetch(`${API_BASE_MODAL}/member/payment-methods`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data?.payment_methods?.length) {
                    subDisplaySavedCards(data.data.payment_methods);
                }
            })
            .catch(console.error);
    }

    function subDisplaySavedCards(cards) {
        const list = document.getElementById('saved-cards-list');   // inside #sub-saved-cards-section
        const section = document.getElementById('sub-saved-cards-section');
        if (!list || !section) return;

        list.innerHTML = cards.map(card => `
            <label class="radio-option-card" for="sub-card-${card.id}" style="display:flex;align-items:center;gap:1rem;border:2px solid var(--border-color,#e2e8f0);border-radius:.5rem;padding:1rem;margin-bottom:1rem;cursor:pointer;transition:border-color .2s;">
                <input type="radio" name="sub_saved_card" id="sub-card-${card.id}" value="${card.id}"
                       onchange="subSelectSavedCard('${card.id}')" class="radio-option-input" style="width:20px;height:20px;">
                <div>
                    <div style="font-weight:600;text-transform:capitalize;">${card.card.brand}</div>
                    <div style="color:var(--text-secondary,#64748b);font-size:.875rem;">•••• ${card.card.last4}</div>
                    <div style="color:var(--text-secondary,#64748b);font-size:.875rem;">Expires ${card.card.exp_month}/${card.card.exp_year}</div>
                </div>
            </label>`).join('');

        section.style.display = 'block';
        const newCardSection = document.getElementById('sub-new-card-section');
        if (newCardSection) newCardSection.style.display = 'none';
    }

    let subSelectedCardId = null;

    function subSelectSavedCard(id) {
        subSelectedCardId = id;
        document.querySelectorAll('[name="sub_saved_card"]').forEach(r => {
            r.closest('.radio-option-card')?.classList.toggle('selected', r.value === id);
        });
    }

    function subShowNewCardForm() {
        subSelectedCardId = null;
        const sec = document.getElementById('sub-saved-cards-section');
        const frm = document.getElementById('sub-new-card-section');
        if (sec) sec.style.display = 'none';
        if (frm) frm.style.display = 'block';
    }

    /* ── Modal open / close ─────────────────────────────────────────── */
    function showSubscriptionModal(planSlug = null, planId = null, isManual = false) {
        const modal = document.getElementById('subscriptionModal');
        if (!modal) return;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Mount card element when the DOM element is accessible
        setTimeout(() => {
            const el = document.getElementById('card-element');
            if (subCardElement && el && !el.hasChildNodes()) {
                subCardElement.mount('#card-element');
                subCardElement.on('change', e => {
                    const err = document.getElementById('card-errors');
                    if (err) err.textContent = e.error ? e.error.message : '';
                });
            }
        }, 100);

        if (planSlug && planId) {
            selectPlan(planSlug, planId);
        } else {
            goToStep(1);
        }

        if (!isManual) {
            trackModalShown();
            fetch('/' + SITE + '/api/subscription-modal/mark-shown', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'}
            });
        }

        // Load saved cards if logged in
        if (IS_LOGGED_IN) {
            subCurrentMember = {id: <?= $member ? (int)$member->id : 'null' ?>};
            subLoadSavedCards();
        }
    }

    function closeSubscriptionModal() {
        const modal = document.getElementById('subscriptionModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    /* ── Step navigation ────────────────────────────────────────────── */
    function goToStep(step) {
        for (let i = 1; i <= 4; i++) {
            const el = document.getElementById('step-' + i);
            const prog = document.querySelector(`.sub-progress-step[data-step="${i}"]`);
            if (el) el.style.display = 'none';
            if (prog) {
                prog.classList.remove('active');
                i < step ? prog.classList.add('completed') : prog.classList.remove('completed');
            }
        }
        const target = document.getElementById('step-' + step);
        const prog = document.querySelector(`.sub-progress-step[data-step="${step}"]`);
        if (target) target.style.display = 'block';
        if (prog) prog.classList.add('active');
    }

    /* ── Plan selection ─────────────────────────────────────────────── */
    function selectPlan(slug, id) {
        selectedPlanSlug = slug;
        selectedPlanId = id;

        const planEl = document.querySelector(`[data-plan-slug="${slug}"]`);
        if (planEl) {
            selectedPlanData = {
                name: planEl.querySelector('.sub-plan-name')?.textContent ?? '',
                price: planEl.querySelector('.sub-price-amount')?.textContent ?? '',
                currency: planEl.querySelector('.sub-price-currency')?.textContent ?? '',
                period: planEl.querySelector('.sub-price-period')?.textContent ?? '',
            };
        }

        IS_LOGGED_IN ? (goToStep(3), updatePaymentSummary()) : goToStep(2);
    }

    function updatePaymentSummary() {
        if (!selectedPlanData) return;
        const set = (id, v) => {
            const el = document.getElementById(id);
            if (el) el.textContent = v;
        };
        set('summary-plan-name', selectedPlanData.name);
        set('summary-billing', 'Billed ' + selectedPlanData.period.replace('/', ''));
        set('summary-total', selectedPlanData.currency + selectedPlanData.price + selectedPlanData.period);
    }

    /* ── Auth tab switching ─────────────────────────────────────────── */
    document.querySelectorAll('.sub-auth-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            const target = this.dataset.tab;
            document.querySelectorAll('.sub-auth-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('register-form').style.display = target === 'register' ? 'block' : 'none';
            document.getElementById('login-form').style.display = target === 'login' ? 'block' : 'none';
        });
    });

    /* ── Register ───────────────────────────────────────────────────── */
    document.getElementById('register-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(this));

        if (data.password !== data.password_confirmation) {
            document.getElementById('register-error').textContent = 'Passwords do not match';
            return;
        }
        if (data.password.length < 8) {
            document.getElementById('register-error').textContent = 'Password must be at least 8 characters';
            return;
        }
        if (!data.terms) {
            document.getElementById('register-error').textContent = 'You must agree to the terms';
            return;
        }

        showLoading();
        try {
            const res = await fetch('/' + SITE + '/member/register', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const result = await res.json();

            if (result.success) {
                subCurrentMember = result.member;
                hideLoading();
                if (forceVerification && result.requires_verification) {
                    showVerificationNotice(data.email);
                } else {
                    goToStep(3);
                    updatePaymentSummary();
                    subLoadSavedCards();
                }
            } else {
                hideLoading();
                if (result.error === 'email_exists' || result.message?.toLowerCase().includes('already registered')) {
                    const errorDiv = document.getElementById('register-error');
                    let msg = 'This email is already registered. ';
                    if (result.is_verified === false) {
                        msg += '<strong>Your account exists but email is not verified.</strong> Please check your email, or '
                            + '<a href="/' + SITE + '/member/resend-verification" style="color:var(--sub-primary);text-decoration:underline;">click here to resend</a>.';
                    } else {
                        msg += '<strong>Please log in instead.</strong>';
                    }
                    errorDiv.innerHTML = `<div>${msg}</div>`;
                    if (result.is_verified !== false) {
                        setTimeout(() => {
                            document.querySelector('[data-tab="login"]').click();
                            const em = document.querySelector('#login-form input[name="email"]');
                            if (em) {
                                em.value = data.email;
                                em.focus();
                            }
                        }, 2000);
                    }
                } else {
                    document.getElementById('register-error').textContent = result.message || 'Registration failed';
                }
            }
        } catch (err) {
            hideLoading();
            document.getElementById('register-error').textContent = 'An error occurred. Please try again.';
        }
    });

    /* ── Login ──────────────────────────────────────────────────────── */
    document.getElementById('login-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(this));
        showLoading();
        try {
            const res = await fetch('/' + SITE + '/member/login', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                subCurrentMember = result.member;
                hideLoading();
                goToStep(3);
                updatePaymentSummary();
                subLoadSavedCards();
            } else {
                hideLoading();
                document.getElementById('login-error').textContent = result.message || 'Login failed';
            }
        } catch (err) {
            hideLoading();
            document.getElementById('login-error').textContent = 'An error occurred. Please try again.';
        }
    });

    /* ── Payment ────────────────────────────────────────────────────── */
    document.getElementById('payment-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!subStripe || !subCardElement) {
            alert('Payment processing is not available');
            return;
        }

        // Validate auto-renewal consent
        const consentCb = document.getElementById('sub-global-renewal-consent');
        if (consentCb && !consentCb.checked) {
            const block = document.getElementById('global-renewal-consent-block');
            block?.classList.add('consent-error');
            alert('Please agree to the subscription terms to continue.');
            return;
        }

        showLoading();
        try {
            let paymentMethodId;

            if (subSelectedCardId) {
                paymentMethodId = subSelectedCardId;
            } else {
                const {paymentMethod, error: pmError} = await subStripe.createPaymentMethod({
                    type: 'card',
                    card: subCardElement
                });
                if (pmError) {
                    hideLoading();
                    document.getElementById('card-errors').textContent = pmError.message;
                    return;
                }
                paymentMethodId = paymentMethod.id;
            }

            const body = {
                subscription_plan_id: selectedPlanId,
                payment_method: 'stripe',
                payment_method_id: paymentMethodId,
                auto_renew_consent: consentCb ? consentCb.checked : true,
            };

            if (subAppliedVoucher) body.voucher_code = subAppliedVoucher.voucher?.code ?? subAppliedVoucher.code;

            const res = await fetch(API_BASE_MODAL + '/checkout/process', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body)
            });
            const result = await res.json();

            if (!result.success) {
                hideLoading();
                document.getElementById('card-errors').textContent = result.message || 'Payment failed';
                return;
            }

            if (result.requires_action && result.payment_intent_client_secret) {
                const {error: confirmError} = await subStripe.confirmCardPayment(result.payment_intent_client_secret);
                if (confirmError) {
                    hideLoading();
                    document.getElementById('card-errors').textContent = confirmError.message;
                    return;
                }
            }

            hideLoading();
            goToStep(4);

        } catch (err) {
            hideLoading();
            document.getElementById('card-errors').textContent = 'An error occurred. Please try again.';
        }
    });

    /* ── Voucher (scoped — uses modal-specific state) ───────────────── */
    async function subApplyVoucher() {
        const input = document.getElementById('voucher-input');
        const msgEl = document.getElementById('voucher-message');
        const code = input?.value.trim();

        if (!code) {
            subShowVoucherMsg('Please enter a voucher code', 'error');
            return;
        }
        if (!selectedPlanSlug) {
            subShowVoucherMsg('Please select a plan first', 'error');
            return;
        }

        const applyBtn = document.getElementById('apply-voucher-btn') ?? document.querySelector('[onclick="subApplyVoucher()"]');
        if (applyBtn) {
            applyBtn.disabled = true;
            applyBtn.textContent = 'Validating...';
        }

        try {
            const res = await fetch(`/api/${SITE}/subscription-plans/${selectedPlanSlug}/validate-voucher`, {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({voucher_code: code}),
            });
            const result = await res.json();

            if (result.success) {
                subAppliedVoucher = result.data;
                subShowVoucherApplied();
                subUpdateSummaryWithDiscount();
                subShowVoucherMsg('Voucher applied successfully!', 'success');
                const group = document.querySelector('.sub-modal-container .voucher-section div[style*="flex"]');
                if (group) group.style.display = 'none';
            } else {
                subShowVoucherMsg(result.message || 'Invalid code', 'error');
            }
        } catch (err) {
            subShowVoucherMsg('Failed to validate voucher', 'error');
        } finally {
            if (applyBtn) {
                applyBtn.disabled = false;
                applyBtn.textContent = 'Apply';
            }
        }
    }

    function subRemoveVoucher() {
        subAppliedVoucher = null;
        const applied = document.getElementById('applied-voucher');
        const row = document.getElementById('discount-row');
        if (applied) applied.style.display = 'none';
        if (row) row.style.display = 'none';
        if (document.getElementById('voucher-input')) document.getElementById('voucher-input').value = '';
        const group = document.querySelector('.sub-modal-container .voucher-section div[style*="display: none"]');
        if (group) group.style.display = 'flex';
        updatePaymentSummary();
    }

    function subShowVoucherApplied() {
        if (!subAppliedVoucher) return;
        const codeEl = document.getElementById('voucher-code-display');
        const discountEl = document.getElementById('voucher-discount-display');
        const appliedEl = document.getElementById('applied-voucher');
        if (codeEl) codeEl.textContent = subAppliedVoucher.voucher?.code ?? '';
        if (discountEl) discountEl.textContent = (subAppliedVoucher.voucher?.currency ?? '$') + (subAppliedVoucher.discount ?? 0).toFixed(2);
        if (appliedEl) appliedEl.style.display = 'block';
    }

    function subShowVoucherMsg(message, type) {
        const el = document.getElementById('voucher-message');
        if (!el) return;
        el.textContent = message;
        el.className = `sub-voucher-message ${type}`;
    }

    function subUpdateSummaryWithDiscount() {
        if (!selectedPlanData || !subAppliedVoucher) return;
        const finalPrice = subAppliedVoucher.final_price ?? 0;
        const discount = subAppliedVoucher.discount ?? 0;
        document.getElementById('summary-plan-name').textContent = selectedPlanData.name;
        document.getElementById('summary-billing').innerHTML = `
            <div>Billed ${selectedPlanData.period.replace('/', '')}</div>
            <div style="font-size:13px;color:#28a745;margin-top:4px;">Voucher: -${selectedPlanData.currency}${discount.toFixed(2)}</div>`;
        document.getElementById('summary-total').innerHTML = `
            <div style="text-decoration:line-through;font-size:14px;color:#999;font-weight:400;">${selectedPlanData.currency}${selectedPlanData.price}${selectedPlanData.period}</div>
            <div style="color:#28a745;">${selectedPlanData.currency}${finalPrice.toFixed(2)}${selectedPlanData.period}</div>`;
    }

    /* ── Loading helpers ────────────────────────────────────────────── */
    function showLoading() {
        document.getElementById('sub-loading').classList.add('show');
    }

    function hideLoading() {
        document.getElementById('sub-loading').classList.remove('show');
    }

    /* ── Verification notice ────────────────────────────────────────── */
    function showVerificationNotice(email) {
        const errorDiv = document.getElementById('register-error');
        errorDiv.style.cssText = 'background:#dbeafe;color:#1e40af;border-left:4px solid #3b82f6;';
        errorDiv.innerHTML = `
        <div style="display:flex;align-items:start;gap:.75rem;">
            <span style="font-size:1.25rem;">📧</span>
            <div>
                <strong>Verification Email Sent!</strong>
                <p style="margin-top:.25rem;font-size:.875rem;">We've sent a link to <strong>${email}</strong>. Please verify before continuing.</p>
                <button onclick="resendVerificationEmail('${email}')" style="margin-top:.75rem;padding:.5rem 1rem;background:#3b82f6;color:white;border:none;border-radius:.375rem;cursor:pointer;font-size:.875rem;font-weight:600;">Resend Email</button>
            </div>
        </div>`;
    }

    async function resendVerificationEmail(email) {
        try {
            const res = await fetch('/' + SITE + '/member/resend-verification', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email})
            });
            const result = await res.json();
            alert(result.success ? 'Verification email resent! Please check your inbox.' : (result.message || 'Failed to resend email.'));
        } catch (err) {
            alert('An error occurred. Please try again.');
        }
    }

    /* ── Cooldown helpers ───────────────────────────────────────────── */
    function shouldShowModalForVisitor() {
        const last = localStorage.getItem(MODAL_STORAGE_KEY);
        return !last || ((Date.now() - parseInt(last, 10)) / 3600000) >= MODAL_COOLDOWN_HOURS;
    }

    function trackModalShown() {
        if (!IS_LOGGED_IN) localStorage.setItem(MODAL_STORAGE_KEY, Date.now().toString());
    }

    /* ── Event listeners ────────────────────────────────────────────── */
    document.querySelector('.sub-modal-overlay')?.addEventListener('click', closeSubscriptionModal);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeSubscriptionModal();
    });

    /* ── Auto-show after delay ──────────────────────────────────────── */
    <?php if ($subscriptionModalData['show_modal'] ?? false): ?>
    setTimeout(() => {
        if (IS_LOGGED_IN || shouldShowModalForVisitor()) showSubscriptionModal(null, null, false);
    }, 3000);
    <?php endif; ?>

    /* ── Public API ─────────────────────────────────────────────────── */
    window.showSubscriptionModalWithPlan = (slug, id) => showSubscriptionModal(slug, id, true);
    window.openSubscriptionModal = () => showSubscriptionModal(null, null, true);
</script>