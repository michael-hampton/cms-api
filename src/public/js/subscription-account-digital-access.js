(() => {
    'use strict';

    const runtime = window.SubscriptionAccount;
    const section = document.getElementById('subscription-digital-access-section');
    const link = document.getElementById('subscription-digital-download');
    const expiry = document.getElementById('subscription-digital-expiry');

    if (!runtime || !section || !link || !expiry) {
        return;
    }

    class SubscriptionDigitalAccessController {
        constructor(state, elements) {
            this.state = state;
            this.section = elements.section;
            this.link = elements.link;
            this.expiry = elements.expiry;

            this.state.subscribe(subscription => this.render(subscription));
        }

        render(subscription) {
            const downloadUrl = subscription?.digital_download_url || null;
            this.section.hidden = !downloadUrl;

            if (!downloadUrl) {
                this.link.removeAttribute('href');
                this.expiry.textContent = '';
                return;
            }

            this.link.href = downloadUrl;
            this.expiry.textContent = subscription.digital_download_expires_at
                ? `Download access expires ${subscription.digital_download_expires_at}.`
                : '';
        }
    }

    new SubscriptionDigitalAccessController(runtime.state, { section, link, expiry });
})();
