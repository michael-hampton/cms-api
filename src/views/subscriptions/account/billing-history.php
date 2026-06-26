<?php
$page_title = 'Billing history';
$billingRows = $billing_history_rows ?? [];
?>

@include('subscriptions/account/_layout')

<main class="page-content">
    <div class="page-heading">
        <div class="page-heading__eyebrow">Subscription billing</div>
        <h1 class="page-heading__title">Billing history</h1>
        <p class="page-heading__sub">View paid subscription orders and their related payment records.</p>
    </div>

    <section class="card" aria-labelledby="billing-history-title">
        <div class="card__header">
            <span class="card__title" id="billing-history-title">Billing history</span>
        </div>
        <div class="card__body">
            <?php if (!empty($billingRows)): ?>
                <div class="table-wrap">
                    <table class="account-table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order</th>
                            <th>Subscription</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Links</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($billingRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($row['date'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['order_number'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['subscription_id'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['reference'] ?? '—')) ?></td>
                                <td><span class="badge badge--<?= htmlspecialchars((string) ($row['status'] ?? 'pending')) ?>"><?= htmlspecialchars((string) ($row['status'] ?? '—')) ?></span></td>
                                <td><?= htmlspecialchars((string) ($row['amount'] ?? '—')) ?></td>
                                <td style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <?php if (!empty($row['order_url'])): ?>
                                        <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars((string) $row['order_url']) ?>">View order</a>
                                    <?php endif; ?>
                                    <?php if (!empty($row['invoice_url'])): ?>
                                        <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars((string) $row['invoice_url']) ?>">View invoice</a>
                                    <?php endif; ?>
                                    <?php if (empty($row['order_url']) && empty($row['invoice_url'])): ?>
                                        <span class="muted">Not available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state__icon">🧾</div>
                    <div class="empty-state__title">No billing history yet</div>
                    <div class="empty-state__sub">Subscription order payments will appear here once available.</div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
</div>
</body>
</html>
