(() => {
    'use strict';

    const runtime = window.SubscriptionAccount;
    const drawer = document.getElementById('subscription-manage-drawer');

    if (!runtime || !drawer) {
        return;
    }

    class SubscriptionPaymentMethodController {
        constructor(api, accountState) {
            this.api = api;
            this.accountState = accountState;

            this.section = document.getElementById('subscription-payment-method-section');
            this.form = document.getElementById('subscription-payment-method-form');
            this.currentEl = document.getElementById('subscription-payment-method-current');
            this.select = document.getElementById('subscription-payment-method-select');
            this.addNewLink = document.getElementById('subscription-payment-method-add-new');
            this.submitBtn = document.getElementById('subscription-payment-method-submit');
            this.messageEl = document.getElementById('subscription-payment-method-message');

            this.state = {
                subscription: null,
                paymentMethods: [],
                loading: false,
                submitting: false,
            };

            this.accountState.subscribe(subscription => this.open(subscription));
            this.bindEvents();
        }

        bindEvents() {
            this.form?.addEventListener('submit', event => this.submit(event));

            this.addNewLink?.addEventListener('click', event => {
                const paymentMethodsPageUrl = this.state.subscription?.payment_methods_page_url;
                if (!paymentMethodsPageUrl) {
                    event.preventDefault();
                    return;
                }
                this.addNewLink.href = paymentMethodsPageUrl;
            });
        }

        open(subscription) {
            this.setState({ subscription });

            if (!this.section) {
                return;
            }

            this.section.hidden = !subscription?.payment_method_endpoint;

            if (subscription?.payment_methods_list_endpoint) {
                this.loadPaymentMethods(subscription.payment_methods_list_endpoint);
            }
        }

        setState(patch) {
            Object.assign(this.state, patch);
            this.render();
        }

        async loadPaymentMethods(listEndpoint) {
            this.setState({ loading: true });

            try {
                const result = await this.api.get(listEndpoint);
                this.setState({ loading: false, paymentMethods: result.payment_methods ?? [] });
            } catch {
                this.setState({ loading: false, paymentMethods: [] });
            }
        }

        render() {
            this.renderCurrent();
            this.renderOptions();
            this.renderSubmitState();
        }

        renderCurrent() {
            if (!this.currentEl) return;

            const subscription = this.state.subscription;
            const current = this.state.paymentMethods.find(pm =>
                Array.isArray(pm.subscription_ids) && pm.subscription_ids.includes(subscription?.id));

            this.currentEl.textContent = current
                ? `Currently paying with ${this.escape(current.brand ?? 'card')} ···· ${this.escape(current.last4 ?? '')}`
                : 'No payment method on file for this subscription yet.';
        }

        renderOptions() {
            if (!this.select) return;

            if (this.state.loading) {
                this.select.innerHTML = '<option value="">Loading saved cards…</option>';
                this.select.disabled = true;
                return;
            }

            const subscription = this.state.subscription;
            const methods = this.state.paymentMethods;

            if (!methods.length) {
                this.select.innerHTML = '<option value="">No saved cards available</option>';
                this.select.disabled = true;
                return;
            }

            this.select.disabled = false;
            this.select.innerHTML = methods.map(pm => {
                const unusable = pm.status === 'expired';
                const isCurrent = Array.isArray(pm.subscription_ids) && pm.subscription_ids.includes(subscription?.id);
                const label = `${pm.brand ?? 'Card'} ···· ${pm.last4 ?? ''} (exp ${pm.exp_month ?? '--'}/${pm.exp_year ?? '--'})`
                    + (unusable ? ' - expired, cannot be used' : '')
                    + (isCurrent ? ' - current' : '');

                return `<option value="${this.escape(pm.id)}" ${unusable ? 'disabled' : ''} ${isCurrent ? 'selected' : ''}>${this.escape(label)}</option>`;
            }).join('');
        }

        renderSubmitState() {
            if (!this.submitBtn) return;
            this.submitBtn.disabled = this.state.submitting;
            this.submitBtn.textContent = this.state.submitting ? 'Saving…' : 'Save payment method';
        }

        async submit(event) {
            event.preventDefault();

            const subscription = this.state.subscription;
            const paymentMethodId = this.select?.value;

            if (!subscription?.payment_method_endpoint || !paymentMethodId || this.state.submitting) {
                return;
            }

            this.setState({ submitting: true });
            this.setMessage('');

            try {
                await this.api.post(subscription.payment_method_endpoint, { payment_method_id: paymentMethodId });
                this.setMessage('Payment method updated for this subscription.', false);

                if (subscription.payment_methods_list_endpoint) {
                    await this.loadPaymentMethods(subscription.payment_methods_list_endpoint);
                }
            } catch (err) {
                this.setMessage(err.message || 'Failed to update payment method.', true);
            } finally {
                this.setState({ submitting: false });
            }
        }

        setMessage(text, error = false) {
            if (!this.messageEl) return;
            this.messageEl.textContent = text;
            this.messageEl.classList.toggle('is-visible', Boolean(text));
            this.messageEl.classList.toggle('is-error', error);
        }

        escape(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    }

    new SubscriptionPaymentMethodController(runtime.api, runtime.state);
})();
