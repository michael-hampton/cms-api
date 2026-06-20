(() => {
    'use strict';

    const drawer = document.getElementById('subscription-manage-drawer');
    if (!drawer) {
        return;
    }

    const accordionEntries = [];

    const createAccordion = section => {
        const heading = section.querySelector(':scope > h3');
        if (!heading) {
            return;
        }

        const details = document.createElement('details');
        details.className = 'subscription-drawer-accordion';
        details.hidden = section.hidden;

        const summary = document.createElement('summary');
        summary.textContent = heading.textContent;
        details.append(summary);

        heading.hidden = true;
        section.parentNode.insertBefore(details, section);
        details.append(section);

        const syncVisibility = () => {
            details.hidden = section.hidden;

            if (details.hidden) {
                details.open = false;
            }
        };

        new MutationObserver(syncVisibility).observe(section, {
            attributes: true,
            attributeFilter: ['hidden'],
        });

        details.addEventListener('toggle', () => {
            if (!details.open) {
                return;
            }

            for (const entry of accordionEntries) {
                if (entry !== details) {
                    entry.open = false;
                }
            }
        });

        accordionEntries.push(details);
    };

    drawer.querySelectorAll('.modal__body > section').forEach(createAccordion);

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-open-subscription-manage]');
        if (!trigger) {
            return;
        }

        let payload = {};

        try {
            payload = JSON.parse(trigger.dataset.subscriptionManage || '{}');
        } catch {
            payload = {};
        }

        if (!payload.id) {
            return;
        }

        const base = `/press-stack/account/subscriptions/${payload.id}`;
        const isPrint = payload.type === 'print';

        payload.can_manage_delivery = isPrint && payload.can_manage_delivery !== false;
        payload.can_upgrade = payload.can_upgrade !== false;
        payload.upgrade_options_endpoint ||= `${base}/upgrades`;
        payload.upgrade_preview_endpoint ||= `${base}/upgrades/preview`;
        payload.upgrade_endpoint ||= `${base}/upgrades`;
        payload.preference_endpoint ||= `${base}/preferences`;
        payload.delivery_address_endpoint ||= `${base}/delivery-addresses`;
        payload.delivery_address_update_endpoint ||=
            `${base}/delivery-addresses/__ADDRESS_ID__/default`;
        payload.issue_delivery_endpoint ||= `${base}/issue-deliveries`;

        trigger.dataset.subscriptionManage = JSON.stringify(payload);

        window.setTimeout(() => {
            const firstVisible = accordionEntries.find(entry => !entry.hidden);
            if (firstVisible) {
                firstVisible.open = true;
            }
        }, 0);
    }, true);
})();
