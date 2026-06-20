(() => {
    'use strict';

    const runtime = window.SubscriptionAccount;
    const historyList = document.getElementById('subscription-history-list');
    const historyMessage = document.getElementById('subscription-history-message');
    const historyMore = document.getElementById('subscription-history-more');
    const deliverySection = document.getElementById('subscription-delivery-section');
    const deliveryForm = document.getElementById('subscription-delivery-form');
    const deliveryStatus = document.getElementById('subscription-delivery-status');
    const deliveryResume = document.getElementById('subscription-delivery-resume');
    const deliveryMessage = document.getElementById('subscription-delivery-message');

    if (!runtime || !historyList || !historyMessage || !historyMore) {
        return;
    }

    class SubscriptionHistoryDeliveryController {
        constructor(api, state, elements) {
            this.api = api;
            this.accountState = state;
            Object.assign(this, elements);
            this.state = {
                subscription: null,
                historyStatus: 'idle',
                historyPage: 1,
                historyEvents: [],
                historyHasMore: false,
                historyError: null,
                deliveryStatus: 'idle',
                deliveryData: null,
                deliveryError: null,
            };

            this.accountState.subscribe(subscription => this.onSubscriptionChanged(subscription));
            this.historyMore.addEventListener('click', () => this.loadMoreHistory());
            this.deliveryForm?.addEventListener('submit', event => this.pauseDelivery(event));
            this.deliveryResume?.addEventListener('click', () => this.resumeDelivery());
        }

        setState(nextState) {
            this.state = { ...this.state, ...nextState };
            this.render();
        }

        render() {
            this.renderHistory();
            this.renderDelivery();
        }

        renderHistory() {
            this.historyList.replaceChildren();

            for (const historyEvent of this.state.historyEvents) {
                const row = document.createElement('div');
                row.className = 'subscription-history-item';

                const name = document.createElement('strong');
                name.textContent = String(historyEvent.event_type || 'Subscription updated').replaceAll('_', ' ');

                const date = document.createElement('time');
                date.textContent = historyEvent.occurred_at
                    ? new Date(historyEvent.occurred_at).toLocaleString()
                    : '';

                row.append(name, date);
                this.historyList.append(row);
            }

            const loading = this.state.historyStatus === 'loading';
            const text = this.state.historyError
                || (loading ? 'Loading history…' : '')
                || (!this.state.historyEvents.length ? 'No subscription history is available yet.' : '');

            this.setMessage(this.historyMessage, text, Boolean(this.state.historyError));
            this.historyMore.hidden = !this.state.historyHasMore || loading;
            this.historyMore.disabled = loading;
        }

        renderDelivery() {
            if (!this.deliverySection) {
                return;
            }

            const subscription = this.state.subscription;
            this.deliverySection.hidden = !subscription?.can_manage_delivery;

            if (this.deliverySection.hidden) {
                return;
            }

            const loading = this.state.deliveryStatus === 'loading';
            const saving = this.state.deliveryStatus === 'saving';
            const delivery = this.state.deliveryData;
            const text = this.state.deliveryError
                || (loading ? 'Loading delivery settings…' : '')
                || (saving ? 'Saving delivery settings…' : '');

            this.setMessage(this.deliveryMessage, text, Boolean(this.state.deliveryError));

            if (!delivery || !this.deliveryForm || !this.deliveryResume) {
                return;
            }

            const paused = Boolean(delivery.is_paused);
            this.deliveryForm.hidden = false;
            this.deliveryResume.hidden = !paused;
            this.deliveryResume.disabled = saving;

            const submit = this.deliveryForm.querySelector('button[type="submit"]');
            if (submit) {
                submit.hidden = paused;
                submit.disabled = saving;
            }

            this.deliveryStatus.textContent = paused
                ? `Delivery is paused until ${delivery.pause_end || 'the selected date'}.`
                : 'Manage temporary delivery pauses for this print subscription.';
        }

        setMessage(element, text, error = false) {
            if (!element) {
                return;
            }

            element.textContent = text;
            element.classList.toggle('is-visible', Boolean(text));
            element.classList.toggle('is-error', error);
        }

        async onSubscriptionChanged(subscription) {
            this.setState({
                subscription,
                historyStatus: 'idle',
                historyPage: 1,
                historyEvents: [],
                historyHasMore: false,
                historyError: null,
                deliveryStatus: 'idle',
                deliveryData: null,
                deliveryError: null,
            });

            await Promise.all([
                this.loadHistory(true),
                this.loadDelivery(),
            ]);
        }

        async loadHistory(reset = false) {
            const subscription = this.state.subscription;
            if (!subscription?.history_endpoint) {
                return;
            }

            const page = reset ? 1 : this.state.historyPage;
            this.setState({ historyStatus: 'loading', historyError: null });

            try {
                const separator = subscription.history_endpoint.includes('?') ? '&' : '?';
                const result = await this.api.get(
                    `${subscription.history_endpoint}${separator}page=${page}&per_page=10`,
                );

                this.setState({
                    historyStatus: 'ready',
                    historyPage: page,
                    historyEvents: reset
                        ? (result.events || [])
                        : [...this.state.historyEvents, ...(result.events || [])],
                    historyHasMore: Boolean(result.pagination?.has_more),
                });
            } catch (error) {
                this.setState({
                    historyStatus: 'error',
                    historyError: error.message || 'Failed to load subscription history.',
                });
            }
        }

        async loadMoreHistory() {
            if (this.state.historyStatus === 'loading' || !this.state.historyHasMore) {
                return;
            }

            this.setState({ historyPage: this.state.historyPage + 1 });
            await this.loadHistory();
        }

        async loadDelivery() {
            const subscription = this.state.subscription;
            if (!subscription?.can_manage_delivery || !subscription.delivery_status_endpoint) {
                return;
            }

            this.setState({ deliveryStatus: 'loading', deliveryError: null });

            try {
                const result = await this.api.get(subscription.delivery_status_endpoint);
                this.setState({ deliveryStatus: 'ready', deliveryData: result });
            } catch (error) {
                this.setState({
                    deliveryStatus: 'error',
                    deliveryError: error.message || 'Failed to load delivery settings.',
                });
            }
        }

        async pauseDelivery(event) {
            event.preventDefault();

            const subscription = this.state.subscription;
            if (!subscription?.delivery_pause_endpoint || this.state.deliveryStatus === 'saving') {
                return;
            }

            const formData = new FormData(this.deliveryForm);
            this.setState({ deliveryStatus: 'saving', deliveryError: null });

            try {
                await this.api.post(subscription.delivery_pause_endpoint, {
                    pause_start: formData.get('pause_start'),
                    pause_end: formData.get('pause_end'),
                    reason: formData.get('reason'),
                });
                await this.loadDelivery();
            } catch (error) {
                this.setState({
                    deliveryStatus: 'error',
                    deliveryError: error.message || 'Failed to pause delivery.',
                });
            }
        }

        async resumeDelivery() {
            const subscription = this.state.subscription;
            if (!subscription?.delivery_resume_endpoint || this.state.deliveryStatus === 'saving') {
                return;
            }

            this.setState({ deliveryStatus: 'saving', deliveryError: null });

            try {
                await this.api.post(subscription.delivery_resume_endpoint);
                await this.loadDelivery();
            } catch (error) {
                this.setState({
                    deliveryStatus: 'error',
                    deliveryError: error.message || 'Failed to resume delivery.',
                });
            }
        }
    }

    new SubscriptionHistoryDeliveryController(runtime.api, runtime.state, {
        historyList,
        historyMessage,
        historyMore,
        deliverySection,
        deliveryForm,
        deliveryStatus,
        deliveryResume,
        deliveryMessage,
    });
})();
