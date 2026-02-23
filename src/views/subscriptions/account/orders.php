<?php
/**
 * View: account/orders.php
 *
 * Variables from ShopAccountController::orders():
 *   $member     – authenticated member
 *   $orders     – sliced Collection of Order models
 *   $pagination – ['current_page', 'total_pages', 'total', 'per_page']
 *   $active_tab – 'orders'
 */
?>
<style>
    /* ── Orders table ────────────────────────────────────────────── */
    .orders-table {
        width: 100%;
        border-collapse: collapse;
    }

    .orders-table th {
        text-align: left;
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--ink-muted);
        padding: 0 16px 12px;
        border-bottom: 1px solid var(--border);
    }

    .orders-table td {
        padding: 15px 16px;
        border-bottom: 1px solid var(--border-soft);
        vertical-align: middle;
        font-size: 14px;
    }

    .orders-table tr:last-child td {
        border-bottom: none;
    }

    .orders-table tbody tr {
        transition: background var(--transition);
    }

    .orders-table tbody tr:hover td {
        background: var(--paper);
    }

    .order-num-link {
        font-weight: 600;
        color: var(--ink);
        text-decoration: none;
        transition: var(--transition);
    }

    .order-num-link:hover {
        color: var(--gold);
    }

    .order-items-summary {
        font-size: 12px;
        color: var(--ink-muted);
        margin-top: 2px;
    }

    .order-amount {
        font-family: var(--font-display);
        font-size: 16px;
        color: var(--ink);
    }

    .table-actions {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    /* Mobile card fallback */
    @media (max-width: 680px) {
        .orders-table thead {
            display: none;
        }

        .orders-table, .orders-table tbody,
        .orders-table tr, .orders-table td {
            display: block;
            width: 100%;
        }

        .orders-table tr {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            margin-bottom: 10px;
            overflow: hidden;
        }

        .orders-table td {
            border-bottom: 1px solid var(--border-soft);
            padding: 12px 16px;
        }

        .orders-table td:last-child {
            border-bottom: none;
        }

        .orders-table td[data-label]::before {
            content: attr(data-label);
            display: block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--ink-muted);
            margin-bottom: 3px;
        }
    }

    /* ── Pagination ──────────────────────────────────────────────── */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 28px;
        flex-wrap: wrap;
    }

    .pag-btn {
        min-width: 34px;
        height: 34px;
        padding: 0 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border);
        border-radius: var(--radius-xs);
        background: var(--white);
        font-family: var(--font-body);
        font-size: 13px;
        color: var(--ink);
        text-decoration: none;
        transition: var(--transition);
        cursor: pointer;
    }

    .pag-btn:hover:not(.active):not(.disabled) {
        border-color: var(--ink);
        background: var(--paper);
    }

    .pag-btn.active {
        background: var(--ink);
        color: #fff;
        border-color: var(--ink);
        pointer-events: none;
    }

    .pag-btn.disabled {
        opacity: .35;
        pointer-events: none;
    }

    /* ── Order cancel modal ──────────────────────────────────────── */
    .oc-step {
        display: none;
    }

    .oc-step.active {
        display: block;
    }

    .oc-order-summary {
        background: var(--paper);
        border: 1px solid var(--border-soft);
        border-radius: var(--radius-sm);
        padding: 14px 16px;
        margin-bottom: 16px;
        font-size: 14px;
    }

    .oc-order-summary__row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        color: var(--ink-soft);
    }

    .oc-order-summary__row strong {
        color: var(--ink);
    }

    .oc-reason-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin: 14px 0;
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

    .refund-notice {
        background: var(--blue-light);
        border: 1px solid #93c5fd;
        border-radius: var(--radius-sm);
        padding: 14px 16px;
        font-size: 13px;
        color: var(--blue);
        margin: 14px 0;
        line-height: 1.6;
    }
</style>

<?php $page_title = 'Orders'; ?>

@include('subscriptions/account/_layout')

<main class="page-content">

    <div class="page-heading">
        <div class="page-heading__eyebrow">Account</div>
        <h1 class="page-heading__title">Order History</h1>
        <p class="page-heading__sub"><?= number_format($pagination['total']) ?> total
            order<?= $pagination['total'] !== 1 ? 's' : '' ?></p>
    </div>

    <div class="card">
        <?php if ($orders->isEmpty()): ?>
            <div class="card__body">
                <div class="empty-state">
                    <div class="empty-state__icon">🛍️</div>
                    <div class="empty-state__title">No orders yet</div>
                    <div class="empty-state__sub">Your purchase history will appear here.</div>
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions" class="btn btn--primary">Start
                        shopping</a>
                </div>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="orders-table">
                    <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th style="text-align:right;">Total</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order):
                        $canCancel = in_array($order->status ?? '', ['pending', 'processing']);
                        $itemCount = $order->items?->count() ?? 0;
                        ?>
                        <tr>
                            <td data-label="Order">
                                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/orders/<?= $order->id ?>"
                                   class="order-num-link">
                                    #<?= htmlspecialchars($order->order_number ?? $order->id) ?>
                                </a>
                                <?php if ($itemCount > 0): ?>
                                    <div class="order-items-summary"><?= $itemCount ?>
                                        item<?= $itemCount !== 1 ? 's' : '' ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Date" style="color:var(--ink-muted);">
                                <?= $order->created_at->format('j M Y') ?>
                            </td>
                            <td data-label="Status">
                                <span class="badge badge--<?= htmlspecialchars($order->status ?? 'pending') ?>">
                                    <?= htmlspecialchars(ucfirst($order->status ?? 'pending')) ?>
                                </span>
                            </td>
                            <td data-label="Payment">
                                <span class="badge badge--<?= htmlspecialchars($order->payment_status ?? 'pending') ?>">
                                    <?= htmlspecialchars(ucfirst($order->payment_status ?? 'pending')) ?>
                                </span>
                            </td>
                            <td data-label="Total" style="text-align:right;">
                                <div class="order-amount">£<?= number_format($order->total ?? 0, 2) ?></div>
                            </td>
                            <td data-label="Actions">
                                <div class="table-actions">
                                    <a href="/press-stack/account/orders/<?= $order->id ?>"
                                       class="btn btn--ghost btn--sm">View</a>
                                    <?php if ($canCancel): ?>
                                        <button
                                                class="btn btn--danger btn--sm"
                                                onclick="openOrderCancelModal(
                                                <?= (int)$order->id ?>,
                                                <?= json_encode('#' . ($order->order_number ?? $order->id)) ?>,
                                                <?= json_encode(number_format($order->total ?? 0, 2)) ?>
                                                        )">Cancel
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div style="padding:0 16px 20px;">
                    <nav class="pagination">
                        <a href="?page=<?= max(1, $pagination['current_page'] - 1) ?>"
                           class="pag-btn <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>">←</a>
                        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                            <a href="?page=<?= $p ?>"
                               class="pag-btn <?= $p === $pagination['current_page'] ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a href="?page=<?= min($pagination['total_pages'], $pagination['current_page'] + 1) ?>"
                           class="pag-btn <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">→</a>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>


    <!-- ── Order cancellation modal (3-step) ─────────────────────────── -->
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
                <!-- Step 1 -->
                <div class="oc-step active" id="oc-step-1">
                    <div class="oc-order-summary" id="oc-order-summary"></div>
                    <div class="refund-notice">
                        <strong>Refund information:</strong> If eligible, your refund will be returned to your original
                        payment method within 5–10 business days.
                    </div>
                    <p style="font-size:13px; color:var(--ink-muted);">
                        Cancellation is only available for pending or unshipped orders.
                    </p>
                </div>

                <!-- Step 2 -->
                <div class="oc-step" id="oc-step-2">
                    <p style="font-size:14px; color:var(--ink-soft); margin-bottom:4px;">Why are you cancelling?</p>
                    <div class="oc-reason-list">
                        <?php
                        $orderReasons = [
                                'wrong_item' => 'Ordered the wrong item',
                                'changed_mind' => 'Changed my mind',
                                'delivery_delay' => 'Delivery taking too long',
                                'other' => 'Other',
                        ];
                        foreach ($orderReasons as $val => $label): ?>
                            <label class="oc-reason-radio" onclick="selectOcReason(this)">
                                <input type="radio" name="oc_reason" value="<?= $val ?>">
                                <?= htmlspecialchars($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="oc-step" id="oc-step-3">
                    <div style="text-align:center; padding:20px 0;">
                        <div style="font-size:48px; margin-bottom:14px;">✅</div>
                        <p style="font-family:var(--font-display); font-size:22px; margin-bottom:8px;">Order
                            Cancelled</p>
                        <p style="font-size:14px; color:var(--ink-muted); line-height:1.6;">
                            Your order has been cancelled. If a refund applies, it will appear
                            on your payment method within 5–10 business days.
                        </p>
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
    let ocOrderId = null;
    let ocOrderNum = '';
    let ocOrderTotal = '';
    let ocStep = 1;

    const OC_STEPS = ['Review', 'Reason', 'Done'];

    function openOrderCancelModal(id, orderNum, total) {
        ocOrderId = id;
        ocOrderNum = orderNum;
        ocOrderTotal = total;
        ocStep = 1;

        document.getElementById('oc-order-summary').innerHTML = `
            <div class="oc-order-summary__row"><span>Order</span><strong>${orderNum}</strong></div>
            <div class="oc-order-summary__row"><span>Total</span><strong>£${total}</strong></div>
            <div class="oc-order-summary__row"><span>Refund to</span><strong>Original payment method</strong></div>`;

        document.querySelectorAll('.oc-reason-radio').forEach(r => r.classList.remove('selected'));
        document.querySelectorAll('input[name="oc_reason"]').forEach(r => r.checked = false);

        renderOcModal();
        document.getElementById('order-cancel-modal').classList.add('open');
    }

    function closeOrderCancelModal() {
        document.getElementById('order-cancel-modal').classList.remove('open');
        if (ocStep === 3) window.location.reload();
        ocOrderId = null;
    }

    function renderOcModal() {
        let html = '<div class="steps">';
        OC_STEPS.forEach((s, i) => {
            const n = i + 1;
            const cls = n < ocStep ? 'done' : (n === ocStep ? 'active' : '');
            html += `<div class="step ${cls}"><div class="step__num">${n < ocStep ? '✓' : n}</div> ${s}</div>`;
            if (i < OC_STEPS.length - 1) html += '<div class="step__divider"></div>';
        });
        html += '</div>';
        document.getElementById('oc-step-indicator').innerHTML = html;

        [1, 2, 3].forEach(n => {
            document.getElementById(`oc-step-${n}`).classList.toggle('active', n === ocStep);
        });

        const footer = document.getElementById('oc-modal-footer');
        if (ocStep === 1) {
            footer.innerHTML = `
                <button class="btn btn--ghost" onclick="closeOrderCancelModal()">Keep Order</button>
                <button class="btn btn--danger" onclick="advanceOcStep()">Continue</button>`;
        } else if (ocStep === 2) {
            footer.innerHTML = `
                <button class="btn btn--ghost" onclick="retreatOcStep()">Back</button>
                <button class="btn btn--danger" onclick="submitOrderCancellation()" id="oc-confirm-btn">Confirm Cancel</button>`;
        } else {
            footer.innerHTML = `<button class="btn btn--primary" onclick="closeOrderCancelModal()">Close</button>`;
        }
    }

    function advanceOcStep() {
        ocStep = Math.min(3, ocStep + 1);
        renderOcModal();
    }

    function retreatOcStep() {
        ocStep = Math.max(1, ocStep - 1);
        renderOcModal();
    }

    function selectOcReason(label) {
        document.querySelectorAll('.oc-reason-radio').forEach(r => r.classList.remove('selected'));
        label.classList.add('selected');
    }

    async function submitOrderCancellation() {
        const reason = document.querySelector('input[name="oc_reason"]:checked')?.value ?? '';
        if (!reason) {
            document.querySelectorAll('.oc-reason-radio').forEach(r => r.style.borderColor = 'var(--red)');
            setTimeout(() => document.querySelectorAll('.oc-reason-radio').forEach(r => r.style.borderColor = ''), 1400);
            return;
        }

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
                alert(data.message ?? 'Something went wrong. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Confirm Cancel';
            }
        } catch (e) {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Confirm Cancel';
        }
    }

    document.getElementById('order-cancel-modal').addEventListener('click', function (e) {
        if (e.target === this) closeOrderCancelModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeOrderCancelModal();
    });
</script>