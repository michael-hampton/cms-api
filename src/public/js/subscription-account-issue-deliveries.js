(() => {
    'use strict';

    const runtime = window.SubscriptionAccount;
    const section = document.getElementById('subscription-issue-delivery-section');
    const list = document.getElementById('subscription-issue-delivery-list');
    const message = document.getElementById('subscription-issue-delivery-message');

    if (!runtime || !section || !list || !message) {
        return;
    }

    class SubscriptionIssueDeliveryController {
        constructor(api, state, elements) {
            this.api = api;
            this.accountState = state;
            this.section = elements.section;
            this.list = elements.list;
            this.message = elements.message;
            this.state = {
                subscription: null,
                status: 'idle',
                deliveries: [],
                error: null,
            };

            this.accountState.subscribe(subscription => this.onSubscriptionChanged(subscription));
        }

        setState(nextState) {
            this.state = { ...this.state, ...nextState };
            this.render();
        }

        render() {
            this.list.replaceChildren();

            const text = this.state.error
                || (this.state.status === 'loading' ? 'Loading upcoming deliveries…' : '')
                || (this.state.status === 'ready' && !this.state.deliveries.length
                    ? 'The delivery schedule is being prepared.'
                    : '');

            this.message.textContent = text;
            this.message.classList.toggle('is-visible', Boolean(text));
            this.message.classList.toggle('is-error', Boolean(this.state.error));

            for (const delivery of this.state.deliveries) {
                this.list.append(this.buildDelivery(delivery));
            }
        }

        buildDelivery(delivery) {
            const row = document.createElement('article');
            row.className = 'subscription-issue-delivery';

            const details = document.createElement('div');
            details.className = 'subscription-issue-delivery__details';

            const title = document.createElement('strong');
            title.textContent = delivery.issue_title || `Issue #${delivery.issue_number}`;

            const date = document.createElement('span');
            date.textContent = this.formatDate(delivery.estimated_delivery_date);

            details.append(title, date);
            row.append(details);

            if (delivery.tracking_number) {
                const tracking = document.createElement('a');
                tracking.className = 'btn btn--ghost btn--sm';
                tracking.textContent = `Track ${delivery.tracking_number}`;
                tracking.href = delivery.tracking_url || '#';
                tracking.target = '_blank';
                tracking.rel = 'noopener';
                row.append(tracking);
            }

            return row;
        }

        formatDate(value) {
            if (!value) {
                return 'Date pending';
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

        async onSubscriptionChanged(subscription) {
            this.setState({
                subscription,
                status: 'idle',
                deliveries: [],
                error: null,
            });

            if (!subscription?.can_manage_delivery || !subscription.issue_delivery_endpoint) {
                this.section.hidden = true;
                return;
            }

            this.section.hidden = false;
            this.setState({ status: 'loading' });

            try {
                const result = await this.api.get(subscription.issue_delivery_endpoint);
                this.setState({
                    status: 'ready',
                    deliveries: result.deliveries || [],
                });
            } catch (error) {
                this.setState({
                    status: 'error',
                    error: error.message || 'Failed to load deliveries.',
                });
            }
        }
    }

    new SubscriptionIssueDeliveryController(runtime.api, runtime.state, {
        section,
        list,
        message,
    });
})();
