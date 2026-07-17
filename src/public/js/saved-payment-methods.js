/**
 * SavedPaymentMethodsPanel
 *
 * Shared, class/state-based controller for the saved-payment-methods panel
 * markup in shared/billing/_payment_methods_panel.php. Used by BOTH:
 *   - PressStack account area (subscriptions/account/billing.php)
 *   - site-scoped member area (member/subscriptions/payment-methods.php)
 *
 * The including page must set `window.SavedPaymentMethodsConfig` before
 * this script loads:
 *
 *   window.SavedPaymentMethodsConfig = {
 *     stripePublicKey: '...',
 *     memberName: '...',
 *     memberEmail: '...',
 *     endpoints: {
 *       list: '/api/{site}/member/payment-methods',
 *       setupIntent: '/{site}/member/payment-methods/setup-intent',
 *       store: '/{site}/member/payment-methods',
 *       setDefault: (id) => `/{site}/member/payment-methods/${id}/set-default`,
 *       remove: (id) => `/{site}/member/payment-methods/${id}`,
 *       removeMethod: 'DELETE', // or 'POST' for PressStack's body-based remove-card endpoint
 *       replace: (id) => `/{site}/member/payment-methods/${id}/update`,
 *     },
 *     root: document.querySelector('[data-spm-panel]'), // optional, defaults to first on page
 *   };
 */
class SavedPaymentMethodsPanel {
    static EXPIRY_WARNING_MONTHS = 2;
    static NETWORK_COLORS = {
        visa: '#1a1f71',
        mastercard: '#eb001b',
        amex: '#2e77bc',
        american_express: '#2e77bc',
        discover: '#ff6000',
        diners: '#0079be',
        jcb: '#0e4c96',
        unionpay: '#e21836',
    };

    #stripe = null;
    #addElements = null;
    #addCardElement = null;
    #updateElements = null;
    #updateCardElement = null;

    #config;
    #root;
    #els = {};

    #state = {
        paymentMethods: [],
        loading: true,
        submittingAdd: false,
        submittingUpdate: false,
        submittingRemove: false,
        submittingDefault: null,
        pendingRemoveId: null,
        pendingUpdatePaymentMethod: null,
    };

    constructor(config) {
        this.#config = config;
        this.#root = config.root || document.querySelector('[data-spm-panel]');

        if (!this.#root) {
            console.error('SavedPaymentMethodsPanel: no [data-spm-panel] element found.');
            return;
        }

        this.#bindElements();
        this.#attachListeners();
        this.#initStripe();
        this.#render();
        this.#loadPaymentMethods();
    }

    #bindElements() {
        const scope = document; // modals render at document level via fixed overlays

        this.#els.warnings = this.#root.querySelector('[data-spm-warnings]');
        this.#els.list = this.#root.querySelector('[data-spm-list]');
        this.#els.openAdd = this.#root.querySelectorAll('[data-spm-open-add]');

        this.#els.modals = {
            add: scope.querySelector('[data-spm-modal="add"]'),
            update: scope.querySelector('[data-spm-modal="update"]'),
            remove: scope.querySelector('[data-spm-modal="remove"]'),
        };
        this.#els.errors = {
            add: scope.querySelector('[data-spm-error="add"]'),
            update: scope.querySelector('[data-spm-error="update"]'),
            remove: scope.querySelector('[data-spm-error="remove"]'),
        };
        this.#els.submitButtons = {
            add: scope.querySelector('[data-spm-submit="add"]'),
            update: scope.querySelector('[data-spm-submit="update"]'),
            remove: scope.querySelector('[data-spm-submit="remove"]'),
        };
        this.#els.setDefaultCheckboxes = {
            add: scope.querySelector('[data-spm-set-default="add"]'),
            update: scope.querySelector('[data-spm-set-default="update"]'),
        };
        this.#els.nameOnCardInputs = {
            add: scope.querySelector('[data-spm-name-on-card="add"]'),
            update: scope.querySelector('[data-spm-name-on-card="update"]'),
        };
        this.#els.updateCurrentCard = scope.querySelector('[data-spm-update-current-card]');
    }

    #attachListeners() {
        this.#els.openAdd.forEach(btn => btn.addEventListener('click', () => this.#openModal('add')));

        document.querySelectorAll('[data-spm-close]').forEach(el =>
            el.addEventListener('click', () => this.#closeModal(el.dataset.spmClose)));

        this.#els.submitButtons.add?.addEventListener('click', () => this.#submitAddCard());
        this.#els.submitButtons.update?.addEventListener('click', () => this.#submitUpdateCard());
        this.#els.submitButtons.remove?.addEventListener('click', () => this.#confirmRemoveCard());

        Object.values(this.#els.modals).forEach(overlay => overlay?.addEventListener('click', e => {
            if (e.target === overlay) this.#closeAllModals();
        }));

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') this.#closeAllModals();
        });
    }

    #initStripe() {
        if (typeof Stripe === 'undefined' || !this.#config.stripePublicKey) {
            console.error('Stripe.js failed to load or no publishable key configured.');
            return;
        }

        this.#stripe = Stripe(this.#config.stripePublicKey);

        const style = {
            base: { fontSize: '16px', color: '#1f2937', '::placeholder': { color: '#9ca3af' } },
            invalid: { color: '#ef4444', iconColor: '#ef4444' },
        };

        this.#addElements = this.#stripe.elements();
        this.#addCardElement = this.#addElements.create('card', { hidePostalCode: true, style });
        this.#addCardElement.on('change', e => this.#toggleError('add', e.error?.message));

        this.#updateElements = this.#stripe.elements();
        this.#updateCardElement = this.#updateElements.create('card', { hidePostalCode: true, style });
        this.#updateCardElement.on('change', e => this.#toggleError('update', e.error?.message));
    }

    async #loadPaymentMethods() {
        this.#setState({ loading: true });

        try {
            const res = await this.#apiFetch(this.#config.endpoints.list);
            const data = await res.json();
            this.#setState({ loading: false, paymentMethods: data.success === false ? [] : (data.payment_methods ?? []) });
        } catch {
            this.#setState({ loading: false });
        }
    }

    #setState(patch) {
        Object.assign(this.#state, patch);
        this.#render();
    }

    #render() {
        this.#renderList();
        this.#renderWarnings();
        this.#renderSubmitButtons();
    }

    // -- Status (rendering only - the status value itself is supplied by
    //    the backend on every payment method payload; see
    //    SavedPaymentMethodsController::payloads()) -----------------------

    #statusBadge(status) {
        if (status === 'expired') return '<span class="spm-badge spm-badge-expired">Expired</span>';
        if (status === 'expiring_soon') return '<span class="spm-badge spm-badge-expiring">Expiring soon</span>';
        return '<span class="spm-badge spm-badge-active">Active</span>';
    }

    #renderWarnings() {
        if (!this.#els.warnings) return;

        const flagged = this.#state.paymentMethods.filter(pm => pm.status && pm.status !== 'active');

        if (!flagged.length) {
            this.#els.warnings.innerHTML = '';
            return;
        }

        this.#els.warnings.innerHTML = flagged.map(pm => `
            <div class="spm-alert ${pm.status === 'expired' ? 'spm-alert-error' : 'spm-alert-warning'}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <div>
                    <strong>${pm.status === 'expired' ? 'Card expired' : 'Card expiring soon'}</strong>
                    <p style="margin-top:4px;">
                        ${this.#escape(pm.brand ?? 'Card')} &middot;&middot;&middot;&middot; ${this.#escape(pm.last4 ?? '')}
                        (exp ${this.#escape(String(pm.exp_month ?? '--'))}/${this.#escape(String(pm.exp_year ?? '--'))}).
                        Please update this payment method to avoid subscription interruption.
                    </p>
                </div>
            </div>
        `).join('');
    }

    #renderList() {
        if (!this.#els.list) return;

        if (this.#state.loading) {
            this.#els.list.innerHTML = `<div class="spm-skeleton"><div class="spm-skeleton-row"></div><div class="spm-skeleton-row"></div></div>`;
            return;
        }

        const methods = this.#state.paymentMethods;

        if (!methods.length) {
            this.#els.list.innerHTML = this.#emptyHtml();
            this.#els.list.querySelector('[data-spm-open-add-inline]')?.addEventListener('click', () => this.#openModal('add'));
            return;
        }

        this.#els.list.innerHTML = `<div class="spm-grid">${methods.map(pm => this.#cardHtml(pm)).join('')}</div>`;

        this.#els.list.querySelectorAll('[data-action="set-default"]').forEach(btn =>
            btn.addEventListener('click', () => this.#setDefault(btn.dataset.id)));
        this.#els.list.querySelectorAll('[data-action="update"]').forEach(btn =>
            btn.addEventListener('click', () => this.#openUpdateCard(btn.dataset.id)));
        this.#els.list.querySelectorAll('[data-action="remove"]').forEach(btn =>
            btn.addEventListener('click', () => this.#openRemoveCard(btn.dataset.id)));
    }

    #emptyHtml() {
        return `
            <div class="spm-empty">
                <div class="spm-empty-icon">💳</div>
                <div class="spm-empty-title">No payment methods saved</div>
                <div class="spm-empty-sub">Add a payment method to manage your subscriptions.</div>
                <button type="button" class="spm-btn spm-btn-primary" data-spm-open-add-inline>Add payment method</button>
            </div>
        `;
    }

    #networkBadgeHtml(brand) {
        const key = String(brand ?? '').toLowerCase().replace(/\s+/g, '_');
        const color = SavedPaymentMethodsPanel.NETWORK_COLORS[key] ?? '#4b5563';
        const label = key === 'american_express' ? 'AMEX' : (brand ?? 'CARD').toString().slice(0, 4).toUpperCase();

        return `<div class="spm-network-badge" style="background:${color};">${this.#escape(label)}</div>`;
    }

    #usageCopy(pm) {
        const count = Number(pm.subscription_count ?? 0);

        if (count === 0) {
            return 'Not paying for any subscription';
        }

        return count === 1 ? 'Pays for 1 subscription' : `Pays for ${count} subscriptions`;
    }

    #cardHtml(pm) {
        const status = pm.status ?? 'active';
        const isDefault = !!pm.is_default;
        const isBusyDefault = this.#state.submittingDefault === pm.id;
        const inUse = pm.in_use === true || pm.can_remove === false;
        const removable = !inUse;

        const badges = [
            isDefault ? '<span class="spm-badge spm-badge-default">Default</span>' : '',
            this.#statusBadge(status),
            inUse ? '<span class="spm-badge spm-badge-inuse">In use</span>' : '',
        ].filter(Boolean).join('');

        const defaultBtn = (!isDefault && status !== 'expired')
            ? `<button type="button" class="spm-btn spm-btn-secondary spm-btn-sm" data-action="set-default" data-id="${this.#escape(pm.id)}" ${isBusyDefault ? 'disabled' : ''}>${isBusyDefault ? 'Saving…' : 'Set as default'}</button>`
            : '';

        const updateBtn = `<button type="button" class="spm-btn spm-btn-secondary spm-btn-sm" data-action="update" data-id="${this.#escape(pm.id)}">Update payment method</button>`;

        const removeTitle = inUse ? 'Move the subscription(s) on this card to another payment method before removing it.' : '';
        const removeBtn = `<button type="button" class="spm-btn spm-btn-danger spm-btn-sm" data-action="remove" data-id="${this.#escape(pm.id)}" ${!removable ? `disabled title="${this.#escape(removeTitle)}"` : ''}>Remove</button>`;

        return `
            <div class="spm-card ${isDefault ? 'is-default' : ''}">
                <div class="spm-card-main">
                    ${this.#networkBadgeHtml(pm.brand)}
                    <div class="spm-card-info">
                        <div class="spm-card-brand">${this.#escape(pm.brand ?? 'Card')} &middot;&middot;&middot;&middot; ${this.#escape(pm.last4 ?? '????')}</div>
                        <div class="spm-card-details">Expires ${this.#escape(String(pm.exp_month ?? '--'))}/${this.#escape(String(pm.exp_year ?? '--'))}</div>
                        <div class="spm-card-usage">${this.#escape(this.#usageCopy(pm))}</div>
                        <div class="spm-badge-row">${badges}</div>
                    </div>
                </div>
                <div class="spm-card-actions">
                    ${defaultBtn}
                    ${updateBtn}
                    ${removeBtn}
                </div>
            </div>
        `;
    }

    #renderSubmitButtons() {
        const add = this.#els.submitButtons.add;
        if (add) {
            add.disabled = this.#state.submittingAdd;
            add.textContent = this.#state.submittingAdd ? 'Processing…' : 'Add Payment Method';
        }

        const update = this.#els.submitButtons.update;
        if (update) {
            update.disabled = this.#state.submittingUpdate;
            update.textContent = this.#state.submittingUpdate ? 'Processing…' : 'Update Card';
        }

        const remove = this.#els.submitButtons.remove;
        if (remove) {
            remove.disabled = this.#state.submittingRemove;
            remove.textContent = this.#state.submittingRemove ? 'Removing…' : 'Remove Card';
        }
    }

    // -- Add card (SetupIntent flow) -------------------------------------

    #openModal(name) {
        if (name === 'add') {
            this.#clearError('add');
            this.#addCardElement?.clear();
            if (this.#els.setDefaultCheckboxes.add) this.#els.setDefaultCheckboxes.add.checked = true;
            if (this.#els.nameOnCardInputs.add) this.#els.nameOnCardInputs.add.value = this.#config.memberName || '';
            this.#els.modals.add?.classList.add('show');
            this.#addCardElement?.mount(document.querySelector('[data-spm-card-element="add"]'));
        }
        document.body.style.overflow = 'hidden';
    }

    #closeModal(name) {
        this.#els.modals[name]?.classList.remove('show');
        document.body.style.overflow = '';
        this.#clearError(name);

        if (name === 'update') this.#setState({ pendingUpdatePaymentMethod: null });
        if (name === 'remove') this.#setState({ pendingRemoveId: null });
    }

    #closeAllModals() {
        this.#closeModal('add');
        this.#closeModal('update');
        this.#closeModal('remove');
    }

    async #submitAddCard() {
        if (this.#state.submittingAdd || !this.#stripe) return;

        const nameOnCard = (this.#els.nameOnCardInputs.add?.value || '').trim();
        if (!nameOnCard) {
            this.#toggleError('add', 'Name on card is required.');
            this.#els.nameOnCardInputs.add?.focus();
            return;
        }

        this.#setState({ submittingAdd: true });
        this.#clearError('add');

        try {
            const intentRes = await this.#apiFetch(this.#config.endpoints.setupIntent, { method: 'POST' });
            const intentData = await intentRes.json();

            if (!intentData.success || !intentData.client_secret) {
                throw new Error(intentData.message || 'Unable to initialise card setup.');
            }

            const result = await this.#stripe.confirmCardSetup(intentData.client_secret, {
                payment_method: {
                    card: this.#addCardElement,
                    billing_details: {
                        name: nameOnCard,
                        email: this.#config.memberEmail || '',
                    },
                },
            });

            if (result.error) throw new Error(result.error.message);

            const setDefault = this.#els.setDefaultCheckboxes.add?.checked ?? true;

            const storeRes = await this.#apiFetch(this.#config.endpoints.store, {
                method: 'POST',
                body: JSON.stringify({
                    setup_intent_id: result.setupIntent.id,
                    set_default: setDefault,
                    name_on_card: nameOnCard,
                }),
            });
            const storeData = await storeRes.json();

            if (!storeData.success) throw new Error(storeData.message || 'Failed to add payment method.');

            this.#closeModal('add');
            await this.#loadPaymentMethods();
        } catch (err) {
            this.#toggleError('add', err.message || 'An unexpected error occurred.');
        } finally {
            this.#setState({ submittingAdd: false });
        }
    }

    // -- Update (replace) card -------------------------------------------

    #openUpdateCard(paymentMethodId) {
        const pm = this.#state.paymentMethods.find(p => String(p.id) === String(paymentMethodId));
        if (!pm) return;

        this.#setState({ pendingUpdatePaymentMethod: pm });
        this.#clearError('update');
        this.#updateCardElement?.clear();
        if (this.#els.setDefaultCheckboxes.update) this.#els.setDefaultCheckboxes.update.checked = false;
        if (this.#els.nameOnCardInputs.update) this.#els.nameOnCardInputs.update.value = this.#config.memberName || '';
        if (this.#els.updateCurrentCard) this.#els.updateCurrentCard.textContent = `${pm.brand ?? 'Card'} ···· ${pm.last4 ?? ''}`;
        this.#els.modals.update?.classList.add('show');
        this.#updateCardElement?.mount(document.querySelector('[data-spm-card-element="update"]'));
        document.body.style.overflow = 'hidden';
    }

    async #submitUpdateCard() {
        const pending = this.#state.pendingUpdatePaymentMethod;
        if (!pending || this.#state.submittingUpdate || !this.#stripe) return;

        const nameOnCard = (this.#els.nameOnCardInputs.update?.value || '').trim();
        if (!nameOnCard) {
            this.#toggleError('update', 'Name on card is required.');
            this.#els.nameOnCardInputs.update?.focus();
            return;
        }

        this.#setState({ submittingUpdate: true });
        this.#clearError('update');

        try {
            const intentRes = await this.#apiFetch(this.#config.endpoints.setupIntent, { method: 'POST' });
            const intentData = await intentRes.json();

            if (!intentData.success || !intentData.client_secret) {
                throw new Error(intentData.message || 'Unable to initialise card setup.');
            }

            const result = await this.#stripe.confirmCardSetup(intentData.client_secret, {
                payment_method: {
                    card: this.#updateCardElement,
                    billing_details: {
                        name: nameOnCard,
                        email: this.#config.memberEmail || '',
                    },
                },
            });

            if (result.error) throw new Error(result.error.message);

            const setDefault = this.#els.setDefaultCheckboxes.update?.checked ?? false;

            const res = await this.#apiFetch(this.#config.endpoints.replace(pending.id), {
                method: 'POST',
                body: JSON.stringify({
                    setup_intent_id: result.setupIntent.id,
                    set_default: setDefault,
                    name_on_card: nameOnCard,
                }),
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.message || 'Failed to update payment method.');

            this.#closeModal('update');
            await this.#loadPaymentMethods();
        } catch (err) {
            this.#toggleError('update', err.message || 'An unexpected error occurred.');
        } finally {
            this.#setState({ submittingUpdate: false });
        }
    }

    // -- Remove card ------------------------------------------------------

    #openRemoveCard(paymentMethodId) {
        this.#setState({ pendingRemoveId: paymentMethodId });
        this.#clearError('remove');
        this.#els.modals.remove?.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    async #confirmRemoveCard() {
        if (this.#state.submittingRemove || !this.#state.pendingRemoveId) return;
        this.#setState({ submittingRemove: true });

        try {
            const id = this.#state.pendingRemoveId;
            const method = this.#config.endpoints.removeMethod || 'DELETE';
            const url = typeof this.#config.endpoints.remove === 'function' ? this.#config.endpoints.remove(id) : this.#config.endpoints.remove;
            const body = method === 'POST' ? JSON.stringify({ payment_method_id: id }) : undefined;

            const res = await this.#apiFetch(url, { method, body });
            const data = await res.json();

            if (!data.success) throw new Error(data.message || 'Failed to remove payment method.');

            this.#closeModal('remove');
            await this.#loadPaymentMethods();
        } catch (err) {
            this.#toggleError('remove', err.message || 'Failed to remove payment method.');
        } finally {
            this.#setState({ submittingRemove: false });
        }
    }

    // -- Default ------------------------------------------------------------

    async #setDefault(paymentMethodId) {
        if (this.#state.submittingDefault) return;
        this.#setState({ submittingDefault: paymentMethodId });

        try {
            const url = typeof this.#config.endpoints.setDefault === 'function'
                ? this.#config.endpoints.setDefault(paymentMethodId)
                : this.#config.endpoints.setDefault;
            const isBodyBased = typeof this.#config.endpoints.setDefault !== 'function';

            const res = await this.#apiFetch(url, {
                method: 'POST',
                body: isBodyBased ? JSON.stringify({ payment_method_id: paymentMethodId }) : undefined,
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.message || 'Failed to update default payment method.');

            await this.#loadPaymentMethods();
        } catch (err) {
            alert(err.message || 'Failed to update default payment method.');
        } finally {
            this.#setState({ submittingDefault: null });
        }
    }

    // -- Shared helpers -------------------------------------------------------

    #toggleError(name, message) {
        const el = this.#els.errors[name];
        if (!el) return;

        if (message) {
            el.textContent = message;
            el.style.display = 'block';
        } else {
            this.#clearError(name);
        }
    }

    #clearError(name) {
        const el = this.#els.errors[name];
        if (!el) return;
        el.style.display = 'none';
        el.textContent = '';
    }

    async #apiFetch(url, options = {}) {
        return fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                // ADD THIS LINE:
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                ...(options.headers ?? {}),
            },
        });
    }

    #escape(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.SavedPaymentMethodsConfig) {
        window.savedPaymentMethodsPanel = new SavedPaymentMethodsPanel(window.SavedPaymentMethodsConfig);
    }
});
