(function () {


    // ── Toast ─────────────────────────────────────────────────────────
    window.showToast = function (message, type = 'success') {
        const toast = document.getElementById('toast');
        if (!toast) return;
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3000);
    };

    alert('here')

    // ── Alert banner ──────────────────────────────────────────────────
    window.showAlert = function (message, type = 'success') {
        const container = document.getElementById('alert-container');
        if (!container) return;
        container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        window.scrollTo({top: 0, behavior: 'smooth'});
        setTimeout(() => {
            container.innerHTML = '';
        }, 5000);
    };

    // ── Voucher message (inline beneath input) ────────────────────────
    window.showVoucherMessage = function (msg, type) {
        const el = document.getElementById('voucher-message');
        if (!el) return;
        el.textContent = msg;
        el.style.color = type === 'error'
            ? 'var(--danger-color)'
            : 'var(--success-color)';
    };

    // ── Apply voucher ─────────────────────────────────────────────────
    window.applyVoucher = async function () {
        const input = document.getElementById('voucher-input');
        const code = input ? input.value.trim() : '';

        if (!code) {
            showVoucherMessage('Please enter a voucher code', 'error');
            return;
        }

        const totalEl = document.getElementById('total');
        const totalAmount = totalEl ? parseFloat(totalEl.dataset.total || '0') : 0;

        const isSubscription = window.location.search.includes('type=subscription')
            || !!document.querySelector('[name="plan_id"]');

        const body = {
            code,
            is_subscription: isSubscription,
            order_value: totalAmount,
        };

        if (isSubscription) {
            const planInput = document.querySelector('[name="plan_id"]');
            if (planInput) body.plan_id = parseInt(planInput.value, 10);
        }

        try {
            const res = await fetch(`${API_BASE}/vouchers/validate`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body),
            });
            const result = await res.json();

            if (result.data.valid) {
                window.appliedVoucher = {
                    code,
                    discount: result.data.discount,
                    voucher_id: result.data.voucher_id,
                };
                displayAppliedVoucher();
                if (input) input.value = '';
                const msgEl = document.getElementById('voucher-message');
                if (msgEl) msgEl.textContent = '';
                showAlert('Voucher applied successfully!', 'success');
            } else {
                showVoucherMessage(result.data.message || 'Invalid voucher code', 'error');
            }
        } catch (err) {
            console.error('applyVoucher error:', err);
            showVoucherMessage('Error applying voucher', 'error');
        }
    };

    // ── Remove voucher ────────────────────────────────────────────────
    window.removeVoucher = async function () {
        try {
            const res = await fetch(`${API_BASE}/vouchers/remove-voucher`, {method: 'POST'});
            const result = await res.json();

            if (result.success) {
                window.appliedVoucher = null;
                const appliedEl = document.getElementById('applied-voucher');
                if (appliedEl) appliedEl.style.display = 'none';
                const discountRow = document.getElementById('discount-row');
                if (discountRow) discountRow.style.display = 'none';
                updateTotals();
                showAlert('Voucher removed', 'success');
            }
        } catch (err) {
            console.error('removeVoucher error:', err);
        }
    };

    // ── Display applied voucher state ─────────────────────────────────
    window.displayAppliedVoucher = function () {
        const v = window.appliedVoucher;
        if (!v) return;

        const codeDisplay = document.getElementById('voucher-code-display');
        const discountDisplay = document.getElementById('voucher-discount-display');
        const appliedEl = document.getElementById('applied-voucher');
        const discountRow = document.getElementById('discount-row');
        const discountAmount = document.getElementById('discount-amount');

        if (codeDisplay) codeDisplay.textContent = v.code;
        if (discountDisplay) discountDisplay.textContent = PLAN_CURRENCY + ' ' + v.discount.toFixed(2);
        if (appliedEl) appliedEl.style.display = 'block';
        if (discountRow) discountRow.style.display = 'flex';
        if (discountAmount) discountAmount.textContent = '-' + PLAN_CURRENCY + ' ' + v.discount.toFixed(2);

        updateTotals();
    };

    // ── Recalculate displayed totals ──────────────────────────────────
    window.updateTotals = function () {
        const discount = window.appliedVoucher
            ? parseFloat(window.appliedVoucher.discount)
            : 0;

        const taxable = INITIAL_SUBTOTAL - discount + INITIAL_SHIPPING;
        const tax = taxable * TAX_RATE;
        const total = taxable + tax;

        const fmt = (n) => PLAN_CURRENCY + ' ' + n.toFixed(2);

        const subtotalEl = document.getElementById('subtotal');
        const shippingEl = document.getElementById('shipping');
        const taxEl = document.getElementById('tax');
        const totalEl = document.getElementById('total');
        const discountAmt = document.getElementById('discount-amount');

        if (subtotalEl) subtotalEl.textContent = fmt(INITIAL_SUBTOTAL);
        if (shippingEl) shippingEl.textContent = INITIAL_SHIPPING > 0 ? fmt(INITIAL_SHIPPING) : 'Free';
        if (taxEl) taxEl.textContent = fmt(tax);
        if (totalEl) totalEl.textContent = fmt(total);
        if (discountAmt && window.appliedVoucher) {
            discountAmt.textContent = '-' + fmt(discount);
        }
    };
})();
