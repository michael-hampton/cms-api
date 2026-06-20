(() => {
    'use strict';

    const runtime = window.SubscriptionAccount;
    const section = document.getElementById('subscription-preference-section');
    const form = document.getElementById('subscription-preference-form');
    const active = document.getElementById('subscription-preference-active');
    const email = document.getElementById('subscription-preference-email');
    const frequency = document.getElementById('subscription-preference-frequency');
    const message = document.getElementById('subscription-preference-message');

    if (!runtime || !section || !form || !active || !email || !frequency || !message) {
        return;
    }

    class SubscriptionPreferenceController {
        constructor(api, state, elements) {
            this.api = api;
            this.accountState = state;
            this.section = elements.section;
            this.form = elements.form;
            this.active = elements.active;
            this.email = elements.email;
            this.frequency = elements.frequency;
            this.message = elements.message;
            this.state = {
                subscription: null,
                status: 'idle',
                error: null,
            };

            this.accountState.subscribe(subscription => this.onSubscriptionChanged(subscription));
            this.form.addEventListener('submit', event => this.submit(event));
        }

        setState(nextState) {
            this.state = { ...this.state, ...nextState };
            this.renderState();
        }

        renderState() {
            const messages = {
                loading: 'Loading email preferences…',
                saving: 'Saving…',
            };

            const text = this.state.error || messages[this.state.status] || '';
            this.message.textContent = text;
            this.message.classList.toggle('is-visible', Boolean(text));
            this.message.classList.toggle('is-error', Boolean(this.state.error));

            const submit = this.form.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = this.state.status === 'saving';
                submit.textContent = this.state.status === 'saving' ? 'Saving…' : 'Save preferences';
            }
        }

        async onSubscriptionChanged(subscription) {
            this.setState({ subscription, status: 'idle', error: null });

            if (!subscription?.preference_endpoint) {
                this.section.hidden = true;
                return;
            }

            this.section.hidden = false;
            this.setState({ status: 'loading' });

            try {
                const result = await this.api.get(subscription.preference_endpoint);
                const preferences = result.preferences || {};
                this.active.checked = Boolean(preferences.is_active);
                this.email.checked = Boolean(preferences.email_notifications);
                this.frequency.value = preferences.newsletter_frequency || 'weekly';
                this.setState({ status: 'ready' });
            } catch (error) {
                this.setState({
                    status: 'error',
                    error: error.message || 'Failed to load email preferences.',
                });
            }
        }

        async submit(event) {
            event.preventDefault();

            const subscription = this.state.subscription;
            if (!subscription?.preference_endpoint || this.state.status === 'saving') {
                return;
            }

            this.setState({ status: 'saving', error: null });

            try {
                const result = await this.api.post(subscription.preference_endpoint, {
                    is_active: this.active.checked,
                    email_notifications: this.email.checked,
                    newsletter_frequency: this.frequency.value,
                });

                this.setState({ status: 'success' });
                this.message.textContent = result.message || 'Email preferences updated.';
                this.message.classList.add('is-visible');
            } catch (error) {
                this.setState({
                    status: 'error',
                    error: error.message || 'Failed to update email preferences.',
                });
            }
        }
    }

    new SubscriptionPreferenceController(runtime.api, runtime.state, {
        section,
        form,
        active,
        email,
        frequency,
        message,
    });
})();
