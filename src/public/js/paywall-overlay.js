(() => {
    'use strict';

    class PaywallOverlayController {
        constructor(root) {
            this.root = root;
            this.dialog = root.querySelector('.paywall-overlay__dialog');
            this.purchase = root.querySelector('[data-paywall-purchase]');
            this.purchaseDialog = root.querySelector('.paywall-purchase__dialog');
            this.openButton = root.querySelector('[data-paywall-open-purchase]');
            this.closeButton = root.querySelector('[data-paywall-close-purchase]');
            this.submitButton = root.querySelector('[data-paywall-submit-payment]');
            this.email = root.querySelector('[data-paywall-email]');
            this.emailError = root.querySelector('[data-paywall-email-error]');
            this.cardHost = root.querySelector('[data-paywall-card]');
            this.cardError = root.querySelector('[data-paywall-card-error]');
            this.form = root.querySelector('[data-paywall-payment-form]');
            this.success = root.querySelector('[data-paywall-payment-success]');
            this.stripe = null;
            this.card = null;
            this.processing = false;
        }

        start() {
            document.body.classList.add('paywall-open');
            queueMicrotask(() => this.dialog?.focus());

            this.openButton?.addEventListener('click', () => this.openPurchase());
            this.closeButton?.addEventListener('click', () => this.closePurchase());
            this.submitButton?.addEventListener('click', () => this.submitPayment());
            document.addEventListener('keydown', event => this.onKeydown(event));
        }

        async openPurchase() {
            if (!this.purchase) return;

            this.purchase.hidden = false;
            await this.ensureStripe();
            this.purchaseDialog?.focus();
        }

        closePurchase() {
            if (!this.purchase || this.processing) return;
            this.purchase.hidden = true;
            this.openButton?.focus();
        }

        onKeydown(event) {
            if (event.key === 'Escape' && this.purchase && !this.purchase.hidden) {
                this.closePurchase();
            }
        }

        async ensureStripe() {
            if (this.stripe || !this.cardHost) return;

            if (!window.Stripe) {
                await new Promise((resolve, reject) => {
                    const existing = document.querySelector('script[src="https://js.stripe.com/v3/"]');
                    if (existing) {
                        existing.addEventListener('load', resolve, {once: true});
                        existing.addEventListener('error', reject, {once: true});
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = 'https://js.stripe.com/v3/';
                    script.addEventListener('load', resolve, {once: true});
                    script.addEventListener('error', reject, {once: true});
                    document.head.append(script);
                });
            }

            const key = this.root.dataset.stripeKey;
            if (!key) throw new Error('Stripe is not configured.');

            this.stripe = window.Stripe(key);
            this.card = this.stripe.elements().create('card', {hidePostalCode: true});
            this.card.mount(this.cardHost);
            this.card.on('change', event => {
                if (this.cardError) this.cardError.textContent = event.error?.message ?? '';
            });
        }

        async submitPayment() {
            if (this.processing || !this.stripe || !this.card) return;

            const email = String(this.email?.value ?? '').trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                if (this.emailError) this.emailError.textContent = 'Please enter a valid email address.';
                return;
            }

            this.processing = true;
            this.submitButton.disabled = true;
            const originalText = this.submitButton.textContent;
            this.submitButton.textContent = 'Processing…';
            if (this.emailError) this.emailError.textContent = '';
            if (this.cardError) this.cardError.textContent = '';

            try {
                const response = await fetch(this.root.dataset.purchaseEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({email}),
                });

                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload.message ?? 'Could not initiate payment.');
                }

                const result = await this.stripe.confirmCardPayment(payload.data.client_secret, {
                    payment_method: {
                        card: this.card,
                        billing_details: {email},
                    },
                });

                if (result.error) throw result.error;
                if (result.paymentIntent?.status !== 'succeeded') {
                    throw new Error('Payment could not be completed.');
                }

                if (this.form) this.form.hidden = true;
                if (this.success) this.success.hidden = false;
                window.setTimeout(() => window.location.reload(), 2000);
            } catch (error) {
                if (this.cardError) this.cardError.textContent = error.message ?? 'Payment failed.';
                this.submitButton.disabled = false;
                this.submitButton.textContent = originalText;
                this.processing = false;
            }
        }
    }

    document.addEventListener('public-content:component-mounted', event => {
        if (event.detail.component.type !== 'paywall-overlay') return;
        const root = event.detail.element.querySelector('[data-paywall-overlay]');
        if (!root || root.dataset.hydrated === 'true') return;
        root.dataset.hydrated = 'true';
        new PaywallOverlayController(root).start();
    });
})();
