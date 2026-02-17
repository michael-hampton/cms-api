<?php

use App\Framework\Support\SiteContext;

?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Create your password – <?= htmlspecialchars($site->name ?? '') ?></title>
        <style>
            *, *::before, *::after {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            :root {
                --color-bg: #f8f9fa;
                --color-surface: #ffffff;
                --color-border: #dee2e6;
                --color-border-focus: #0d6efd;
                --color-text: #212529;
                --color-muted: #6c757d;
                --color-error: #dc3545;
                --color-success: #198754;
                --color-rule-met: #198754;
                --color-rule-unmet: #6c757d;
                --color-btn: #0d6efd;
                --color-btn-hover: #0b5ed7;
                --color-btn-disabled: #adb5bd;
                --radius: 8px;
                --shadow: 0 2px 8px rgba(0, 0, 0, .08);
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: var(--color-bg);
                color: var(--color-text);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
            }

            .card {
                background: var(--color-surface);
                border-radius: var(--radius);
                box-shadow: var(--shadow);
                padding: 2.5rem 2rem;
                width: 100%;
                max-width: 440px;
            }

            /* ── Header ── */
            .card__lock {
                display: flex;
                justify-content: center;
                margin-bottom: 1.25rem;
            }

            .card__lock svg {
                width: 40px;
                height: 40px;
                color: var(--color-btn);
            }

            h1 {
                font-size: 1.5rem;
                font-weight: 700;
                text-align: center;
                margin-bottom: .375rem;
            }

            .subtitle {
                font-size: .9rem;
                color: var(--color-muted);
                text-align: center;
                margin-bottom: 2rem;
            }

            .subtitle strong {
                color: var(--color-text);
                font-weight: 500;
            }

            /* ── Form ── */
            .form-group {
                margin-bottom: 1.25rem;
            }

            label {
                display: block;
                font-size: .875rem;
                font-weight: 500;
                margin-bottom: .375rem;
            }

            .input-wrap {
                position: relative;
                display: flex;
                align-items: center;
            }

            .input-wrap input {
                width: 100%;
                padding: .625rem 2.75rem .625rem .875rem;
                border: 1.5px solid var(--color-border);
                border-radius: var(--radius);
                font-size: 1rem;
                transition: border-color .15s, box-shadow .15s;
                outline: none;
                color: var(--color-text);
            }

            .input-wrap input:focus {
                border-color: var(--color-border-focus);
                box-shadow: 0 0 0 3px rgba(13, 110, 253, .15);
            }

            .input-wrap input[aria-invalid="true"] {
                border-color: var(--color-error);
            }

            .input-wrap input[aria-invalid="true"]:focus {
                box-shadow: 0 0 0 3px rgba(220, 53, 69, .15);
            }

            /* Visibility toggle */
            .toggle-visibility {
                position: absolute;
                right: .75rem;
                background: none;
                border: none;
                cursor: pointer;
                padding: .25rem;
                color: var(--color-muted);
                line-height: 0;
                border-radius: 4px;
            }

            .toggle-visibility:focus-visible {
                outline: 2px solid var(--color-border-focus);
                outline-offset: 2px;
            }

            .toggle-visibility svg {
                width: 20px;
                height: 20px;
            }

            /* Inline field error */
            .field-error {
                display: none;
                font-size: .8125rem;
                color: var(--color-error);
                margin-top: .3rem;
            }

            .field-error[aria-live] {
                display: block;
            }

            /* always in DOM for screen readers */
            .field-error:not(:empty) {
                display: block;
            }

            /* ── Password rules ── */
            .rules {
                list-style: none;
                margin: .625rem 0 0;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: .25rem .5rem;
            }

            .rules li {
                font-size: .8125rem;
                color: var(--color-rule-unmet);
                display: flex;
                align-items: center;
                gap: .3rem;
                transition: color .2s;
            }

            .rules li.met {
                color: var(--color-rule-met);
            }

            .rules li svg {
                width: 14px;
                height: 14px;
                flex-shrink: 0;
            }

            /* ── Strength meter ── */
            .strength-wrap {
                margin-top: .75rem;
            }

            .strength-label {
                font-size: .75rem;
                color: var(--color-muted);
                margin-bottom: .25rem;
                display: flex;
                justify-content: space-between;
            }

            .strength-bar {
                height: 4px;
                background: var(--color-border);
                border-radius: 2px;
                overflow: hidden;
            }

            .strength-fill {
                height: 100%;
                width: 0;
                border-radius: 2px;
                transition: width .3s, background .3s;
            }

            /* ── Submit ── */
            .btn-submit {
                width: 100%;
                padding: .75rem;
                background: var(--color-btn);
                color: #fff;
                border: none;
                border-radius: var(--radius);
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: background .15s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: .5rem;
                margin-top: .75rem;
            }

            .btn-submit:hover:not(:disabled) {
                background: var(--color-btn-hover);
            }

            .btn-submit:disabled {
                background: var(--color-btn-disabled);
                cursor: not-allowed;
            }

            .btn-submit .spinner {
                display: none;
                width: 18px;
                height: 18px;
                border: 2.5px solid rgba(255, 255, 255, .4);
                border-top-color: #fff;
                border-radius: 50%;
                animation: spin .7s linear infinite;
            }

            .btn-submit.loading .spinner {
                display: block;
            }

            .btn-submit.loading .btn-label {
                opacity: .7;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            /* ── Success state ── */
            .success-panel {
                display: none;
                text-align: center;
                padding: 1rem 0;
            }

            .success-panel svg {
                width: 56px;
                height: 56px;
                color: var(--color-success);
                margin-bottom: 1rem;
            }

            .success-panel h2 {
                font-size: 1.25rem;
                margin-bottom: .5rem;
            }

            .success-panel p {
                color: var(--color-muted);
                font-size: .9rem;
            }

            .success-panel .countdown {
                font-weight: 600;
                color: var(--color-text);
            }

            /* ── Form error banner ── */
            .form-error-banner {
                display: none;
                background: #fff5f5;
                border: 1px solid var(--color-error);
                border-radius: var(--radius);
                padding: .75rem 1rem;
                font-size: .875rem;
                color: var(--color-error);
                margin-bottom: 1.25rem;
            }

            .form-error-banner:not(:empty) {
                display: block;
            }

            /* ── Why am I seeing this ── */
            .why-link {
                display: block;
                text-align: center;
                margin-top: 1.5rem;
                font-size: .8125rem;
                color: var(--color-muted);
            }

            .why-link a {
                color: var(--color-btn);
                text-decoration: none;
            }

            .why-link a:hover {
                text-decoration: underline;
            }

            /* ── Responsive ── */
            @media (max-width: 480px) {
                .card {
                    padding: 2rem 1.25rem;
                }

                .rules {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>

    <main class="card" id="main-content">

        <!-- ── Lock icon (decorative) ── -->
        <div class="card__lock" role="presentation">
            <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>

        <!-- ── Success panel (hidden until JS reveals it) ── -->
        <div class="success-panel" id="success-panel" role="status" aria-live="polite">
            <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <h2>Password set successfully</h2>
            <p>You're all set. Redirecting in <span class="countdown" id="countdown">5</span>s…</p>
        </div>

        <!-- ── Form panel ── -->
        <div id="form-panel">

            <h1>Create your password</h1>
            <p class="subtitle">
                Finish setting up your account for
                <strong id="masked-email"><?= htmlspecialchars($this->maskEmail($email)) ?></strong>
            </p>

            <!-- Server-side error banner (rendered if redirect-back with errors) -->
            <?php if (!empty($errors['form'])): ?>
                <div class="form-error-banner" role="alert">
                    <?= htmlspecialchars($errors['form']) ?>
                </div>
            <?php endif; ?>

            <!-- JS error banner -->
            <div class="form-error-banner" id="js-error-banner" role="alert" aria-live="assertive"></div>

            <form
                    id="activation-form"
                    method="POST"
                    action="/<?= htmlspecialchars(SiteContext::slug()) ?>/account/activate/<?= htmlspecialchars($token) ?>"
                    novalidate
            >
                <?= csrf_field() ?>

                <!-- Thread the order number through for post-success redirect -->
                <?php if (!empty($orderNumber)): ?>
                    <input type="hidden" name="order_number" value="<?= htmlspecialchars($orderNumber) ?>">
                <?php endif; ?>

                <!-- ── New password ── -->
                <div class="form-group">
                    <label for="password">New password</label>
                    <div class="input-wrap">
                        <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="new-password"
                                aria-describedby="password-error password-rules"
                                aria-required="true"
                                aria-invalid="false"
                        >
                        <button
                                type="button"
                                class="toggle-visibility"
                                aria-label="Show password"
                                aria-controls="password"
                                data-target="password"
                        >
                            <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 class="icon-eye">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 class="icon-eye-off" style="display:none">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Password rules (real-time feedback) -->
                    <ul class="rules" id="password-rules" aria-label="Password requirements">
                        <li id="rule-length" data-rule="length">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            8+ characters
                        </li>
                        <li id="rule-upper" data-rule="upper">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Uppercase letter
                        </li>
                        <li id="rule-number" data-rule="number">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Number
                        </li>
                        <li id="rule-special" data-rule="special">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Special character
                        </li>
                    </ul>

                    <!-- Strength meter -->
                    <div class="strength-wrap" aria-hidden="true">
                        <div class="strength-label">
                            <span>Strength</span>
                            <span id="strength-label-text">—</span>
                        </div>
                        <div class="strength-bar">
                            <div class="strength-fill" id="strength-fill"></div>
                        </div>
                    </div>

                    <span class="field-error" id="password-error" role="alert" aria-live="polite"></span>
                </div>

                <!-- ── Confirm password ── -->
                <div class="form-group">
                    <label for="password_confirmation">Confirm password</label>
                    <div class="input-wrap">
                        <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                autocomplete="new-password"
                                aria-describedby="confirm-error"
                                aria-required="true"
                                aria-invalid="false"
                        >
                        <button
                                type="button"
                                class="toggle-visibility"
                                aria-label="Show confirm password"
                                aria-controls="password_confirmation"
                                data-target="password_confirmation"
                        >
                            <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 class="icon-eye">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 class="icon-eye-off" style="display:none">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <span class="field-error" id="confirm-error" role="alert" aria-live="polite"></span>
                </div>

                <button type="submit" class="btn-submit" id="submit-btn" disabled>
                    <span class="spinner" aria-hidden="true"></span>
                    <span class="btn-label">Set your password</span>
                </button>

            </form>

            <p class="why-link">
                <a href="#" id="why-link-toggle">Why am I seeing this?</a>
            </p>

            <div id="why-explanation"
                 style="display:none; margin-top:.75rem; font-size:.8125rem; color:var(--color-muted); line-height:1.6;">
                When you checked out as a guest, we created an account for you so you can
                track your orders. Setting a password activates your account and gives you
                full access.
            </div>
        </div>

    </main>

    <script>
        (function () {
            'use strict';

            // ── Elements ──────────────────────────────────────────────────────────────
            const form = document.getElementById('activation-form');
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('password_confirmation');
            const submitBtn = document.getElementById('submit-btn');
            const formPanel = document.getElementById('form-panel');
            const successPanel = document.getElementById('success-panel');
            const countdownEl = document.getElementById('countdown');
            const passwordErr = document.getElementById('password-error');
            const confirmErr = document.getElementById('confirm-error');
            const jsErrorBanner = document.getElementById('js-error-banner');
            const whyToggle = document.getElementById('why-link-toggle');
            const whyExplain = document.getElementById('why-explanation');
            const strengthFill = document.getElementById('strength-fill');
            const strengthLabel = document.getElementById('strength-label-text');

            // ── Password rules ────────────────────────────────────────────────────────
            const RULES = [
                {key: 'length', test: v => v.length >= 8, label: '8+ characters'},
                {key: 'upper', test: v => /[A-Z]/.test(v), label: 'Uppercase letter'},
                {key: 'number', test: v => /[0-9]/.test(v), label: 'Number'},
                {key: 'special', test: v => /[^A-Za-z0-9]/.test(v), label: 'Special character'},
            ];

            // ── Strength config ───────────────────────────────────────────────────────
            const STRENGTH_MAP = [
                {max: 0, label: '—', color: '#dee2e6', pct: 0},
                {max: 1, label: 'Very weak', color: '#dc3545', pct: 20},
                {max: 2, label: 'Weak', color: '#fd7e14', pct: 45},
                {max: 3, label: 'Fair', color: '#ffc107', pct: 65},
                {max: 4, label: 'Good', color: '#20c997', pct: 85},
                {max: 5, label: 'Strong', color: '#198754', pct: 100},
            ];

            function getStrengthScore(value) {
                let score = RULES.filter(r => r.test(value)).length;
                // Bonus point for long passwords
                if (value.length >= 16) score = Math.min(5, score + 1);
                return score;
            }

            // ── Rule UI ───────────────────────────────────────────────────────────────
            function updateRules(value) {
                RULES.forEach(rule => {
                    const el = document.getElementById('rule-' + rule.key);
                    if (!el) return;
                    const met = rule.test(value);
                    el.classList.toggle('met', met);
                });
            }

            // ── Strength meter UI ─────────────────────────────────────────────────────
            function updateStrength(value) {
                const score = getStrengthScore(value);
                const config = STRENGTH_MAP.find(s => score <= s.max) ?? STRENGTH_MAP[STRENGTH_MAP.length - 1];
                strengthFill.style.width = config.pct + '%';
                strengthFill.style.background = config.color;
                strengthLabel.textContent = config.label;
            }

            // ── Validation state ──────────────────────────────────────────────────────
            function allRulesMet(value) {
                // Only require the first 3 (length, upper, number) — special is a bonus.
                return RULES.slice(0, 3).every(r => r.test(value));
            }

            function passwordsMatch() {
                return passwordInput.value === confirmInput.value;
            }

            function isFormValid() {
                return allRulesMet(passwordInput.value)
                    && confirmInput.value.length > 0
                    && passwordsMatch();
            }

            function setFieldError(input, errorEl, message) {
                errorEl.textContent = message;
                input.setAttribute('aria-invalid', message ? 'true' : 'false');
            }

            function evaluateSubmitState() {
                submitBtn.disabled = !isFormValid();
            }

            // ── Event: password input ─────────────────────────────────────────────────
            passwordInput.addEventListener('input', function () {
                const v = this.value;
                updateRules(v);
                updateStrength(v);

                if (v.length === 0) {
                    setFieldError(this, passwordErr, '');
                } else if (!allRulesMet(v)) {
                    setFieldError(this, passwordErr, 'Password does not meet all requirements above.');
                } else {
                    setFieldError(this, passwordErr, '');
                }

                // Re-evaluate confirm match immediately if user has started confirming.
                if (confirmInput.value.length > 0) {
                    evaluateConfirmMatch();
                }

                evaluateSubmitState();
            });

            // ── Event: confirm password ───────────────────────────────────────────────
            confirmInput.addEventListener('input', function () {
                evaluateConfirmMatch();
                evaluateSubmitState();
            });

            function evaluateConfirmMatch() {
                if (confirmInput.value.length === 0) {
                    setFieldError(confirmInput, confirmErr, '');
                    return;
                }
                if (!passwordsMatch()) {
                    setFieldError(confirmInput, confirmErr, 'Passwords do not match.');
                } else {
                    setFieldError(confirmInput, confirmErr, '');
                }
            }

            // ── Visibility toggles ────────────────────────────────────────────────────
            document.querySelectorAll('.toggle-visibility').forEach(btn => {
                btn.addEventListener('click', function () {
                    const target = document.getElementById(this.dataset.target);
                    const isShown = target.type === 'text';
                    target.type = isShown ? 'password' : 'text';

                    const eyeOn = this.querySelector('.icon-eye');
                    const eyeOff = this.querySelector('.icon-eye-off');
                    eyeOn.style.display = isShown ? '' : 'none';
                    eyeOff.style.display = isShown ? 'none' : '';

                    this.setAttribute('aria-label', isShown ? 'Show password' : 'Hide password');
                });
            });

            // ── Form submission ───────────────────────────────────────────────────────
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                // Final guard — should not be reachable with button disabled, but
                // belt-and-suspenders for JS edge cases.
                if (!isFormValid()) return;

                // Loading state
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                jsErrorBanner.textContent = '';

                try {
                    const formData = new FormData(form);

                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const data = await response.json();

                    if (data.success) {
                        showSuccess(data.redirect_url);
                    } else {
                        // Restore submit button on failure
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('loading');

                        if (data.errors) {
                            if (data.errors.password) setFieldError(passwordInput, passwordErr, data.errors.password);
                            if (data.errors.password_confirmation) setFieldError(confirmInput, confirmErr, data.errors.password_confirmation);
                            if (data.errors.form) jsErrorBanner.textContent = data.errors.form;
                        } else {
                            jsErrorBanner.textContent = data.message || 'Something went wrong. Please try again.';
                        }
                    }

                } catch (err) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    jsErrorBanner.textContent = 'A network error occurred. Please try again.';
                }
            });

            // ── Success state ─────────────────────────────────────────────────────────
            function showSuccess(redirectUrl) {
                formPanel.style.display = 'none';
                successPanel.style.display = 'block';

                let seconds = 5;
                countdownEl.textContent = seconds;

                const interval = setInterval(() => {
                    seconds -= 1;
                    countdownEl.textContent = seconds;

                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = redirectUrl || '/member/orders';
                    }
                }, 1000);
            }

            // ── "Why am I seeing this?" toggle ───────────────────────────────────────
            whyToggle.addEventListener('click', function (e) {
                e.preventDefault();
                const hidden = whyExplain.style.display === 'none';
                whyExplain.style.display = hidden ? 'block' : 'none';
                this.textContent = hidden ? 'Hide explanation' : 'Why am I seeing this?';
            });

        }());
    </script>

    </body>
    </html>

<?php
// ── Template helper: mask email for display ───────────────────────────────────
// Keeps this in the view file since it's purely a presentational concern.
// e.g. mark@example.com → m***@example.com
function maskEmail(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return $email;
    }

    [$local, $domain] = $parts;

    $visible = mb_substr($local, 0, 1);
    $masked = str_repeat('*', max(3, mb_strlen($local) - 1));

    return "{$visible}{$masked}@{$domain}";
}

?>