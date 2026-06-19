<?php
/**
 * View: account/billing.php
 *
 * Variables from ShopAccountController::billing():
 * $member            – authenticated member
 * $active_tab        – 'billing'
 * $stripe_public_key – Stripe publishable key (e.g., pk_test_...)
 */
?>
<script src="https://js.stripe.com/v3/"></script>

<style>
    /* ── Billing layout ──────────────────────────────────────────── */
    .billing-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* ── Payment method cards ────────────────────────────────────── */
    .pm-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .pm-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 15px 18px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--white);
        transition: var(--transition);
    }

    .pm-card.is-default {
        border-color: var(--ink);
        box-shadow: var(--shadow-xs);
    }

    .pm-card:hover {
        box-shadow: var(--shadow-sm);
    }

    .pm-card__network {
        width: 50px;
        height: 32px;
        border-radius: 5px;
        background: var(--paper-dark);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        flex-shrink: 0;
    }

    .pm-card__info {
        flex: 1;
        min-width: 0;
    }

    .pm-card__number {
        font-size: 14px;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: 2px;
    }

    .pm-card__expiry {
        font-size: 12px;
        color: var(--ink-muted);
    }

    .pm-card__default-badge {
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        background: var(--gold-light);
        color: var(--gold);
        padding: 3px 9px;
        border-radius: 100px;
        flex-shrink: 0;
        border: 1px solid rgba(184, 134, 11, .2);
    }

    .pm-card__actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    /* Add card button */
    .add-card-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 15px 18px;
        border: 1.5px dashed var(--border);
        border-radius: var(--radius-sm);
        background: none;
        cursor: pointer;
        font-family: var(--font-body);
        font-size: 14px;
        color: var(--ink-muted);
        transition: var(--transition);
        width: 100%;
    }

    .add-card-btn:hover {
        border-color: var(--ink);
        color: var(--ink);
        background: var(--paper);
    }

    /* ── Address grid layout ─────────────────────────────────────── */
    .address-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
    }

    .address-card {
        padding: 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--paper-light);
        position: relative;
    }

    .address-card__badge {
        display: inline-block;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        padding: 2px 6px;
        border-radius: 4px;
        margin-bottom: 8px;
        background: var(--border);
        color: var(--ink-soft);
    }

    .address-card__badge.is-billing {
        background: rgba(0, 102, 204, 0.1);
        color: #0066cc;
    }

    .address-card__badge.is-shipping {
        background: rgba(0, 153, 76, 0.1);
        color: #00994c;
    }

    /* ── Add card modal ──────────────────────────────────────────── */
    .stripe-field-wrapper {
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 14px;
        background: var(--white);
        transition: var(--transition);
        margin-bottom: 14px;
        min-height: 45px;
    }

    .stripe-field-wrapper:focus-within {
        border-color: var(--ink);
    }

    .stripe-field-label {
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .09em;
        color: var(--ink-muted);
        margin-bottom: 10px;
    }

    .security-note {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: var(--ink-muted);
        margin-top: 8px;
    }

    .security-note svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
    }

    /* Empty payment state */
    .no-payment-state {
        text-align: center;
        padding: 44px 24px;
    }

    .no-payment-state__icon {
        font-size: 40px;
        margin-bottom: 12px;
        opacity: .4;
    }

    .no-payment-state__title {
        font-family: var(--font-display);
        font-size: 18px;
        margin-bottom: 6px;
    }

    .no-payment-state__sub {
        font-size: 14px;
        color: var(--ink-muted);
        margin-bottom: 20px;
    }

    /* Skeleton loader */
    .pm-skeleton {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .pm-skeleton__row {
        height: 64px;
        border-radius: var(--radius-sm);
        background: linear-gradient(90deg, var(--paper-dark) 25%, var(--paper) 50%, var(--paper-dark) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.4s infinite;
    }

    @keyframes shimmer {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>

<?php $page_title = 'Billing'; ?>

@include('subscriptions/account/_layout')

<main class="page-content">

    <div class="page-heading">
        <div class="page-heading__eyebrow">Account</div>
        <h1 class="page-heading__title">Billing</h1>
        <p class="page-heading__sub">Manage your payment methods and billing details</p>
    </div>

    <div class="billing-grid">

        <div class="card">
            <div class="card__header">
                <span class="card__title">Payment Methods</span>
                <button class="btn btn--ghost btn--sm" id="open-add-card-btn">+ Add card</button>
            </div>
            <div class="card__body" id="payment-methods-body">
                <div class="pm-skeleton">
                    <div class="pm-skeleton__row"></div>
                    <div class="pm-skeleton__row"></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__header">
                <span class="card__title">Saved Addresses</span>
            </div>
            <div class="card__body" id="billing-address-body">
                <div class="pm-skeleton"><div class="pm-skeleton__row" style="height:90px"></div></div>
            </div>
        </div>

        <div style="display:flex; align-items:flex-start; gap:12px; padding:15px 18px; background:var(--white); border:1px solid var(--border); border-radius:var(--radius-sm); font-size:13px; color:var(--ink-muted); line-height:1.65; box-shadow:var(--shadow-xs);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 style="width:18px;height:18px;flex-shrink:0;margin-top:1px;color:var(--green);">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <span>
                Your payment information is encrypted and stored securely via Stripe.
                PressStack never stores your card number directly.
            </span>
        </div>
    </div>

    <div class="modal-overlay" id="add-card-modal" role="dialog" aria-modal="true" aria-labelledby="add-card-title">
        <div class="modal">
            <div class="modal__header">
                <h2 class="modal__title" id="add-card-title">Add Payment Method</h2>
                <button class="modal__close" id="close-add-card-btn" aria-label="Close">×</button>
            </div>
            <div class="modal__body">
                <div class="stripe-field-label">Card details</div>
                <div class="stripe-field-wrapper" id="stripe-card-element"></div>
                <div id="card-errors" style="color:var(--red); font-size:13px; margin-bottom:12px; display:none;" role="alert"></div>

                <label style="display:flex; align-items:center; gap:10px; font-size:14px; cursor:pointer; margin-bottom:16px;">
                    <input type="checkbox" id="set-as-default" checked
                           style="accent-color:var(--ink); width:16px; height:16px;">
                    Set as default payment method
                </label>

                <div class="security-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Secured by Stripe. Your card details are encrypted.
                </div>
            </div>
            <div class="modal__footer">
                <button class="btn btn--ghost" id="cancel-add-card-btn">Cancel</button>
                <button class="btn btn--primary" id="submit-add-card-btn">Add Card</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="remove-card-modal" role="dialog" aria-modal="true" aria-labelledby="remove-card-title">
        <div class="modal">
            <div class="modal__header">
                <h2 class="modal__title" id="remove-card-title">Remove Card</h2>
                <button class="modal__close" id="close-remove-card-btn" aria-label="Close">×</button>
            </div>
            <div class="modal__body">
                <p style="font-size:14px; color:var(--ink-soft); line-height:1.65;">
                    Are you sure you want to remove this card? This action cannot be undone.
                    Any active subscriptions using this card will need to be updated.
                </p>
            </div>
            <div class="modal__footer">
                <button class="btn btn--ghost" id="cancel-remove-card-btn">Cancel</button>
                <button class="btn btn--danger" id="confirm-remove-card-btn">Remove Card</button>
            </div>
        </div>
    </div>

</main>
</div></body>
</html>

<script>
    class BillingPage {
        // ── Constants ────────────────────────────────────────────────────
        static NETWORK_ICONS = {
            visa: '💳',
            mastercard: '💳',
            amex: '💳',
            discover: '💳',
        };

        static STRIPE_PUBLIC_KEY = '<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key') ?>';

        static ENDPOINTS = {
            paymentMethods: '/api/<?= \App\Framework\Support\SiteContext::slug() ?>/member/account/billing/payment-methods',
            addresses:      '/api/<?= \App\Framework\Support\SiteContext::slug() ?>/member/addresses',
            setupIntent:    '/api/<?= \App\Framework\Support\SiteContext::slug() ?>/member/account/billing/setup-intent',
            addCard:        '/api/<?= \App\Framework\Support\SiteContext::slug() ?>/member/account/billing/finalise-setup-intent',
            removeCard:     '/api/<?= \App\Framework\Support\SiteContext::slug() ?>/member/account/billing/remove-card',
            setDefault:     '/api/<?= \App\Framework\Support\SiteContext::slug() ?>/member/account/billing/set-default',
        };

        // ── Stripe State Instances ───────────────────────────────────────
        #stripe = null;
        #elements = null;
        #cardElement = null;

        // ── Core State ───────────────────────────────────────────────────
        #state = {
            paymentMethods:   [],
            addresses:        [],
            loadingPMs:       true,
            loadingAddress:   true,
            pendingRemoveId:  null,
            submittingAdd:    false,
            submittingRemove: false,
            submittingDefault: null,
        };

        // ── DOM refs ─────────────────────────────────────────────────────
        #els = {};

        constructor() {
            this.#bindElements();
            this.#attachListeners();
            this.#load();
        }

        // ── Bootstrap ────────────────────────────────────────────────────

        #bindElements() {
            const ids = [
                'payment-methods-body',
                'billing-address-body',
                'add-card-modal',
                'remove-card-modal',
                'open-add-card-btn',
                'close-add-card-btn',
                'cancel-add-card-btn',
                'submit-add-card-btn',
                'close-remove-card-btn',
                'cancel-remove-card-btn',
                'confirm-remove-card-btn',
                'card-errors',
                'set-as-default',
            ];
            ids.forEach(id => {
                this.#els[id] = document.getElementById(id);
            });
        }

        #attachListeners() {
            // Add card modal triggers
            this.#els['open-add-card-btn'].addEventListener('click', () => this.#openAddCard());
            this.#els['close-add-card-btn'].addEventListener('click', () => this.#closeAddCard());
            this.#els['cancel-add-card-btn'].addEventListener('click', () => this.#closeAddCard());
            this.#els['submit-add-card-btn'].addEventListener('click', () => this.#submitAddCard());

            // Remove card modal triggers
            this.#els['close-remove-card-btn'].addEventListener('click', () => this.#closeRemoveCard());
            this.#els['cancel-remove-card-btn'].addEventListener('click', () => this.#closeRemoveCard());
            this.#els['confirm-remove-card-btn'].addEventListener('click', () => this.#confirmRemoveCard());

            // Backdrop dismissal
            this.#els['add-card-modal'].addEventListener('click', e => {
                if (e.target === this.#els['add-card-modal']) this.#closeAddCard();
            });
            this.#els['remove-card-modal'].addEventListener('click', e => {
                if (e.target === this.#els['remove-card-modal']) this.#closeRemoveCard();
            });

            // Escape key handling
            document.addEventListener('keydown', e => {
                if (e.key !== 'Escape') return;
                this.#closeAddCard();
                this.#closeRemoveCard();
            });
        }

        // ── Stripe.js Management ─────────────────────────────────────────

        #initStripe() {
            if (this.#stripe) return;

            if (typeof Stripe === 'undefined') {
                console.error('Stripe.js failed to load.');
                return;
            }

            this.#stripe = Stripe(BillingPage.STRIPE_PUBLIC_KEY);
            this.#elements = this.#stripe.elements();

            // Match current UI theme rules safely inside the Stripe iframe container
            const style = {
                base: {
                    color: '#111111',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    fontSmoothing: 'antialiased',
                    fontSize: '14px',
                    '::placeholder': {
                        color: '#999999',
                    },
                },
                invalid: {
                    color: '#e53e3e',
                    iconColor: '#e53e3e',
                },
            };

            this.#cardElement = this.#elements.create('card', { style, hidePostalCode: true });

            // Inline live validation updates
            this.#cardElement.on('change', event => {
                const errorEl = this.#els['card-errors'];
                if (event.error) {
                    errorEl.textContent = event.error.message;
                    errorEl.style.display = 'block';
                } else {
                    this.#clearCardErrors();
                }
            });
        }

        // ── Data Actions ─────────────────────────────────────────────────

        async #load() {
            await Promise.all([
                this.#loadPaymentMethods(),
                this.#loadAddresses()
            ]);
        }

        async #loadPaymentMethods() {
            this.#setState({ loadingPMs: true });
            try {
                const res  = await this.#apiFetch(BillingPage.ENDPOINTS.paymentMethods);
                const data = await res.json();

                this.#setState({
                    loadingPMs:     false,
                    paymentMethods: data.data?.payment_methods ?? data.payment_methods ?? [],
                });
            } catch {
                this.#setState({ loadingPMs: false, paymentMethods: [] });
            }
        }

        async #loadAddresses() {
            this.#setState({ loadingAddress: true });
            try {
                const res  = await this.#apiFetch(BillingPage.ENDPOINTS.addresses);
                const data = await res.json();

                this.#setState({
                    loadingAddress: false,
                    addresses:      data.items ?? [],
                });
            } catch {
                this.#setState({ loadingAddress: false, addresses: [] });
            }
        }

        async #submitAddCard() {
            if (this.#state.submittingAdd) return;

            this.#setState({ submittingAdd: true });
            this.#renderAddCardBtn();
            this.#clearCardErrors();

            try {
                // Step 1: Request SetupIntent token from local app container backend
                const intentRes = await this.#apiFetch(BillingPage.ENDPOINTS.setupIntent, { method: 'POST' });
                const intentData = await intentRes.json();

                if (!intentData.success || !intentData.client_secret) {
                    throw new Error(intentData.message || 'Unable to initialize card transaction setup.');
                }

                // Step 2: Confirm Intent directly via Stripe API Core engine
                const result = await this.#stripe.confirmCardSetup(intentData.client_secret, {
                    payment_method: {
                        card: this.#cardElement,
                        billing_details: {
                            name: '<?= addslashes($member->name ?? "") ?>',
                            email: '<?= addslashes($member->email ?? "") ?>'
                        }
                    }
                });

                if (result.error) {
                    throw new Error(result.error.message);
                }

                // Step 3: Pass resulting confirmed payment method string token to app database router
                const attachRes = await this.#apiFetch(BillingPage.ENDPOINTS.addCard, {
                    method: 'POST',
                    body:   JSON.stringify({
                        setup_intent_id: result.setupIntent.id,
                        set_default:       this.#els['set-as-default'].checked
                    }),
                });
                const attachData = await attachRes.json();

                if (attachData.success) {
                    this.#closeAddCard();
                    await this.#loadPaymentMethods();
                } else {
                    throw new Error(attachData.message || 'Failed to link new card to profile account.');
                }
            } catch (err) {
                const errorEl = this.#els['card-errors'];
                errorEl.textContent = err.message || 'An unexpected connection error occurred.';
                errorEl.style.display = 'block';
            } finally {
                this.#setState({ submittingAdd: false });
                this.#renderAddCardBtn();
            }
        }

        async #confirmRemoveCard() {
            if (this.#state.submittingRemove) return;

            this.#setState({ submittingRemove: true });
            this.#renderRemoveCardBtn();

            try {
                const res  = await this.#apiFetch(BillingPage.ENDPOINTS.removeCard, {
                    method: 'POST',
                    body:   JSON.stringify({ payment_method_id: this.#state.pendingRemoveId }),
                });
                const data = await res.json();

                if (data.success) {
                    this.#closeRemoveCard();
                    await this.#loadPaymentMethods();
                } else {
                    alert(data.message ?? 'Failed to remove card.');
                }
            } catch {
                alert('Network error. Please try again.');
            } finally {
                this.#setState({ submittingRemove: false, pendingRemoveId: null });
                this.#renderRemoveCardBtn();
            }
        }

        async #setDefault(paymentMethodId) {
            if (this.#state.submittingDefault) return;

            this.#setState({ submittingDefault: paymentMethodId });
            this.#renderPaymentMethods();

            try {
                const res  = await this.#apiFetch(BillingPage.ENDPOINTS.setDefault, {
                    method: 'POST',
                    body:   JSON.stringify({ payment_method_id: paymentMethodId }),
                });
                const data = await res.json();

                if (data.success) {
                    await this.#loadPaymentMethods();
                } else {
                    alert(data.message ?? 'Failed to set default.');
                }
            } catch {
                alert('Network error. Please try again.');
            } finally {
                this.#setState({ submittingDefault: null });
            }
        }

        // ── State management ─────────────────────────────────────────────

        #setState(patch) {
            Object.assign(this.#state, patch);
            this.#render();
        }

        #render() {
            this.#renderPaymentMethods();
            this.#renderAddresses();
        }

        // ── Renderers ────────────────────────────────────────────────────

        #renderPaymentMethods() {
            const body = this.#els['payment-methods-body'];

            if (this.#state.loadingPMs) {
                body.innerHTML = `
                <div class="pm-skeleton">
                    <div class="pm-skeleton__row"></div>
                    <div class="pm-skeleton__row"></div>
                </div>`;
                return;
            }

            const methods = this.#state.paymentMethods;

            if (!methods.length) {
                body.innerHTML = this.#emptyPaymentHtml();
                body.querySelector('.js-open-add-card')?.addEventListener('click', () => this.#openAddCard());
                return;
            }

            const listHtml = methods.map(pm => this.#paymentCardHtml(pm)).join('');
            body.innerHTML = `<div class="pm-list">${listHtml}<button class="add-card-btn js-open-add-card">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add new card
        </button></div>`;

            body.querySelectorAll('[data-action="set-default"]').forEach(btn => {
                btn.addEventListener('click', () => this.#setDefault(btn.dataset.id));
            });
            body.querySelectorAll('[data-action="remove"]').forEach(btn => {
                btn.addEventListener('click', () => this.#openRemoveCard(btn.dataset.id));
            });
            body.querySelector('.js-open-add-card')?.addEventListener('click', () => this.#openAddCard());
        }

        #renderAddresses() {
            const body = this.#els['billing-address-body'];

            if (this.#state.loadingAddress) {
                body.innerHTML = `<div class="pm-skeleton"><div class="pm-skeleton__row" style="height:90px"></div></div>`;
                return;
            }

            const addresses = this.#state.addresses;
            if (!addresses.length) {
                body.innerHTML = `<p style="font-size:14px; color:var(--ink-muted);">No addresses on file.</p>`;
                return;
            }

            // Clean looping rendering tracking exact item array parameters: address_line_1, address_line_2, city, state, postcode, country
            const cardsHtml = addresses.map(addr => {
                const typeClass = addr.type === 'billing' ? 'is-billing' : (addr.type === 'shipping' ? 'is-shipping' : '');
                const typeLabel = addr.type ? addr.type.charAt(0).toUpperCase() + addr.type.slice(1) : 'Address';

                return `
                <div class="address-card">
                    <span class="address-card__badge ${typeClass}">${typeLabel}</span>
                    <div style="font-size:14px; color:var(--ink-soft); line-height:1.7;">
                        <strong style="color:var(--ink); display:block; margin-bottom: 4px;">${this.#escape(addr.label ?? '')}</strong>
                        ${this.#escape(addr.address_line_1 ?? '')}<br>
                        ${addr.address_line_2 ? this.#escape(addr.address_line_2) + '<br>' : ''}
                        ${this.#escape(addr.city ?? '')}${addr.state ? ', ' + this.#escape(addr.state) : ''} ${this.#escape(addr.postcode ?? '')}<br>
                        ${this.#escape(addr.country ?? '')}
                    </div>
                </div>`;
            }).join('');

            body.innerHTML = `<div class="address-grid">${cardsHtml}</div>`;
        }

        #renderRemoveCardBtn() {
            const btn = this.#els['confirm-remove-card-btn'];
            btn.disabled    = this.#state.submittingRemove;
            btn.textContent = this.#state.submittingRemove ? 'Removing…' : 'Remove Card';
        }

        #renderAddCardBtn() {
            const btn = this.#els['submit-add-card-btn'];
            btn.disabled    = this.#state.submittingAdd;
            btn.textContent = this.#state.submittingAdd ? 'Processing…' : 'Add Card';
        }

        // ── HTML builders ────────────────────────────────────────────────

        #paymentCardHtml(pm) {
            const icon      = BillingPage.NETWORK_ICONS[pm.brand?.toLowerCase()] ?? '💳';
            const isDefault = pm.is_default;
            const isBusy    = this.#state.submittingDefault === pm.id;
            const brand     = this.#escape(pm.brand ?? 'Card');
            const last4     = this.#escape(pm.last4 ?? '????');
            const expMonth  = this.#escape(String(pm.exp_month ?? '--'));
            const expYear   = this.#escape(String(pm.exp_year ?? '--'));
            const removeDisabled = pm.can_remove === false;

            const defaultBtn = !isDefault
                ? `<button class="btn btn--ghost btn--sm" data-action="set-default" data-id="${this.#escape(pm.id)}" ${isBusy ? 'disabled' : ''}>
                   ${isBusy ? 'Saving…' : 'Set default'}
               </button>`
                : '';

            const badge = isDefault
                ? `<span class="pm-card__default-badge">Default</span>`
                : '';

            return `
            <div class="pm-card ${isDefault ? 'is-default' : ''}">
                <div class="pm-card__network">${icon}</div>
                <div class="pm-card__info">
                    <div class="pm-card__number">${brand} ···· ${last4}</div>
                    <div class="pm-card__expiry">Expires ${expMonth}/${expYear}</div>
                </div>
                ${badge}
                <div class="pm-card__actions">
                    ${defaultBtn}
                    <button class="btn btn--danger btn--sm" data-action="remove" data-id="${this.#escape(pm.id)}" ${removeDisabled ? 'disabled title="Add another card before removing this one."' : ''}>Remove</button>
                </div>
            </div>`;
        }

        #emptyPaymentHtml() {
            return `
            <div class="no-payment-state">
                <div class="no-payment-state__icon">💳</div>
                <div class="no-payment-state__title">No payment methods saved</div>
                <div class="no-payment-state__sub">Add a payment method to speed up checkout and renewals.</div>
                <button class="btn btn--primary js-open-add-card">Add card</button>
            </div>`;
        }

        // ── Modal helpers ────────────────────────────────────────────────

        #openAddCard() {
            this.#els['add-card-modal'].classList.add('open');
            this.#initStripe();
            if (this.#cardElement) {
                this.#cardElement.mount('#stripe-card-element');
            }
            this.#els['close-add-card-btn'].focus();
        }

        #closeAddCard() {
            this.#els['add-card-modal'].classList.remove('open');
            if (this.#cardElement) {
                this.#cardElement.unmount();
            }
            this.#clearCardErrors();
        }

        #openRemoveCard(paymentMethodId) {
            this.#setState({ pendingRemoveId: paymentMethodId });
            this.#els['remove-card-modal'].classList.add('open');
            this.#els['close-remove-card-btn'].focus();
        }

        #closeRemoveCard() {
            this.#els['remove-card-modal'].classList.remove('open');
            this.#setState({ pendingRemoveId: null, submittingRemove: false });
            this.#renderRemoveCardBtn();
        }

        #clearCardErrors() {
            const el = this.#els['card-errors'];
            el.style.display = 'none';
            el.textContent   = '';
        }

        // ── Utilities ────────────────────────────────────────────────────

        async #apiFetch(url, options = {}) {
            return fetch(url, {
                ...options,
                headers: {
                    'Content-Type':      'application/json',
                    'X-Requested-With':  'XMLHttpRequest',
                    'Accept':            'application/json',
                    'X-CSRF-TOKEN':      document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    ...(options.headers ?? {}),
                },
            });
        }

        #escape(str) {
            return String(str)
                .replace(/&/g,  '&amp;')
                .replace(/</g,  '&lt;')
                .replace(/>/g,  '&gt;')
                .replace(/"/g,  '&quot;')
                .replace(/'/g,  '&#039;');
        }
    }

    document.addEventListener('DOMContentLoaded', () => new BillingPage());
</script>
