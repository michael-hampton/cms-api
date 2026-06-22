(() => {
    'use strict';

    const runtime = window.SubscriptionAccount;
    const section = document.getElementById('subscription-delivery-address-section');
    const list = document.getElementById('subscription-delivery-address-list');
    const message = document.getElementById('subscription-delivery-address-message');

    if (!runtime || !section || !list || !message) {
        return;
    }

    class SubscriptionDeliveryAddressController {
        constructor(api, state, elements) {
            this.api = api;
            this.accountState = state;
            this.section = elements.section;
            this.list = elements.list;
            this.message = elements.message;
            this.state = {
                subscription: null,
                status: 'idle',
                addresses: [],
                error: null,
                updatingAddressId: null,
            };

            this.accountState.subscribe(subscription => this.onSubscriptionChanged(subscription));
            this.list.addEventListener('click', event => this.handleAddressClick(event));
        }

        setState(nextState) {
            this.state = { ...this.state, ...nextState };
            this.render();
        }

        render() {
            this.list.replaceChildren();

            const messages = {
                loading: 'Loading delivery addresses…',
                updating: 'Updating delivery address…',
            };

            const text = this.state.error || messages[this.state.status] || '';
            this.message.textContent = text;
            this.message.classList.toggle('is-visible', Boolean(text));
            this.message.classList.toggle('is-error', Boolean(this.state.error));

            if (this.state.status === 'loading') {
                return;
            }

            if (!this.state.addresses.length && !this.state.error) {
                this.message.textContent = 'No shipping address is available for this subscription.';
                this.message.classList.add('is-visible');
                return;
            }

            for (const address of this.state.addresses) {
                this.list.append(this.buildAddress(address));
            }
        }

        buildAddress(address) {
            const card = document.createElement('article');
            card.className = 'subscription-delivery-address';

            const body = document.createElement('div');
            body.className = 'subscription-delivery-address__body';

            const lineOne = document.createElement('strong');
            lineOne.textContent = address.address_line_1 || '';

            const lineTwo = document.createElement('span');
            lineTwo.textContent = [address.address_line_2, address.city, address.postcode, address.country]
                .filter(Boolean)
                .join(', ');

            body.append(lineOne, lineTwo);

            const button = document.createElement('button');
            button.type = 'button';
            button.className = address.is_default ? 'btn btn--ghost btn--sm' : 'btn btn--gold btn--sm';
            button.textContent = address.is_default ? 'Current address' : 'Use this address';
            button.disabled = Boolean(address.is_default)
                || this.state.updatingAddressId === String(address.id);
            button.dataset.deliveryAddressId = address.id;

            card.append(body, button);

            return card;
        }

        async onSubscriptionChanged(subscription) {
            this.setState({
                subscription,
                status: 'idle',
                addresses: [],
                error: null,
                updatingAddressId: null,
            });

            if (!subscription?.can_manage_delivery || !subscription.delivery_address_endpoint) {
                this.section.hidden = true;
                return;
            }

            this.section.hidden = false;
            await this.loadAddresses();
        }

        async loadAddresses() {
            const subscription = this.state.subscription;
            if (!subscription?.delivery_address_endpoint) {
                return;
            }

            this.setState({ status: 'loading', error: null });

            try {
                const result = await this.api.get(subscription.delivery_address_endpoint);
                this.setState({
                    status: 'ready',
                    addresses: result.addresses || [],
                    updatingAddressId: null,
                });
            } catch (error) {
                this.setState({
                    status: 'error',
                    error: error.message || 'Failed to load delivery addresses.',
                });
            }
        }

        async handleAddressClick(event) {
            const button = event.target.closest('[data-delivery-address-id]');
            const subscription = this.state.subscription;

            if (!button || !subscription?.delivery_address_update_endpoint || this.state.status === 'updating') {
                return;
            }

            const addressId = button.dataset.deliveryAddressId;
            const endpoint = subscription.delivery_address_update_endpoint
                .replace('__ADDRESS_ID__', addressId);

            this.setState({
                status: 'updating',
                error: null,
                updatingAddressId: addressId,
            });

            try {
                const result = await this.api.post(endpoint);
                await this.loadAddresses();
                this.message.textContent = result.message || 'Delivery address updated.';
                this.message.classList.add('is-visible');
            } catch (error) {
                this.setState({
                    status: 'error',
                    error: error.message || 'Failed to update delivery address.',
                    updatingAddressId: null,
                });
            }
        }
    }

    new SubscriptionDeliveryAddressController(runtime.api, runtime.state, {
        section,
        list,
        message,
    });
})();
