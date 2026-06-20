(() => {
    'use strict';

    const runtime = window.SubscriptionAccount;
    const drawer = document.getElementById('subscription-manage-drawer');

    if (!runtime || !drawer) {
        return;
    }

    class SubscriptionManagementController {
        constructor(api, accountState, element) {
            this.api = api;
            this.accountState = accountState;
            this.drawer = element;
            this.title = document.getElementById('subscription-manage-title');
            this.status = document.getElementById('subscription-manage-status');
            this.facts = document.getElementById('subscription-manage-facts');
            this.autoRenewSection = document.getElementById('subscription-auto-renew-section');
            this.autoRenewForm = document.getElementById('subscription-auto-renew-form');
            this.autoRenewToggle = document.getElementById('subscription-auto-renew-toggle');
            this.consentContainer = document.getElementById('subscription-auto-renew-consent');
            this.consentCheckbox = document.getElementById('subscription-auto-renew-consent-checkbox');
            this.autoRenewMessage = document.getElementById('subscription-auto-renew-message');
            this.billingSection = document.getElementById('subscription-billing-date-section');
            this.billingForm = document.getElementById('subscription-billing-date-form');
            this.billingDay = document.getElementById('subscription-billing-day');
            this.billingPreview = document.getElementById('subscription-billing-preview');
            this.billingMessage = document.getElementById('subscription-billing-date-message');
            this.state = {
                subscription: null,
                trigger: null,
                open: false,
                autoRenewStatus: 'idle',
                autoRenewError: null,
                billingStatus: 'idle',
                billingError: null,
                billingPreview: null,
            };

            this.accountState.subscribe(subscription => this.open(subscription));
            this.bindEvents();
        }

        bindEvents() {
            document.addEventListener('click', event => {
                const trigger = event.target.closest('[data-open-subscription-manage]');
                if (trigger) {
                    this.state.trigger = trigger;
                    return;
                }

                if (event.target.closest('[data-close-subscription-manage]') || event.target === this.drawer) {
                    this.close();
                }
            });

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && this.state.open) {
                    this.close();
                }
            });

            this.autoRenewToggle?.addEventListener('change', () => this.renderAutoRenewConsent());
            this.autoRenewForm?.addEventListener('submit', event => this.submitAutoRenew(event));
            this.billingPreview?.addEventListener('click', () => this.previewBillingDate());
            this.billingForm?.addEventListener('submit', event => this.updateBillingDate(event));
        }

        setState(nextState) {
            this.state = { ...this.state, ...nextState };
            this.render();
        }

        open(subscription) {
            if (!subscription?.id) {
                return;
            }

            this.setState({
                subscription,
                open: true,
                autoRenewStatus: 'idle',
                autoRenewError: null,
                billingStatus: 'idle',
                billingError: null,
                billingPreview: null,
            });

            this.drawer.hidden = false;
            this.drawer.classList.add('open');
            document.body.style.overflow = 'hidden';
            this.drawer.querySelector('[data-close-subscription-manage]')?.focus();
        }

        close() {
            this.drawer.classList.remove('open');
            this.drawer.hidden = true;
            document.body.style.overflow = '';
            this.state.trigger?.focus();
            this.setState({
                subscription: null,
                trigger: null,
                open: false,
                billingPreview: null,
            });
        }

        render() {
            const subscription = this.state.subscription;
            if (!subscription) {
                return;
            }

            this.title.textContent = subscription.plan_name || 'Subscription';
            this.status.textContent = subscription.status_label || '';
            this.renderFacts(subscription.facts);
            this.renderAutoRenew(subscription);
            this.renderBilling(subscription);
        }

        renderFacts(subscriptionFacts) {
            this.facts.replaceChildren();

            for (const fact of Array.isArray(subscriptionFacts) ? subscriptionFacts : []) {
                const row = document.createElement('div');
                row.className = 'sub-detail';

                const label = document.createElement('div');
                label.className = 'sub-detail__label';
                label.textContent = fact.label || '';

                const value = document.createElement('div');
                value.className = 'sub-detail__value';
                value.textContent = fact.value || '—';

                row.append(label, value);
                this.facts.append(row);
            }
        }

        renderAutoRenew(subscription) {
            this.autoRenewSection.hidden = !subscription.can_manage_auto_renew;
            this.autoRenewToggle.checked = Boolean(subscription.auto_renew);
            this.renderAutoRenewConsent();

            const loading = this.state.autoRenewStatus === 'saving';
            const text = this.state.autoRenewError || (loading ? 'Saving…' : '');
            this.setMessage(this.autoRenewMessage, text, Boolean(this.state.autoRenewError));

            const submit = this.autoRenewForm?.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = loading;
                submit.textContent = loading ? 'Saving…' : 'Save renewal preference';
            }
        }

        renderAutoRenewConsent() {
            const subscription = this.state.subscription;
            if (!subscription || !this.consentContainer || !this.consentCheckbox) {
                return;
            }

            const enabling = this.autoRenewToggle.checked && !subscription.auto_renew;
            this.consentContainer.hidden = !enabling;

            if (!enabling) {
                this.consentCheckbox.checked = false;
            }
        }

        renderBilling(subscription) {
            if (!this.billingSection || !this.billingForm || !this.billingDay) {
                return;
            }

            this.billingSection.hidden = !subscription.can_manage_billing_date;
            this.billingDay.value = String(subscription.billing_day_of_month || 1);

            if (this.state.billingPreview) {
                this.renderBillingPreview(this.state.billingPreview);
            } else {
                const loading = ['previewing', 'saving'].includes(this.state.billingStatus);
                const text = this.state.billingError
                    || (this.state.billingStatus === 'previewing' ? 'Loading billing preview…' : '')
                    || (this.state.billingStatus === 'saving' ? 'Saving…' : '');
                this.setMessage(this.billingMessage, text, Boolean(this.state.billingError));
                this.billingPreview.disabled = loading;

                const submit = this.billingForm.querySelector('button[type="submit"]');
                if (submit) {
                    submit.disabled = loading;
                    submit.textContent = this.state.billingStatus === 'saving' ? 'Saving…' : 'Update billing date';
                }
            }
        }

        async submitAutoRenew(event) {
            event.preventDefault();

            const subscription = this.state.subscription;
            if (!subscription?.auto_renew_endpoint || this.state.autoRenewStatus === 'saving') {
                return;
            }

            const enabling = this.autoRenewToggle.checked;
            const requiresConsent = enabling && !subscription.auto_renew;
            const consentGiven = this.consentCheckbox.checked;

            if (requiresConsent && !consentGiven) {
                this.setState({
                    autoRenewStatus: 'error',
                    autoRenewError: 'Please confirm consent before enabling automatic renewal.',
                });
                this.consentCheckbox.focus();
                return;
            }

            this.setState({ autoRenewStatus: 'saving', autoRenewError: null });

            try {
                const result = await this.api.post(subscription.auto_renew_endpoint, {
                    auto_renew: enabling,
                    consent_given: requiresConsent && consentGiven,
                });

                const updatedSubscription = {
                    ...subscription,
                    auto_renew: Boolean(result.auto_renew),
                };

                if (this.state.trigger) {
                    this.state.trigger.dataset.subscriptionManage = JSON.stringify(updatedSubscription);
                }

                this.setState({
                    subscription: updatedSubscription,
                    autoRenewStatus: 'success',
                    autoRenewError: null,
                });
                this.setMessage(this.autoRenewMessage, result.message || 'Renewal preference updated.');
                this.consentCheckbox.checked = false;
            } catch (error) {
                this.setState({
                    autoRenewStatus: 'error',
                    autoRenewError: error.message || 'Failed to update automatic renewal.',
                });
            }
        }

        async previewBillingDate() {
            const subscription = this.state.subscription;
            if (!subscription?.billing_date_preview_endpoint || this.state.billingStatus === 'previewing') {
                return;
            }

            this.setState({ billingStatus: 'previewing', billingError: null, billingPreview: null });

            try {
                const result = await this.api.post(subscription.billing_date_preview_endpoint, {
                    day_of_month: this.billingDay.value,
                });
                this.setState({
                    billingStatus: 'ready',
                    billingPreview: result.preview || {},
                });
            } catch (error) {
                this.setState({
                    billingStatus: 'error',
                    billingError: error.message || 'Failed to preview billing date change.',
                });
            }
        }

        async updateBillingDate(event) {
            event.preventDefault();

            const subscription = this.state.subscription;
            if (!subscription?.billing_date_update_endpoint || this.state.billingStatus === 'saving') {
                return;
            }

            this.setState({ billingStatus: 'saving', billingError: null });

            try {
                const result = await this.api.post(subscription.billing_date_update_endpoint, {
                    day_of_month: this.billingDay.value,
                });

                const updatedSubscription = {
                    ...subscription,
                    billing_day_of_month: Number(this.billingDay.value),
                };

                if (this.state.trigger) {
                    this.state.trigger.dataset.subscriptionManage = JSON.stringify(updatedSubscription);
                }

                this.setState({
                    subscription: updatedSubscription,
                    billingStatus: 'success',
                    billingPreview: null,
                });
                this.setMessage(this.billingMessage, result.message || 'Billing date updated successfully.');
            } catch (error) {
                this.setState({
                    billingStatus: 'error',
                    billingError: error.message || 'Failed to update billing date.',
                });
            }
        }

        renderBillingPreview(preview) {
            this.billingMessage.replaceChildren();
            this.billingMessage.classList.add('is-visible');
            this.billingMessage.classList.remove('is-error');

            const panel = document.createElement('div');
            panel.className = 'billing-preview';

            this.appendPreviewItem(panel, 'Current renewal date', this.formatDate(preview.current_period_end));
            this.appendPreviewItem(panel, 'Proposed renewal date', this.formatDate(preview.new_billing_date));

            const daysDifference = Number(preview.days_difference || 0);
            const daysText = daysDifference === 0
                ? 'No change'
                : `${Math.abs(daysDifference)} day${Math.abs(daysDifference) === 1 ? '' : 's'} ${daysDifference > 0 ? 'later' : 'earlier'}`;
            this.appendPreviewItem(panel, 'Schedule change', daysText);

            const amount = Math.abs(Number(preview.proration_amount || 0));
            const isCredit = Boolean(preview.is_credit);
            const adjustmentText = amount === 0
                ? 'No estimated adjustment'
                : `${isCredit ? 'Estimated credit' : 'Estimated charge'}: £${amount.toFixed(2)}`;
            this.appendPreviewItem(
                panel,
                'Estimated billing adjustment',
                adjustmentText,
                true,
                amount === 0 ? '' : (isCredit ? 'is-credit' : 'is-charge'),
            );

            this.billingMessage.append(panel);
        }

        appendPreviewItem(panel, labelText, valueText, full = false, valueClass = '') {
            const item = document.createElement('div');
            item.className = `billing-preview__item${full ? ' billing-preview__item--full' : ''}`;

            const label = document.createElement('span');
            label.className = 'billing-preview__label';
            label.textContent = labelText;

            const value = document.createElement('strong');
            value.className = `billing-preview__value${valueClass ? ` ${valueClass}` : ''}`;
            value.textContent = valueText;

            item.append(label, value);
            panel.append(item);
        }

        formatDate(value) {
            if (!value) {
                return '—';
            }

            const date = new Date(`${value}T00:00:00`);

            return Number.isNaN(date.getTime())
                ? value
                : date.toLocaleDateString(undefined, {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                });
        }

        setMessage(element, text, error = false) {
            if (!element) {
                return;
            }

            element.textContent = text;
            element.classList.toggle('is-visible', Boolean(text));
            element.classList.toggle('is-error', error);
        }
    }

    new SubscriptionManagementController(runtime.api, runtime.state, drawer);
})();
