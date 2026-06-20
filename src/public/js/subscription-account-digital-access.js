(() => {
    const section = document.getElementById('subscription-digital-access-section');
    const link = document.getElementById('subscription-digital-download');
    const expiry = document.getElementById('subscription-digital-expiry');

    if (!section || !link || !expiry) {
        return;
    }

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-open-subscription-manage]');
        if (!trigger) {
            return;
        }

        let data = {};

        try {
            data = JSON.parse(trigger.dataset.subscriptionManage || '{}');
        } catch {
            data = {};
        }

        section.hidden = !data.digital_download_url;

        if (!data.digital_download_url) {
            link.removeAttribute('href');
            expiry.textContent = '';
            return;
        }

        link.href = data.digital_download_url;
        expiry.textContent = data.digital_download_expires_at
            ? `Download access expires ${data.digital_download_expires_at}.`
            : '';
    });
})();
