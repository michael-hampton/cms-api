(() => {
    'use strict';

    const form = document.getElementById('auto-renew-form');
    if (!form) {
        return;
    }

    const toggle = document.getElementById('auto-renew-toggle');
    const consentContainer = document.getElementById('auto-renew-consent');
    const consent = document.getElementById('auto-renew-consent-checkbox');
    const message = document.getElementById('auto-renew-message');
    const submit = form.querySelector('button[type="submit"]');

    const updateConsentVisibility = () => {
        consentContainer.hidden = !toggle.checked;
        if (!toggle.checked) {
            consent.checked = false;
        }
    };

    toggle.addEventListener('change', updateConsentVisibility);

    form.addEventListener('submit', async event => {
        event.preventDefault();

        if (toggle.checked && !consent.checked) {
            message.textContent = 'Please confirm consent before enabling automatic renewal.';
            message.classList.add('is-visible', 'is-error');
            consent.focus();
            return;
        }

        const originalLabel = submit.textContent;
        submit.disabled = true;
        submit.textContent = 'Saving…';
        message.textContent = '';
        message.classList.remove('is-visible', 'is-error');

        try {
            const response = await fetch(form.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    auto_renew: toggle.checked,
                    consent_given: toggle.checked ? consent.checked : false,
                }),
            });

            const data = await response.json();
            const payload = data.data ?? data;

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Failed to update automatic renewal.');
            }

            message.textContent = payload.message || 'Renewal preference updated.';
            message.classList.add('is-visible');
            consent.checked = false;
            updateConsentVisibility();
        } catch (error) {
            message.textContent = error.message || 'Failed to update automatic renewal.';
            message.classList.add('is-visible', 'is-error');
        } finally {
            submit.disabled = false;
            submit.textContent = originalLabel;
        }
    });
})();
