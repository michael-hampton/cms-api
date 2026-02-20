<?php
/**
 * Step 3: Subscription Linking  (link-subscription.php)
 * Step 4: Preferences           (rendered when $step === 'preferences', or use separate view)
 *
 * Variables available:
 *   $member, $site, $nextStepUrl, $linkUrl, $csrfToken
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Your Subscription – <?= htmlspecialchars($site->name ?? 'My Account') ?></title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #f5f4f0;
            --surface: #ffffff;
            --border: #e2e0d8;
            --text: #1a1a1a;
            --muted: #6b6b6b;
            --accent: #1a1a1a;
            --accent-h: #333;
            --success: #1e7e4a;
            --success-bg: #edf7f1;
            --error: #c0392b;
            --error-bg: #fdf3f2;
            --radius: 8px;
            --radius-lg: 12px;
            --shadow: 0 1px 3px rgba(0, 0, 0, .08), 0 4px 16px rgba(0, 0, 0, .06);
            --transition: .18s ease;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .onboarding-shell {
            width: 100%;
            max-width: 520px;
        }

        /* Progress bar */
        .progress-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 32px;
        }

        .progress-step {
            height: 4px;
            flex: 1;
            border-radius: 99px;
            background: var(--border);
        }

        .progress-step.done {
            background: var(--accent);
        }

        .progress-step.active {
            background: var(--accent);
            opacity: .45;
        }

        .progress-label {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
        }

        /* Card */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 40px 40px 36px;
        }

        @media (max-width: 480px) {
            .card {
                padding: 28px 20px 24px;
            }
        }

        /* Step icon */
        .step-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #f0ece3;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .step-icon svg {
            width: 22px;
            height: 22px;
        }

        h1.step-title {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -.3px;
            margin-bottom: 8px;
        }

        p.step-subtitle {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.55;
            margin-bottom: 28px;
        }

        /* Benefits */
        .benefits {
            list-style: none;
            margin-bottom: 32px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .benefits li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 14px;
        }

        .benefits li .check {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--accent);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 1px;
        }

        .benefits li .check svg {
            width: 10px;
            height: 10px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            font-size: 15px;
            font-weight: 600;
            border-radius: var(--radius);
            padding: 13px 20px;
            cursor: pointer;
            border: none;
            transition: background var(--transition), opacity var(--transition), transform var(--transition);
            text-decoration: none;
            width: 100%;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--accent-h);
        }

        .btn-primary:active {
            transform: scale(.98);
        }

        .btn-ghost {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover {
            background: var(--bg);
        }

        .btn-success {
            background: var(--success);
            color: #fff;
            pointer-events: none;
        }

        .btn:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .decision-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* Form */
        .link-form {
            display: none;
        }

        .link-form.visible {
            display: block;
        }

        .form-divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 28px 0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        input[type="text"], input[type="number"],
        input[type="email"], textarea, select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 15px;
            color: var(--text);
            background: var(--surface);
            transition: border-color var(--transition), box-shadow var(--transition);
            outline: none;
            font-family: inherit;
        }

        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
        }

        input:focus, textarea:focus, select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(26, 26, 26, .08);
        }

        input.is-error {
            border-color: var(--error);
            box-shadow: 0 0 0 3px rgba(192, 57, 43, .08);
        }

        .field-hint {
            font-size: 12px;
            color: var(--muted);
            margin-top: 5px;
        }

        textarea {
            resize: vertical;
            min-height: 90px;
        }

        /* Alert */
        .alert {
            border-radius: var(--radius);
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 18px;
            display: none;
        }

        .alert.visible {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .alert svg {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            margin-top: 1px;
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error);
            border: 1px solid rgba(192, 57, 43, .18);
        }

        /* Spinner */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spinner {
            width: 17px;
            height: 17px;
            border: 2px solid rgba(255, 255, 255, .35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .65s linear infinite;
            display: none;
        }

        .btn.loading .spinner {
            display: block;
        }

        .btn.loading .btn-label {
            opacity: 0;
            position: absolute;
        }

        /* ═══ Preferences (Step 4) ═══════════════════════════════════════ */
        .pref-section {
            margin-bottom: 28px;
        }

        .pref-section-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pref-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Toggle rows */
        .pref-toggle {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }

        .pref-toggle:last-child {
            border-bottom: none;
        }

        .pref-toggle__text {
        }

        .pref-toggle__label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .pref-toggle__desc {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.4;
        }

        /* iOS-style switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 26px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            inset: 0;
            background: #ccc;
            border-radius: 99px;
            cursor: pointer;
            transition: background .2s;
        }

        .slider::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .18);
            transition: transform .2s;
        }

        .switch input:checked + .slider {
            background: var(--accent);
        }

        .switch input:checked + .slider::before {
            transform: translateX(18px);
        }

        /* Frequency picker chips */
        .chip-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            padding: 6px 14px;
            border: 1.5px solid var(--border);
            border-radius: 100px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            background: var(--surface);
            color: var(--text);
        }

        .chip:hover {
            border-color: var(--text);
        }

        .chip.active {
            background: var(--text);
            color: #fff;
            border-color: var(--text);
        }

        /* Category grid */
        .cat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .cat-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            background: var(--surface);
            color: var(--text);
        }

        .cat-chip:hover {
            border-color: var(--text);
        }

        .cat-chip.active {
            background: var(--text);
            color: #fff;
            border-color: var(--text);
        }

        .cat-chip .cat-icon {
            font-size: 18px;
        }

        /* Progress indicator at bottom */
        .save-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 32px;
        }

        .save-bar .hint {
            font-size: 12px;
            color: var(--muted);
        }
    </style>
</head>
<body>
<div class="onboarding-shell">

    <!-- ═══════════════════════════════════════════════════════════
         STEP 3 — Link subscription
    ══════════════════════════════════════════════════════════════ -->
    <div id="step3">

        <div class="progress-bar">
            <div class="progress-step done"></div>
            <div class="progress-step done"></div>
            <div class="progress-step active"></div>
            <div class="progress-step"></div>
            <span class="progress-label">Step 3 of 4</span>
        </div>

        <div class="card">

            <div class="step-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="M3 9h18M9 21V9"/>
                </svg>
            </div>

            <h1 class="step-title">Link your print subscription</h1>
            <p class="step-subtitle">
                Already subscribe in print? Connect your account to unlock your full digital benefits — no extra charge.
            </p>

            <ul class="benefits">
                <li>
                    <span class="check"><svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round" stroke-linejoin="round"><polyline
                                    points="1.5,5 4,7.5 8.5,2.5"/></svg></span>
                    Full digital archive access
                </li>
                <li>
                    <span class="check"><svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round" stroke-linejoin="round"><polyline
                                    points="1.5,5 4,7.5 8.5,2.5"/></svg></span>
                    Manage delivery preferences online
                </li>
                <li>
                    <span class="check"><svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round" stroke-linejoin="round"><polyline
                                    points="1.5,5 4,7.5 8.5,2.5"/></svg></span>
                    Early access to subscriber-only content
                </li>
            </ul>

            <div class="decision-actions" id="decisionActions">
                <button type="button" class="btn btn-primary" id="btnYesLink">Yes, link my subscription</button>
                <button type="button" class="btn btn-ghost" id="btnSkipToPrefs">Skip for now →</button>
            </div>

            <div class="link-form" id="linkForm">
                <hr class="form-divider">

                <div class="alert alert-error" id="alertError" role="alert">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                              clip-rule="evenodd"/>
                    </svg>
                    <span id="alertErrorText"></span>
                </div>

                <form id="subscriptionLinkForm" novalidate>
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="form-group">
                        <label for="accountNumber">Account Number</label>
                        <input type="text" id="accountNumber" name="account_number"
                               placeholder="e.g. 1234567" autocomplete="off" spellcheck="false">
                        <p class="field-hint">Found on your mailing label or subscription confirmation email.</p>
                    </div>

                    <div class="form-group">
                        <label for="postcode">Zip / Post Code</label>
                        <input type="text" id="postcode" name="postcode"
                               placeholder="e.g. SW1A 1AA" autocomplete="postal-code" spellcheck="false">
                        <p class="field-hint">The postcode registered to your print subscription.</p>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btnSubmit" style="margin-top: 8px;">
                        <span class="spinner"></span>
                        <span class="btn-label">Verify &amp; Link Subscription</span>
                    </button>
                </form>

                <div style="margin-top: 12px;">
                    <button type="button" class="btn btn-ghost" id="btnCancel" style="font-size: 13px; padding: 10px;">←
                        Back
                    </button>
                </div>
            </div>

        </div>
    </div><!-- /#step3 -->


    <!-- ═══════════════════════════════════════════════════════════
         STEP 4 — Preferences  (hidden until step 3 completes)
    ══════════════════════════════════════════════════════════════ -->
    <div id="step4" style="display:none;">

        <div class="progress-bar">
            <div class="progress-step done"></div>
            <div class="progress-step done"></div>
            <div class="progress-step done"></div>
            <div class="progress-step active"></div>
            <span class="progress-label">Step 4 of 4</span>
        </div>

        <div class="card">

            <div class="step-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                </svg>
            </div>

            <h1 class="step-title">Personalise your experience</h1>
            <p class="step-subtitle">
                Tell us what you enjoy so we can surface the content that matters most to you. You can change these any
                time from your account.
            </p>

            <form id="preferencesForm" novalidate>
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <!-- Notifications -->
                <div class="pref-section">
                    <div class="pref-section-title">Notifications</div>

                    <div class="pref-toggle">
                        <div class="pref-toggle__text">
                            <div class="pref-toggle__label">Breaking news alerts</div>
                            <div class="pref-toggle__desc">Push notifications for major stories as they break</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notify_breaking" checked>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="pref-toggle">
                        <div class="pref-toggle__text">
                            <div class="pref-toggle__label">Daily digest email</div>
                            <div class="pref-toggle__desc">A morning summary of the day's top stories</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notify_digest" checked>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="pref-toggle">
                        <div class="pref-toggle__text">
                            <div class="pref-toggle__label">Offers &amp; subscriber deals</div>
                            <div class="pref-toggle__desc">Exclusive discounts and early-access promotions</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notify_offers">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Digest frequency -->
                <div class="pref-section">
                    <div class="pref-section-title">Digest frequency</div>
                    <div class="chip-group" id="freqChips">
                        <button type="button" class="chip active" data-value="daily">Daily</button>
                        <button type="button" class="chip" data-value="weekdays">Weekdays only</button>
                        <button type="button" class="chip" data-value="weekly">Weekly</button>
                    </div>
                    <input type="hidden" name="digest_frequency" id="freqInput" value="daily">
                </div>

                <!-- Content interests -->
                <div class="pref-section">
                    <div class="pref-section-title">Content interests</div>
                    <div class="cat-grid" id="catGrid">
                        <button type="button" class="cat-chip active" data-value="news">
                            <span class="cat-icon">🗞️</span> News
                        </button>
                        <button type="button" class="cat-chip" data-value="sport">
                            <span class="cat-icon">🏅</span> Sport
                        </button>
                        <button type="button" class="cat-chip" data-value="business">
                            <span class="cat-icon">💼</span> Business
                        </button>
                        <button type="button" class="cat-chip" data-value="culture">
                            <span class="cat-icon">🎭</span> Culture
                        </button>
                        <button type="button" class="cat-chip" data-value="technology">
                            <span class="cat-icon">💻</span> Technology
                        </button>
                        <button type="button" class="cat-chip" data-value="lifestyle">
                            <span class="cat-icon">🌿</span> Lifestyle
                        </button>
                        <button type="button" class="cat-chip" data-value="travel">
                            <span class="cat-icon">✈️</span> Travel
                        </button>
                        <button type="button" class="cat-chip" data-value="food">
                            <span class="cat-icon">🍽️</span> Food &amp; Drink
                        </button>
                    </div>
                    <input type="hidden" name="interests" id="interestsInput" value="news">
                    <p class="field-hint" style="margin-top: 10px;">Select all that interest you</p>
                </div>

                <!-- Reading preferences -->
                <div class="pref-section">
                    <div class="pref-section-title">Reading</div>

                    <div class="form-group">
                        <label for="prefFontSize">Preferred text size</label>
                        <select id="prefFontSize" name="font_size">
                            <option value="default" selected>Default</option>
                            <option value="large">Large</option>
                            <option value="xlarge">Extra large</option>
                        </select>
                    </div>

                    <div class="pref-toggle">
                        <div class="pref-toggle__text">
                            <div class="pref-toggle__label">Save articles for offline</div>
                            <div class="pref-toggle__desc">Automatically cache articles when on Wi-Fi</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="offline_save">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <div class="alert alert-error" id="alertPrefsError" style="display:none;" role="alert">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                              clip-rule="evenodd"/>
                    </svg>
                    <span id="alertPrefsText"></span>
                </div>

                <div class="save-bar">
                    <span class="hint">You can update these anytime in Settings</span>
                    <button type="submit" class="btn btn-primary" id="btnSavePrefs"
                            style="width: auto; padding: 13px 28px;">
                        <span class="spinner"></span>
                        <span class="btn-label">Save &amp; Finish</span>
                    </button>
                </div>
            </form>

        </div>
    </div><!-- /#step4 -->

</div><!-- /.onboarding-shell -->

<script>
    (function () {
        'use strict';

        const LINK_URL = <?= json_encode($linkUrl) ?>;
        const NEXT_URL = <?= json_encode($nextStepUrl) ?>;
        const PREFS_URL = <?= json_encode(rtrim($nextStepUrl, '/') . '/save') ?>;
        const SUCCESS_DELAY_MS = 1600;

        /* ── Step 3 refs ─────────────────────────────────────────────────────── */
        const step3 = document.getElementById('step3');
        const step4 = document.getElementById('step4');
        const decisionActions = document.getElementById('decisionActions');
        const linkForm = document.getElementById('linkForm');
        const form = document.getElementById('subscriptionLinkForm');
        const btnYesLink = document.getElementById('btnYesLink');
        const btnSkipToPrefs = document.getElementById('btnSkipToPrefs');
        const btnCancel = document.getElementById('btnCancel');
        const btnSubmit = document.getElementById('btnSubmit');
        const alertError = document.getElementById('alertError');
        const alertErrorText = document.getElementById('alertErrorText');
        const inputAccount = document.getElementById('accountNumber');
        const inputPostcode = document.getElementById('postcode');

        /* ── Step 3 – show/hide form ─────────────────────────────────────────── */
        btnYesLink.addEventListener('click', () => {
            decisionActions.style.display = 'none';
            linkForm.classList.add('visible');
            inputAccount.focus();
        });

        btnCancel.addEventListener('click', () => {
            linkForm.classList.remove('visible');
            decisionActions.style.display = 'flex';
            clearErrors();
        });

        btnSkipToPrefs.addEventListener('click', () => showStep4());

        /* ── Step 3 – form submit ────────────────────────────────────────────── */
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            clearErrors();

            const accountNumber = inputAccount.value.trim();
            const postcode = inputPostcode.value.trim();

            let hasError = false;
            if (!accountNumber) {
                markFieldError(inputAccount);
                hasError = true;
            }
            if (!postcode) {
                markFieldError(inputPostcode);
                hasError = true;
            }
            if (hasError) {
                showError('Please fill in both fields before continuing.');
                return;
            }

            setLoading(btnSubmit, true);

            try {
                const response = await fetch(LINK_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': form.querySelector('[name="_token"]').value,
                    },
                    body: JSON.stringify({account_number: accountNumber, postcode}),
                });

                const payload = await response.json();

                if (!response.ok || !payload.success) {
                    setLoading(btnSubmit, false);
                    const code = payload.error_code ?? null;
                    if (code === 'already_linked') {
                        showError(payload.message ?? 'This subscription is already linked to another account.');
                    } else if (code === 'mismatch' || response.status === 422) {
                        markFieldError(inputAccount);
                        markFieldError(inputPostcode);
                        showError('We couldn\'t find a subscription matching those details. Please check your mailing label and try again.');
                    } else {
                        showError(payload.message ?? 'Something went wrong. Please try again.');
                    }
                    return;
                }

                // Success — show brief confirmation then transition to step 4
                setLoading(btnSubmit, false);
                btnSubmit.classList.add('btn-success');
                btnSubmit.querySelector('.btn-label').textContent = '✓ Subscription linked!';

                setTimeout(showStep4, SUCCESS_DELAY_MS);

            } catch (err) {
                setLoading(btnSubmit, false);
                showError('A network error occurred. Please check your connection and try again.');
            }
        });

        /* ── Transition to step 4 ────────────────────────────────────────────── */
        function showStep4() {
            step3.style.transition = 'opacity .3s ease, transform .3s ease';
            step3.style.opacity = '0';
            step3.style.transform = 'translateY(-12px)';
            setTimeout(() => {
                step3.style.display = 'none';
                step4.style.display = 'block';
                step4.style.opacity = '0';
                step4.style.transform = 'translateY(12px)';
                step4.style.transition = 'opacity .3s ease, transform .3s ease';
                requestAnimationFrame(() => {
                    step4.style.opacity = '1';
                    step4.style.transform = 'translateY(0)';
                    window.scrollTo({top: 0, behavior: 'smooth'});
                });
            }, 300);
        }

        /* ── Step 4 – chip / category toggles ───────────────────────────────── */
        // Frequency chips
        document.querySelectorAll('#freqChips .chip').forEach(chip => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('#freqChips .chip').forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                document.getElementById('freqInput').value = chip.dataset.value;
            });
        });

        // Interest categories (multi-select)
        let selectedInterests = new Set(['news']);
        document.querySelectorAll('#catGrid .cat-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const val = chip.dataset.value;
                if (selectedInterests.has(val)) {
                    if (selectedInterests.size > 1) { // keep at least one
                        selectedInterests.delete(val);
                        chip.classList.remove('active');
                    }
                } else {
                    selectedInterests.add(val);
                    chip.classList.add('active');
                }
                document.getElementById('interestsInput').value = [...selectedInterests].join(',');
            });
        });

        /* ── Step 4 – preferences submit ────────────────────────────────────── */
        const prefsForm = document.getElementById('preferencesForm');
        const btnSavePrefs = document.getElementById('btnSavePrefs');

        prefsForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            setLoading(btnSavePrefs, true);

            const fd = new FormData(prefsForm);
            const body = {};
            for (const [k, v] of fd.entries()) body[k] = v;
            body.interests = [...selectedInterests];

            try {
                // POST to /member/onboarding/preferences/save
                const res = await fetch(PREFS_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': prefsForm.querySelector('[name="_token"]').value,
                    },
                    body: JSON.stringify(body),
                });

                // Regardless of API, redirect to dashboard after save
                // (treat errors gracefully — preferences are non-critical)
                window.location.href = NEXT_URL;

            } catch (err) {
                setLoading(btnSavePrefs, false);
                const errEl = document.getElementById('alertPrefsError');
                errEl.style.display = 'flex';
                document.getElementById('alertPrefsText').textContent = 'Could not save preferences. Please try again.';
            }
        });

        /* ── Helpers ─────────────────────────────────────────────────────────── */
        function setLoading(btn, state) {
            btn.disabled = state;
            btn.classList.toggle('loading', state);
        }

        function markFieldError(input) {
            input.classList.add('is-error');
            input.addEventListener('input', function clearOnChange() {
                input.classList.remove('is-error');
                input.removeEventListener('input', clearOnChange);
            });
        }

        function showError(message) {
            alertErrorText.textContent = message;
            alertError.classList.add('visible');
            alertError.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }

        function clearErrors() {
            alertError.classList.remove('visible');
            alertErrorText.textContent = '';
            inputAccount.classList.remove('is-error');
            inputPostcode.classList.remove('is-error');
        }
    }());
</script>
</body>
</html>