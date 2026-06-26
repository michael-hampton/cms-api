<?php
/**
 * View: account/billing-history.php
 *
 * Variables from ShopAccountController::billingHistory():
 *   $member                      – authenticated member
 *   $billing_history_rows         – paginated billing history row array
 *   $billing_history_pagination   – ['current_page', 'total_pages', 'total', 'per_page']
 *   $filters                     – ['search' => string, 'date_from' => string, 'date_to' => string, 'status' => string]
 *   $active_tab                  – 'billing_history'
 */
$page_title = 'Billing history';
$billingRows = $billing_history_rows ?? [];
$pagination = $billing_history_pagination ?? [
    'current_page' => 1,
    'total_pages' => 1,
    'total' => count($billingRows),
    'per_page' => 10,
];
$filters = $filters ?? ['search' => '', 'date_from' => '', 'date_to' => '', 'status' => ''];
$hasActiveFilters = !empty($filters['search']) || !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['status']);
?>
<style>
    .orders-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; padding: 16px 16px 0; }
    .orders-filters__group { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 160px; }
    .orders-filters__group label { font-size: 10.5px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--ink-muted); }
    .orders-filters__input { height: 36px; padding: 0 10px; border: 1px solid var(--border); border-radius: var(--radius-xs); background: var(--white); font-family: var(--font-body); font-size: 13px; color: var(--ink); transition: border-color var(--transition); width: 100%; box-sizing: border-box; }
    .orders-filters__input:focus { outline: none; border-color: var(--ink); }
    .orders-filters__input::placeholder { color: var(--ink-muted); opacity: .6; }
    .orders-filters__actions { display: flex; gap: 6px; align-items: flex-end; flex-shrink: 0; }
    .orders-filters__clear { font-size: 12px; color: var(--ink-muted); text-decoration: underline; background: none; border: none; cursor: pointer; padding: 0 4px; height: 36px; font-family: var(--font-body); transition: color var(--transition); }
    .orders-filters__clear:hover { color: var(--ink); }
    .orders-filters__divider { height: 1px; background: var(--border-soft); margin: 14px 16px 0; }
    .orders-filter-summary { padding: 8px 16px 0; font-size: 12px; color: var(--ink-muted); }
    .orders-filter-summary strong { color: var(--ink); }
    .orders-table { width: 100%; border-collapse: collapse; }
    .orders-table th { text-align: left; font-size: 10.5px; font-weight: 600; letter-spacing: .09em; text-transform: uppercase; color: var(--ink-muted); padding: 0 16px 12px; border-bottom: 1px solid var(--border); }
    .orders-table td { padding: 15px 16px; border-bottom: 1px solid var(--border-soft); vertical-align: middle; font-size: 14px; }
    .orders-table tr:last-child td { border-bottom: none; }
    .orders-table tbody tr { transition: background var(--transition); }
    .orders-table tbody tr:hover td { background: var(--paper); }
    .order-num-link { font-weight: 600; color: var(--ink); text-decoration: none; transition: var(--transition); }
    .order-num-link:hover { color: var(--gold); }
    .order-items-summary { font-size: 12px; color: var(--ink-muted); margin-top: 2px; }
    .order-amount { font-family: var(--font-display); font-size: 16px; color: var(--ink); }
    .table-actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .empty-search-state { padding: 48px 24px; text-align: center; }
    .empty-search-state__icon { font-size: 36px; margin-bottom: 12px; }
    .empty-search-state__title { font-family: var(--font-display); font-size: 18px; margin-bottom: 6px; }
    .empty-search-state__sub { font-size: 13px; color: var(--ink-muted); }
    .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 28px; flex-wrap: wrap; }
    .pag-btn { min-width: 34px; height: 34px; padding: 0 10px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border); border-radius: var(--radius-xs); background: var(--white); font-family: var(--font-body); font-size: 13px; color: var(--ink); text-decoration: none; transition: var(--transition); cursor: pointer; }
    .pag-btn:hover:not(.active):not(.disabled) { border-color: var(--ink); background: var(--paper); }
    .pag-btn.active { background: var(--ink); color: #fff; border-color: var(--ink); pointer-events: none; }
    .pag-btn.disabled { opacity: .35; pointer-events: none; }

    @media (max-width: 680px) {
        .orders-filters { flex-direction: column; }
        .orders-filters__group { min-width: unset; width: 100%; }
        .orders-filters__actions { width: 100%; justify-content: flex-end; }
        .orders-table thead { display: none; }
        .orders-table, .orders-table tbody, .orders-table tr, .orders-table td { display: block; width: 100%; }
        .orders-table tr { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius-sm); margin-bottom: 10px; overflow: hidden; }
        .orders-table td { border-bottom: 1px solid var(--border-soft); padding: 12px 16px; }
        .orders-table td:last-child { border-bottom: none; }
        .orders-table td[data-label]::before { content: attr(data-label); display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--ink-muted); margin-bottom: 3px; }
    }
</style>

@include('subscriptions/account/_layout')

<main class="page-content">
    <div class="page-heading">
        <div class="page-heading__eyebrow">Account</div>
        <h1 class="page-heading__title">Billing history</h1>
        <p class="page-heading__sub"><?= number_format($pagination['total']) ?> billing record<?= $pagination['total'] !== 1 ? 's' : '' ?></p>
    </div>

    <div class="card">
        <form id="billing-history-filter-form" method="GET" action="">
            <div class="orders-filters">
                <div class="orders-filters__group" style="max-width:260px;">
                    <label for="filter-search">Order, reference or subscription</label>
                    <input id="filter-search" type="search" name="search" class="orders-filters__input" placeholder="e.g. ORD-1234" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" autocomplete="off">
                </div>

                <div class="orders-filters__group" style="max-width:180px;">
                    <label for="filter-date-from">From</label>
                    <input id="filter-date-from" type="date" name="date_from" class="orders-filters__input" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
                </div>

                <div class="orders-filters__group" style="max-width:180px;">
                    <label for="filter-date-to">To</label>
                    <input id="filter-date-to" type="date" name="date_to" class="orders-filters__input" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
                </div>

                <div class="orders-filters__group" style="max-width:160px;">
                    <label for="filter-status">Status</label>
                    <select id="filter-status" name="status" class="orders-filters__input" data-billing-filter-submit>
                        <option value="">All statuses</option>
                        <?php
                        $statusOptions = [
                            'paid' => 'Paid',
                            'pending' => 'Pending',
                            'processing' => 'Processing',
                            'completed' => 'Completed',
                            'failed' => 'Failed',
                            'cancelled' => 'Cancelled',
                            'refunded' => 'Refunded',
                        ];
                        foreach ($statusOptions as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($filters['status'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="orders-filters__actions">
                    <button type="submit" class="btn btn--primary btn--sm">Search</button>
                    <?php if ($hasActiveFilters): ?>
                        <button type="button" class="orders-filters__clear" data-billing-action="clearFilters">Clear</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <?php if ($hasActiveFilters): ?>
            <div class="orders-filter-summary">
                Showing <strong><?= number_format($pagination['total']) ?></strong> result<?= $pagination['total'] !== 1 ? 's' : '' ?>
                <?php if (!empty($filters['search'])): ?>
                    for <strong>"<?= htmlspecialchars($filters['search']) ?>"</strong>
                <?php endif; ?>
                <?php if (!empty($filters['status'])): ?>
                    &mdash; status <strong><?= htmlspecialchars(ucfirst($filters['status'])) ?></strong>
                <?php endif; ?>
                <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                    <?php if (!empty($filters['date_from']) && !empty($filters['date_to'])): ?>
                        between <strong><?= htmlspecialchars($filters['date_from']) ?></strong> and <strong><?= htmlspecialchars($filters['date_to']) ?></strong>
                    <?php elseif (!empty($filters['date_from'])): ?>
                        from <strong><?= htmlspecialchars($filters['date_from']) ?></strong>
                    <?php else: ?>
                        up to <strong><?= htmlspecialchars($filters['date_to']) ?></strong>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="orders-filters__divider"></div>

        <?php if (empty($billingRows)): ?>
            <?php if ($hasActiveFilters): ?>
                <div class="empty-search-state">
                    <div class="empty-search-state__icon">🔍</div>
                    <div class="empty-search-state__title">No billing records found</div>
                    <div class="empty-search-state__sub">Try adjusting your search, status or date range.</div>
                </div>
            <?php else: ?>
                <div class="card__body">
                    <div class="empty-state">
                        <div class="empty-state__icon">🧾</div>
                        <div class="empty-state__title">No billing history yet</div>
                        <div class="empty-state__sub">Subscription order payments will appear here once available.</div>
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
                        <th>Reference</th>
                        <th style="text-align:right;">Total</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($billingRows as $row): ?>
                        <tr>
                            <td data-label="Order">
                                <a href="<?= htmlspecialchars((string) ($row['order_url'] ?? '#')) ?>" class="order-num-link">
                                    #<?= htmlspecialchars((string) ($row['order_number'] ?? $row['order_id'] ?? '—')) ?>
                                </a>
                                <?php if (!empty($row['subscription_id'])): ?>
                                    <div class="order-items-summary">Subscription #<?= htmlspecialchars((string) $row['subscription_id']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Date" style="color:var(--ink-muted);"><?= htmlspecialchars((string) ($row['date'] ?? '—')) ?></td>
                            <td data-label="Status">
                                <span class="badge badge--<?= htmlspecialchars((string) ($row['order_status'] ?? 'pending')) ?>">
                                    <?= htmlspecialchars(ucfirst((string) ($row['order_status'] ?? 'pending'))) ?>
                                </span>
                            </td>
                            <td data-label="Payment">
                                <span class="badge badge--<?= htmlspecialchars((string) ($row['payment_status'] ?? 'pending')) ?>">
                                    <?= htmlspecialchars(ucfirst((string) ($row['payment_status'] ?? 'pending'))) ?>
                                </span>
                            </td>
                            <td data-label="Reference" style="color:var(--ink-muted);"><?= htmlspecialchars((string) ($row['reference'] ?? '—')) ?></td>
                            <td data-label="Total" style="text-align:right;"><div class="order-amount"><?= htmlspecialchars((string) ($row['amount'] ?? '—')) ?></div></td>
                            <td data-label="Actions">
                                <div class="table-actions">
                                    <?php if (!empty($row['order_url'])): ?>
                                        <a href="<?= htmlspecialchars((string) $row['order_url']) ?>" class="btn btn--ghost btn--sm">View order</a>
                                    <?php endif; ?>
                                    <?php if (!empty($row['invoice_url'])): ?>
                                        <a href="<?= htmlspecialchars((string) $row['invoice_url']) ?>" class="btn btn--ghost btn--sm">View invoice</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

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
                        <a href="<?= $paginationBase ?>page=<?= max(1, $pagination['current_page'] - 1) ?>" class="pag-btn <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>">←</a>
                        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                            <a href="<?= $paginationBase ?>page=<?= $p ?>" class="pag-btn <?= $p === $pagination['current_page'] ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a href="<?= $paginationBase ?>page=<?= min($pagination['total_pages'], $pagination['current_page'] + 1) ?>" class="pag-btn <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">→</a>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
</div><!-- /.shell -->
<script>
    document.querySelector('[data-billing-action="clearFilters"]')?.addEventListener('click', function () {
        window.location.href = window.location.pathname;
    });

    document.querySelector('[data-billing-filter-submit]')?.addEventListener('change', function () {
        document.getElementById('billing-history-filter-form')?.submit();
    });
</script>
</body>
</html>
