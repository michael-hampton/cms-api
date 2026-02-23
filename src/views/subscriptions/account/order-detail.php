<?php
/**
 * View: account/order-detail.php
 *
 * Variables from ShopAccountController::orderDetail():
 *   $member     – authenticated member
 *   $order      – Order model with items, history, refunds eager-loaded
 *   $active_tab – 'orders'
 */
$canCancel = in_array($order->status ?? '', ['pending', 'processing']);
?>
<style>
    /* ── Receipt card ────────────────────────────────────────────── */
    .receipt {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-xs);
    }

    /* Gold accent top strip */
    .receipt::before {
        content: '';
        display: block;
        height: 3px;
        background: linear-gradient(90deg, var(--gold) 0%, var(--gold-mid) 60%, transparent 100%);
    }

    .receipt__hero {
        padding: 28px 28px 22px;
        border-bottom: 1px solid var(--border-soft);
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
    }

    .receipt__order-num {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--gold);
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .receipt__order-num::before {
        content: '';
        width: 16px;
        height: 1.5px;
        background: var(--gold);
        border-radius: 2px;
    }

    .receipt__date {
        font-family: var(--font-display);
        font-size: 24px;
        color: var(--ink);
        margin-bottom: 10px;
        letter-spacing: -.01em;
    }

    .receipt__hero-right {
        text-align: right;
    }

    .receipt__total-label {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--ink-muted);
        margin-bottom: 5px;
    }

    .receipt__total {
        font-family: var(--font-display);
        font-size: 38px;
        color: var(--ink);
        line-height: 1;
        letter-spacing: -.02em;
    }

    /* Sections */
    .receipt__section {
        padding: 20px 28px;
        border-bottom: 1px solid var(--border-soft);
    }

    .receipt__section-title {
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--ink-muted);
        margin-bottom: 14px;
    }

    /* Line items */
    .line-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 11px 0;
        border-bottom: 1px solid var(--border-soft);
    }

    .line-item:last-child {
        border-bottom: none;
    }

    .line-item__name {
        font-size: 14px;
        font-weight: 500;
        color: var(--ink);
    }

    .line-item__meta {
        font-size: 12px;
        color: var(--ink-muted);
        margin-top: 2px;
    }

    .line-item__price {
        font-size: 14px;
        font-weight: 600;
        color: var(--ink);
        flex-shrink: 0;
        font-family: var(--font-display);
    }

    /* Totals */
    .totals-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 14px;
        color: var(--ink-soft);
    }
    .totals-row--total {
        font-weight: 700;
        font-size: 16px;
        color: var(--ink);
        padding-top: 12px;
        border-top: 1.5px solid var(--border);
        margin-top: 4px;
    }

    .totals-row--total span:last-child {
        font-family: var(--font-display);
        font-size: 18px;
    }

    .totals-row--discount {
        color: var(--green);
    }

    /* Address */
    .address-block {
        font-size: 14px;
        color: var(--ink-soft);
        line-height: 1.8;
    }

    .address-block strong {
        color: var(--ink);
    }

    /* Payment method row */
    .payment-method-row {
        display: flex;
        align-items: center;
        gap: 13px;
        font-size: 14px;
    }
    .payment-method-row__icon {
        width: 42px;
        height: 28px;
        background: var(--paper-dark);
        border: 1px solid var(--border);
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    /* Timeline */
    .timeline {
        display: flex;
        flex-direction: column;
    }

    .timeline-item {
        display: flex;
        gap: 14px;
        padding-bottom: 16px;
        position: relative;
    }

    .timeline-item:last-child {
        padding-bottom: 0;
    }

    .timeline-item:last-child .timeline-item__line {
        display: none;
    }

    .timeline-item__dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--border);
        flex-shrink: 0;
        margin-top: 5px;
    }

    .timeline-item__dot.done {
        background: var(--green);
    }

    .timeline-item__line {
        position: absolute;
        left: 4px;
        top: 14px;
        bottom: 0;
        width: 2px;
        background: var(--border-soft);
    }

    .timeline-item__label {
        font-size: 14px;
        font-weight: 500;
        color: var(--ink);
    }
    .timeline-item__date {
        font-size: 12px;
        color: var(--ink-muted);
        margin-top: 2px;
    }

    /* Actions bar */
    .actions-bar {
        padding: 18px 28px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        background: var(--paper);
    }

    /* ── Order cancel modal ──────────────────────────────────────── */
    .oc-step {
        display: none;
    }

    .oc-step.active {
        display: block;
    }

    .oc-reason-radio {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 14px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: var(--transition);
        font-size: 14px;
        color: var(--ink-soft);
        margin-bottom: 8px;
    }

    .oc-reason-radio:hover {
        border-color: var(--ink-muted);
        color: var(--ink);
    }

    .oc-reason-radio.selected {
        border-color: var(--ink);
        background: var(--paper);
        color: var(--ink);
        font-weight: 500;
    }

    .oc-reason-radio input {
        accent-color: var(--ink);
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }
</style>

<?php $page_title = 'Order #' . ($order->order_number ?? $order->id); ?>

@include('subscriptions/account/_layout')

<main class="page-content">

    <div style="margin-bottom:18px;">
        <a href="/press-stack/account/orders"
           class="btn btn--ghost btn--sm">← Back to orders</a>
    </div>

    <div class="page-heading">
        <div class="page-heading__eyebrow">Order Detail</div>
        <h1 class="page-heading__title">Order #<?= htmlspecialchars($order->order_number ?? $order->id) ?></h1>
        <p class="page-heading__sub">Placed on <?= $order->created_at->format('j F Y') ?></p>
    </div>

    <div class="receipt">

        <!-- Hero -->
        <div class="receipt__hero">
            <div>
                <div class="receipt__order-num">
                    Order <?= htmlspecialchars($order->order_number ?? '#' . $order->id) ?></div>
                <div class="receipt__date"><?= $order->created_at->format('j F Y') ?></div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:4px;">
                    <span class="badge badge--<?= htmlspecialchars($order->status ?? 'pending') ?>">
                        <?= htmlspecialchars(ucfirst($order->status ?? 'pending')) ?>
                    </span>
                    <span class="badge badge--<?= htmlspecialchars($order->payment_status ?? 'pending') ?>">
                        <?= htmlspecialchars(ucfirst($order->payment_status ?? 'pending')) ?>
                    </span>
                </div>
            </div>
            <div class="receipt__hero-right">
                <div class="receipt__total-label">Order total</div>
                <div class="receipt__total">£<?= number_format($order->total ?? 0, 2) ?></div>
            </div>
        </div>

        <!-- Items -->
        <div class="receipt__section">
            <div class="receipt__section-title">Items</div>
            <?php if ($order->items && $order->items->count() > 0): ?>
                <?php foreach ($order->items as $item): ?>
                    <div class="line-item">
                        <div>
                            <div class="line-item__name"><?= htmlspecialchars($item->subscription?->plan_name ?? $item->product_name ?? 'Item') ?></div>
                            <?php if (!empty($item->variant)): ?>
                                <div class="line-item__meta"><?= htmlspecialchars($item->variant) ?></div>
                            <?php endif; ?>
                            <?php if ($item->quantity > 1): ?>
                                <div class="line-item__meta">Qty: <?= (int)$item->quantity ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="line-item__price">
                            £<?= number_format(($item->unit_price ?? 0) * ($item->quantity ?? 1), 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="font-size:14px; color:var(--ink-muted);">No itemised data available.</p>
            <?php endif; ?>
        </div>

        <!-- Totals summary -->
        <div class="receipt__section">
            <div class="receipt__section-title">Summary</div>
            <div class="totals-row">
                <span>Subtotal</span>
                <span>£<?= number_format($order->subtotal ?? $order->total ?? 0, 2) ?></span>
            </div>
            <?php if (!empty($order->discount) && $order->discount > 0): ?>
                <div class="totals-row totals-row--discount">
                    <span>Discount</span>
                    <span>−£<?= number_format($order->discount, 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($order->shipping) && $order->shipping > 0): ?>
                <div class="totals-row">
                    <span>Shipping</span>
                    <span>£<?= number_format($order->shipping, 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($order->tax) && $order->tax > 0): ?>
                <div class="totals-row">
                    <span>Tax</span>
                    <span>£<?= number_format($order->tax, 2) ?></span>
                </div>
            <?php endif; ?>
            <div class="totals-row totals-row--total">
                <span>Total</span>
                <span>£<?= number_format($order->total ?? 0, 2) ?></span>
            </div>
        </div>

        <!-- Payment + shipping address -->
        <div class="receipt__section" style="display:grid; grid-template-columns:1fr 1fr; gap:28px; flex-wrap:wrap;">
            <div>
                <div class="receipt__section-title">Payment</div>
                <div class="payment-method-row">
                    <div class="payment-method-row__icon">💳</div>
                    <div>
                        <div style="font-size:14px; font-weight:500;">
                            <?= htmlspecialchars(ucfirst($order->payment_method ?? 'Card')) ?>
                        </div>
                        <div style="font-size:12px; color:var(--ink-muted);">
                            <?= $order->created_at->format('j F Y') ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($order->shippingAddress): ?>
                <div>
                    <div class="receipt__section-title">Shipping to</div>
                    <div class="address-block">
                        <?php $addr = $order->shippingAddress; ?>
                        <strong><?= htmlspecialchars($addr->name ?? '') ?></strong><br>
                        <?= htmlspecialchars($addr->line1 ?? '') ?><br>
                        <?php if (!empty($addr->line2)): ?>
                            <?= htmlspecialchars($addr->line2) ?><br>
                        <?php endif; ?>
                        <?= htmlspecialchars($addr->city ?? '') ?>, <?= htmlspecialchars($addr->postcode ?? '') ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Timeline -->
        <?php if ($order->history && $order->history->count() > 0): ?>
            <div class="receipt__section">
                <div class="receipt__section-title">Order timeline</div>
                <div class="timeline">
                    <?php foreach ($order->history->sortBy('created_at') as $event): ?>
                        <div class="timeline-item">
                            <div class="timeline-item__dot done"></div>
                            <div class="timeline-item__line"></div>
                            <div>
                                <div class="timeline-item__label">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $event->status ?? $event->event ?? ''))) ?>
                                </div>
                                <div class="timeline-item__date"><?= $event->created_at->format('j M Y, H:i') ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="actions-bar">
            <?php if ($canCancel): ?>
                <button
                        class="btn btn--danger"
                        onclick='openOrderCancelModal(
                        <?= (int)$order->id ?>,
                        <?= json_encode('#' . ($order->order_number ?? $order->id)) ?>,
                        <?= json_encode(number_format($order->total ?? 0, 2)) ?>
                                )'>Cancel Order
                </button>
            <?php endif; ?>
            <a href="/press-stack/account/orders"
               class="btn btn--ghost">← Back to orders</a>
        </div>
    </div>


    <!-- ── Order cancel modal ─────────────────────────────────────────── -->
    <div class="modal-overlay" id="order-cancel-modal" role="dialog" aria-modal="true">
        <div class="modal">
            <div class="modal__header">
                <div>
                    <h2 class="modal__title">Cancel Order</h2>
                    <div id="oc-step-indicator" style="margin-top:10px;"></div>
                </div>
                <button class="modal__close" onclick="closeOrderCancelModal()" aria-label="Close">×</button>
            </div>
            <div class="modal__body">
                <div class="oc-step active" id="oc-step-1">
                    <div style="background:var(--paper); border:1px solid var(--border-soft); border-radius:var(--radius-sm); padding:14px 16px; margin-bottom:16px; font-size:14px;"
                         id="oc-order-summary"></div>
                    <div style="background:var(--blue-light); border:1px solid #93c5fd; border-radius:var(--radius-sm); padding:14px; font-size:13px; color:var(--blue); line-height:1.6;">
                        <strong>Refund:</strong> If eligible, you'll receive a refund within 5–10 business days to your
                        original payment method.
                    </div>
                </div>
                <div class="oc-step" id="oc-step-2">
                    <p style="font-size:14px; color:var(--ink-soft); margin-bottom:12px;">Why are you cancelling?</p>
                    <div style="display:flex; flex-direction:column; gap:0;">
                        <?php foreach (['wrong_item' => 'Ordered wrong item', 'changed_mind' => 'Changed my mind', 'delivery_delay' => 'Delivery taking too long', 'other' => 'Other'] as $v => $l): ?>
                            <label class="oc-reason-radio"
                                   onclick="this.parentNode.querySelectorAll('label').forEach(x => x.classList.remove('selected')); this.classList.add('selected');">
                                <input type="radio" name="oc_reason" value="<?= $v ?>"
                                       style="accent-color:var(--ink); width:16px; height:16px; flex-shrink:0;">
                                <?= htmlspecialchars($l) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="oc-step" id="oc-step-3">
                    <div style="text-align:center; padding:20px 0;">
                        <div style="font-size:48px; margin-bottom:14px;">✅</div>
                        <p style="font-family:var(--font-display); font-size:22px; margin-bottom:8px;">Order
                            Cancelled</p>
                        <p style="font-size:14px; color:var(--ink-muted);">Your refund will appear within 5–10 business
                            days.</p>
                    </div>
                </div>
            </div>
            <div class="modal__footer" id="oc-modal-footer"></div>
        </div>
    </div>

</main>
</div><!-- /.shell -->
</body>
</html>


<script>
    let ocOrderId = <?= $canCancel ? (int)$order->id : 'null' ?>;
    let ocStep = 1;
    const OC_STEPS = ['Review', 'Reason', 'Done'];

    function openOrderCancelModal(id, num, total) {
        ocOrderId = id;
        ocStep = 1;
        document.getElementById('oc-order-summary').innerHTML =
            `<div style="display:flex;justify-content:space-between;padding:4px 0;font-size:14px;color:var(--ink-soft);">
                <span>Order</span><strong style="color:var(--ink);">${num}</strong></div>
             <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:14px;color:var(--ink-soft);">
                <span>Total</span><strong style="color:var(--ink);">£${total}</strong></div>`;
        renderOcModal();
        document.getElementById('order-cancel-modal').classList.add('open');
    }

    function closeOrderCancelModal() {
        document.getElementById('order-cancel-modal').classList.remove('open');
        if (ocStep === 3) window.location.href = '/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/orders';
    }

    function renderOcModal() {
        let h = '<div class="steps">';
        OC_STEPS.forEach((s, i) => {
            const n = i + 1;
            const cls = n < ocStep ? 'done' : (n === ocStep ? 'active' : '');
            h += `<div class="step ${cls}"><div class="step__num">${n < ocStep ? '✓' : n}</div> ${s}</div>`;
            if (i < OC_STEPS.length - 1) h += '<div class="step__divider"></div>';
        });
        h += '</div>';
        document.getElementById('oc-step-indicator').innerHTML = h;
        [1, 2, 3].forEach(n => document.getElementById(`oc-step-${n}`).classList.toggle('active', n === ocStep));

        const f = document.getElementById('oc-modal-footer');
        if (ocStep === 1) {
            f.innerHTML = `<button class="btn btn--ghost" onclick="closeOrderCancelModal()">Keep Order</button>
                           <button class="btn btn--danger" onclick="ocStep++;renderOcModal()">Continue</button>`;
        } else if (ocStep === 2) {
            f.innerHTML = `<button class="btn btn--ghost" onclick="ocStep--;renderOcModal()">Back</button>
                           <button class="btn btn--danger" onclick="submitOrderCancellation()" id="oc-confirm-btn">Confirm Cancel</button>`;
        } else {
            f.innerHTML = `<button class="btn btn--primary" onclick="closeOrderCancelModal()">Close</button>`;
        }
    }

    async function submitOrderCancellation() {
        const reason = document.querySelector('input[name="oc_reason"]:checked')?.value;
        if (!reason) return;
        const btn = document.getElementById('oc-confirm-btn');
        btn.disabled = true;
        btn.textContent = 'Cancelling…';
        try {
            const res = await fetch(`/account/orders/${ocOrderId}/cancel`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({reason}),
            });
            const data = await res.json();
            if (data.success) {
                ocStep = 3;
                renderOcModal();
            } else {
                alert(data.message ?? 'Error');
                btn.disabled = false;
                btn.textContent = 'Confirm Cancel';
            }
        } catch (e) {
            alert('Network error');
            btn.disabled = false;
            btn.textContent = 'Confirm Cancel';
        }
    }

    document.getElementById('order-cancel-modal').addEventListener('click', e => {
        if (e.target === document.getElementById('order-cancel-modal')) closeOrderCancelModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeOrderCancelModal();
    });
</script>