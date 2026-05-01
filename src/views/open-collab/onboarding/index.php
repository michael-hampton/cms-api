<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Started — OpenCollab</title>
    @css('open-collab.css')
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body style="background:var(--cream);min-height:100vh;display:flex;flex-direction:column;">

<!-- Minimal header -->
<header style="padding:20px 32px;border-bottom:1px solid var(--border);background:#fff;display:flex;align-items:center;gap:12px;">
    <div style="width:32px;height:32px;background:var(--navy);border-radius:6px;display:grid;place-items:center;">
        <span style="font-family:var(--font-display);font-weight:700;font-size:16px;color:var(--amber);">O</span>
    </div>
    <span style="font-family:var(--font-display);font-size:1rem;font-weight:600;color:var(--navy);">OpenCollab</span>
    <span style="margin-left:auto;font-size:.8rem;color:var(--slate);">Setting up your account</span>
</header>

<main style="flex:1;display:flex;align-items:flex-start;justify-content:center;padding:48px 20px;">
    <div style="width:100%;max-width:580px;">

        <!-- Step Indicator -->
        <div class="oc-steps" style="margin-bottom:36px;">
            <?php foreach ($vm->steps() as $step): ?>
                <div class="oc-step oc-step--<?= $step->cssState() ?>">
                    <div class="oc-step__dot">
                        <?php if ($step->isDone): ?>
                            <svg viewBox="0 0 20 20" fill="currentColor" width="13">
                                <path fill-rule="evenodd"
                                      d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                        <?php else: ?>
                            <?= $step->oneBasedIndex ?>
                        <?php endif; ?>
                    </div>
                    <span class="oc-step__label"><?= $step->label ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Step Card -->
        <div class="oc-card" style="box-shadow:var(--shadow-lg);">
            <div class="oc-card__header" style="padding:22px 28px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:38px;height:38px;background:var(--navy);border-radius:8px;display:grid;place-items:center;flex-shrink:0;">
                        <svg viewBox="0 0 20 20" fill="var(--amber)" width="18"><?= $vm->currentStepIcon() ?></svg>
                    </div>
                    <div>
                        <div class="oc-card__title" style="margin-bottom:2px;"><?= $vm->currentStepTitle() ?></div>
                        <div style="font-size:.78rem;color:var(--slate);">
                            Step <?= $vm->currentStepNumber() ?> of <?= $vm->totalSteps() ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="oc-card__body" style="padding:28px;">

                <div id="form-errors" class="oc-form-errors" style="display:none;" role="alert"></div>
                <div id="form-success" class="oc-alert oc-alert--success" style="display:none;" role="status"></div>

                <?php if ($vm->currentStepReason()): ?>
                    <div class="oc-alert oc-alert--info" style="margin-bottom:16px;">
                        <?= htmlspecialchars($vm->currentStepReason()) ?>
                    </div>
                <?php endif; ?>

                <!-- ── PROFILE STEP ─────────────────────────── -->
                <?php if ($vm->currentStepName() === 'profile'): ?>
                    <form id="onboarding-form" novalidate>
                        <p style="font-size:.875rem;color:var(--slate);margin-bottom:22px;">
                            Tell readers a little about you. This appears on your articles and public profile.
                        </p>
                        <div class="oc-form-group">
                            <label class="oc-label" for="bio">Your bio</label>
                            <textarea class="oc-textarea" id="bio" name="bio" rows="5"
                                      placeholder="I'm a writer specialising in…" required
                                      style="min-height:140px;"></textarea>
                            <div class="oc-help">Between 20 and 1000 characters.</div>
                            <div class="oc-error-msg" id="bio-error"></div>
                        </div>
                        <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">
                            Save &amp; continue
                            <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                                <path fill-rule="evenodd"
                                      d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </form>

                    <!-- ── PAYMENT STEP ─────────────────────────── -->
                <?php elseif ($vm->currentStepName() === 'payment'): ?>
                    <form id="onboarding-form" novalidate>
                        <div class="oc-alert oc-alert--info" style="margin-bottom:20px;">
                            <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                                <path fill-rule="evenodd"
                                      d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                      clip-rule="evenodd"/>
                            </svg>
                            Card details are tokenised by Stripe and never stored on our servers.
                        </div>

                        <div class="oc-form-group">
                            <label class="oc-label">Payout method</label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:4px;">
                                <label style="cursor:pointer;">
                                    <input type="radio" name="payment_method_type" value="stripe" checked
                                           style="display:none;" id="pm-stripe">
                                    <div class="payment-method-card" data-for="pm-stripe"
                                         style="border:2px solid var(--navy);background:var(--navy);border-radius:8px;padding:12px;text-align:center;">
                                        <svg viewBox="0 0 20 20" fill="var(--amber)" width="22"
                                             style="margin-bottom:6px;">
                                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                                            <path fill-rule="evenodd"
                                                  d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                        <span style="display:block;font-size:.78rem;font-weight:600;color:#fff;">Card (Stripe)</span>
                                    </div>
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="payment_method_type" value="bank_transfer"
                                           style="display:none;" id="pm-bank">
                                    <div class="payment-method-card" data-for="pm-bank"
                                         style="border:2px solid var(--border);border-radius:8px;padding:12px;text-align:center;">
                                        <svg viewBox="0 0 20 20" fill="var(--slate)" width="22"
                                             style="margin-bottom:6px;">
                                            <path fill-rule="evenodd"
                                                  d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-5L9 4H4zm7 5a1 1 0 10-2 0v1H8a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V9z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                        <span style="display:block;font-size:.78rem;font-weight:600;color:var(--slate);">Bank transfer</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="stripe-section">
                            <div class="oc-form-group">
                                <label class="oc-label">Card details</label>
                                <div id="stripe-card-element"
                                     style="border:1.5px solid var(--border);border-radius:var(--radius);padding:12px 14px;background:#fff;"></div>
                                <div id="stripe-card-errors" class="oc-error-msg" style="margin-top:6px;"></div>
                            </div>
                        </div>

                        <div id="bank-section" style="display:none;">
                            <div class="oc-form-group">
                                <label class="oc-label" for="bank-account">Account email / reference</label>
                                <input class="oc-input" type="text" id="bank-account" name="bank_account"
                                       placeholder="e.g. your bank email or sort code reference">
                                <div class="oc-help">We'll use this to process manual payouts.</div>
                            </div>
                        </div>

                        <div class="oc-form-group">
                            <label class="oc-label" for="tax-country">Country of tax residence</label>
                            <select class="oc-select" id="tax-country" name="tax_country">
                                <option value="">Select country…</option>
                                <option value="GB">United Kingdom</option>
                                <option value="US">United States</option>
                                <option value="CA">Canada</option>
                                <option value="AU">Australia</option>
                                <option value="DE">Germany</option>
                                <option value="FR">France</option>
                                <option value="IE">Ireland</option>
                                <option value="NL">Netherlands</option>
                                <option value="OTHER">Other</option>
                            </select>
                            <div class="oc-help">Used for tax reporting purposes only.</div>
                        </div>

                        <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">
                            Save payment details
                            <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                                <path fill-rule="evenodd"
                                      d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </form>

                    <!-- ── CONTRACT STEP ─────────────────────────── -->
                <?php elseif ($vm->currentStepName() === 'contract'): ?>
                    <?php $meta = $vm->currentStepMeta(); ?>
                    <form id="onboarding-form" novalidate>
                        <?php if ($contract): ?>
                            <input type="hidden" name="contract_id" value="<?= (int)$contract->id ?>">
                            <p style="font-size:.875rem;color:var(--slate);margin-bottom:16px;">
                                Please read the full contributor agreement before signing.
                            </p>
                            <div style="height:260px;overflow-y:scroll;border:1.5px solid var(--border);border-radius:var(--radius);padding:18px 20px;font-size:.875rem;line-height:1.75;color:var(--navy);background:#fff;margin-bottom:20px;"
                                 id="contract-scroll">
                                <?= $contract->content ?>
                            </div>
                            <div class="oc-toggle-row" style="margin-bottom:20px;">
                                <label class="oc-toggle">
                                    <input type="checkbox" id="agreed" name="agreed" required>
                                    <span class="oc-toggle__slider"></span>
                                </label>
                                <div class="oc-toggle-label">
                                    <strong>I have read and agree to this contributor agreement</strong>
                                    <span>Version <?= (int)$contract->version ?>, signed electronically</span>
                                </div>
                            </div>
                            <div class="oc-error-msg" id="agree-error"></div>
                            <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">
                                Sign &amp; continue
                            </button>
                        <?php else: ?>
                            <p style="color:var(--slate);">No contract is currently required for this site. Continue to
                                the next step.</p>
                            <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">Continue
                            </button>
                        <?php endif; ?>
                    </form>

                    <!-- ── GUIDELINES STEP ─────────────────────────── -->
                <?php elseif ($vm->currentStepName() === 'guidelines'): ?>
                    <?php $meta = $vm->currentStepMeta(); ?>
                    <form id="onboarding-form" novalidate>
                        <input type="hidden" name="version" value="<?= (int)($siteGuidelinesVersion ?? 1) ?>">
                        <p style="font-size:.875rem;color:var(--slate);margin-bottom:20px;">
                            Before you start publishing, please review our editorial standards and brand guidelines.
                        </p>
                        <div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                            <div>
                                <div style="font-weight:600;font-size:.9rem;color:var(--navy);margin-bottom:2px;">Brand
                                    & Editorial Guidelines
                                </div>
                                <div style="font-size:.78rem;color:var(--slate);">
                                    Version <?= (int)($siteGuidelinesVersion ?? 1) ?></div>
                            </div>
                            <a href="/guidelines" target="_blank" class="oc-btn oc-btn--ghost oc-btn--sm">
                                <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                                    <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                                    <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                                </svg>
                                Read now
                            </a>
                        </div>
                        <div class="oc-toggle-row" style="margin-bottom:20px;">
                            <label class="oc-toggle">
                                <input type="checkbox" id="guidelines-agreed" name="agreed" required>
                                <span class="oc-toggle__slider"></span>
                            </label>
                            <div class="oc-toggle-label">
                                <strong>I have read and will follow the brand guidelines</strong>
                                <span>Content that violates these guidelines may be removed.</span>
                            </div>
                        </div>
                        <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">
                            Complete onboarding
                            <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                                <path fill-rule="evenodd"
                                      d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </form>

                <?php endif; ?>

            </div>
        </div>

    </div>
</main>

<script>
    const STRIPE_KEY = '<?= htmlspecialchars($stripePublicKey ?? '') ?>';
    const CURRENT_STEP = '<?= $vm->currentStepName() ?>';
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    class OnboardingStepBase {
        _site;
        _token;

        constructor(site, token) {
            this._site = site;
            this._token = token;
            this._bindForm();
        }

        _bindForm() {
            document.getElementById('onboarding-form')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this._submit();
            });
        }

        // Subclasses implement this
        async _submit() {
        }

        _showError(msg) {
            const el = document.getElementById('form-errors');
            el.textContent = msg;
            el.style.display = 'block';
        }

        _clearError() {
            document.getElementById('form-errors').style.display = 'none';
        }

        async _post(endpoint, payload) {
            return fetch(`/api/${this._site}/open-collab/onboarding/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    Authorization: `Bearer ${this._token}`,
                },
                body: JSON.stringify(payload),
            });
        }

        _setButtonLoading(text = 'Saving…') {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.innerHTML = `<div class="oc-spinner"></div> ${text}`;
            return btn;
        }

        _resetButton(btn, label) {
            btn.disabled = false;
            btn.innerHTML = label;
        }

        async _handleResponse(res, btn, originalLabel) {
            const data = await res.json();
            if (res.ok) {
                window.location.reload();
            } else {
                let msg = data.message || 'An error occurred.';
                if (data.errors) msg = Object.values(data.errors).flat().join(' ');
                this._showError(msg);
                this._resetButton(btn, originalLabel);
            }
        }
    }

    class ProfileStep extends OnboardingStepBase {
        async _submit() {
            this._clearError();
            const bio = document.getElementById('bio')?.value.trim();

            if (!bio || bio.length < 20) {
                document.getElementById('bio-error').textContent = 'Bio must be at least 20 characters.';
                document.getElementById('bio-error').classList.add('visible');
                return;
            }

            const btn = this._setButtonLoading('Saving…');
            const res = await this._post('profile', {bio});
            await this._handleResponse(res, btn, 'Save & continue');
        }
    }

    class PaymentStep extends OnboardingStepBase {
        #stripe = null;
        #cardElement = null;

        constructor(site, token, stripeKey) {
            super(site, token);
            if (stripeKey) this.#initStripe(stripeKey);
            this.#bindMethodToggle();
        }

        #initStripe(key) {
            this.#stripe = Stripe(key);
            const elems = this.#stripe.elements();
            this.#cardElement = elems.create('card', {
                hidePostalCode: true,
                style: {
                    base: {
                        fontFamily: "'DM Sans', sans-serif",
                        fontSize: '15px',
                        color: '#0f1929',
                        '::placeholder': {color: '#94a3b8'},
                    },
                },
            });
            this.#cardElement.mount('#stripe-card-element');
            this.#cardElement.on('change', ({error}) => {
                document.getElementById('stripe-card-errors').textContent = error ? error.message : '';
            });
        }

        #bindMethodToggle() {
            document.querySelectorAll('[name="payment_method_type"]').forEach(radio => {
                radio.addEventListener('change', () => this.#onMethodChange());
            });
        }

        #onMethodChange() {
            const isStripe = document.querySelector('[name="payment_method_type"]:checked')?.value === 'stripe';
            document.getElementById('stripe-section').style.display = isStripe ? 'block' : 'none';
            document.getElementById('bank-section').style.display = isStripe ? 'none' : 'block';

            document.querySelectorAll('.payment-method-card').forEach(card => {
                const checked = document.getElementById(card.dataset.for)?.checked;
                card.style.borderColor = checked ? 'var(--navy)' : 'var(--border)';
                card.style.background = checked ? 'var(--navy)' : '';
                card.querySelectorAll('svg').forEach(s => s.setAttribute('fill', checked ? 'var(--amber)' : 'var(--slate)'));
                card.querySelectorAll('span').forEach(s => {
                    s.style.color = checked ? '#fff' : 'var(--slate)';
                });
            });
        }

        async _submit() {
            this._clearError();
            const btn = this._setButtonLoading('Saving…');
            const pmType = document.querySelector('[name="payment_method_type"]:checked')?.value;

            if (pmType === 'stripe' && this.#stripe && this.#cardElement) {
                const {token: stripeToken, error} = await this.#stripe.createToken(this.#cardElement);
                if (error) {
                    document.getElementById('stripe-card-errors').textContent = error.message;
                    this._resetButton(btn, 'Save payment details');
                    return;
                }
                const res = await this._post('payment', {
                    payment_method_type: 'stripe',
                    stripe_token: stripeToken.id,
                    tax_country: document.getElementById('tax-country')?.value || '',
                });
                await this._handleResponse(res, btn, 'Save payment details');
            } else {
                const res = await this._post('payment', {
                    payment_method_type: 'bank_transfer',
                    stripe_token: document.getElementById('bank-account')?.value || 'bank',
                    tax_country: document.getElementById('tax-country')?.value || '',
                });
                await this._handleResponse(res, btn, 'Save payment details');
            }
        }
    }

    class ContractStep extends OnboardingStepBase {
        async _submit() {
            this._clearError();
            const agreed = document.getElementById('agreed')?.checked;
            const cid = document.querySelector('[name="contract_id"]')?.value;

            if (!agreed) {
                document.getElementById('agree-error').textContent = 'You must agree to continue.';
                document.getElementById('agree-error').classList.add('visible');
                return;
            }

            const btn = this._setButtonLoading('Signing…');
            const res = await this._post('contract', {
                contract_id: parseInt(cid),
                agreed: true,
            });
            await this._handleResponse(res, btn, 'Sign & continue');
        }
    }

    class GuidelinesStep extends OnboardingStepBase {
        async _submit() {
            this._clearError();
            const agreed = document.getElementById('guidelines-agreed')?.checked;
            const version = document.querySelector('[name="version"]')?.value;

            if (!agreed) {
                this._showError('Please acknowledge the guidelines.');
                return;
            }

            const btn = this._setButtonLoading('Completing…');
            const res = await this._post('guidelines', {
                version: parseInt(version),
                agreed: true,
            });
            await this._handleResponse(res, btn, 'Complete onboarding');
        }
    }

    class OnboardingManager {
        #step;

        constructor({site, token, stripeKey, currentStep}) {
            this.#step = this.#createStep(currentStep, site, token, stripeKey);
        }

        #createStep(name, site, token, stripeKey) {
            switch (name) {
                case 'profile':
                    return new ProfileStep(site, token);
                case 'payment':
                    return new PaymentStep(site, token, stripeKey);
                case 'contract':
                    return new ContractStep(site, token);
                case 'guidelines':
                    return new GuidelinesStep(site, token);
                default:
                    return null;
            }
        }
    }

    const onboardingManager = new OnboardingManager({
        site: SITE,
        token: localStorage.getItem('oc_token') || '',
        stripeKey: STRIPE_KEY,
        currentStep: CURRENT_STEP,
    });
</script>
</body>
</html>