<?php
/**
 * View: account/orders.php
 *
 * Variables from ShopAccountController::orders():
 *   $member      – authenticated member
 *   $orders      – Collection of Order models (already filtered/paginated)
 *   $pagination  – ['current_page', 'total_pages', 'total', 'per_page']
 *   $filters     – ['search' => string, 'date_from' => string, 'date_to' => string, 'status' => string]
 *   $active_tab  – 'orders'
 */
?>
<style>
    /* ── Filter bar ──────────────────────────────────────────────── */
    .orders-filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: flex-end;
        padding: 16px 16px 0;
    }

    .orders-filters__group {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
        min-width: 160px;
    }

    .orders-filters__group label {
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--ink-muted);
    }

    .orders-filters__input {
        height: 36px;
        padding: 0 10px;
        border: 1px solid var(--border);
        border-radius: var(--radius-xs);
        background: var(--white);
        font-family: var(--font-body);
        font-size: 13px;
        color: var(--ink);
        transition: border-color var(--transition);
        width: 100%;
        box-sizing: border-box;
    }

    .orders-filters__input:focus {
        outline: none;
        border-color: var(--ink);
    }

    .orders-filters__input::placeholder {
        color: var(--ink-muted);
        opacity: .6;
    }

    .orders-filters__actions {
        display: flex;
        gap: 6px;
        align-items: flex-end;
        flex-shrink: 0;
    }

    .orders-filters__clear {
        font-size: 12px;
        color: var(--ink-muted);
        text-decoration: underline;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0 4px;
        height: 36px;
        font-family: var(--font-body);
        transition: color var(--transition);
    }

    .orders-filters__clear:hover {
        color: var(--ink);
    }

    .orders-filters__divider {
        height: 1px;
        background: var(--border-soft);
        margin: 14px 16px 0;
    }

    .orders-filter-summary {
        padding: 8px 16px 0;
        font-size: 12px;
        color: var(--ink-muted);
    }

    .orders-filter-summary strong {
        color: var(--ink);
    }

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

    /* ── Empty search state ──────────────────────────────────────── */
    .empty-search-state {
        padding: 48px 24px;
        text-align: center;
    }

    .empty-search-state__icon {
        font-size: 36px;
        margin-bottom: 12px;
    }

    .empty-search-state__title {
        font-family: var(--font-display);
        font-size: 18px;
        margin-bottom: 6px;
    }

    .empty-search-state__sub {
        font-size: 13px;
        color: var(--ink-muted);
    }

    /* Mobile card fallback */
    @media (max-width: 680px) {
        .orders-filters {
            flex-direction: column;
        }

        .orders-filters__group {
            min-width: unset;
            width: 100%;
        }

        .orders-filters__actions {
            width: 100%;
            justify-content: flex-end;
        }

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

        <!-- ── Filters ───────────────────────────────────────────────────── -->
        <form id="orders-filter-form" method="GET" action="">
            <div class="orders-filters">

                <div class="orders-filters__group" style="max-width:260px;">
                    <label for="filter-search">Order number</label>
                    <input
                            id="filter-search"
                            type="search"
                            name="search"
                            class="orders-filters__input"
                            placeholder="e.g. ORD-1234"
                            value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                            autocomplete="off"
                    >
                </div>

                <div class="orders-filters__group" style="max-width:180px;">
                    <label for="filter-date-from">From</label>
                    <input
                            id="filter-date-from"
                            type="date"
                            name="date_from"
                            class="orders-filters__input"
                            value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>"
                    >
                </div>

                <div class="orders-filters__group" style="max-width:180px;">
                    <label for="filter-date-to">To</label>
                    <input
                            id="filter-date-to"
                            type="date"
                            name="date_to"
                            class="orders-filters__input"
                            value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>"
                    >
                </div>

                <div class="orders-filters__group" style="max-width:160px;">
                    <label for="filter-status">Status</label>
                    <select
                            id="filter-status"
                            name="status"
                            class="orders-filters__input"
                            data-order-filter-submit
                    >
                        <option value="">All statuses</option>
                        <?php
                        $statusOptions = [
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                        ];
                        foreach ($statusOptions as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($filters['status'] ?? '') === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="orders-filters__actions">
                    <button type="submit" class="btn btn--primary btn--sm">Search</button>
                    <?php if (!empty($filters['search']) || !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['status'])): ?>
                        <button type="button" class="orders-filters__clear" data-order-action="clearFilters">Clear</button>
                    <?php endif; ?>
                </div>

            </div>
        </form>

        <?php
        $hasActiveFilters = !empty($filters['search']) || !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['status']);
        if ($hasActiveFilters): ?>
            <div class="orders-filter-summary">
                Showing <strong><?= number_format($pagination['total']) ?></strong>
                result<?= $pagination['total'] !== 1 ? 's' : '' ?>
                <?php if (!empty($filters['search'])): ?>
                    for <strong>"<?= htmlspecialchars($filters['search']) ?>"</strong>
                <?php endif; ?>
                <?php if (!empty($filters['status'])): ?>
                    &mdash; status <strong><?= htmlspecialchars(ucfirst($filters['status'])) ?></strong>
                <?php endif; ?>
                <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                    <?php if (!empty($filters['date_from']) && !empty($filters['date_to'])): ?>
                        between <strong><?= htmlspecialchars($filters['date_from']) ?></strong> and
                        <strong><?= htmlspecialchars($filters['date_to']) ?></strong>
                    <?php elseif (!empty($filters['date_from'])): ?>
                        from <strong><?= htmlspecialchars($filters['date_from']) ?></strong>
                    <?php else: ?>
                        up to <strong><?= htmlspecialchars($filters['date_to']) ?></strong>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="orders-filters__divider"></div>

        <!-- ── Table ─────────────────────────────────────────────────────── -->
        <?php if ($orders->isEmpty()): ?>
            <?php if ($hasActiveFilters): ?>
                <div class="empty-search-state">
                    <div class="empty-search-state__icon">🔍</div>
                    <div class="empty-search-state__title">No orders found</div>
                    <div class="empty-search-state__sub">Try adjusting your search or date range.</div>
                </div>
            <?php else: ?>
                <div class="card__body">
                    <div class="empty-state">
                        <div class="empty-state__icon">🛍️</div>
                        <div class="empty-state__title">No orders yet</div>
                        <div class="empty-state__sub">Your purchase history will appear here.</div>
                        <a href="/press-stack"
                           class="btn btn--primary">Start shopping</a>
                    </div>
                </div>
            <?php endif; ?>
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
                                <a href="/press-stack/account/orders/<?= $order->id ?>"
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
                                                type="button"
                                                data-order-cancel
                                                data-order-id="<?= (int)$order->id ?>"
                                                data-order-number="<?= htmlspecialchars('#' . ($order->order_number ?? $order->id)) ?>"
                                                data-order-total="<?= htmlspecialchars(number_format($order->total ?? 0, 2)) ?>">Cancel
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination (preserves active filters across pages) -->
            <?php if ($pagination['total_pages'] > 1):
                $paginationBase = '?' . http_build_query(array_filter([
                                'search' => $filters['search'] ?? '',
                                'date_from' => $filters['date_from'] ?? '',
                                'date_to' => $filters['date_to'] ?? '',
                                'status' => $filters['status'] ?? '',
                        ]));
                $paginationBase = $paginationBase === '?' ? '?' : $paginationBase . '&';
                ?>
                <div style="padding:0 16px 20px;">
                    <nav class="pagination">
                        <a href="<?= $paginationBase ?>page=<?= max(1, $pagination['current_page'] - 1) ?>"
                           class="pag-btn <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>">←</a>
                        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                            <a href="<?= $paginationBase ?>page=<?= $p ?>"
                               class="pag-btn <?= $p === $pagination['current_page'] ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a href="<?= $paginationBase ?>page=<?= min($pagination['total_pages'], $pagination['current_page'] + 1) ?>"
                           class="pag-btn <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">→</a>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>


    <!-- ── Order cancellation modal (3-step) ─────────────────────────── -->
    <div class="modal-overlay"
         id="order-cancel-modal"
         role="dialog"
         aria-modal="true"
         data-cancel-endpoint="/press-stack/account/orders/__ORDER_ID__/cancel"
         data-completed-url="/press-stack/account/orders">
        <div class="modal">
            <div class="modal__header">
                <div>
                    <h2 class="modal__title">Cancel Order</h2>
                    <div id="oc-step-indicator" style="margin-top:10px;"></div>
                </div>
                <button class="modal__close" type="button" data-order-action="close" aria-label="Close">×</button>
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
                            <label class="oc-reason-radio">
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
<script src="/public/js/order-account.js" defer></script>
</body>
</html>
