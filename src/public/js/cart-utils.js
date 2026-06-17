(function () {


    // ── Toast ─────────────────────────────────────────────────────────
    /*window.showToast = function (message, type = 'success') {
        const toast = document.getElementById('toast');
        if (!toast) return;
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3000);
    };*/

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
        if (!input) return;
        const code = input.value.trim();

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

            if (result.data?.valid) {
                window.appliedVoucher = {
                    code,
                    discount: parseFloat(result.data.discount || 0),
                    voucher_id: result.data.voucher_id,
                };
                displayAppliedVoucher();
                input.value = '';
                const msgEl = document.getElementById('voucher-message');
                if (msgEl) msgEl.textContent = '';
                showToast('Voucher applied successfully!', {
                    level: 'success',
                    times_out: true,
                });
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
                showToast('Voucher removed', {
                    level: 'info',
                    times_out: true,
                });
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
        const discount = parseFloat(v.discount || 0);

        if (discountDisplay) discountDisplay.textContent = PLAN_CURRENCY + ' ' + discount.toFixed(2);
        if (appliedEl) appliedEl.style.display = 'block';
        if (discountRow) discountRow.style.display = 'flex';
        if (discountAmount) discountAmount.textContent = '-' + PLAN_CURRENCY + ' ' + discount.toFixed(2);

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

    /**
     * Build a map of { merchantKey => { id, name, items[], subtotal } }
     * from a flat array of cart items, deduplicating by product/plan.
     */
    function _groupAndDedup(items) {
        // Step 1: deduplicate
        const deduped = {};
        for (const item of items) {
            const key = item.subscription_plan_id
                ? `plan:${item.subscription_plan_id}`
                : `product:${item.product_id ?? 'x'}:${item.variant_id ?? ''}`;

            if (!deduped[key]) {
                deduped[key] = {...item};
            } else {
                deduped[key].quantity = (deduped[key].quantity || 1) + (item.quantity || 1);
                deduped[key].subtotal = (deduped[key].subtotal || 0) + (item.subtotal || 0);
            }
        }

        // Step 2: group by merchant
        const groups = {};
        for (const item of Object.values(deduped)) {
            const merchantId = item.merchant_id ?? 0;
            const merchantName = merchantId
                ? (item.merchant_name || `Merchant ${merchantId}`)
                : 'Direct';

            if (!groups[merchantId]) {
                groups[merchantId] = {id: merchantId, name: merchantName, items: [], subtotal: 0};
            }
            groups[merchantId].items.push(item);
            groups[merchantId].subtotal += parseFloat(item.subtotal || 0);
        }

        return groups;
    }

    function _merchantInitials(name) {
        return (name || '?')
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map(w => w[0].toUpperCase())
            .join('') || '?';
    }

    function _escHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function _fmtPrice(n) {
        return PLAN_CURRENCY + ' ' + parseFloat(n || 0).toFixed(2);
    }

    function _itemRowHtml(item) {
        const isFreeGift = (item.options?.type === 'free_gift')
            || (item.options?.is_gift === true)
            || parseFloat(item.price || 0) === 0;

        const name = _escHtml(item.product_name || item.name || 'Item');
        const imgUrl = _escHtml(item.product_image || '');
        const qty = parseInt(item.quantity || 1, 10);
        const subtotal = parseFloat(item.subtotal || 0);
        const priceStr = isFreeGift ? 'FREE' : _fmtPrice(subtotal);

        const thumbHtml = imgUrl
            ? `<img src="${imgUrl}" alt="${name}" class="cs-item-img">`
            : `<div class="cs-item-img-placeholder" aria-hidden="true">
             <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
               <rect x="3" y="3" width="18" height="18" rx="2"/>
               <circle cx="8.5" cy="8.5" r="1.5"/>
               <polyline points="21 15 16 10 5 21"/>
             </svg>
           </div>`;

        let variantHtml = '';
        if (item.variant_id && item.variant_options) {
            const parts = Object.entries(item.variant_options)
                .map(([k, v]) => `${_escHtml(k.charAt(0).toUpperCase() + k.slice(1))}: <strong>${_escHtml(v)}</strong>`)
                .join(' · ');
            const sku = item.sku ? `<div class="cs-item-sku">SKU: ${_escHtml(item.sku)}</div>` : '';
            variantHtml = `<div class="cs-item-variant">${parts}</div>${sku}`;
        }

        const trialHtml = item.trial_days
            ? `<div style="display:inline-flex;align-items:center;gap:.35rem;background:#f0fdf4;border:1px solid #6ee7b7;border-radius:100px;padding:.2rem .75rem;font-size:.75rem;font-weight:600;color:#065f46;margin-top:.4rem;line-height:1.6;">
             <span aria-hidden="true">🎁</span> ${parseInt(item.trial_days, 10)}-day free trial included
           </div>`
            : '';

        const deliveryHtml = item.estimated_delivery
            ? `<div class="cs-item-delivery">📦 ${_escHtml(item.estimated_delivery)}</div>`
            : '';

        return `
    <div class="cs-item">
      ${thumbHtml}
      <div class="cs-item-details">
        <div class="cs-item-name">${name}</div>
        ${variantHtml}
        <div class="cs-item-meta">Qty: ${qty}</div>
        ${trialHtml}
        ${deliveryHtml}
      </div>
      <div class="cs-item-price${isFreeGift ? ' cs-item-free' : ''}">${priceStr}</div>
    </div>`;
    }

    /**
     * Re-render the #order-items element with the current cart items.
     * Call this after any cart mutation (add / update / remove / clear).
     *
     * @param {Array} items - flat array of cart item objects
     */
    window.renderOrderSummaryItems = function (items) {
        const container = document.getElementById('order-items');
        if (!container) return;

        if (!items || items.length === 0) {
            container.innerHTML = '';
            return;
        }

        const groups = _groupAndDedup(items);
        const groupKeys = Object.keys(groups);
        const groupCount = groupKeys.length;

        let html = '';

        groupKeys.forEach((merchantId, idx) => {
            const group = groups[merchantId];
            const showHeader = groupCount > 1 || parseInt(merchantId, 10) !== 0;

            html += `<div class="cs-merchant-group">`;
            html += _merchantHeaderHtml(group, showHeader);

            for (const item of group.items) {
                html += _itemRowHtml(item);
            }

            html += `</div>`;

            if (idx < groupCount - 1) {
                html += `<div class="cs-group-divider"></div>`;
            }
        });

        container.innerHTML = html;
    }

    window.updateCartCount = function (count) {
        const el = document.getElementById('cart-count');
        if (el) el.textContent = count;
    }

    function _merchantHeaderHtml(group, showHeader) {
        if (!showHeader) return '';
        const initials = _merchantInitials(group.name);
        return `
    <div class="cs-merchant-header">
      <div class="cs-merchant-avatar" aria-hidden="true">${_escHtml(initials)}</div>
      <div class="cs-merchant-meta">
        <div class="cs-merchant-name">${_escHtml(group.name)}</div>
        <span class="cs-merchant-pill">${group.items.length} item${group.items.length !== 1 ? 's' : ''}</span>
      </div>
      <div class="cs-merchant-subtotal">${_fmtPrice(group.subtotal)}</div>
    </div>`;
    }

    /**
     * Re-renders the cart items list, preserving merchant group headers.
     * Called after any CRUD operation (updateQuantity / removeItem / clearCart).
     */
    window.renderCartItemsList = function (items) {
        const container = document.getElementById('cart-items-list');
        if (!container) return;

        const groups = groupItemsByMerchant(items);

        container.innerHTML = Object.entries(groups).map(([merchantId, group]) => {
            const itemsHtml = group.items.map(item => {
                const isFree = (item.options?.type === 'free_gift')
                    || (item.options?.is_gift === true)
                    || parseFloat(item.price || 0) === 0;

                let variantHtml = '';
                if (item.variant_id && item.variant_options) {
                    const badges = Object.entries(item.variant_options)
                        .map(([k, v]) =>
                            `<span style="display:inline-block;background:var(--bg-light);color:var(--text-secondary);padding:.25rem .75rem;border-radius:1rem;font-size:.875rem;margin-right:.5rem;border:1px solid var(--border-color);">
                            ${k.charAt(0).toUpperCase() + k.slice(1)}: <strong>${v}</strong>
                        </span>`)
                        .join('');
                    const sku = item.sku ? `<div style="font-size:.75rem;color:var(--text-secondary);margin-top:.25rem;">SKU: ${item.sku}</div>` : '';
                    variantHtml = `<div style="margin-top:.5rem;">${badges}</div>${sku}`;
                }

                const priceHtml = isFree ? `<span style="color:#10b981;font-weight:700;font-size:1rem;">FREE</span>`
                    : `<span class="sale-price">${formatCurrency(item.price)}</span>`;
                const subtotalHtml = isFree ? `<span style="color:#10b981;font-weight:700;">FREE</span>`
                    : formatCurrency(item.subtotal);
                const deliveryHtml = item.estimated_delivery
                    ? `<span style="font-size:.75rem;color:var(--success-color);margin-top:.25rem;">📦 Delivery: ${item.estimated_delivery}</span>`
                    : '';

                return `
            <div class="cart-item" data-item-id="${item.id}">
                <img src="${item.product_image || '/images/placeholder.jpg'}" alt="${item.product_name}" class="item-image">
                <div class="item-details">
                    <a href="/shop/details/${item.product_slug}" class="item-name">${item.product_name}</a>
                    ${variantHtml}
                    <div class="item-price">${priceHtml}</div>
                    <div class="quantity-controls">
                        <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity - 1})" aria-label="Decrease">-</button>
                        <input type="number" class="qty-input" value="${item.quantity}" min="1"
                               onchange="updateQuantity(${item.id}, this.value)" aria-label="Quantity">
                        <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity + 1})" aria-label="Increase">+</button>
                    </div>
                    ${deliveryHtml}
                </div>
                <div class="item-actions">
                    <div class="item-subtotal">${subtotalHtml}</div>
                    <button class="remove-btn" onclick="removeItem(${item.id})" aria-label="Remove">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </div>`;
            }).join('');

            return `
        <div class="merchant-group">
            <div class="merchant-header">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         style="display:inline-block;vertical-align:middle;margin-right:.5rem;" aria-hidden="true">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    ${group.name}
                </h3>
                <p>${group.items.length} item(s)</p>
            </div>
            ${itemsHtml}
        </div>`;
        }).join('');
    }

    /* ── Cart item list (main column) — merchant-grouped ─────────────────── */
    /**
     * Groups a flat items array by merchant_id, preserving the merchant_name.
     * Returns { merchantId: { name, items: [] }, … }
     */
    function groupItemsByMerchant(items) {
        const groups = {};
        for (const item of items) {
            const mid = item.merchant_id ?? item.options?.merchant_id ?? 0;
            const name = (mid && item.merchant_name) ? item.merchant_name
                : mid ? 'Merchant ' + mid
                    : 'Direct';

            if (!groups[mid]) {
                groups[mid] = {name, items: []};
            }
            groups[mid].items.push(item);
        }
        return groups;
    }

    function formatCurrency(amount) {
        return PLAN_CURRENCY + parseFloat(amount).toFixed(2);
    }

    // ── Login status / saved addresses ───────────────────────────────────
    window.checkLoginStatus = async function () {
        try {
            const res = await fetch('/member/me');
            if (res.ok) {
                const data = await res.json();
                if (data.member) {
                    window.isLoggedIn = true;
                    window.currentMember = data.member;
                    if (requiresShipping) await loadSavedAddresses();
                    await loadSavedCards();
                }
            }
        } catch (e) { /* guest */
        }
    }

    window.handleCountryChange = function (countryCode) {
        const usBlock = document.getElementById('us-renewal-consent-block');
        if (!usBlock) return;
        if (countryCode === 'US') {
            usBlock.style.display = 'block';
            usBlock.classList.remove('consent-error');
        } else {
            usBlock.style.display = 'none';
            const cb = document.getElementById('us-renewal-consent');
            if (cb) cb.checked = false;
        }
    }

    async function loadSavedAddresses() {
        try {
            const res = await fetch(`/api/${SITE}/${window.currentMember.id}/addresses?type=shipping`);
            const data = await res.json();
            console.log('data', data)
            if (data.items?.length) displaySavedAddresses(data.items);
        } catch (e) {
            console.error(e);
        }
    }

    function displaySavedAddresses(addresses) {
        console.log('addresses', addresses)
        const container = document.getElementById('saved-addresses-list');
        const section = document.getElementById('saved-addresses-section');
        if (!container || !section) return;
        container.innerHTML = addresses.map(addr => `
        <label class="saved-address-card" for="addr-${addr.id}">
            <input type="radio" name="saved_address" value="${addr.id}" id="addr-${addr.id}"
                   onchange="selectAddress(${addr.id})">
            <div class="address-details">
                <strong>${addr.label || 'Address'}</strong>
                <p>${addr.formatted}</p>
            </div>
            ${addr.is_default ? '<span class="badge" style="position:static;background:var(--primary-color);">Default</span>' : ''}
        </label>`).join('');
        section.style.display = 'block';
        const shippingForm = document.getElementById('shipping-address-form');
        if (shippingForm) shippingForm.style.display = 'none';
    }

    selectAddress = function (id) {
        selectedAddressId = id;
        const form = document.getElementById('shipping-address-form');
        if (form) form.style.display = 'none';
    }
    window.showNewAddressForm = function () {
        selectedAddressId = null;
        document.getElementById('saved-addresses-section').style.display = 'none';
        document.getElementById('shipping-address-form').style.display = 'block';
        const btn = document.getElementById('back-to-saved-btn');
        if (btn) btn.style.display = 'block';
        document.querySelectorAll('[name="saved_address"]').forEach(r => r.checked = false);
    }
    window.showSavedAddresses = function () {
        selectedAddressId = null;
        document.getElementById('saved-addresses-section').style.display = 'block';
        document.getElementById('shipping-address-form').style.display = 'none';
        const btn = document.getElementById('back-to-saved-btn');
        if (btn) btn.style.display = 'none';
        document.querySelectorAll('[name="saved_address"]').forEach(r => r.checked = false);
    }
})();
