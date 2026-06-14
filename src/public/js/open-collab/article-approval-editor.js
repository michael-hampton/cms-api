(() => {
    const REQUEST_LABEL = 'Request approval';
    const REQUESTING_LABEL = 'Requesting approval…';
    const REQUESTED_LABEL = 'Approval requested';

    function setButtonLabel(button, label) {
        const icon = button.querySelector('svg')?.outerHTML ?? '';
        button.innerHTML = icon ? `${icon} ${label}` : label;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const button = document.getElementById('publish-btn');
        if (!button) {
            return;
        }

        const status = document.querySelector('.oc-badge')?.textContent
            ?.trim()
            .toLowerCase()
            .replace(/\s+/g, '_');

        if (status === 'waiting_approval') {
            button.removeAttribute('onclick');
            button.disabled = true;
            setButtonLabel(button, REQUESTED_LABEL);
            return;
        }

        setButtonLabel(button, REQUEST_LABEL);

        button.addEventListener('click', () => {
            setButtonLabel(button, REQUESTING_LABEL);
        }, {capture: true});

        const observer = new MutationObserver(() => {
            if (button.disabled) {
                return;
            }

            const label = button.textContent?.trim().toLowerCase() ?? '';
            if (label === 'publish' || label === 'update') {
                setButtonLabel(button, REQUEST_LABEL);
            }
        });

        observer.observe(button, {
            attributes: true,
            attributeFilter: ['disabled'],
            childList: true,
            subtree: true,
        });

        if (typeof window.showToast === 'function') {
            const originalShowToast = window.showToast;
            window.showToast = (message, success = true) => {
                originalShowToast(
                    message === '✓ Published!' ? '✓ Approval requested' : message,
                    success,
                );
            };
        }
    });
})();
