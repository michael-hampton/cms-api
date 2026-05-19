<?php
/**
 * Step 3: Subscription Linking  (link-subscription.php)
 * Step 4: Preferences           (hidden until step 3 completes)
 *
 * Variables available:
 *   $member, $site, $nextStepUrl, $prefsSaveUrl, $linkUrl, $csrfToken
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Your Subscription – <?= htmlspecialchars($site->name ?? 'My Account') ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

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
            --shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
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

        .onboarding-shell { width: 100%; max-width: 520px; }

        /* Progress */
        .progress-bar { display: flex; align-items: center; gap: 6px; margin-bottom: 32px; }
        .progress-step { height: 4px; flex: 1; border-radius: 99px; background: var(--border); }
        .progress-step.done   { background: var(--accent); }
        .progress-step.active { background: var(--accent); opacity: .45; }
        .progress-label { font-size: 12px; color: var(--muted); white-space: nowrap; }

        /* Card */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 40px 40px 36px;
        }
        @media (max-width: 480px) { .card { padding: 28px 20px 24px; } }

        /* Step icon */
        .step-icon {
            width: 48px; height: 48px; border-radius: 50%;
            background: #f0ece3;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
        }
        .step-icon svg { width: 22px; height: 22px; }

        h1.step-title { font-size: 22px; font-weight: 700; letter-spacing: -.3px; margin-bottom: 8px; }
        p.step-subtitle { font-size: 14px; color: var(--muted); line-height: 1.55; margin-bottom: 28px; }

        /* Benefits */
        .benefits { list-style: none; margin-bottom: 32px; display: flex; flex-direction: column; gap: 10px; }
        .benefits li { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; }
        .benefits li .check {
            flex-shrink: 0; width: 18px; height: 18px; border-radius: 50%;
            background: var(--accent); color: #fff;
            display: flex; align-items: center; justify-content: center; margin-top: 1px;
        }
        .benefits li .check svg { width: 10px; height: 10px; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            font-size: 15px; font-weight: 600; border-radius: var(--radius);
            padding: 13px 20px; cursor: pointer; border: none;
            transition: background var(--transition), opacity var(--transition), transform var(--transition);
            text-decoration: none; width: 100%; position: relative;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-h); }
        .btn-primary:active { transform: scale(.98); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
        .btn-ghost:hover { background: var(--bg); }
        .btn-success { background: var(--success); color: #fff; pointer-events: none; }
        .btn:disabled { opacity: .55; cursor: not-allowed; }
        .decision-actions { display: flex; flex-direction: column; gap: 10px; }

        /* Linked subscriptions list */
        .linked-list { margin-bottom: 24px; }
        .linked-list__title { font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: 10px; }
        .linked-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 8px;
            font-size: 14px;
        }
        .linked-item__icon { color: var(--success); flex-shrink: 0; }
        .linked-item__icon svg { width: 16px; height: 16px; }

        /* Form */
        .link-form { display: none; }
        .link-form.visible { display: block; }
        .form-divider { border: none; border-top: 1px solid var(--border); margin: 28px 0; }
        .form-group { margin-bottom: 18px; }

        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }

        input[type="text"], input[type="number"], input[type="email"], textarea, select {
            width: 100%; padding: 11px 14px;
            border: 1px solid var(--border); border-radius: var(--radius);
            font-size: 15px; color: var(--text); background: var(--surface);
            transition: border-color var(--transition), box-shadow var(--transition);
            outline: none; font-family: inherit;
        }
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button { -webkit-appearance: none; }
        input:focus, textarea:focus, select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(26,26,26,.08);
        }
        input.is-error {
            border-color: var(--error);
            box-shadow: 0 0 0 3px rgba(192,57,43,.08);
        }

        .field-hint { font-size: 12px; color: var(--muted); margin-top: 5px; }
        .field-hint a, .field-hint button.help-link {
            color: var(--accent); text-decoration: underline;
            background: none; border: none; cursor: pointer;
            font-size: 12px; font-family: inherit; padding: 0;
        }
        .field-hint button.help-link:hover { opacity: .7; }

        /* Alert */
        .alert {
            border-radius: var(--radius); padding: 12px 14px;
            font-size: 13px; line-height: 1.5; margin-bottom: 18px; display: none;
        }
        .alert.visible { display: flex; gap: 10px; align-items: flex-start; }
        .alert svg { flex-shrink: 0; width: 16px; height: 16px; margin-top: 1px; }
        .alert-error { background: var(--error-bg); color: var(--error); border: 1px solid rgba(192,57,43,.18); }

        /* Spinner */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 17px; height: 17px;
            border: 2px solid rgba(255,255,255,.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .65s linear infinite;
            display: none;
        }
        .btn.loading .spinner { display: block; }
        .btn.loading .btn-label { opacity: 0; position: absolute; }

        /* ── Account help modal ────────────────────────────────────────────── */
        .modal-backdrop {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 100;
            display: none;
            align-items: center; justify-content: center;
            padding: 24px;
        }
        .modal-backdrop.open { display: flex; }

        .modal-dialog {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 48px rgba(0,0,0,.18);
            max-width: 440px; width: 100%;
            padding: 32px 28px 28px;
            position: relative;
        }
        .modal-close {
            position: absolute; top: 16px; right: 16px;
            background: none; border: none; cursor: pointer;
            color: var(--muted); padding: 4px;
        }
        .modal-close:hover { color: var(--text); }
        .modal-close svg { width: 18px; height: 18px; }

        .modal-title { font-size: 17px; font-weight: 700; margin-bottom: 12px; }
        .modal-img {
            width: 100%; border-radius: var(--radius);
            border: 1px solid var(--border); margin-bottom: 14px;
            background: #f9f8f5;
            aspect-ratio: 16/7;
            display: flex; align-items: center; justify-content: center;
            color: var(--muted); font-size: 13px;
        }
        .modal-img img { width: 100%; border-radius: var(--radius); display: block; }
        .modal-body { font-size: 14px; line-height: 1.55; color: var(--muted); }

        /* ── Preferences (Step 4) ─────────────────────────────────────────── */
        .pref-section { margin-bottom: 28px; }
        .pref-section-title {
            font-size: 13px; font-weight: 700; letter-spacing: .04em;
            text-transform: uppercase; color: var(--muted); margin-bottom: 14px;
            display: flex; align-items: center; gap: 8px;
        }
        .pref-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .pref-toggle {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 16px; padding: 14px 0; border-bottom: 1px solid var(--border);
        }
        .pref-toggle:last-child { border-bottom: none; }
        .pref-toggle__label { font-size: 14px; font-weight: 600; margin-bottom: 2px; }
        .pref-toggle__desc { font-size: 12px; color: var(--muted); line-height: 1.4; }

        .switch { position: relative; display: inline-block; width: 44px; height: 26px; flex-shrink: 0; margin-top: 2px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; inset: 0; background: #ccc; border-radius: 99px; cursor: pointer; transition: background .2s; }
        .slider::before {
            content: ''; position: absolute; width: 20px; height: 20px;
            left: 3px; bottom: 3px; background: #fff;
            border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,.18); transition: transform .2s;
        }
        .switch input:checked + .slider { background: var(--accent); }
        .switch input:checked + .slider::before { transform: translateX(18px); }

        .chip-group { display: flex; flex-wrap: wrap; gap: 8px; }
        .chip {
            padding: 6px 14px; border: 1.5px solid var(--border);
            border-radius: 100px; font-size: 13px; font-weight: 500;
            cursor: pointer; transition: var(--transition);
            background: var(--surface); color: var(--text);
        }
        .chip:hover { border-color: var(--text); }
        .chip.active { background: var(--text); color: #fff; border-color: var(--text); }

        .cat-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 8px; }
        .cat-chip {
            display: flex; align-items: center; gap: 8px; padding: 10px 12px;
            border: 1.5px solid var(--border); border-radius: var(--radius);
            font-size: 13px; font-weight: 500; cursor: pointer;
            transition: var(--transition); background: var(--surface); color: var(--text);
        }
        .cat-chip:hover { border-color: var(--text); }
        .cat-chip.active { background: var(--text); color: #fff; border-color: var(--text); }
        .cat-chip .cat-icon { font-size: 18px; }

        .save-bar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; margin-top: 32px;
        }
        .save-bar .hint { font-size: 12px; color: var(--muted); }

        textarea { resize: vertical; min-height: 90px; }
    </style>
</head>
<body>
<div class="onboarding-shell">

    <!-- ═══ STEP 3 — Link subscription ═════════════════════════════════ -->
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
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
                    <span class="check"><svg viewBox="0 0 10 10" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1.5,5 4,7.5 8.5,2.5"/></svg></span>
                    Full digital archive access
                </li>
                <li>
                    <span class="check"><svg viewBox="0 0 10 10" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1.5,5 4,7.5 8.5,2.5"/></svg></span>
                    Manage delivery preferences online
                </li>
                <li>
                    <span class="check"><svg viewBox="0 0 10 10" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1.5,5 4,7.5 8.5,2.5"/></svg></span>
                    Early access to subscriber-only content
                </li>
            </ul>

            <!-- Populated by LinkingOrchestrator when subscriptions are already linked -->
            <div id="linkedListContainer" class="linked-list" style="display:none;">
                <p class="linked-list__title">Currently linked subscriptions</p>
                <div id="linkedItems"></div>
            </div>

            <div class="decision-actions" id="decisionActions">
                <button type="button" class="btn btn-primary" id="btnYesLink">Yes, link my subscription</button>
                <button type="button" class="btn btn-ghost"   id="btnSkipToPrefs">Skip for now →</button>
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
                               placeholder="e.g. 1234567890"
                               autocomplete="off" spellcheck="false"
                               inputmode="numeric" maxlength="10">
                        <p class="field-hint">
                            10-digit number from your mailing label &mdash;
                            <button type="button" class="help-link" id="btnAccountHelp">
                                Where can I find it?
                            </button>
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="postcode">Zip / Post Code</label>
                        <input type="text" id="postcode" name="postcode"
                               placeholder="e.g. SW1A 1AA"
                               autocomplete="postal-code" spellcheck="false">
                        <p class="field-hint">The postcode registered to your print subscription.</p>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btnSubmit" style="margin-top: 8px;">
                        <span class="spinner"></span>
                        <span class="btn-label">Verify &amp; Link Subscription</span>
                    </button>
                </form>

                <div style="margin-top: 12px;">
                    <button type="button" class="btn btn-ghost" id="btnCancel"
                            style="font-size: 13px; padding: 10px;">← Back</button>
                </div>
            </div>
        </div>
    </div><!-- /#step3 -->


    <!-- ═══ STEP 4 — Preferences ════════════════════════════════════════ -->
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                </svg>
            </div>

            <h1 class="step-title">Personalise your experience</h1>
            <p class="step-subtitle">
                Tell us what you enjoy so we can surface the content that matters most to you.
                You can change these any time from your account.
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
                        <label class="switch"><input type="checkbox" name="notify_breaking" checked><span class="slider"></span></label>
                    </div>
                    <div class="pref-toggle">
                        <div class="pref-toggle__text">
                            <div class="pref-toggle__label">Daily digest email</div>
                            <div class="pref-toggle__desc">A morning summary of the day's top stories</div>
                        </div>
                        <label class="switch"><input type="checkbox" name="notify_digest" checked><span class="slider"></span></label>
                    </div>
                    <div class="pref-toggle">
                        <div class="pref-toggle__text">
                            <div class="pref-toggle__label">Offers &amp; subscriber deals</div>
                            <div class="pref-toggle__desc">Exclusive discounts and early-access promotions</div>
                        </div>
                        <label class="switch"><input type="checkbox" name="notify_offers"><span class="slider"></span></label>
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
                        <button type="button" class="cat-chip active" data-value="news"><span class="cat-icon">🗞️</span> News</button>
                        <button type="button" class="cat-chip" data-value="sport"><span class="cat-icon">🏅</span> Sport</button>
                        <button type="button" class="cat-chip" data-value="business"><span class="cat-icon">💼</span> Business</button>
                        <button type="button" class="cat-chip" data-value="culture"><span class="cat-icon">🎭</span> Culture</button>
                        <button type="button" class="cat-chip" data-value="technology"><span class="cat-icon">💻</span> Technology</button>
                        <button type="button" class="cat-chip" data-value="lifestyle"><span class="cat-icon">🌿</span> Lifestyle</button>
                        <button type="button" class="cat-chip" data-value="travel"><span class="cat-icon">✈️</span> Travel</button>
                        <button type="button" class="cat-chip" data-value="food"><span class="cat-icon">🍽️</span> Food &amp; Drink</button>
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
                        <label class="switch"><input type="checkbox" name="offline_save"><span class="slider"></span></label>
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

<!-- ═══ Account number help modal ════════════════════════════════════ -->
<div class="modal-backdrop" id="accountHelpModal" role="dialog"
     aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-dialog">
        <button type="button" class="modal-close" id="btnCloseModal" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
        <h2 class="modal-title" id="modalTitle">Finding your account number</h2>
        <div class="modal-img">
            <!-- Swap src for the real magazine label illustration -->
            <img src="/images/magazine-label-guide.png"
                 alt="Magazine label with account number highlighted"
                 onerror="this.parentElement.textContent='[Magazine label illustration]'">
        </div>
        <p class="modal-body">
            Your 10-digit code is found on your magazine label or any letter we&apos;ve sent you.
        </p>
    </div>
</div>

<script>
    (function () {
        'use strict';

        /* ─────────────────────────────────────────────────────────────────
           Config — injected server-side
        ───────────────────────────────────────────────────────────────── */
        const CONFIG = Object.freeze({
            linkUrl:   <?= json_encode($linkUrl) ?>,
            nextUrl:   <?= json_encode($nextStepUrl) ?>,
            prefsUrl:  <?= json_encode($prefsSaveUrl) ?>,
            csrfToken: <?= json_encode($csrfToken) ?>,
            successDelayMs: 1600,
        });

        /* ─────────────────────────────────────────────────────────────────
           Analytics — thin wrapper so callers never touch gtag directly
        ───────────────────────────────────────────────────────────────── */
        class Analytics {
            track(eventName, params = {}) {
                if (typeof gtag === 'function') {
                    gtag('event', eventName, params);
                }
            }

            subscriptionLinked() {
                this.track('subscription_linked', {
                    event_category: 'onboarding',
                    event_label:    'step_3_link_success',
                });
            }

            subscriptionLinkSkipped() {
                this.track('subscription_link_skipped', {
                    event_category: 'onboarding',
                    event_label:    'step_3_skip',
                });
            }
        }

        /* ─────────────────────────────────────────────────────────────────
           LinkingApi — all fetch calls live here
        ───────────────────────────────────────────────────────────────── */
        class LinkingApi {
            async link(accountNumber, postcode) {
                const response = await fetch(CONFIG.linkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type':    'application/json',
                        'Accept':          'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token':    CONFIG.csrfToken,
                    },
                    body: JSON.stringify({ account_number: accountNumber, postcode }),
                });

                const payload = await response.json();
                return { ok: response.ok && payload.success, status: response.status, payload };
            }

            async savePreferences(body) {
                const response = await fetch(CONFIG.prefsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type':    'application/json',
                        'Accept':          'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token':    CONFIG.csrfToken,
                    },
                    body: JSON.stringify(body),
                });
                return { ok: response.ok };
            }
        }

        /* ─────────────────────────────────────────────────────────────────
           FormValidator — client-side rules for the linking form
        ───────────────────────────────────────────────────────────────── */
        class FormValidator {
            validate(accountNumber, postcode, accountInput, postcodeInput) {
                const errors = [];

                if (!accountNumber) {
                    errors.push({ field: accountInput, message: 'Account number is required.' });
                } else if (accountNumber.replace(/\D/g, '').length !== 10) {
                    errors.push({ field: accountInput, message: 'Please enter a valid 10-digit account number.' });
                }

                if (!postcode) {
                    errors.push({ field: postcodeInput, message: 'Postcode is required.' });
                }

                return { valid: errors.length === 0, errors };
            }
        }

        /* ─────────────────────────────────────────────────────────────────
           AccountHelpModal — manages the "where is my number?" modal
        ───────────────────────────────────────────────────────────────── */
        class AccountHelpModal {
            #backdrop;

            constructor() {
                this.#backdrop = document.getElementById('accountHelpModal');
                document.getElementById('btnAccountHelp').addEventListener('click', () => this.open());
                document.getElementById('btnCloseModal').addEventListener('click', () => this.close());
                this.#backdrop.addEventListener('click', (e) => {
                    if (e.target === this.#backdrop) this.close();
                });
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') this.close();
                });
            }

            open() {
                this.#backdrop.classList.add('open');
                document.getElementById('btnCloseModal').focus();
            }

            close() {
                this.#backdrop.classList.remove('open');
            }
        }

        /* ─────────────────────────────────────────────────────────────────
           StepTransition — animates between step 3 and step 4
        ───────────────────────────────────────────────────────────────── */
        class StepTransition {
            showStep4() {
                const step3 = document.getElementById('step3');
                const step4 = document.getElementById('step4');

                step3.style.transition = 'opacity .3s ease, transform .3s ease';
                step3.style.opacity    = '0';
                step3.style.transform  = 'translateY(-12px)';

                setTimeout(() => {
                    step3.style.display = 'none';
                    step4.style.display = 'block';
                    step4.style.opacity = '0';
                    step4.style.transform = 'translateY(12px)';
                    step4.style.transition = 'opacity .3s ease, transform .3s ease';

                    requestAnimationFrame(() => {
                        step4.style.opacity   = '1';
                        step4.style.transform = 'translateY(0)';
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                }, 300);
            }
        }

        /* ─────────────────────────────────────────────────────────────────
           LinkedSubscriptionsList — renders the "already linked" list
        ───────────────────────────────────────────────────────────────── */
        class LinkedSubscriptionsList {
            #container;
            #itemsEl;

            constructor() {
                this.#container = document.getElementById('linkedListContainer');
                this.#itemsEl   = document.getElementById('linkedItems');
            }

            render(subscriptions) {
                if (!subscriptions.length) {
                    this.#container.style.display = 'none';
                    return;
                }

                this.#itemsEl.innerHTML = subscriptions.map(sub => `
                <div class="linked-item">
                    <span class="linked-item__icon">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <span>${this.#escape(sub.accountNumber ?? sub.label ?? 'Subscription')}</span>
                </div>
            `).join('');

                this.#container.style.display = 'block';
            }

            #escape(str) {
                return str
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }
        }

        /* ─────────────────────────────────────────────────────────────────
           PreferencesController — owns Step 4 chip/toggle interactions
           and the save submit
        ───────────────────────────────────────────────────────────────── */
        class PreferencesController {
            #api;
            #selectedInterests;

            constructor(api) {
                this.#api               = api;
                this.#selectedInterests = new Set(['news']);
                this.#bindFreqChips();
                this.#bindCatChips();
                this.#bindSubmit();
            }

            #bindFreqChips() {
                document.querySelectorAll('#freqChips .chip').forEach(chip => {
                    chip.addEventListener('click', () => {
                        document.querySelectorAll('#freqChips .chip').forEach(c => c.classList.remove('active'));
                        chip.classList.add('active');
                        document.getElementById('freqInput').value = chip.dataset.value;
                    });
                });
            }

            #bindCatChips() {
                document.querySelectorAll('#catGrid .cat-chip').forEach(chip => {
                    chip.addEventListener('click', () => {
                        const val = chip.dataset.value;
                        if (this.#selectedInterests.has(val)) {
                            if (this.#selectedInterests.size > 1) {
                                this.#selectedInterests.delete(val);
                                chip.classList.remove('active');
                            }
                        } else {
                            this.#selectedInterests.add(val);
                            chip.classList.add('active');
                        }
                        document.getElementById('interestsInput').value =
                            [...this.#selectedInterests].join(',');
                    });
                });
            }

            #bindSubmit() {
                const form   = document.getElementById('preferencesForm');
                const btnSave = document.getElementById('btnSavePrefs');

                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    this.#setLoading(btnSave, true);

                    const fd   = new FormData(form);
                    const body = {};
                    for (const [k, v] of fd.entries()) body[k] = v;
                    body.interests = [...this.#selectedInterests];

                    try {
                        await this.#api.savePreferences(body);
                        // Preferences are non-critical; redirect regardless of API outcome.
                        window.location.href = CONFIG.nextUrl;
                    } catch {
                        this.#setLoading(btnSave, false);
                        const errEl  = document.getElementById('alertPrefsError');
                        const errTxt = document.getElementById('alertPrefsText');
                        errEl.style.display  = 'flex';
                        errTxt.textContent   = 'Could not save preferences. Please try again.';
                    }
                });
            }

            #setLoading(btn, state) {
                btn.disabled = state;
                btn.classList.toggle('loading', state);
            }
        }

        /* ─────────────────────────────────────────────────────────────────
           LinkingOrchestrator — central state machine for Step 3.
           All Step 3 logic lives here; no handlers exist outside this class.

           State machine:
             decision  → form (user clicks "Yes")
             form      → decision (user clicks "Back")
             form      → success (link accepted)
             success   → decision (user clicks "Link another")
             success   → step4   (user clicks "Continue" or after timeout)
             decision  → step4   (user clicks "Skip")
        ───────────────────────────────────────────────────────────────── */
        class LinkingOrchestrator {
            /** @type {'decision'|'form'|'success'} */
            #state = 'decision';

            #api;
            #analytics;
            #validator;
            #transition;
            #subsList;

            #linkedSubscriptions = [];

            // DOM refs
            #decisionActions;
            #linkForm;
            #form;
            #btnYesLink;
            #btnSkipToPrefs;
            #btnCancel;
            #btnSubmit;
            #alertError;
            #alertErrorText;
            #inputAccount;
            #inputPostcode;

            constructor(api, analytics, validator, transition, subsList) {
                this.#api        = api;
                this.#analytics  = analytics;
                this.#validator  = validator;
                this.#transition = transition;
                this.#subsList   = subsList;

                this.#decisionActions = document.getElementById('decisionActions');
                this.#linkForm        = document.getElementById('linkForm');
                this.#form            = document.getElementById('subscriptionLinkForm');
                this.#btnYesLink      = document.getElementById('btnYesLink');
                this.#btnSkipToPrefs  = document.getElementById('btnSkipToPrefs');
                this.#btnCancel       = document.getElementById('btnCancel');
                this.#btnSubmit       = document.getElementById('btnSubmit');
                this.#alertError      = document.getElementById('alertError');
                this.#alertErrorText  = document.getElementById('alertErrorText');
                this.#inputAccount    = document.getElementById('accountNumber');
                this.#inputPostcode   = document.getElementById('postcode');

                this.#bindEvents();
            }

            #bindEvents() {
                this.#btnYesLink.addEventListener('click',  () => this.#transition_to('form'));
                this.#btnCancel.addEventListener('click',   () => this.#transition_to('decision'));
                this.#btnSkipToPrefs.addEventListener('click', () => {
                    this.#analytics.subscriptionLinkSkipped();
                    this.#transition.showStep4();
                });
                this.#form.addEventListener('submit', (e) => this.#handleSubmit(e));
            }

            #transition_to(newState) {
                this.#state = newState;
                this.#render();
            }

            #render() {
                switch (this.#state) {
                    case 'decision':
                        this.#linkForm.classList.remove('visible');
                        this.#decisionActions.style.display = 'flex';
                        this.#clearErrors();
                        this.#resetForm();
                        this.#subsList.render(this.#linkedSubscriptions);
                        break;

                    case 'form':
                        this.#decisionActions.style.display = 'none';
                        this.#linkForm.classList.add('visible');
                        this.#inputAccount.focus();
                        break;

                    case 'success':
                        this.#setSubmitSuccess();
                        setTimeout(() => this.#showPostSuccessPrompt(), CONFIG.successDelayMs);
                        break;
                }
            }

            async #handleSubmit(e) {
                e.preventDefault();
                this.#clearErrors();

                const accountNumber = this.#inputAccount.value.trim();
                const postcode      = this.#inputPostcode.value.trim();

                const { valid, errors } = this.#validator.validate(
                    accountNumber, postcode,
                    this.#inputAccount, this.#inputPostcode
                );

                if (!valid) {
                    errors.forEach(err => this.#markFieldError(err.field));
                    this.#showError(errors[0].message);
                    return;
                }

                this.#setLoading(true);

                try {
                    const { ok, status, payload } = await this.#api.link(accountNumber, postcode);

                    if (!ok) {
                        this.#setLoading(false);
                        const code = payload.error_code ?? null;

                        if (code === 'already_linked') {
                            this.#showError(payload.message ?? 'This subscription is already linked to another account.');
                        } else if (code === 'mismatch' || status === 422) {
                            this.#markFieldError(this.#inputAccount);
                            this.#markFieldError(this.#inputPostcode);
                            this.#showError('We couldn\'t find a subscription matching those details. Please check your mailing label and try again.');
                        } else {
                            this.#showError(payload.message ?? 'Something went wrong. Please try again.');
                        }
                        return;
                    }

                    // Record the linked subscription for the "already linked" list
                    this.#linkedSubscriptions.push({ label: accountNumber });
                    this.#analytics.subscriptionLinked();
                    this.#transition_to('success');

                } catch {
                    this.#setLoading(false);
                    this.#showError('A network error occurred. Please check your connection and try again.');
                }
            }

            /** After the success flash, prompt "Link another?" */
            #showPostSuccessPrompt() {
                this.#resetSubmitButton();
                this.#subsList.render(this.#linkedSubscriptions);

                // Swap CTA copy to "Link another subscription"
                this.#btnYesLink.querySelector('.btn-label') !== null
                    ? (this.#btnYesLink.querySelector('.btn-label').textContent = 'Link another subscription')
                    : (this.#btnYesLink.textContent = 'Link another subscription');

                this.#transition_to('decision');
            }

            // ── Button state helpers ─────────────────────────────────────────

            #setLoading(state) {
                this.#btnSubmit.disabled = state;
                this.#btnSubmit.classList.toggle('loading', state);
            }

            #setSubmitSuccess() {
                this.#setLoading(false);
                this.#btnSubmit.classList.add('btn-success');
                this.#btnSubmit.querySelector('.btn-label').textContent = '✓ Subscription linked!';
            }

            #resetSubmitButton() {
                this.#btnSubmit.classList.remove('btn-success', 'loading');
                this.#btnSubmit.disabled = false;
                this.#btnSubmit.querySelector('.btn-label').textContent = 'Verify & Link Subscription';
            }

            #resetForm() {
                this.#form.reset();
            }

            // ── Error helpers ────────────────────────────────────────────────

            #markFieldError(input) {
                input.classList.add('is-error');
                input.addEventListener('input', function clearOnChange() {
                    input.classList.remove('is-error');
                    input.removeEventListener('input', clearOnChange);
                });
            }

            #showError(message) {
                this.#alertErrorText.textContent = message;
                this.#alertError.classList.add('visible');
                this.#alertError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            #clearErrors() {
                this.#alertError.classList.remove('visible');
                this.#alertErrorText.textContent = '';
                this.#inputAccount.classList.remove('is-error');
                this.#inputPostcode.classList.remove('is-error');
            }
        }

        /* ─────────────────────────────────────────────────────────────────
           Bootstrap — wire everything together
        ───────────────────────────────────────────────────────────────── */
        const api        = new LinkingApi();
        const analytics  = new Analytics();
        const validator  = new FormValidator();
        const transition = new StepTransition();
        const subsList   = new LinkedSubscriptionsList();

        new AccountHelpModal();
        new LinkingOrchestrator(api, analytics, validator, transition, subsList);
        new PreferencesController(api);

    }());
</script>
</body>
</html>