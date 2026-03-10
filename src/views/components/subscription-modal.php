<?php
// views/components/subscription-modal.php
/**
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

        <!-- Step 1: Choose Plan -->
        <div class="sub-step" id="step-1" style="display: block;">
            <div class="sub-header">
                <h2 class="sub-title">Choose Your Plan</h2>
                <p class="sub-subtitle">Select the perfect plan for your needs</p>
            </div>

            <div class="sub-plans">
                <?php foreach ($plans as $plan): ?>
                    <div class="sub-plan <?= $plan->is_featured ? 'featured' : '' ?>" data-plan-id="<?= $plan->id ?>"
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

                        <button class="sub-plan-btn"
                                onclick="selectPlan('<?= htmlspecialchars($plan->slug) ?>', <?= $plan->id ?>)">
                            Select Plan
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 2: Account (Register/Login) -->
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
                        <span>I agree to the <a href="/terms" target="_blank">Terms</a> and <a href="/privacy"
                                                                                               target="_blank">Privacy Policy</a></span>
                    </label>
                </div>

                <div class="sub-error" id="register-error"></div>

                <button type="submit" class="sub-btn-primary">
                    Create Account & Continue
                </button>
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

                <button type="submit" class="sub-btn-primary">
                    Sign In & Continue
                </button>

                <div class="sub-form-footer">
                    <a href="/member/forgot-password" target="_blank">Forgot password?</a>
                </div>
            </form>

            <button class="sub-btn-back" onclick="goToStep(1)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Plans
            </button>
        </div>

        <!-- Step 3: Payment -->
        <div class="sub-step" id="step-3" style="display: none;">
            <div class="sub-header">
                <h2 class="sub-title">Payment Details</h2>
                <p class="sub-subtitle">Secure checkout with Stripe</p>
            </div>

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

            <div class="sub-voucher-section">
                <div class="sub-form-group">
                    <label class="sub-label">
                        Have a voucher code?
                        <span class="sub-hint">Optional - Enter your discount code</span>
                    </label>
                    <div class="sub-voucher-input-group">
                        <input
                                type="text"
                                id="voucher-code"
                                class="sub-input"
                                placeholder="Enter voucher code"
                                style="margin-bottom: 0;"
                        >
                        <button
                                type="button"
                                id="apply-voucher-btn"
                                class="sub-btn-voucher"
                                onclick="applyVoucher()"
                        >
                            Apply
                        </button>
                    </div>
                    <div id="voucher-message" class="sub-voucher-message"></div>
                </div>

                <div id="voucher-applied" class="sub-voucher-applied" style="display: none;">
                    <div class="sub-voucher-success">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <div class="sub-voucher-details">
                            <strong id="voucher-discount-text">-</strong>
                            <span id="voucher-code-text">-</span>
                        </div>
                    </div>
                    <button type="button" class="sub-voucher-remove" onclick="removeVoucher()">
                        ✕
                    </button>
                </div>
            </div>

            <form id="payment-form" class="sub-form">
                <div class="sub-form-group">
                    <label class="sub-label">Card Information</label>
                    <div id="card-element" class="sub-card-element"></div>
                    <div id="card-errors" class="sub-error"></div>
                </div>

                <button type="submit" class="sub-btn-primary" id="submit-payment">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    Complete Subscription
                </button>

                <div class="sub-form-group" style="margin-top: 16px;">
                    <label class="sub-checkbox">
                        <input type="checkbox" id="auto-renew-consent" name="auto_renew" checked>
                        <span>
                            I agree to automatic renewal. Your subscription will renew automatically at the end of each billing period.
                            You can cancel at any time. By checking this box you consent to recurring charges per our
                            <a href="/terms" target="_blank">Terms</a>.
                        </span>
                    </label>
                </div>
            </form>

            <div class="sub-security">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Secured by Stripe • 256-bit SSL encryption
            </div>

            <button class="sub-btn-back" onclick="goToStep(<?= $isLoggedIn ? 1 : 2 ?>)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back
            </button>
        </div>

        <!-- Step 4: Success -->
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

                <button class="sub-btn-primary"
                        onclick="window.location.href='/<?= \App\Framework\Support\SiteContext::slug() ?>/member/dashboard'">
                    Go to Dashboard
                </button>
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

    .sub-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 99999;
        animation: subFadeIn 0.3s ease-out;
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
            transform: translateY(30px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .sub-modal-overlay {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.7);
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
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        animation: subSlideUp 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
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
        transition: all 0.2s;
        z-index: 10;
    }

    .sub-modal-close:hover {
        background: var(--sub-border);
        color: var(--sub-text);
        transform: rotate(90deg);
    }

    /* Progress Indicator */
    .sub-progress {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 48px;
        gap: 0;
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
        transition: all 0.3s;
    }

    .sub-progress-step.active .sub-progress-circle {
        background: var(--sub-primary);
        border-color: var(--sub-primary);
        color: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
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
        letter-spacing: 0.5px;
    }

    .sub-progress-step.active .sub-progress-label {
        color: var(--sub-primary);
    }

    .sub-progress-line {
        width: 80px;
        height: 2px;
        background: var(--sub-border);
        margin: 0 4px 24px 4px;
        transition: all 0.3s;
    }

    .sub-progress-step.active ~ .sub-progress-line {
        background: var(--sub-border);
    }

    /* Header */
    .sub-header {
        text-align: center;
        margin-bottom: 40px;
    }

    .sub-title {
        font-size: 36px;
        font-weight: 800;
        color: var(--sub-text);
        margin-bottom: 8px;
        letter-spacing: -0.5px;
    }

    .sub-subtitle {
        font-size: 16px;
        color: var(--sub-text-light);
    }

    /* Plans Grid */
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
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        cursor: pointer;
    }

    .sub-plan:hover {
        border-color: var(--sub-primary);
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(99, 102, 241, 0.15);
    }

    .sub-plan.featured {
        border-color: var(--sub-primary);
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
        transform: scale(1.05);
    }

    .sub-plan.featured:hover {
        transform: translateY(-4px) scale(1.05);
        box-shadow: 0 20px 40px rgba(99, 102, 241, 0.2);
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
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
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

    .sub-plan-btn {
        width: 100%;
        padding: 14px 24px;
        background: var(--sub-primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
    }

    .sub-plan-btn:hover {
        background: var(--sub-primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3);
    }

    .sub-plan.featured .sub-plan-btn {
        background: linear-gradient(135deg, var(--sub-primary), #8b5cf6);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    }

    /* Auth Toggle */
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
        transition: all 0.3s;
    }

    .sub-auth-tab.active {
        background: white;
        color: var(--sub-primary);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    /* Forms */
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
        transition: all 0.3s;
    }

    .sub-input:focus {
        outline: none;
        border-color: var(--sub-primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
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

    .sub-checkbox a:hover {
        text-decoration: underline;
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

    .sub-btn-primary {
        width: 100%;
        padding: 16px 24px;
        background: linear-gradient(135deg, var(--sub-primary), var(--sub-primary-dark));
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .sub-btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(99, 102, 241, 0.3);
    }

    .sub-btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
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

    .sub-form-footer a:hover {
        text-decoration: underline;
    }

    .sub-btn-back {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 12px 24px;
        background: transparent;
        border: 2px solid var(--sub-border);
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        color: var(--sub-text-light);
        cursor: pointer;
        margin-top: 16px;
        transition: all 0.3s;
    }

    .sub-btn-back:hover {
        border-color: var(--sub-primary);
        color: var(--sub-primary);
        background: rgba(99, 102, 241, 0.05);
    }

    /* Payment Summary */
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

    .sub-card-element {
        padding: 14px 16px;
        border: 2px solid var(--sub-border);
        border-radius: 10px;
        background: white;
        transition: all 0.3s;
    }

    .sub-card-element:focus-within {
        border-color: var(--sub-primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }

    .sub-security {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 13px;
        color: var(--sub-text-light);
        margin-top: 24px;
    }

    .sub-security svg {
        color: var(--sub-success);
    }

    /* Success */
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
        animation: successPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
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

    /* Loading */
    .sub-loading {
        display: none;
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.95);
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
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .sub-loading p {
        font-size: 16px;
        font-weight: 600;
        color: var(--sub-text);
    }

    /* Responsive */
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

        .sub-progress {
            gap: 0;
        }

        .sub-progress-label {
            display: none;
        }

        .sub-progress-line {
            width: 40px;
            margin-bottom: 0;
        }
    }

    .sub-voucher-section {
        margin-bottom: 24px;
    }

    .sub-voucher-input-group {
        display: flex;
        gap: 12px;
    }

    .sub-voucher-input-group .sub-input {
        flex: 1;
    }

    .sub-btn-voucher {
        padding: 12px 24px;
        background: white;
        border: 2px solid var(--sub-primary);
        color: var(--sub-primary);
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .sub-btn-voucher:hover {
        background: var(--sub-primary);
        color: white;
    }

    .sub-btn-voucher:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .sub-voucher-message {
        margin-top: 8px;
        font-size: 14px;
        padding: 10px 14px;
        border-radius: 8px;
        display: none;
    }

    .sub-voucher-message.success {
        display: block;
        background: #d4edda;
        color: #155724;
    }

    .sub-voucher-message.error {
        display: block;
        background: #f8d7da;
        color: #721c24;
    }

    .sub-voucher-applied {
        margin-top: 16px;
        padding: 16px;
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        border-radius: 10px;
        border: 2px solid #28a745;
    }

    .sub-voucher-success {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .sub-voucher-success svg {
        color: #28a745;
        flex-shrink: 0;
    }

    .sub-voucher-details {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .sub-voucher-details strong {
        font-size: 16px;
        color: #155724;
    }

    .sub-voucher-details span {
        font-size: 13px;
        color: #28a745;
        font-weight: 600;
    }

    .sub-voucher-remove {
        background: transparent;
        border: none;
        color: #28a745;
        font-size: 20px;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .sub-voucher-remove:hover {
        background: rgba(40, 167, 69, 0.1);
    }

    /* Single Plan Layout Improvements */
    .sub-plans:has(.sub-plan:only-child) {
        display: flex;
        justify-content: center;
    }

    .sub-plans:has(.sub-plan:only-child) .sub-plan {
        max-width: 500px;
        width: 100%;
    }

    .sub-plan:only-child {
        transform: scale(1) !important;
    }

    .sub-plan:only-child .sub-plan-badge {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .sub-plan:only-child:hover {
        transform: scale(1.02) !important;
    }

    /* Center single plan content */
    .sub-plans:has(.sub-plan:only-child) {
        padding: 2rem 0;
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
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    const API_BASE = '/api/' + SITE;
    const STRIPE_KEY = '<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? '' ?>';
    const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
    const MODAL_STORAGE_KEY = 'subscription_modal_last_shown_' + SITE;
    const MODAL_COOLDOWN_HOURS = 24;
    forceVerification = false

    let stripe = null;
    let cardElement = null;
    let selectedPlanId = null;
    let selectedPlanSlug = null;
    let selectedPlanData = null;
    let currentMember = null;

    // Initialize Stripe
    if (STRIPE_KEY) {
        stripe = Stripe(STRIPE_KEY);
        const elements = stripe.elements();
        cardElement = elements.create('card', {
            hidePostalCode: true,
            style: {
                base: {
                    fontSize: '15px',
                    color: '#0f172a',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                    '::placeholder': {color: '#94a3b8'}
                },
                invalid: {
                    color: '#ef4444',
                    iconColor: '#ef4444'
                }
            }
        });
    }

    // Show modal
    function showSubscriptionModal(planSlug = null, planId = null, isManual = false) {
        const modal = document.getElementById('subscriptionModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Mount Stripe card element when step 3 becomes visible
            setTimeout(() => {
                if (cardElement && document.getElementById('card-element')) {
                    cardElement.mount('#card-element');
                }
            }, 100);

            if (planSlug && planId) {
                selectPlan(planSlug, planId);
            } else {
                // Show step 1 (plan selection)
                goToStep(1);
            }

            // Only track if not a manual open
            if (!isManual) {
                trackModalShown();

                // Mark as shown on server (for logged-in users)
                fetch('/' + SITE + '/api/subscription-modal/mark-shown', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'}
                });
            }
        }
    }

    // Close modal
    function closeSubscriptionModal() {
        const modal = document.getElementById('subscriptionModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    // Navigate to step
    function goToStep(step) {
        // Hide all steps
        for (let i = 1; i <= 4; i++) {
            const stepEl = document.getElementById('step-' + i);
            if (stepEl) stepEl.style.display = 'none';

            const progressStep = document.querySelector(`.sub-progress-step[data-step="${i}"]`);
            if (progressStep) {
                progressStep.classList.remove('active');
                if (i < step) progressStep.classList.add('completed');
                else progressStep.classList.remove('completed');
            }
        }

        // Show target step
        const targetStep = document.getElementById('step-' + step);
        if (targetStep) {
            targetStep.style.display = 'block';

            const progressStep = document.querySelector(`.sub-progress-step[data-step="${step}"]`);
            if (progressStep) progressStep.classList.add('active');
        }
    }

    // Select plan
    function selectPlan(slug, id) {
        selectedPlanSlug = slug;
        selectedPlanId = id;

        // Get plan data
        const planEl = document.querySelector(`[data-plan-slug="${slug}"]`);
        if (planEl) {
            selectedPlanData = {
                name: planEl.querySelector('.sub-plan-name').textContent,
                price: planEl.querySelector('.sub-price-amount').textContent,
                currency: planEl.querySelector('.sub-price-currency').textContent,
                period: planEl.querySelector('.sub-price-period').textContent
            };
        }

        if (IS_LOGGED_IN) {
            goToStep(3);
            updatePaymentSummary();
        } else {
            goToStep(2);
        }
    }

    // Update payment summary
    function updatePaymentSummary() {
        if (selectedPlanData) {
            document.getElementById('summary-plan-name').textContent = selectedPlanData.name;
            document.getElementById('summary-billing').textContent = 'Billed ' + selectedPlanData.period.replace('/', '');
            document.getElementById('summary-total').textContent =
                selectedPlanData.currency + selectedPlanData.price + selectedPlanData.period;
        }
    }

    // Auth tab switching
    document.querySelectorAll('.sub-auth-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            const targetTab = this.dataset.tab;

            document.querySelectorAll('.sub-auth-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            document.getElementById('register-form').style.display = targetTab === 'register' ? 'block' : 'none';
            document.getElementById('login-form').style.display = targetTab === 'login' ? 'block' : 'none';
        });
    });

    // Register form
    document.getElementById('register-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = Object.fromEntries(formData);

        // Client-side validation
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
            const response = await fetch('/<?= \App\Framework\Support\SiteContext::slug() ?>/member/register', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                currentMember = result.member;

                // Check if email verification is required
                if (forceVerification && result.requires_verification) {
                    hideLoading();
                    showVerificationNotice(data.email);
                } else {
                    hideLoading();
                    goToStep(3);
                    updatePaymentSummary();
                }
            } else {
                hideLoading();

                // Check if email already exists
                if (result.error === 'email_exists' || result.message?.toLowerCase().includes('already registered')) {
                    const errorDiv = document.getElementById('register-error');

                    let message = 'This email is already registered. ';

                    if (result.is_verified === false) {
                        message += '<strong>Your account exists but email is not verified.</strong> Please check your email for the verification link, or ';
                        message += '<a href="/' + SITE + '/member/resend-verification" style="color: var(--sub-primary); text-decoration: underline;">click here to resend</a>.';
                    } else {
                        message += '<strong>Please log in instead.</strong>';
                    }

                    errorDiv.innerHTML = `<div>${message}</div>`;

                    // Switch to login tab after a moment
                    if (result.is_verified !== false) {
                        setTimeout(() => {
                            document.querySelector('[data-tab="login"]').click();
                            const emailInput = document.querySelector('#login-form input[name="email"]');
                            if (emailInput) {
                                emailInput.value = data.email;
                                emailInput.focus();
                            }
                        }, 2000);
                    }
                } else {
                    document.getElementById('register-error').textContent = result.message || 'Registration failed';
                }
            }
        } catch (error) {
            hideLoading();
            document.getElementById('register-error').textContent = 'An error occurred. Please try again.';
        }
    });

    // Login form
    document.getElementById('login-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = Object.fromEntries(formData);

        showLoading();

        try {
            const response = await fetch('/<?= \App\Framework\Support\SiteContext::slug()?>/member/login', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                currentMember = result.member;
                hideLoading();
                goToStep(3);
                updatePaymentSummary();
            } else {
                hideLoading();
                document.getElementById('login-error').textContent = result.message || 'Login failed';
            }
        } catch (error) {
            hideLoading();
            document.getElementById('login-error').textContent = 'An error occurred. Please try again.';
        }
    });

    // Payment form
    document.getElementById('payment-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!stripe || !cardElement) {
            alert('Payment processing is not available');
            return;
        }

        showLoading();

        try {
            // Create payment method
            const {paymentMethod, error: pmError} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
            });

            if (pmError) {
                hideLoading();
                document.getElementById('card-errors').textContent = pmError.message;
                return;
            }

            // Process subscription
            const requestBody = {
                subscription_plan_id: selectedPlanId,
                payment_method: 'stripe',
                payment_method_id: paymentMethod.id,
                auto_renew_consent: document.getElementById('auto-renew-consent').checked,
            };

            // Include voucher code if applied
            if (appliedVoucher) {
                requestBody.voucher_code = appliedVoucher.voucher.code;
            }

            const response = await fetch(API_BASE + '/checkout/process', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(requestBody)
            });

            const result = await response.json();

            if (!result.success) {
                hideLoading();
                document.getElementById('card-errors').textContent = result.message || 'Payment failed';
                return;
            }

            // Handle 3D Secure if needed
            if (result.requires_action && result.payment_intent_client_secret) {
                const {error: confirmError} = await stripe.confirmCardPayment(
                    result.payment_intent_client_secret
                );

                if (confirmError) {
                    hideLoading();
                    document.getElementById('card-errors').textContent = confirmError.message;
                    return;
                }
            }

            hideLoading();
            goToStep(4);

        } catch (error) {
            hideLoading();
            document.getElementById('card-errors').textContent = 'An error occurred. Please try again.';
        }
    });

    let appliedVoucher = null;

    async function applyVoucher() {
        const input = document.getElementById('voucher-code');
        const button = document.getElementById('apply-voucher-btn');
        const message = document.getElementById('voucher-message');
        const code = input.value.trim();

        if (!code) {
            showVoucherMessage('Please enter a voucher code', 'error');
            return;
        }

        if (!selectedPlanSlug) {
            showVoucherMessage('Please select a plan first', 'error');
            return;
        }

        button.disabled = true;
        button.textContent = 'Validating...';
        message.className = 'sub-voucher-message';
        message.textContent = '';

        try {
            const response = await fetch(`/api/${SITE}/subscription-plans/${selectedPlanSlug}/validate-voucher`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({voucher_code: code})
            });

            const result = await response.json();

            if (result.success) {
                appliedVoucher = result.data;
                showVoucherApplied();
                updatePaymentSummaryWithDiscount();
                showVoucherMessage('Voucher applied successfully!', 'success');

                // Hide input and button
                document.querySelector('.sub-voucher-input-group').style.display = 'none';
            } else {
                showVoucherMessage(result.message, 'error');
            }
        } catch (error) {
            showVoucherMessage('Failed to validate voucher', 'error');
        } finally {
            button.disabled = false;
            button.textContent = 'Apply';
        }
    }

    function removeVoucher() {
        appliedVoucher = null;
        document.getElementById('voucher-applied').style.display = 'none';
        document.querySelector('.sub-voucher-input-group').style.display = 'flex';
        document.getElementById('voucher-code').value = '';
        document.getElementById('voucher-message').className = 'sub-voucher-message';
        updatePaymentSummary();
    }

    function showVoucherApplied() {
        if (!appliedVoucher) return;

        const container = document.getElementById('voucher-applied');
        const discountText = document.getElementById('voucher-discount-text');
        const codeText = document.getElementById('voucher-code-text');

        discountText.textContent = `-${appliedVoucher.voucher.currency || '$'}${appliedVoucher.discount.toFixed(2)} discount`;
        codeText.textContent = `Code: ${appliedVoucher.voucher.code}`;

        container.style.display = 'flex';
    }

    function showVoucherMessage(message, type) {
        const messageEl = document.getElementById('voucher-message');
        messageEl.textContent = message;
        messageEl.className = `sub-voucher-message ${type}`;
    }

    function updatePaymentSummaryWithDiscount() {
        if (selectedPlanData && appliedVoucher) {
            const originalPrice = parseFloat(selectedPlanData.price.replace(/,/g, ''));
            const finalPrice = appliedVoucher.final_price;

            document.getElementById('summary-plan-name').textContent = selectedPlanData.name;
            document.getElementById('summary-billing').innerHTML = `
            <div>
                <div>Billed ${selectedPlanData.period.replace('/', '')}</div>
                <div style="font-size: 13px; color: #28a745; margin-top: 4px;">
                    Voucher: -${selectedPlanData.currency}${appliedVoucher.discount.toFixed(2)}
                </div>
            </div>
        `;
            document.getElementById('summary-total').innerHTML = `
            <div>
                <div style="text-decoration: line-through; font-size: 14px; color: #999; font-weight: 400;">
                    ${selectedPlanData.currency}${selectedPlanData.price}${selectedPlanData.period}
                </div>
                <div style="color: #28a745;">
                    ${selectedPlanData.currency}${finalPrice.toFixed(2)}${selectedPlanData.period}
                </div>
            </div>
        `;
        }
    }

    // Loading helpers
    function showLoading() {
        document.getElementById('sub-loading').classList.add('show');
    }

    function hideLoading() {
        document.getElementById('sub-loading').classList.remove('show');
    }

    // Show verification notice
    function showVerificationNotice(email) {
        const errorDiv = document.getElementById('register-error');
        errorDiv.style.background = '#dbeafe';
        errorDiv.style.color = '#1e40af';
        errorDiv.style.borderLeft = '4px solid #3b82f6';
        errorDiv.innerHTML = `
        <div style="display: flex; align-items: start; gap: 0.75rem;">
            <span style="font-size: 1.25rem;">📧</span>
            <div>
                <strong>Verification Email Sent!</strong>
                <p style="margin-top: 0.25rem; font-size: 0.875rem;">
                    We've sent a verification link to <strong>${email}</strong>.
                    Please check your inbox and click the link to verify your account before proceeding with payment.
                </p>
                <button onclick="resendVerificationEmail('${email}')"
                        style="margin-top: 0.75rem; padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem; font-weight: 600;">
                    Resend Verification Email
                </button>
            </div>
        </div>
    `;
    }

    async function resendVerificationEmail(email) {
        try {
            const response = await fetch('/' + SITE + '/member/resend-verification', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email})
            });

            const result = await response.json();

            if (result.success) {
                alert('Verification email resent! Please check your inbox.');
            } else {
                alert(result.message || 'Failed to resend email. Please try again.');
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
        }
    }

    // Check if we should show modal for non-logged-in visitors
    function shouldShowModalForVisitor() {
        const lastShown = localStorage.getItem(MODAL_STORAGE_KEY);

        if (!lastShown) {
            return true;
        }

        const lastShownTime = parseInt(lastShown, 10);
        const hoursSince = (Date.now() - lastShownTime) / (1000 * 60 * 60);

        return hoursSince >= MODAL_COOLDOWN_HOURS;
    }

    function trackModalShown() {
        if (!IS_LOGGED_IN) {
            localStorage.setItem(MODAL_STORAGE_KEY, Date.now().toString());
        }
    }

    // Close on overlay click
    document.querySelector('.sub-modal-overlay')?.addEventListener('click', closeSubscriptionModal);

    // Close on ESC
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeSubscriptionModal();
    });

    // Show modal after delay
    <?php if ($subscriptionModalData['show_modal'] ?? false): ?>
    setTimeout(() => {
        // For logged-in users, server already checked - just show
        if (IS_LOGGED_IN) {
            showSubscriptionModal(null, null, false);
        }
        // For visitors, check localStorage first
        else if (shouldShowModalForVisitor()) {
            showSubscriptionModal(null, null, false);
        }
    }, 3000);
    <?php endif; ?>

    // Manual trigger (from button clicks) - always show, don't track
    window.showSubscriptionModalWithPlan = function (planSlug, planId) {
        showSubscriptionModal(planSlug, planId, true);
    };

    // Also add a manual trigger without plan
    window.openSubscriptionModal = function () {
        showSubscriptionModal(null, null, true);
    };
</script>