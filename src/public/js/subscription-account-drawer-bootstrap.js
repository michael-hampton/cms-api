(() => {
    'use strict';

    const runtime = window.SubscriptionAccount;
    const drawer = document.getElementById('subscription-manage-drawer');

    if (!runtime || !drawer) {
        return;
    }

    class SubscriptionAccountDrawerCoordinator {
        constructor(element, state) {
            this.element = element;
            this.state = state;
            this.accordions = [];

            this.buildAccordions();
            this.bindEvents();
        }

        buildAccordions() {
            this.element.querySelectorAll('.modal__body > section').forEach(section => {
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

                new MutationObserver(() => this.syncAccordion(details, section)).observe(section, {
                    attributes: true,
                    attributeFilter: ['hidden'],
                });

                details.addEventListener('toggle', () => this.handleAccordionToggle(details));
                this.accordions.push(details);
            });
        }

        bindEvents() {
            document.addEventListener('click', event => {
                const trigger = event.target.closest('[data-open-subscription-manage]');
                if (!trigger) {
                    return;
                }

                const subscription = this.parseSubscription(trigger);
                if (!subscription?.id) {
                    return;
                }

                trigger.dataset.subscriptionManage = JSON.stringify(subscription);
                this.state.setSubscription(subscription);
                window.setTimeout(() => this.openFirstVisibleAccordion(), 0);
            }, true);
        }

        parseSubscription(trigger) {
            let payload = {};

            try {
                payload = JSON.parse(trigger.dataset.subscriptionManage || '{}');
            } catch {
                return null;
            }

            if (!payload.id) {
                return null;
            }

            const base = `/press-stack/account/subscriptions/${payload.id}`;
            const isPrint = payload.type === 'print';

            return {
                ...payload,
                can_manage_delivery: isPrint && payload.can_manage_delivery !== false,
                can_upgrade: payload.can_upgrade !== false,
                upgrade_options_endpoint: payload.upgrade_options_endpoint || `${base}/upgrades`,
                upgrade_preview_endpoint: payload.upgrade_preview_endpoint || `${base}/upgrades/preview`,
                upgrade_endpoint: payload.upgrade_endpoint || `${base}/upgrades`,
                preference_endpoint: payload.preference_endpoint || `${base}/preferences`,
                delivery_address_endpoint: payload.delivery_address_endpoint || `${base}/delivery-addresses`,
                delivery_address_update_endpoint: payload.delivery_address_update_endpoint
                    || `${base}/delivery-addresses/__ADDRESS_ID__/default`,
                issue_delivery_endpoint: payload.issue_delivery_endpoint || `${base}/issue-deliveries`,
            };
        }

        syncAccordion(details, section) {
            details.hidden = section.hidden;

            if (details.hidden) {
                details.open = false;
            }
        }

        handleAccordionToggle(activeAccordion) {
            if (!activeAccordion.open) {
                return;
            }

            for (const accordion of this.accordions) {
                if (accordion !== activeAccordion) {
                    accordion.open = false;
                }
            }
        }

        openFirstVisibleAccordion() {
            const firstVisible = this.accordions.find(accordion => !accordion.hidden);

            if (firstVisible) {
                firstVisible.open = true;
            }
        }
    }

    new SubscriptionAccountDrawerCoordinator(drawer, runtime.state);
})();
