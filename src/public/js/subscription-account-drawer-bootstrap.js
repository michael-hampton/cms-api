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
            window.setTimeout(() => this.openDeepLinkedSubscription(), 0);
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

        openDeepLinkedSubscription() {
            const subscriptionId = this.deepLinkedSubscriptionId();
            if (!subscriptionId) {
                return;
            }

            const triggers = Array.from(document.querySelectorAll('[data-open-subscription-manage][data-subscription-manage]'));
            const trigger = triggers.find(candidate => {
                const subscription = this.parseSubscription(candidate);
                return String(subscription?.id ?? '') === subscriptionId;
            });

            if (!trigger) {
                return;
            }

            const previousSection = trigger.closest('.previous-subscriptions');
            if (previousSection instanceof HTMLDetailsElement) {
                previousSection.open = true;
            }

            trigger.click();
        }

        deepLinkedSubscriptionId() {
            const params = new URLSearchParams(window.location.search);
            const action = params.get('action');

            if (action && action !== 'manage') {
                return null;
            }

            return params.get('subscription_id')
                || params.get('subscription')
                || params.get('manage_subscription')
                || params.get('manage');
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

            const isPrint = payload.type === 'print';

            return {
                ...payload,
                can_manage_delivery: isPrint
                    && payload.can_manage_delivery !== false
                    && Boolean(payload.delivery_status_endpoint),
                can_upgrade: payload.can_upgrade !== false
                    && Boolean(payload.upgrade_options_endpoint),
                upgrade_options_endpoint: payload.upgrade_options_endpoint ?? null,
                upgrade_preview_endpoint: payload.upgrade_preview_endpoint ?? null,
                upgrade_endpoint: payload.upgrade_endpoint ?? null,
                preference_endpoint: payload.preference_endpoint ?? null,
                delivery_address_endpoint: payload.delivery_address_endpoint ?? null,
                delivery_address_update_endpoint: payload.delivery_address_update_endpoint ?? null,
                issue_delivery_endpoint: payload.issue_delivery_endpoint ?? null
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
