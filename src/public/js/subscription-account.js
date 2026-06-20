(() => {
    'use strict';

    class SubscriptionAccountPage {
        static STEPS = ['Review', 'Reason', 'Confirm'];

        #state = {
            subscriptionId: null,
            step: 1,
            submitting: false,
            returnFocus: null,
            flow: {},
            message: '',
        };

        #els = {};

        constructor(root = document) {
            this.root = root;
            this.#bindElements();
            if (!this.#els.modal) {
                return;
            }
            this.#bindEvents();
        }

        #bindElements() {
            this.#els = {
                modal: this.root.getElementById('cancel-modal'),
                planName: this.root.getElementById('cancel-plan-name'),
                endDate: this.root.getElementById('cancel-end-date'),
                confirmEndDate: this.root.getElementById('confirm-end-date'),
                reviewCopy: this.root.getElementById('cancel-review-copy'),
                lostBenefits: this.root.getElementById('cancel-lost-benefits'),
                confirmationCopy: this.root.getElementById('cancel-confirmation-copy'),
                refundCopy: this.root.getElementById('cancel-refund-copy'),
                otherReason: this.root.getElementById('other-reason-text'),
                indicator: this.root.getElementById('step-indicator'),
                footer: this.root.getElementById('cancel-modal-footer'),
                message: this.root.getElementById('cancel-message'),
            };
        }

        #bindEvents() {
            this.root.addEventListener('click', event => this.#handleClick(event));
            this.root.addEventListener('change', event => this.#handleChange(event));
            this.root.addEventListener('keydown', event => this.#handleKeydown(event));
        }

        #handleClick(event) {
            const cancelTrigger = event.target.closest('[data-open-cancel]');
            if (cancelTrigger) {
                this.#open(cancelTrigger);
                return;
            }

            const accountAction = event.target.closest('[data-account-action="api"]');
            if (accountAction) {
                this.#submitAccountAction(accountAction);
                return;
            }

            const actionButton = event.target.closest('[data-cancel-action]');
            if (actionButton) {
                const actions = {
                    close: () => this.#close(),
                    next: () => this.#next(),
                    back: () => this.#back(),
                    submit: () => this.#submitCancellation(actionButton),
                };
                actions[actionButton.dataset.cancelAction]?.();
                return;
            }

            if (event.target === this.#els.modal) {
                this.#close();
            }
        }

        #handleChange(event) {
            if (!event.target.matches('input[name="cancel_reason"]')) {
                return;
            }

            this.root.querySelectorAll('.reason-radio').forEach(row => {
                row.classList.toggle('selected', row.contains(event.target));
                row.classList.remove('is-invalid');
            });
            this.#els.otherReason.hidden = event.target.value !== 'other';
            this.#setState({message: ''});
        }

        #handleKeydown(event) {
            if (!this.#els.modal.classList.contains('open')) {
                return;
            }
            if (event.key === 'Escape') {
                this.#close();
                return;
            }
            if (event.key === 'Tab') {
                this.#trapFocus(event);
            }
        }

        #open(trigger) {
            let flow = {};
            try {
                flow = JSON.parse(trigger.dataset.cancellationFlow || '{}');
            } catch {
                flow = {};
            }

            this.#state = {
                subscriptionId: trigger.dataset.subscriptionId,
                step: 1,
                submitting: false,
                returnFocus: trigger,
                flow,
                message: '',
            };
            this.#els.planName.textContent = trigger.dataset.planName || 'your subscription';
            this.#els.endDate.textContent = flow.effective_date
                || trigger.dataset.endDate
                || 'the end of your current term';
            this.#els.confirmEndDate.textContent = flow.confirmation?.access_end_date
                || trigger.dataset.endDate
                || 'the end of your current term';
            if (flow.review_copy) {
                this.#els.reviewCopy.textContent = flow.review_copy;
            }
            if (Array.isArray(flow.lost_benefits)) {
                this.#els.lostBenefits.innerHTML = flow.lost_benefits
                    .map(benefit => `<li><span class="benefit-list__icon">×</span>${this.#escape(benefit)}</li>`)
                    .join('');
            }
            if (flow.confirmation?.access_message || flow.confirmation?.further_payments) {
                this.#els.confirmationCopy.textContent = [
                    flow.confirmation.access_message,
                    flow.confirmation.further_payments,
                ].filter(Boolean).join(' ');
            }
            if (flow.confirmation?.refund_outcome || flow.refund_message) {
                this.#els.refundCopy.textContent = flow.confirmation?.refund_outcome || flow.refund_message;
            }
            this.#els.otherReason.hidden = true;
            this.#els.otherReason.value = '';
            this.root.querySelectorAll('input[name="cancel_reason"]').forEach(input => {
                input.checked = false;
            });
            this.root.querySelectorAll('.reason-radio').forEach(row => {
                row.classList.remove('selected', 'is-invalid');
            });
            this.#els.modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            this.#render();
            this.#els.modal.querySelector('.modal__close')?.focus();
        }

        #close() {
            this.#els.modal.classList.remove('open');
            document.body.style.overflow = '';
            this.#state.returnFocus?.focus();
            this.#state.subscriptionId = null;
            this.#state.returnFocus = null;
        }

        #next() {
            if (this.#state.step === 2 && !this.#selectedReason()) {
                this.root.querySelectorAll('.reason-radio').forEach(row => row.classList.add('is-invalid'));
                this.#setState({message: 'Please select a cancellation reason.'});
                return;
            }
            this.#setState({step: Math.min(3, this.#state.step + 1), message: ''});
        }

        #back() {
            this.#setState({step: Math.max(1, this.#state.step - 1), message: ''});
        }

        #setState(patch) {
            Object.assign(this.#state, patch);
            this.#render();
        }

        #render() {
            this.#els.indicator.innerHTML = `<div class="steps">${SubscriptionAccountPage.STEPS.map((label, index) => {
                const number = index + 1;
                const className = number < this.#state.step ? 'done' : (number === this.#state.step ? 'active' : '');
                const divider = index < SubscriptionAccountPage.STEPS.length - 1 ? '<div class="step__divider"></div>' : '';
                return `<div class="step ${className}"><div class="step__num">${number < this.#state.step ? '✓' : number}</div>${label}</div>${divider}`;
            }).join('')}</div>`;

            [1, 2, 3].forEach(number => {
                this.root.getElementById(`cancel-step-${number}`)?.classList.toggle('active', number === this.#state.step);
            });

            const submitting = this.#state.submitting;
            if (this.#state.step === 1) {
                this.#els.footer.innerHTML = `
                    <button class="btn btn--ghost" type="button" data-cancel-action="close">Keep subscription</button>
                    <button class="btn btn--danger" type="button" data-cancel-action="next">Continue to cancel</button>`;
            } else if (this.#state.step === 2) {
                this.#els.footer.innerHTML = `
                    <button class="btn btn--ghost" type="button" data-cancel-action="back">Back</button>
                    <button class="btn btn--danger" type="button" data-cancel-action="next">Next</button>`;
            } else {
                this.#els.footer.innerHTML = `
                    <button class="btn btn--ghost" type="button" data-cancel-action="back" ${submitting ? 'disabled' : ''}>Back</button>
                    <button class="btn btn--danger" type="button" data-cancel-action="submit" ${submitting ? 'disabled' : ''}>
                        ${submitting ? 'Cancelling…' : 'Cancel renewal'}
                    </button>`;
            }

            this.#els.message.textContent = this.#state.message;
            this.#els.message.classList.toggle('is-visible', Boolean(this.#state.message));
            this.#els.message.classList.toggle('is-error', Boolean(this.#state.message));
        }

        async #submitCancellation() {
            const endpoint = (this.#state.flow.endpoint || this.#els.modal.dataset.cancelEndpoint || '')
                .replace('__SUBSCRIPTION_ID__', encodeURIComponent(this.#state.subscriptionId));
            this.#setState({submitting: true, message: ''});

            try {
                const response = await this.#request(endpoint, {
                    reason: this.#selectedReason()?.value || '',
                    other_text: this.#els.otherReason.value,
                });
                if (response.redirected) {
                    return;
                }
                window.location.reload();
            } catch (error) {
                this.#setState({
                    submitting: false,
                    message: error.message || 'Network error. Please try again.',
                });
            }
        }

        async #submitAccountAction(button) {
            const originalLabel = button.textContent;
            button.disabled = true;
            button.textContent = 'Working…';

            try {
                await this.#request(button.dataset.endpoint, {});
                window.location.reload();
            } catch (error) {
                button.disabled = false;
                button.textContent = originalLabel;
                window.alert(error.message || 'The action could not be completed.');
            }
        }

        async #request(endpoint, body) {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.root.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(body),
            });

            if (response.status === 401) {
                window.location.assign(this.#els.modal.dataset.loginUrl || '/member/login');
                return {redirected: true};
            }

            const data = await response.json();
            const payload = data.data ?? data;
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Something went wrong. Please try again.');
            }
            return payload;
        }

        #selectedReason() {
            return this.root.querySelector('input[name="cancel_reason"]:checked');
        }

        #trapFocus(event) {
            const focusable = [...this.#els.modal.querySelectorAll(
                'button:not([disabled]), input:not([disabled]), textarea:not([disabled]), a[href]'
            )].filter(element => !element.hidden);
            if (!focusable.length) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        #escape(value) {
            const element = document.createElement('div');
            element.textContent = String(value ?? '');
            return element.innerHTML;
        }
    }

    document.addEventListener('DOMContentLoaded', () => new SubscriptionAccountPage());
})();
