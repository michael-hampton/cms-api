(() => {
    'use strict';

    const modal = document.getElementById('cancel-modal');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    if (!modal) {
        return;
    }

    const steps = ['Review', 'Reason', 'Confirm'];
    const state = {
        subscriptionId: null,
        step: 1,
        returnFocus: null,
    };

    const els = {
        planName: document.getElementById('cancel-plan-name'),
        endDate: document.getElementById('cancel-end-date'),
        confirmEndDate: document.getElementById('confirm-end-date'),
        otherReason: document.getElementById('other-reason-text'),
        indicator: document.getElementById('step-indicator'),
        footer: document.getElementById('cancel-modal-footer'),
        message: document.getElementById('cancel-message'),
    };

    function setMessage(message = '') {
        els.message.textContent = message;
        els.message.classList.toggle('is-visible', Boolean(message));
        els.message.classList.toggle('is-error', Boolean(message));
    }

    function selectedReason() {
        return document.querySelector('input[name="cancel_reason"]:checked');
    }

    function render() {
        els.indicator.innerHTML = `<div class="steps">${steps.map((label, index) => {
            const number = index + 1;
            const className = number < state.step ? 'done' : (number === state.step ? 'active' : '');
            const marker = number < state.step ? '✓' : number;
            const divider = index < steps.length - 1 ? '<div class="step__divider"></div>' : '';
            return `<div class="step ${className}"><div class="step__num">${marker}</div>${label}</div>${divider}`;
        }).join('')}</div>`;

        [1, 2, 3].forEach(number => {
            document.getElementById(`cancel-step-${number}`).classList.toggle('active', number === state.step);
        });

        if (state.step === 1) {
            els.footer.innerHTML = `
                <button class="btn btn--ghost" type="button" data-cancel-action="close">Keep subscription</button>
                <button class="btn btn--danger" type="button" data-cancel-action="next">Continue to cancel</button>`;
        } else if (state.step === 2) {
            els.footer.innerHTML = `
                <button class="btn btn--ghost" type="button" data-cancel-action="back">Back</button>
                <button class="btn btn--danger" type="button" data-cancel-action="next">Next</button>`;
        } else {
            els.footer.innerHTML = `
                <button class="btn btn--ghost" type="button" data-cancel-action="back">Back</button>
                <button class="btn btn--danger" type="button" data-cancel-action="submit">Cancel renewal</button>`;
        }
    }

    function open(trigger) {
        let flow = {};
        try {
            flow = JSON.parse(trigger.dataset.cancellationFlow || '{}');
        } catch {
            flow = {};
        }
        state.subscriptionId = trigger.dataset.subscriptionId;
        state.step = 1;
        state.returnFocus = trigger;
        els.planName.textContent = trigger.dataset.planName || 'your subscription';
        els.endDate.textContent = flow.effective_date || trigger.dataset.endDate || 'the end of your current term';
        els.confirmEndDate.textContent = flow.confirmation?.access_end_date || trigger.dataset.endDate || 'the end of your current term';
        els.otherReason.hidden = true;
        els.otherReason.value = '';
        document.querySelectorAll('.reason-radio').forEach(row => row.classList.remove('selected', 'is-invalid'));
        document.querySelectorAll('input[name="cancel_reason"]').forEach(input => {
            input.checked = false;
        });
        setMessage();
        render();
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        modal.querySelector('.modal__close')?.focus();
    }

    function close() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
        state.subscriptionId = null;
        state.returnFocus?.focus();
        state.returnFocus = null;
    }

    function next() {
        if (state.step === 2 && !selectedReason()) {
            document.querySelectorAll('.reason-radio').forEach(row => row.classList.add('is-invalid'));
            setMessage('Please select a cancellation reason.');
            return;
        }

        setMessage();
        state.step = Math.min(3, state.step + 1);
        render();
    }

    function back() {
        setMessage();
        state.step = Math.max(1, state.step - 1);
        render();
    }

    async function submit(button) {
        const reason = selectedReason()?.value || '';
        const endpointTemplate = modal.dataset.cancelEndpoint || '';
        const endpoint = endpointTemplate.replace('__SUBSCRIPTION_ID__', encodeURIComponent(state.subscriptionId));

        button.disabled = true;
        button.textContent = 'Cancelling…';
        setMessage();

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    reason,
                    other_text: els.otherReason.value,
                }),
            });

            if (response.status === 401) {
                window.location.assign(modal.dataset.loginUrl || '/member/login');
                return;
            }

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Something went wrong. Please try again.');
            }

            window.location.reload();
        } catch (error) {
            setMessage(error.message || 'Network error. Please try again.');
            button.disabled = false;
            button.textContent = 'Cancel renewal';
        }
    }

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-open-cancel]');
        if (trigger) {
            open(trigger);
            return;
        }

        const accountAction = event.target.closest('[data-account-action="api"]');
        if (accountAction) {
            accountAction.disabled = true;
            const originalLabel = accountAction.textContent;
            accountAction.textContent = 'Working…';
            fetch(accountAction.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: '{}',
            }).then(async response => {
                if (response.status === 401) {
                    window.location.assign(modal.dataset.loginUrl || '/member/login');
                    return;
                }
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'The action could not be completed.');
                }
                window.location.reload();
            }).catch(error => {
                setMessage(error.message);
                accountAction.disabled = false;
                accountAction.textContent = originalLabel;
            });
            return;
        }

        const actionButton = event.target.closest('[data-cancel-action]');
        if (!actionButton) {
            return;
        }

        const actions = {
            close,
            next,
            back,
            submit: () => submit(actionButton),
        };

        actions[actionButton.dataset.cancelAction]?.();
    });

    document.querySelectorAll('input[name="cancel_reason"]').forEach(input => {
        input.addEventListener('change', () => {
            document.querySelectorAll('.reason-radio').forEach(row => row.classList.remove('selected', 'is-invalid'));
            input.closest('.reason-radio')?.classList.add('selected');
            els.otherReason.hidden = input.value !== 'other';
            setMessage();
        });
    });

    modal.addEventListener('click', event => {
        if (event.target === modal) {
            close();
        }
    });

    document.addEventListener('keydown', event => {
        if (!modal.classList.contains('open')) {
            return;
        }

        if (event.key === 'Escape') {
            close();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = [...modal.querySelectorAll(
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
    });
})();
