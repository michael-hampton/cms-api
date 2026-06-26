class PressStackAccountUi {
    static escape(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    static skeleton(height = null) {
        const style = height ? ` style="height:${height}"` : '';
        return `<div class="pm-skeleton"><div class="pm-skeleton__row"${style}></div></div>`;
    }
}

class PressStackAccountApiClient {
    async request(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                ...(options.headers ?? {}),
            },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.success === false) throw new Error(payload.message || 'Request failed.');
        return payload.data ?? payload;
    }
}

class PressStackAccountStore {
    constructor(initialState) { this.state = { ...initialState }; this.listeners = []; }
    subscribe(listener) { this.listeners.push(listener); listener(this.state); }
    setState(patch) { this.state = { ...this.state, ...patch }; this.listeners.forEach(listener => listener(this.state)); }
}

class PressStackPaymentMethodStore extends PressStackAccountStore {
    constructor() { super({ paymentMethods: [], loading: true, pendingRemoveId: null, submittingAdd: false, submittingRemove: false, submittingDefault: null }); }
}

class PressStackAddressStore extends PressStackAccountStore {
    constructor() { super({ addresses: [], loading: true, saving: false, editingId: null, deletingId: null, defaultingId: null, error: null }); }
}

class PressStackPaymentMethodCard {
    static render(paymentMethod, state) {
        const icon = PressStackPaymentMethodManager.NETWORK_ICONS[paymentMethod.brand?.toLowerCase()] ?? '💳';
        const isDefault = !!paymentMethod.is_default;
        const isBusy = state.submittingDefault === paymentMethod.id;
        const removeDisabled = paymentMethod.can_remove === false;
        const defaultButton = !isDefault ? `<button class="btn btn--ghost btn--sm" data-action="set-default" data-id="${PressStackAccountUi.escape(paymentMethod.id)}" ${isBusy ? 'disabled' : ''}>${isBusy ? 'Saving…' : 'Set default'}</button>` : '';
        const badge = isDefault ? `<span class="pm-card__default-badge">Default</span>` : '';
        return `<div class="pm-card ${isDefault ? 'is-default' : ''}"><div class="pm-card__network">${icon}</div><div class="pm-card__info"><div class="pm-card__number">${PressStackAccountUi.escape(paymentMethod.brand || 'Card')} ···· ${PressStackAccountUi.escape(paymentMethod.last4 || '????')}</div><div class="pm-card__expiry">Expires ${PressStackAccountUi.escape(paymentMethod.exp_month || '--')}/${PressStackAccountUi.escape(paymentMethod.exp_year || '--')}</div></div>${badge}<div class="pm-card__actions">${defaultButton}<button class="btn btn--danger btn--sm" data-action="remove" data-id="${PressStackAccountUi.escape(paymentMethod.id)}" ${removeDisabled ? 'disabled title="Add another card before removing this one."' : ''}>Remove</button></div></div>`;
    }
}

class PressStackPaymentMethodManager {
    static NETWORK_ICONS = { visa: '💳', mastercard: '💳', amex: '💳', discover: '💳' };
    constructor(api, endpoints, member) {
        this.api = api; this.endpoints = endpoints; this.member = member; this.store = new PressStackPaymentMethodStore();
        this.stripe = null; this.elements = null; this.cardElement = null;
        this.els = { body: document.getElementById('payment-methods-body'), addModal: document.getElementById('add-card-modal'), removeModal: document.getElementById('remove-card-modal'), openAdd: document.getElementById('open-add-card-btn'), closeAdd: document.getElementById('close-add-card-btn'), cancelAdd: document.getElementById('cancel-add-card-btn'), submitAdd: document.getElementById('submit-add-card-btn'), closeRemove: document.getElementById('close-remove-card-btn'), cancelRemove: document.getElementById('cancel-remove-card-btn'), confirmRemove: document.getElementById('confirm-remove-card-btn'), errors: document.getElementById('card-errors'), setDefault: document.getElementById('set-as-default') };
    }
    init() { this.bindEvents(); this.store.subscribe(state => this.render(state)); this.load(); }
    bindEvents() { this.els.openAdd?.addEventListener('click', () => this.openAddModal()); this.els.closeAdd?.addEventListener('click', () => this.closeAddModal()); this.els.cancelAdd?.addEventListener('click', () => this.closeAddModal()); this.els.submitAdd?.addEventListener('click', () => this.submitAddCard()); this.els.closeRemove?.addEventListener('click', () => this.closeRemoveModal()); this.els.cancelRemove?.addEventListener('click', () => this.closeRemoveModal()); this.els.confirmRemove?.addEventListener('click', () => this.confirmRemoveCard()); this.els.addModal?.addEventListener('click', event => { if (event.target === this.els.addModal) this.closeAddModal(); }); this.els.removeModal?.addEventListener('click', event => { if (event.target === this.els.removeModal) this.closeRemoveModal(); }); }
    async load() { this.store.setState({ loading: true }); try { const data = await this.api.request(this.endpoints.paymentMethods); this.store.setState({ loading: false, paymentMethods: data.payment_methods ?? [] }); } catch { this.store.setState({ loading: false, paymentMethods: [] }); } }
    render(state) { this.renderList(state); this.renderButtons(state); }
    renderList(state) { if (!this.els.body) return; if (state.loading) { this.els.body.innerHTML = `<div class="pm-skeleton"><div class="pm-skeleton__row"></div><div class="pm-skeleton__row"></div></div>`; return; } if (!state.paymentMethods.length) { this.els.body.innerHTML = `<div class="no-payment-state"><div class="no-payment-state__icon">💳</div><div class="no-payment-state__title">No payment methods saved</div><div class="no-payment-state__sub">Add a payment method to speed up checkout and renewals.</div><button class="btn btn--primary js-open-add-card">Add card</button></div>`; this.els.body.querySelector('.js-open-add-card')?.addEventListener('click', () => this.openAddModal()); return; } this.els.body.innerHTML = `<div class="pm-list">${state.paymentMethods.map(paymentMethod => PressStackPaymentMethodCard.render(paymentMethod, state)).join('')}<button class="add-card-btn js-open-add-card">Add new card</button></div>`; this.els.body.querySelector('.js-open-add-card')?.addEventListener('click', () => this.openAddModal()); this.els.body.querySelectorAll('[data-action="set-default"]').forEach(button => button.addEventListener('click', () => this.setDefault(button.dataset.id))); this.els.body.querySelectorAll('[data-action="remove"]').forEach(button => button.addEventListener('click', () => this.openRemoveModal(button.dataset.id))); }
    renderButtons(state) { if (this.els.submitAdd) { this.els.submitAdd.disabled = state.submittingAdd; this.els.submitAdd.textContent = state.submittingAdd ? 'Processing…' : 'Add Card'; } if (this.els.confirmRemove) { this.els.confirmRemove.disabled = state.submittingRemove; this.els.confirmRemove.textContent = state.submittingRemove ? 'Removing…' : 'Remove Card'; } }
    initStripe() { if (this.stripe) return; if (typeof Stripe === 'undefined') return; this.stripe = Stripe(this.endpoints.stripePublicKey); this.elements = this.stripe.elements(); this.cardElement = this.elements.create('card', { hidePostalCode: true }); this.cardElement.on('change', event => event.error ? this.showError(event.error.message) : this.clearErrors()); }
    openAddModal() { this.els.addModal?.classList.add('open'); this.initStripe(); this.cardElement?.mount('#stripe-card-element'); }
    closeAddModal() { this.els.addModal?.classList.remove('open'); this.cardElement?.unmount(); this.clearErrors(); }
    openRemoveModal(paymentMethodId) { this.store.setState({ pendingRemoveId: paymentMethodId }); this.els.removeModal?.classList.add('open'); }
    closeRemoveModal() { this.els.removeModal?.classList.remove('open'); this.store.setState({ pendingRemoveId: null, submittingRemove: false }); }
    async submitAddCard() { if (this.store.state.submittingAdd) return; this.store.setState({ submittingAdd: true }); this.clearErrors(); try { const intentData = await this.api.request(this.endpoints.setupIntent, { method: 'POST' }); const result = await this.stripe.confirmCardSetup(intentData.client_secret, { payment_method: { card: this.cardElement, billing_details: { name: this.member.name, email: this.member.email } } }); if (result.error) throw new Error(result.error.message); await this.api.request(this.endpoints.addCard, { method: 'POST', body: JSON.stringify({ setup_intent_id: result.setupIntent.id, set_default: this.els.setDefault?.checked ?? true }) }); this.closeAddModal(); await this.load(); } catch (error) { this.showError(error.message || 'An unexpected connection error occurred.'); } finally { this.store.setState({ submittingAdd: false }); } }
    async confirmRemoveCard() { if (this.store.state.submittingRemove) return; this.store.setState({ submittingRemove: true }); try { await this.api.request(this.endpoints.removeCard, { method: 'POST', body: JSON.stringify({ payment_method_id: this.store.state.pendingRemoveId }) }); this.closeRemoveModal(); await this.load(); } catch (error) { alert(error.message || 'Failed to remove card.'); } finally { this.store.setState({ submittingRemove: false, pendingRemoveId: null }); } }
    async setDefault(paymentMethodId) { if (this.store.state.submittingDefault) return; this.store.setState({ submittingDefault: paymentMethodId }); try { await this.api.request(this.endpoints.setDefault, { method: 'POST', body: JSON.stringify({ payment_method_id: paymentMethodId }) }); await this.load(); } catch (error) { alert(error.message || 'Failed to set default.'); } finally { this.store.setState({ submittingDefault: null }); } }
    showError(message) { if (!this.els.errors) return; this.els.errors.textContent = message; this.els.errors.style.display = 'block'; }
    clearErrors() { if (!this.els.errors) return; this.els.errors.style.display = 'none'; this.els.errors.textContent = ''; }
}

class PressStackAddressCard { constructor(address, state) { this.address = address; this.state = state; } render() { const addr = this.address; const isDefault = !!Number(addr.is_default); const typeClass = addr.type === 'billing' ? 'is-billing' : (addr.type === 'shipping' ? 'is-shipping' : ''); const deleting = String(this.state.deletingId) === String(addr.id); const defaulting = String(this.state.defaultingId) === String(addr.id); return `<div class="address-card ${isDefault ? 'is-default' : ''}"><div class="address-card__head"><div><div class="address-card__label">${PressStackAccountUi.escape(addr.label || 'Address')}</div><span class="address-card__badge ${typeClass}">${PressStackAccountUi.escape(addr.type || 'both')}</span></div>${isDefault ? '<span class="address-card__default-badge">Default</span>' : ''}</div><div class="address-card__body">${PressStackAccountUi.escape(addr.address_line_1 || '')}<br>${addr.address_line_2 ? PressStackAccountUi.escape(addr.address_line_2) + '<br>' : ''}${PressStackAccountUi.escape(addr.city || '')}${addr.state ? ', ' + PressStackAccountUi.escape(addr.state) : ''} ${PressStackAccountUi.escape(addr.postcode || '')}<br>${PressStackAccountUi.escape(addr.country || '')}</div><div class="address-card__actions">${!isDefault ? `<button class="btn btn--ghost btn--sm" data-address-action="default" data-id="${PressStackAccountUi.escape(addr.id)}" ${defaulting ? 'disabled' : ''}>${defaulting ? 'Updating…' : 'Set default'}</button>` : ''}<button class="btn btn--ghost btn--sm" data-address-action="edit" data-id="${PressStackAccountUi.escape(addr.id)}">Edit</button><button class="btn btn--danger btn--sm" data-address-action="delete" data-id="${PressStackAccountUi.escape(addr.id)}" ${deleting ? 'disabled' : ''}>${deleting ? 'Deleting…' : 'Delete'}</button></div></div>`; } }

class PressStackAddressModal { constructor(manager) { this.manager = manager; this.el = document.getElementById('address-modal'); this.form = document.getElementById('address-form'); this.title = document.getElementById('address-modal-title'); this.errorEl = document.getElementById('address-errors'); this.submitButton = document.getElementById('save-address-btn'); } bindEvents() { document.getElementById('open-address-modal-btn')?.addEventListener('click', () => this.open()); document.getElementById('close-address-modal-btn')?.addEventListener('click', () => this.close()); document.getElementById('cancel-address-modal-btn')?.addEventListener('click', () => this.close()); this.form?.addEventListener('submit', event => this.manager.submit(event)); this.el?.addEventListener('click', event => { if (event.target === this.el) this.close(); }); } open(address = null) { this.clearError(); this.form?.reset(); this.manager.store.setState({ editingId: address?.id ?? null }); this.title.textContent = address ? 'Edit Address' : 'Add Address'; this.setField('address-id', address?.id ?? ''); this.setField('address-label', address?.label ?? ''); this.setField('address-type', address?.type ?? 'both'); this.setField('address-line-1', address?.address_line_1 ?? ''); this.setField('address-line-2', address?.address_line_2 ?? ''); this.setField('address-city', address?.city ?? ''); this.setField('address-state', address?.state ?? ''); this.setField('address-postcode', address?.postcode ?? ''); this.setField('address-country', address?.country ?? 'GB'); document.getElementById('address-default').checked = !!Number(address?.is_default ?? 0); this.el?.classList.add('open'); } close() { this.el?.classList.remove('open'); this.manager.store.setState({ editingId: null }); this.clearError(); } setSaving(saving) { if (!this.submitButton) return; this.submitButton.disabled = saving; this.submitButton.textContent = saving ? 'Saving…' : 'Save Address'; } values(memberId) { const formData = new FormData(this.form); const data = Object.fromEntries(formData.entries()); data.member_id = memberId; data.is_default = formData.get('is_default') ? 1 : 0; return data; } setField(id, value) { const field = document.getElementById(id); if (field) field.value = value; } showError(message) { if (!this.errorEl) return; this.errorEl.textContent = message; this.errorEl.style.display = 'block'; } clearError() { if (!this.errorEl) return; this.errorEl.textContent = ''; this.errorEl.style.display = 'none'; } }

class PressStackAddressManager { constructor(api, endpoints, memberId) { this.api = api; this.endpoints = endpoints; this.memberId = memberId; this.store = new PressStackAddressStore(); this.container = document.getElementById('billing-address-body'); this.modal = new PressStackAddressModal(this); } init() { this.modal.bindEvents(); this.store.subscribe(state => this.render(state)); this.load(); } async load() { this.store.setState({ loading: true, error: null }); try { const data = await this.api.request(this.endpoints.addresses); this.store.setState({ addresses: data.items ?? [], loading: false }); } catch (error) { this.store.setState({ addresses: [], loading: false, error: error.message || 'Failed to load addresses.' }); } } render(state) { this.modal.setSaving(state.saving); if (!this.container) return; if (state.loading) { this.container.innerHTML = PressStackAccountUi.skeleton('90px'); return; } if (state.error) { this.container.innerHTML = `<div class="no-payment-state"><div class="no-payment-state__icon">⚠️</div><div class="no-payment-state__title">Unable to load addresses</div><div class="no-payment-state__sub">${PressStackAccountUi.escape(state.error)}</div><button class="btn btn--primary js-reload-addresses">Retry</button></div>`; this.container.querySelector('.js-reload-addresses')?.addEventListener('click', () => this.load()); return; } if (!state.addresses.length) { this.container.innerHTML = `<div class="no-payment-state"><div class="no-payment-state__icon">📍</div><div class="no-payment-state__title">No addresses saved</div><div class="no-payment-state__sub">Add an address to use for billing and subscription delivery.</div><button class="btn btn--primary js-add-address">Add address</button></div>`; this.container.querySelector('.js-add-address')?.addEventListener('click', () => this.modal.open()); return; } this.container.innerHTML = `<div class="address-grid">${state.addresses.map(address => new PressStackAddressCard(address, state).render()).join('')}</div>`; this.container.querySelectorAll('[data-address-action="edit"]').forEach(button => button.addEventListener('click', () => this.modal.open(state.addresses.find(address => String(address.id) === String(button.dataset.id))))); this.container.querySelectorAll('[data-address-action="delete"]').forEach(button => button.addEventListener('click', () => this.delete(button.dataset.id))); this.container.querySelectorAll('[data-address-action="default"]').forEach(button => button.addEventListener('click', () => this.setDefault(button.dataset.id))); } async submit(event) { event.preventDefault(); if (this.store.state.saving) return; const id = this.store.state.editingId; const endpoint = id ? this.endpoints.address(id) : this.endpoints.addAddress; const method = id ? 'PUT' : 'POST'; const data = this.modal.values(this.memberId); this.store.setState({ saving: true }); this.modal.clearError(); try { await this.api.request(endpoint, { method, body: JSON.stringify(data) }); this.modal.close(); await this.load(); } catch (error) { this.modal.showError(error.message || 'Address could not be saved.'); } finally { this.store.setState({ saving: false }); } } async delete(id) { if (!confirm('Delete this address?')) return; this.store.setState({ deletingId: id }); try { await this.api.request(this.endpoints.deleteAddress(id), { method: 'POST' }); await this.load(); } catch (error) { alert(error.message || 'Address could not be deleted.'); } finally { this.store.setState({ deletingId: null }); } } async setDefault(id) { this.store.setState({ defaultingId: id }); try { await this.api.request(this.endpoints.defaultAddress(id), { method: 'POST', body: JSON.stringify({ member_id: this.memberId }) }); await this.load(); } catch (error) { alert(error.message || 'Default address could not be updated.'); } finally { this.store.setState({ defaultingId: null }); } } }

class PressStackBillingPage { constructor(config) { this.config = config; this.api = new PressStackAccountApiClient(); this.paymentMethods = new PressStackPaymentMethodManager(this.api, config.endpoints, config.member); this.addresses = new PressStackAddressManager(this.api, config.endpoints, config.member.id); } init() { this.paymentMethods.init(); this.addresses.init(); document.addEventListener('keydown', event => { if (event.key !== 'Escape') return; this.paymentMethods.closeAddModal(); this.paymentMethods.closeRemoveModal(); this.addresses.modal.close(); }); } }

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-press-stack-billing]');
    if (!root) return;
    window.pressStackBillingPage = new PressStackBillingPage({
        member: { id: Number(root.dataset.memberId || 0), name: root.dataset.memberName || '', email: root.dataset.memberEmail || '' },
        endpoints: { stripePublicKey: root.dataset.stripePublicKey || '', paymentMethods: '/press-stack/account/billing/payment-methods', setupIntent: '/press-stack/account/billing/setup-intent', addCard: '/press-stack/account/billing/finalise-setup-intent', removeCard: '/press-stack/account/billing/remove-card', setDefault: '/press-stack/account/billing/set-default', addresses: '/press-stack/account/addresses/search', addAddress: '/press-stack/account/addresses', address: id => `/press-stack/account/addresses/${id}`, deleteAddress: id => `/press-stack/account/addresses/${id}/delete`, defaultAddress: id => `/press-stack/account/addresses/${id}/set-default` },
    });
    window.pressStackBillingPage.init();
});
