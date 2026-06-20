(() => {
    'use strict';

    const runtime = window.SubscriptionAccount;
    const section = document.getElementById('subscription-upgrade-section');
    const optionsContainer = document.getElementById('subscription-upgrade-options');
    const previewContainer = document.getElementById('subscription-upgrade-preview');
    const message = document.getElementById('subscription-upgrade-message');

    if (!runtime || !section || !optionsContainer || !previewContainer || !message) {
        return;
    }

    class SubscriptionUpgradeController {
        constructor(api, state, elements) {
            this.api = api;
            this.accountState = state;
            this.section = elements.section;
            this.optionsContainer = elements.optionsContainer;
            this.previewContainer = elements.previewContainer;
            this.message = elements.message;
            this.state = {
                subscription: null,
                status: 'idle',
                upgrade: null,
                preview: null,
                selectedPlanId: null,
                error: null,
                success: null,
            };

            this.accountState.subscribe(subscription => this.onSubscriptionChanged(subscription));
            this.section.addEventListener('click', event => this.handleClick(event));
        }

        setState(nextState) {
            this.state = { ...this.state, ...nextState };
            this.render();
        }

        render() {
            const messages = {
                loading: 'Loading upgrade options…',
                previewing: 'Loading upgrade preview…',
                submitting: 'Applying upgrade…',
            };
            const text = this.state.error
                || this.state.success
                || messages[this.state.status]
                || '';

            this.message.textContent = text;
            this.message.classList.toggle('is-visible', Boolean(text));
            this.message.classList.toggle('is-error', Boolean(this.state.error));

            this.renderOptions();
            this.renderPreview();
        }

        renderOptions() {
            this.optionsContainer.replaceChildren();

            const upgrade = this.state.upgrade;
            if (!upgrade || this.state.status === 'loading') {
                return;
            }

            if (!upgrade.can_upgrade || !upgrade.options?.length) {
                if (!this.state.error) {
                    this.message.textContent = upgrade.reason || 'No upgrade options are currently available.';
                    this.message.classList.add('is-visible');
                }
                return;
            }

            for (const option of upgrade.options) {
                this.optionsContainer.append(this.buildOption(option));
            }
        }

        buildOption(option) {
            const card = document.createElement('article');
            card.className = 'subscription-upgrade-option';

            const heading = document.createElement('h4');
            heading.textContent = option.plan_name;

            const description = document.createElement('p');
            description.textContent = option.description || '';

            const price = document.createElement('strong');
            price.className = 'subscription-upgrade-option__price';
            price.textContent = `${this.money(option.new_total_price)} / ${option.billing_period || 'term'}`;

            const difference = document.createElement('span');
            difference.className = 'subscription-upgrade-option__difference';
            difference.textContent = `Estimated change today: ${this.money(option.price_difference)}`;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn--ghost btn--sm';
            button.textContent = 'Review upgrade';
            button.dataset.upgradePlanId = option.plan_id;
            button.disabled = this.state.status === 'previewing' || this.state.status === 'submitting';

            card.append(
                heading,
                description,
                price,
                difference,
                this.buildFeatureList(option.features),
                button,
            );

            return card;
        }

        renderPreview() {
            this.previewContainer.replaceChildren();
            this.previewContainer.hidden = !this.state.preview;

            const preview = this.state.preview;
            if (!preview) {
                return;
            }

            const title = document.createElement('h4');
            title.textContent = `${preview.current_plan?.name || 'Current plan'} → ${preview.upgrade_plan?.name || 'Upgrade plan'}`;

            const charge = document.createElement('p');
            charge.textContent = `Estimated immediate charge: ${this.money(preview.pricing?.immediate_charge)}`;

            const ongoing = document.createElement('p');
            ongoing.textContent = `New plan price: ${this.money(preview.pricing?.upgrade_price)}`;

            const note = document.createElement('p');
            note.className = 'subscription-upgrade-preview__note';
            note.textContent = preview.pricing?.estimate_note
                || 'The final charge will be confirmed before the upgrade is applied.';

            const confirm = document.createElement('button');
            confirm.type = 'button';
            confirm.className = 'btn btn--gold btn--sm';
            confirm.textContent = this.state.status === 'submitting' ? 'Applying…' : 'Confirm upgrade';
            confirm.dataset.confirmUpgradePlanId = preview.upgrade_plan?.id || '';
            confirm.disabled = this.state.status === 'submitting';

            this.previewContainer.append(title, charge, ongoing, note, confirm);
        }

        buildFeatureList(features) {
            const list = document.createElement('ul');
            list.className = 'subscription-upgrade-features';

            for (const feature of Array.isArray(features) ? features : []) {
                const item = document.createElement('li');
                item.textContent = feature;
                list.append(item);
            }

            return list;
        }

        money(value) {
            return `£${Number(value || 0).toFixed(2)}`;
        }

        async onSubscriptionChanged(subscription) {
            this.setState({
                subscription,
                status: 'idle',
                upgrade: null,
                preview: null,
                selectedPlanId: null,
                error: null,
                success: null,
            });

            if (!subscription?.can_upgrade || !subscription.upgrade_options_endpoint) {
                this.section.hidden = true;
                return;
            }

            this.section.hidden = false;
            await this.loadOptions();
        }

        async loadOptions() {
            const subscription = this.state.subscription;
            if (!subscription?.upgrade_options_endpoint) {
                return;
            }

            this.setState({ status: 'loading', error: null, success: null });

            try {
                const result = await this.api.get(subscription.upgrade_options_endpoint);
                this.setState({
                    status: 'ready',
                    upgrade: result.upgrade || {},
                    preview: null,
                });
            } catch (error) {
                this.setState({
                    status: 'error',
                    error: error.message || 'Failed to load upgrade options.',
                });
            }
        }

        async handleClick(event) {
            const previewButton = event.target.closest('[data-upgrade-plan-id]');
            if (previewButton) {
                await this.loadPreview(previewButton.dataset.upgradePlanId);
                return;
            }

            const confirmButton = event.target.closest('[data-confirm-upgrade-plan-id]');
            if (confirmButton) {
                await this.applyUpgrade(confirmButton.dataset.confirmUpgradePlanId);
            }
        }

        async loadPreview(planId) {
            const subscription = this.state.subscription;
            if (!subscription?.upgrade_preview_endpoint || this.state.status === 'previewing') {
                return;
            }

            this.setState({
                status: 'previewing',
                selectedPlanId: planId,
                error: null,
                success: null,
            });

            try {
                const result = await this.api.post(subscription.upgrade_preview_endpoint, {
                    upgrade_plan_id: planId,
                });
                this.setState({ status: 'ready', preview: result.preview || {} });
            } catch (error) {
                this.setState({
                    status: 'error',
                    error: error.message || 'Failed to preview upgrade.',
                });
            }
        }

        async applyUpgrade(planId) {
            const subscription = this.state.subscription;
            if (!subscription?.upgrade_endpoint || this.state.status === 'submitting') {
                return;
            }

            this.setState({ status: 'submitting', error: null, success: null });

            try {
                const result = await this.api.post(subscription.upgrade_endpoint, {
                    upgrade_plan_id: planId,
                });

                this.setState({
                    status: 'success',
                    success: result.message || 'Subscription upgraded successfully.',
                    preview: null,
                });

                await this.loadOptions();
            } catch (error) {
                this.setState({
                    status: 'error',
                    error: error.message || 'Failed to upgrade subscription.',
                });
            }
        }
    }

    new SubscriptionUpgradeController(runtime.api, runtime.state, {
        section,
        optionsContainer,
        previewContainer,
        message,
    });
})();
