<?php
/**
 * View: account/billing.php
 *
 * Variables from ShopAccountController::billing():
 *   $member     – authenticated member
 *   $active_tab – 'billing'
 *
 * Payment method cards are read from Stripe directly in JS via Stripe Elements
 * or a dedicated API endpoint. Mutations (add/remove) are handled by separate
 * endpoints not covered in this read-only controller.
 */
?>
<style>
    .billing-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* ── Payment method card ──────────────────────────────────────── */
    .pm-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .pm-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px 18px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--white);
        transition: var(--transition);
    }

    .pm-card.is-default {
        border-color: var(--ink);
    }

    .pm-card:hover {
        box-shadow: var(--shadow-sm);
    }

    .pm-card__network {
        width: 48px;
        height: 32px;
        border-radius: 4px;
        background: var(--surface);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .pm-card__info {
        flex: 1;
        min-width: 0;
    }

    .pm-card__number {
        font-size: 14px;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: 2px;
    }

    .pm-card__expiry {
        font-size: 12px;
        color: var(--ink-muted);
    }

    .pm-card__default-badge {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        background: var(--accent-light);
        color: var(--accent);
        padding: 2px 8px;
        border-radius: 100px;
        flex-shrink: 0;
    }

    .pm-card__actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    /* Add card button */
    .add-card-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 16px 18px;
        border: 1.5px dashed var(--border);
        border-radius: var(--radius-sm);
        background: none;
        cursor: pointer;
        font-family: var(--font-body);
        font-size: 14px;
        color: var(--ink-muted);
        transition: var(--transition);
        width: 100%;
    }

    .add-card-btn:hover {
        border-color: var(--ink);
        color: var(--ink);
        background: var(--surface);
    }

    /* ── Add card modal ───────────────────────────────────────────── */
    .stripe-field-wrapper {
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 12px 14px;
        background: var(--white);
        transition: var(--transition);
        margin-bottom: 14px;
    }

    .stripe-field-wrapper:focus-within {
        border-color: var(--ink);
    }

    .stripe-field-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--ink-muted);
        margin-bottom: 8px;
    }

    /* Security note */
    .security-note {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: var(--ink-muted);
        margin-top: 8px;
    }

    .security-note svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
    }

    /* Billing address section */
    .form-field {
        margin-bottom: 14px;
    }

    .form-field label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--ink-muted);
        margin-bottom: 6px;
    }

    .form-field input, .form-field select {
        width: 100%;
        padding: 10px 12px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        font-family: var(--font-body);
        font-size: 14px;
        color: var(--ink);
        background: var(--white);
        outline: none;
        transition: var(--transition);
    }

    .form-field input:focus, .form-field select:focus {
        border-color: var(--ink);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    /* Empty payment state */
    .no-payment-state {
        text-align: center;
        padding: 40px 24px;
    }

    .no-payment-state__icon {
        font-size: 40px;
        margin-bottom: 12px;
        opacity: .4;
    }

    .no-payment-state__title {
        font-family: var(--font-display);
        font-size: 18px;
        margin-bottom: 6px;
    }

    .no-payment-state__sub {
        font-size: 14px;
        color: var(--ink-muted);
        margin-bottom: 20px;
    }
</style>

<?php
$page_title = 'Billing';
?>

@include('subscriptions/account/_layout')


<!-- Page content slot -->
<main class="page-content">

    <div class="page-heading">
        <h1 class="page-heading__title">Billing</h1>
        <p class="page-heading__sub">Manage your payment methods and billing details</p>
    </div>

    <div class="billing-grid">

        <!-- Payment methods -->
        <div class="card">
            <div class="card__header">
                <span class="card__title">Payment Methods</span>
                <button class="btn btn--ghost btn--sm" onclick="openAddCardModal()">+ Add card</button>
            </div>
            <div class="card__body" id="payment-methods-body">
                <!-- Populated by JS from your payment API endpoint -->
                <div class="no-payment-state" id="pm-loading-state">
                    <div class="no-payment-state__icon">💳</div>
                    <div class="no-payment-state__title">Loading payment methods…</div>
                    <div class="no-payment-state__sub">Fetching your saved cards.</div>
                </div>
            </div>
        </div>

        <!-- Billing address (informational — mutations via account settings) -->
        <div class="card">
            <div class="card__header">
                <span class="card__title">Billing Address</span>
            </div>
            <div class="card__body" id="billing-address-body">
                <div style="font-size:14px; color:var(--ink-muted);">
                    Loading billing address…
                </div>
            </div>
        </div>

        <!-- Security note -->
        <div style="display:flex; align-items:flex-start; gap:10px; padding:14px 18px; background:var(--white); border:1px solid var(--border); border-radius:var(--radius-sm); font-size:13px; color:var(--ink-muted); line-height:1.6;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 style="width:18px;height:18px;flex-shrink:0;margin-top:1px;">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <span>
            Your payment information is encrypted and stored securely via Stripe. We never store your card number directly.
        </span>
        </div>
    </div>

    <!-- ── Add card modal ───────────────────────────────────────────────── -->
    <div class="modal-overlay" id="add-card-modal" role="dialog" aria-modal="true">
        <div class="modal">
            <div class="modal__header">
                <h2 class="modal__title">Add Payment Method</h2>
                <button class="modal__close" onclick="closeAddCardModal()">×</button>
            </div>
            <div class="modal__body">
                <div class="stripe-field-label">Card details</div>
                <div class="stripe-field-wrapper" id="stripe-card-element">
                    <!-- Stripe Elements mounts here -->
                    <div style="height:20px; background:var(--surface); border-radius:3px; display:flex; align-items:center; padding:0 4px;">
                        <span style="font-size:13px; color:var(--ink-muted);">Card number, expiry, CVC</span>
                    </div>
                </div>
                <div id="card-errors" style="color:var(--red); font-size:13px; margin-bottom:12px; display:none;"></div>

                <label style="display:flex; align-items:center; gap:10px; font-size:14px; cursor:pointer; margin-bottom:16px;">
                    <input type="checkbox" id="set-as-default" checked
                           style="accent-color:var(--ink); width:16px; height:16px;">
                    Set as default payment method
                </label>

                <div class="security-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Secured by Stripe. Your card details are encrypted.
                </div>
            </div>
            <div class="modal__footer">
                <button class="btn btn--ghost" onclick="closeAddCardModal()">Cancel</button>
                <button class="btn btn--primary" onclick="submitAddCard()" id="add-card-submit">Add Card</button>
            </div>
        </div>
    </div>

    <!-- Remove card confirmation -->
    <div class="modal-overlay" id="remove-card-modal" role="dialog" aria-modal="true">
        <div class="modal">
            <div class="modal__header">
                <h2 class="modal__title">Remove Card</h2>
                <button class="modal__close"
                        onclick="document.getElementById('remove-card-modal').classList.remove('open')">×
                </button>
            </div>
            <div class="modal__body">
                <p style="font-size:14px; color:var(--ink-soft);">
                    Are you sure you want to remove this card? This action cannot be undone.
                    Any active subscriptions using this card will need to be updated.
                </p>
            </div>
            <div class="modal__footer">
                <button class="btn btn--ghost"
                        onclick="document.getElementById('remove-card-modal').classList.remove('open')">Cancel
                </button>
                <button class="btn btn--danger" onclick="confirmRemoveCard()" id="remove-confirm-btn">Remove Card
                </button>
            </div>
        </div>
    </div>
</main>

</body>
</html>


<script>
    let removeCardId = null;

    /* ── Load payment methods from your API ────────────────────── */
    async function loadPaymentMethods() {
        try {
            const res = await fetch('/api/<?= \App\Framework\Support\SiteContext::slug() ?>/member/payment-methods', {
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
            });
            const data = await res.json();
            renderPaymentMethods(data.data.payment_methods ?? [], data.data.billing_address ?? null);
        } catch (e) {
            document.getElementById('payment-methods-body').innerHTML = `
                <div class="no-payment-state">
                    <div class="no-payment-state__icon">💳</div>
                    <div class="no-payment-state__title">No payment methods</div>
                    <div class="no-payment-state__sub">Add a payment method to speed up checkout and renewals.</div>
                    <button class="btn btn--primary" onclick="openAddCardModal()">Add card</button>
                </div>`;
        }
    }

    const NETWORK_ICONS = {visa: '💳', mastercard: '💳', amex: '💳', discover: '💳'};

    function renderPaymentMethods(methods, billingAddress) {
        const body = document.getElementById('payment-methods-body');

        if (!methods || methods.length === 0) {
            body.innerHTML = `
                <div class="no-payment-state">
                    <div class="no-payment-state__icon">💳</div>
                    <div class="no-payment-state__title">No payment methods saved</div>
                    <div class="no-payment-state__sub">Add a payment method to speed up checkout and renewals.</div>
                    <button class="btn btn--primary" onclick="openAddCardModal()">Add card</button>
                </div>`;
        } else {
            let html = '<div class="pm-list">';
            methods.forEach(pm => {
                const icon = NETWORK_ICONS[pm.brand?.toLowerCase()] ?? '💳';
                const isDefault = pm.is_default ? 'is-default' : '';
                html += `
                    <div class="pm-card ${isDefault}">
                        <div class="pm-card__network">${icon}</div>
                        <div class="pm-card__info">
                            <div class="pm-card__number">${pm.brand ?? 'Card'} ···· ${pm.last4 ?? '????'}</div>
                            <div class="pm-card__expiry">Expires ${pm.exp_month ?? '--'}/${pm.exp_year ?? '--'}</div>
                        </div>
                        ${pm.is_default ? '<span class="pm-card__default-badge">Default</span>' : ''}
                        <div class="pm-card__actions">
                            ${!pm.is_default ? `<button class="btn btn--ghost btn--sm" onclick="setDefault('${pm.id}')">Set default</button>` : ''}
                            <button class="btn btn--danger btn--sm" onclick="openRemoveCard('${pm.id}')">Remove</button>
                        </div>
                    </div>`;
            });
            html += `<button class="add-card-btn" onclick="openAddCardModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add new card
                     </button>`;
            html += '</div>';
            body.innerHTML = html;
        }

        // Billing address
        if (billingAddress) {
            const addrEl = document.getElementById('billing-address-body');
            addrEl.innerHTML = `
                <div style="font-size:14px; color:var(--ink-soft); line-height:1.8;">
                    <strong style="color:var(--ink);">${billingAddress.name ?? ''}</strong><br>
                    ${billingAddress.line1 ?? ''}<br>
                    ${billingAddress.line2 ? billingAddress.line2 + '<br>' : ''}
                    ${billingAddress.city ?? ''}, ${billingAddress.postcode ?? ''}<br>
                    ${billingAddress.country ?? ''}
                </div>`;
        }
    }

    function openAddCardModal() {
        document.getElementById('add-card-modal').classList.add('open');
        // In production: mount Stripe Elements here
    }

    function closeAddCardModal() {
        document.getElementById('add-card-modal').classList.remove('open');
    }

    async function submitAddCard() {
        // In production: call stripe.createPaymentMethod() then POST to /account/billing/add-card
        alert('Card addition will be wired up to Stripe Elements. Implement stripe.confirmCardSetup() here.');
    }

    function openRemoveCard(id) {
        removeCardId = id;
        document.getElementById('remove-card-modal').classList.add('open');
    }

    async function confirmRemoveCard() {
        const btn = document.getElementById('remove-confirm-btn');
        btn.disabled = true;
        btn.textContent = 'Removing…';
        try {
            const res = await fetch('/account/billing/remove-card', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({payment_method_id: removeCardId}),
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('remove-card-modal').classList.remove('open');
                loadPaymentMethods();
            } else {
                alert(data.message ?? 'Failed to remove card.');
                btn.disabled = false;
                btn.textContent = 'Remove Card';
            }
        } catch (e) {
            alert('Network error.');
            btn.disabled = false;
            btn.textContent = 'Remove Card';
        }
    }

    async function setDefault(id) {
        try {
            const res = await fetch('/account/billing/set-default', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({payment_method_id: id}),
            });
            const data = await res.json();
            if (data.success) loadPaymentMethods();
            else alert(data.message ?? 'Failed.');
        } catch (e) {
            alert('Network error.');
        }
    }

    // Modal backdrop
    ['add-card-modal', 'remove-card-modal'].forEach(id => {
        document.getElementById(id).addEventListener('click', function (e) {
            if (e.target === this) this.classList.remove('open');
        });
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
        }
    });

    // Load on mount
    loadPaymentMethods();
</script>