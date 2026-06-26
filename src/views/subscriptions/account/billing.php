<?php
/**
 * View: subscriptions/account/billing.php
 *
 * Shared PressStack account billing view for:
 * - Payment methods
 * - Manage Addresses
 */

$billingSection = $billing_section ?? 'payment_methods';
$pageTitleBySection = [
    'payment_methods' => 'Payment methods',
    'addresses' => 'Manage Addresses',
];
$pageSubBySection = [
    'payment_methods' => 'Manage saved cards for your PressStack subscription payments.',
    'addresses' => 'Create, edit, delete and choose default addresses for your subscriptions.',
];
$page_title = $page_title ?? ($pageTitleBySection[$billingSection] ?? 'Billing');
$page_subtitle = $pageSubBySection[$billingSection] ?? 'Manage your payment methods and billing details.';
?>
<script src="https://js.stripe.com/v3/"></script>

<style>
    .billing-grid { display: flex; flex-direction: column; gap: 20px; }
    .pm-list { display: flex; flex-direction: column; gap: 10px; }
    .pm-card { display: flex; align-items: center; gap: 16px; padding: 15px 18px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); background: var(--white); transition: var(--transition); }
    .pm-card.is-default { border-color: var(--ink); box-shadow: var(--shadow-xs); }
    .pm-card:hover { box-shadow: var(--shadow-sm); }
    .pm-card__network { width: 50px; height: 32px; border-radius: 5px; background: var(--paper-dark); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
    .pm-card__info { flex: 1; min-width: 0; }
    .pm-card__number { font-size: 14px; font-weight: 600; color: var(--ink); margin-bottom: 2px; }
    .pm-card__expiry { font-size: 12px; color: var(--ink-muted); }
    .pm-card__default-badge, .address-card__default-badge { font-size: 9.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; background: var(--gold-light); color: var(--gold); padding: 3px 9px; border-radius: 100px; flex-shrink: 0; border: 1px solid rgba(184, 134, 11, .2); }
    .pm-card__actions, .address-card__actions { display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; }
    .add-card-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 15px 18px; border: 1.5px dashed var(--border); border-radius: var(--radius-sm); background: none; cursor: pointer; font-family: var(--font-body); font-size: 14px; color: var(--ink-muted); transition: var(--transition); width: 100%; }
    .add-card-btn:hover { border-color: var(--ink); color: var(--ink); background: var(--paper); }
    .address-toolbar { display:flex; justify-content:flex-end; margin-bottom:14px; }
    .address-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
    .address-card { padding: 16px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--paper-light); position: relative; }
    .address-card.is-default { border-color: var(--gold); background: var(--white); box-shadow: var(--shadow-xs); }
    .address-card__head { display:flex; justify-content:space-between; gap:12px; margin-bottom:10px; }
    .address-card__label { font-weight:700; color:var(--ink); }
    .address-card__badge { display: inline-block; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 2px 6px; border-radius: 4px; margin-top: 5px; background: var(--border); color: var(--ink-soft); }
    .address-card__badge.is-billing { background: rgba(0, 102, 204, 0.1); color: #0066cc; }
    .address-card__badge.is-shipping { background: rgba(0, 153, 76, 0.1); color: #00994c; }
    .address-card__body { font-size:14px; color:var(--ink-soft); line-height:1.7; margin-bottom:14px; }
    .stripe-field-wrapper, .address-field { border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 12px 14px; background: var(--white); transition: var(--transition); width:100%; box-sizing:border-box; font-family:var(--font-body); font-size:14px; }
    .stripe-field-wrapper:focus-within, .address-field:focus { border-color: var(--ink); outline:none; }
    .stripe-field-label, .address-label { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .09em; color: var(--ink-muted); margin-bottom: 8px; display:block; }
    .address-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .address-form-group { margin-bottom:12px; }
    .security-note { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--ink-muted); margin-top: 8px; }
    .security-note svg { width: 14px; height: 14px; flex-shrink: 0; }
    .no-payment-state { text-align: center; padding: 44px 24px; }
    .no-payment-state__icon { font-size: 40px; margin-bottom: 12px; opacity: .4; }
    .no-payment-state__title { font-family: var(--font-display); font-size: 18px; margin-bottom: 6px; }
    .no-payment-state__sub { font-size: 14px; color: var(--ink-muted); margin-bottom: 20px; }
    .pm-skeleton { display: flex; flex-direction: column; gap: 10px; }
    .pm-skeleton__row { height: 64px; border-radius: var(--radius-sm); background: linear-gradient(90deg, var(--paper-dark) 25%, var(--paper) 50%, var(--paper-dark) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
    .inline-error { color:var(--red); font-size:13px; margin-bottom:12px; display:none; }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    @media (max-width:680px) { .address-row { grid-template-columns:1fr; } }

    <?php if ($billingSection === 'payment_methods'): ?>
    .billing-card--addresses { display: none; }
    <?php elseif ($billingSection === 'addresses'): ?>
    .billing-card--payment-methods, .billing-security-note { display: none; }
    <?php endif; ?>
</style>

@include('subscriptions/account/_layout')

<main class="page-content">
    <div class="page-heading">
        <div class="page-heading__eyebrow">Account</div>
        <h1 class="page-heading__title"><?= htmlspecialchars($page_title) ?></h1>
        <p class="page-heading__sub"><?= htmlspecialchars($page_subtitle) ?></p>
    </div>

    <div class="billing-grid">
        <section class="card billing-card billing-card--payment-methods" aria-labelledby="payment-methods-title">
            <div class="card__header">
                <span class="card__title" id="payment-methods-title">Payment Methods</span>
                <button class="btn btn--ghost btn--sm" id="open-add-card-btn">+ Add card</button>
            </div>
            <div class="card__body" id="payment-methods-body">
                <div class="pm-skeleton"><div class="pm-skeleton__row"></div><div class="pm-skeleton__row"></div></div>
            </div>
        </section>

        <section class="card billing-card billing-card--addresses" aria-labelledby="addresses-title">
            <div class="card__header">
                <span class="card__title" id="addresses-title">Saved Addresses</span>
                <button class="btn btn--ghost btn--sm" id="open-address-modal-btn">+ Add address</button>
            </div>
            <div class="card__body" id="billing-address-body">
                <div class="pm-skeleton"><div class="pm-skeleton__row" style="height:90px"></div></div>
            </div>
        </section>

        <div class="billing-security-note" style="display:flex; align-items:flex-start; gap:12px; padding:15px 18px; background:var(--white); border:1px solid var(--border); border-radius:var(--radius-sm); font-size:13px; color:var(--ink-muted); line-height:1.65; box-shadow:var(--shadow-xs);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0;margin-top:1px;color:var(--green);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Your payment information is encrypted and stored securely via Stripe. PressStack never stores your card number directly.</span>
        </div>
    </div>

    <div class="modal-overlay" id="add-card-modal" role="dialog" aria-modal="true" aria-labelledby="add-card-title">
        <div class="modal">
            <div class="modal__header"><h2 class="modal__title" id="add-card-title">Add Payment Method</h2><button class="modal__close" id="close-add-card-btn" aria-label="Close">×</button></div>
            <div class="modal__body">
                <div class="stripe-field-label">Card details</div>
                <div class="stripe-field-wrapper" id="stripe-card-element"></div>
                <div id="card-errors" class="inline-error" role="alert"></div>
                <label style="display:flex; align-items:center; gap:10px; font-size:14px; cursor:pointer; margin-bottom:16px;"><input type="checkbox" id="set-as-default" checked style="accent-color:var(--ink); width:16px; height:16px;">Set as default payment method</label>
                <div class="security-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Secured by Stripe. Your card details are encrypted.</div>
            </div>
            <div class="modal__footer"><button class="btn btn--ghost" id="cancel-add-card-btn">Cancel</button><button class="btn btn--primary" id="submit-add-card-btn">Add Card</button></div>
        </div>
    </div>

    <div class="modal-overlay" id="remove-card-modal" role="dialog" aria-modal="true" aria-labelledby="remove-card-title">
        <div class="modal">
            <div class="modal__header"><h2 class="modal__title" id="remove-card-title">Remove Card</h2><button class="modal__close" id="close-remove-card-btn" aria-label="Close">×</button></div>
            <div class="modal__body"><p style="font-size:14px; color:var(--ink-soft); line-height:1.65;">Are you sure you want to remove this card? This action cannot be undone. Any active subscriptions using this card will need to be updated.</p></div>
            <div class="modal__footer"><button class="btn btn--ghost" id="cancel-remove-card-btn">Cancel</button><button class="btn btn--danger" id="confirm-remove-card-btn">Remove Card</button></div>
        </div>
    </div>

    <div class="modal-overlay" id="address-modal" role="dialog" aria-modal="true" aria-labelledby="address-modal-title">
        <div class="modal">
            <div class="modal__header"><h2 class="modal__title" id="address-modal-title">Add Address</h2><button class="modal__close" id="close-address-modal-btn" aria-label="Close">×</button></div>
            <form id="address-form">
                <div class="modal__body">
                    <input type="hidden" id="address-id" name="id">
                    <input type="hidden" name="member_id" value="<?= (int) $member->id ?>">
                    <div id="address-errors" class="inline-error" role="alert"></div>
                    <div class="address-form-group"><label class="address-label" for="address-label">Label</label><input class="address-field" id="address-label" name="label" placeholder="Home, Work"></div>
                    <div class="address-form-group"><label class="address-label" for="address-type">Type</label><select class="address-field" id="address-type" name="type" required><option value="both">Shipping & Billing</option><option value="shipping">Shipping only</option><option value="billing">Billing only</option></select></div>
                    <div class="address-form-group"><label class="address-label" for="address-line-1">Address line 1</label><input class="address-field" id="address-line-1" name="address_line_1" required></div>
                    <div class="address-form-group"><label class="address-label" for="address-line-2">Address line 2</label><input class="address-field" id="address-line-2" name="address_line_2"></div>
                    <div class="address-row"><div class="address-form-group"><label class="address-label" for="address-city">City</label><input class="address-field" id="address-city" name="city" required></div><div class="address-form-group"><label class="address-label" for="address-state">County / State</label><input class="address-field" id="address-state" name="state"></div></div>
                    <div class="address-row"><div class="address-form-group"><label class="address-label" for="address-postcode">Postcode</label><input class="address-field" id="address-postcode" name="postcode" required></div><div class="address-form-group"><label class="address-label" for="address-country">Country</label><input class="address-field" id="address-country" name="country" value="GB" required></div></div>
                    <label style="display:flex; align-items:center; gap:10px; font-size:14px; cursor:pointer;"><input type="checkbox" id="address-default" name="is_default" value="1" style="accent-color:var(--ink); width:16px; height:16px;">Set as default address</label>
                </div>
                <div class="modal__footer"><button type="button" class="btn btn--ghost" id="cancel-address-modal-btn">Cancel</button><button type="submit" class="btn btn--primary" id="save-address-btn">Save Address</button></div>
            </form>
        </div>
    </div>
</main>

<script>
    class BillingPage {
        static NETWORK_ICONS = { visa: '💳', mastercard: '💳', amex: '💳', discover: '💳' };
        static MEMBER_ID = <?= (int) $member->id ?>;
        static STRIPE_PUBLIC_KEY = '<?= $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key') ?>';
        static ENDPOINTS = {
            paymentMethods: '/press-stack/account/billing/payment-methods',
            setupIntent: '/press-stack/account/billing/setup-intent',
            addCard: '/press-stack/account/billing/finalise-setup-intent',
            removeCard: '/press-stack/account/billing/remove-card',
            setDefault: '/press-stack/account/billing/set-default',
            addresses: '/press-stack/account/addresses/search',
            addAddress: '/press-stack/account/addresses',
            address: id => `/press-stack/account/addresses/${id}`,
            deleteAddress: id => `/press-stack/account/addresses/${id}/delete`,
            defaultAddress: id => `/press-stack/account/addresses/${id}/set-default`,
        };

        #stripe = null; #elements = null; #cardElement = null;
        #state = { paymentMethods: [], addresses: [], loadingPMs: true, loadingAddress: true, pendingRemoveId: null, submittingAdd: false, submittingRemove: false, submittingDefault: null, savingAddress: false, editingAddressId: null, deletingAddressId: null, defaultingAddressId: null };
        #els = {};

        constructor() { this.#bindElements(); this.#attachListeners(); this.#load(); }
        #bindElements() { ['payment-methods-body','billing-address-body','add-card-modal','remove-card-modal','open-add-card-btn','close-add-card-btn','cancel-add-card-btn','submit-add-card-btn','close-remove-card-btn','cancel-remove-card-btn','confirm-remove-card-btn','card-errors','set-as-default','address-modal','address-form','open-address-modal-btn','close-address-modal-btn','cancel-address-modal-btn','save-address-btn','address-errors','address-modal-title'].forEach(id => { this.#els[id] = document.getElementById(id); }); }
        #attachListeners() {
            this.#els['open-add-card-btn']?.addEventListener('click', () => this.#openAddCard()); this.#els['close-add-card-btn']?.addEventListener('click', () => this.#closeAddCard()); this.#els['cancel-add-card-btn']?.addEventListener('click', () => this.#closeAddCard()); this.#els['submit-add-card-btn']?.addEventListener('click', () => this.#submitAddCard());
            this.#els['close-remove-card-btn']?.addEventListener('click', () => this.#closeRemoveCard()); this.#els['cancel-remove-card-btn']?.addEventListener('click', () => this.#closeRemoveCard()); this.#els['confirm-remove-card-btn']?.addEventListener('click', () => this.#confirmRemoveCard());
            this.#els['open-address-modal-btn']?.addEventListener('click', () => this.#openAddressModal()); this.#els['close-address-modal-btn']?.addEventListener('click', () => this.#closeAddressModal()); this.#els['cancel-address-modal-btn']?.addEventListener('click', () => this.#closeAddressModal()); this.#els['address-form']?.addEventListener('submit', e => this.#submitAddress(e));
            this.#els['add-card-modal']?.addEventListener('click', e => { if (e.target === this.#els['add-card-modal']) this.#closeAddCard(); }); this.#els['remove-card-modal']?.addEventListener('click', e => { if (e.target === this.#els['remove-card-modal']) this.#closeRemoveCard(); }); this.#els['address-modal']?.addEventListener('click', e => { if (e.target === this.#els['address-modal']) this.#closeAddressModal(); });
            document.addEventListener('keydown', e => { if (e.key !== 'Escape') return; this.#closeAddCard(); this.#closeRemoveCard(); this.#closeAddressModal(); });
        }
        async #load() { await Promise.all([this.#loadPaymentMethods(), this.#loadAddresses()]); }
        async #loadPaymentMethods() { this.#setState({ loadingPMs: true }); try { const res = await this.#apiFetch(BillingPage.ENDPOINTS.paymentMethods); const data = await res.json(); this.#setState({ loadingPMs: false, paymentMethods: data.data?.payment_methods ?? data.payment_methods ?? [] }); } catch { this.#setState({ loadingPMs: false, paymentMethods: [] }); } }
        async #loadAddresses() { this.#setState({ loadingAddress: true }); try { const res = await this.#apiFetch(BillingPage.ENDPOINTS.addresses); const data = await res.json(); this.#setState({ loadingAddress: false, addresses: data.data?.items ?? data.items ?? [] }); } catch { this.#setState({ loadingAddress: false, addresses: [] }); } }
        #setState(patch) { Object.assign(this.#state, patch); this.#render(); }
        #render() { this.#renderPaymentMethods(); this.#renderAddresses(); this.#renderRemoveCardBtn(); this.#renderAddCardBtn(); this.#renderSaveAddressBtn(); }

        #renderPaymentMethods() { const body = this.#els['payment-methods-body']; if (!body) return; if (this.#state.loadingPMs) { body.innerHTML = `<div class="pm-skeleton"><div class="pm-skeleton__row"></div><div class="pm-skeleton__row"></div></div>`; return; } const methods = this.#state.paymentMethods; if (!methods.length) { body.innerHTML = this.#emptyPaymentHtml(); body.querySelector('.js-open-add-card')?.addEventListener('click', () => this.#openAddCard()); return; } body.innerHTML = `<div class="pm-list">${methods.map(pm => this.#paymentCardHtml(pm)).join('')}<button class="add-card-btn js-open-add-card">Add new card</button></div>`; body.querySelectorAll('[data-action="set-default"]').forEach(btn => btn.addEventListener('click', () => this.#setDefault(btn.dataset.id))); body.querySelectorAll('[data-action="remove"]').forEach(btn => btn.addEventListener('click', () => this.#openRemoveCard(btn.dataset.id))); body.querySelector('.js-open-add-card')?.addEventListener('click', () => this.#openAddCard()); }
        #renderAddresses() { const body = this.#els['billing-address-body']; if (!body) return; if (this.#state.loadingAddress) { body.innerHTML = `<div class="pm-skeleton"><div class="pm-skeleton__row" style="height:90px"></div></div>`; return; } const addresses = this.#state.addresses; if (!addresses.length) { body.innerHTML = `<div class="no-payment-state"><div class="no-payment-state__icon">📍</div><div class="no-payment-state__title">No addresses saved</div><div class="no-payment-state__sub">Add an address to use for billing and subscription delivery.</div><button class="btn btn--primary js-add-address">Add address</button></div>`; body.querySelector('.js-add-address')?.addEventListener('click', () => this.#openAddressModal()); return; } body.innerHTML = `<div class="address-grid">${addresses.map(addr => this.#addressCardHtml(addr)).join('')}</div>`; body.querySelectorAll('[data-address-action="edit"]').forEach(btn => btn.addEventListener('click', () => this.#openAddressModal(this.#state.addresses.find(a => String(a.id) === String(btn.dataset.id))))); body.querySelectorAll('[data-address-action="delete"]').forEach(btn => btn.addEventListener('click', () => this.#deleteAddress(btn.dataset.id))); body.querySelectorAll('[data-address-action="default"]').forEach(btn => btn.addEventListener('click', () => this.#setDefaultAddress(btn.dataset.id))); }
        #addressCardHtml(addr) { const isDefault = !!Number(addr.is_default); const typeClass = addr.type === 'billing' ? 'is-billing' : (addr.type === 'shipping' ? 'is-shipping' : ''); const deleting = String(this.#state.deletingAddressId) === String(addr.id); const defaulting = String(this.#state.defaultingAddressId) === String(addr.id); return `<div class="address-card ${isDefault ? 'is-default' : ''}"><div class="address-card__head"><div><div class="address-card__label">${this.#escape(addr.label || 'Address')}</div><span class="address-card__badge ${typeClass}">${this.#escape(addr.type || 'both')}</span></div>${isDefault ? '<span class="address-card__default-badge">Default</span>' : ''}</div><div class="address-card__body">${this.#escape(addr.address_line_1 || '')}<br>${addr.address_line_2 ? this.#escape(addr.address_line_2) + '<br>' : ''}${this.#escape(addr.city || '')}${addr.state ? ', ' + this.#escape(addr.state) : ''} ${this.#escape(addr.postcode || '')}<br>${this.#escape(addr.country || '')}</div><div class="address-card__actions">${!isDefault ? `<button class="btn btn--ghost btn--sm" data-address-action="default" data-id="${this.#escape(addr.id)}" ${defaulting ? 'disabled' : ''}>${defaulting ? 'Updating…' : 'Set default'}</button>` : ''}<button class="btn btn--ghost btn--sm" data-address-action="edit" data-id="${this.#escape(addr.id)}">Edit</button><button class="btn btn--danger btn--sm" data-address-action="delete" data-id="${this.#escape(addr.id)}" ${deleting ? 'disabled' : ''}>${deleting ? 'Deleting…' : 'Delete'}</button></div></div>`; }

        #initStripe() { if (this.#stripe) return; if (typeof Stripe === 'undefined') { console.error('Stripe.js failed to load.'); return; } this.#stripe = Stripe(BillingPage.STRIPE_PUBLIC_KEY); this.#elements = this.#stripe.elements(); this.#cardElement = this.#elements.create('card', { style: { base: { color: '#111111', fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif', fontSmoothing: 'antialiased', fontSize: '14px', '::placeholder': { color: '#999999' } }, invalid: { color: '#e53e3e', iconColor: '#e53e3e' } }, hidePostalCode: true }); this.#cardElement.on('change', event => { const errorEl = this.#els['card-errors']; if (!errorEl) return; if (event.error) { errorEl.textContent = event.error.message; errorEl.style.display = 'block'; } else { this.#clearCardErrors(); } }); }
        async #submitAddCard() { if (this.#state.submittingAdd) return; this.#setState({ submittingAdd: true }); this.#clearCardErrors(); try { const intentRes = await this.#apiFetch(BillingPage.ENDPOINTS.setupIntent, { method: 'POST' }); const intentData = await intentRes.json(); if (!intentData.success || !intentData.client_secret) throw new Error(intentData.message || 'Unable to initialize card transaction setup.'); const result = await this.#stripe.confirmCardSetup(intentData.client_secret, { payment_method: { card: this.#cardElement, billing_details: { name: '<?= addslashes($member->name ?? "") ?>', email: '<?= addslashes($member->email ?? "") ?>' } } }); if (result.error) throw new Error(result.error.message); const attachRes = await this.#apiFetch(BillingPage.ENDPOINTS.addCard, { method: 'POST', body: JSON.stringify({ setup_intent_id: result.setupIntent.id, set_default: this.#els['set-as-default']?.checked ?? true }) }); const attachData = await attachRes.json(); if (attachData.success) { this.#closeAddCard(); await this.#loadPaymentMethods(); } else { throw new Error(attachData.message || 'Failed to link new card to profile account.'); } } catch (err) { const errorEl = this.#els['card-errors']; if (errorEl) { errorEl.textContent = err.message || 'An unexpected connection error occurred.'; errorEl.style.display = 'block'; } } finally { this.#setState({ submittingAdd: false }); } }
        async #confirmRemoveCard() { if (this.#state.submittingRemove) return; this.#setState({ submittingRemove: true }); try { const res = await this.#apiFetch(BillingPage.ENDPOINTS.removeCard, { method: 'POST', body: JSON.stringify({ payment_method_id: this.#state.pendingRemoveId }) }); const data = await res.json(); if (data.success) { this.#closeRemoveCard(); await this.#loadPaymentMethods(); } else { alert(data.message ?? 'Failed to remove card.'); } } catch { alert('Network error. Please try again.'); } finally { this.#setState({ submittingRemove: false, pendingRemoveId: null }); } }
        async #setDefault(paymentMethodId) { if (this.#state.submittingDefault) return; this.#setState({ submittingDefault: paymentMethodId }); try { const res = await this.#apiFetch(BillingPage.ENDPOINTS.setDefault, { method: 'POST', body: JSON.stringify({ payment_method_id: paymentMethodId }) }); const data = await res.json(); if (data.success) await this.#loadPaymentMethods(); else alert(data.message ?? 'Failed to set default.'); } catch { alert('Network error. Please try again.'); } finally { this.#setState({ submittingDefault: null }); } }

        #openAddressModal(address = null) { this.#clearAddressErrors(); this.#els['address-form']?.reset(); this.#state.editingAddressId = address?.id ?? null; this.#els['address-modal-title'].textContent = address ? 'Edit Address' : 'Add Address'; document.getElementById('address-id').value = address?.id ?? ''; document.getElementById('address-label').value = address?.label ?? ''; document.getElementById('address-type').value = address?.type ?? 'both'; document.getElementById('address-line-1').value = address?.address_line_1 ?? ''; document.getElementById('address-line-2').value = address?.address_line_2 ?? ''; document.getElementById('address-city').value = address?.city ?? ''; document.getElementById('address-state').value = address?.state ?? ''; document.getElementById('address-postcode').value = address?.postcode ?? ''; document.getElementById('address-country').value = address?.country ?? 'GB'; document.getElementById('address-default').checked = !!Number(address?.is_default ?? 0); this.#els['address-modal']?.classList.add('open'); }
        #closeAddressModal() { this.#els['address-modal']?.classList.remove('open'); this.#state.editingAddressId = null; this.#clearAddressErrors(); }
        async #submitAddress(e) { e.preventDefault(); if (this.#state.savingAddress) return; const formData = new FormData(this.#els['address-form']); const data = Object.fromEntries(formData.entries()); data.member_id = BillingPage.MEMBER_ID; data.is_default = formData.get('is_default') ? 1 : 0; const id = this.#state.editingAddressId; this.#setState({ savingAddress: true }); this.#clearAddressErrors(); try { const res = await this.#apiFetch(id ? BillingPage.ENDPOINTS.address(id) : BillingPage.ENDPOINTS.addAddress, { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) }); const result = await res.json(); if (!res.ok || result.success === false) throw new Error(result.message || 'Address could not be saved.'); this.#closeAddressModal(); await this.#loadAddresses(); } catch (err) { const el = this.#els['address-errors']; if (el) { el.textContent = err.message || 'Address could not be saved.'; el.style.display = 'block'; } } finally { this.#setState({ savingAddress: false }); } }
        async #deleteAddress(id) { if (!confirm('Delete this address?')) return; this.#setState({ deletingAddressId: id }); try { const res = await this.#apiFetch(BillingPage.ENDPOINTS.deleteAddress(id), { method: 'POST' }); const result = await res.json(); if (!res.ok || result.success === false) throw new Error(result.message || 'Address could not be deleted.'); await this.#loadAddresses(); } catch (err) { alert(err.message || 'Address could not be deleted.'); } finally { this.#setState({ deletingAddressId: null }); } }
        async #setDefaultAddress(id) { this.#setState({ defaultingAddressId: id }); try { const res = await this.#apiFetch(BillingPage.ENDPOINTS.defaultAddress(id), { method: 'POST', body: JSON.stringify({ member_id: BillingPage.MEMBER_ID }) }); const result = await res.json(); if (!res.ok || result.success === false) throw new Error(result.message || 'Default address could not be updated.'); await this.#loadAddresses(); } catch (err) { alert(err.message || 'Default address could not be updated.'); } finally { this.#setState({ defaultingAddressId: null }); } }

        #paymentCardHtml(pm) { const icon = BillingPage.NETWORK_ICONS[pm.brand?.toLowerCase()] ?? '💳'; const isDefault = pm.is_default; const isBusy = this.#state.submittingDefault === pm.id; const removeDisabled = pm.can_remove === false; const defaultBtn = !isDefault ? `<button class="btn btn--ghost btn--sm" data-action="set-default" data-id="${this.#escape(pm.id)}" ${isBusy ? 'disabled' : ''}>${isBusy ? 'Saving…' : 'Set default'}</button>` : ''; const badge = isDefault ? `<span class="pm-card__default-badge">Default</span>` : ''; return `<div class="pm-card ${isDefault ? 'is-default' : ''}"><div class="pm-card__network">${icon}</div><div class="pm-card__info"><div class="pm-card__number">${this.#escape(pm.brand ?? 'Card')} ···· ${this.#escape(pm.last4 ?? '????')}</div><div class="pm-card__expiry">Expires ${this.#escape(String(pm.exp_month ?? '--'))}/${this.#escape(String(pm.exp_year ?? '--'))}</div></div>${badge}<div class="pm-card__actions">${defaultBtn}<button class="btn btn--danger btn--sm" data-action="remove" data-id="${this.#escape(pm.id)}" ${removeDisabled ? 'disabled title="Add another card before removing this one."' : ''}>Remove</button></div></div>`; }
        #emptyPaymentHtml() { return `<div class="no-payment-state"><div class="no-payment-state__icon">💳</div><div class="no-payment-state__title">No payment methods saved</div><div class="no-payment-state__sub">Add a payment method to speed up checkout and renewals.</div><button class="btn btn--primary js-open-add-card">Add card</button></div>`; }
        #openAddCard() { this.#els['add-card-modal']?.classList.add('open'); this.#initStripe(); if (this.#cardElement) this.#cardElement.mount('#stripe-card-element'); this.#els['close-add-card-btn']?.focus(); }
        #closeAddCard() { this.#els['add-card-modal']?.classList.remove('open'); if (this.#cardElement) this.#cardElement.unmount(); this.#clearCardErrors(); }
        #openRemoveCard(paymentMethodId) { this.#setState({ pendingRemoveId: paymentMethodId }); this.#els['remove-card-modal']?.classList.add('open'); this.#els['close-remove-card-btn']?.focus(); }
        #closeRemoveCard() { this.#els['remove-card-modal']?.classList.remove('open'); this.#setState({ pendingRemoveId: null, submittingRemove: false }); }
        #renderRemoveCardBtn() { const btn = this.#els['confirm-remove-card-btn']; if (btn) { btn.disabled = this.#state.submittingRemove; btn.textContent = this.#state.submittingRemove ? 'Removing…' : 'Remove Card'; } }
        #renderAddCardBtn() { const btn = this.#els['submit-add-card-btn']; if (btn) { btn.disabled = this.#state.submittingAdd; btn.textContent = this.#state.submittingAdd ? 'Processing…' : 'Add Card'; } }
        #renderSaveAddressBtn() { const btn = this.#els['save-address-btn']; if (btn) { btn.disabled = this.#state.savingAddress; btn.textContent = this.#state.savingAddress ? 'Saving…' : 'Save Address'; } }
        #clearCardErrors() { const el = this.#els['card-errors']; if (!el) return; el.style.display = 'none'; el.textContent = ''; }
        #clearAddressErrors() { const el = this.#els['address-errors']; if (!el) return; el.style.display = 'none'; el.textContent = ''; }
        async #apiFetch(url, options = {}) { return fetch(url, { ...options, headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', ...(options.headers ?? {}) } }); }
        #escape(str) { return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
    }
    document.addEventListener('DOMContentLoaded', () => new BillingPage());
</script>
</div>
</body>
</html>
