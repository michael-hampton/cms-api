<?php
$page_title = 'Billing history';
$grouped = $grouped ?? [];
$invoiceRows = [];

foreach (['current', 'action_required', 'previous'] as $group) {
    foreach ($grouped[$group] ?? [] as $subscription) {
        foreach (($subscription['billing_history'] ?? $subscription['invoice_history'] ?? []) as $invoice) {
            $invoiceRows[] = [
                'date' => $invoice['date'] ?? $invoice['issued_at'] ?? $invoice['created_at'] ?? '—',
                'number' => $invoice['invoice_number'] ?? $invoice['number'] ?? '—',
                'amount' => $invoice['amount'] ?? $invoice['total'] ?? '—',
                'pdf_url' => $invoice['pdf_url'] ?? $invoice['invoice_pdf'] ?? null,
            ];
        }
    }
}
?>

@include('subscriptions/account/_layout')

<main class="page-content">
    <div class="page-heading">
        <div class="page-heading__eyebrow">Subscription billing</div>
        <h1 class="page-heading__title">Billing history</h1>
        <p class="page-heading__sub">View subscription invoice records when billing history is available.</p>
    </div>

    <section class="card" aria-labelledby="billing-history-title">
        <div class="card__header">
            <span class="card__title" id="billing-history-title">Invoice history</span>
        </div>
        <div class="card__body">
            <?php if (!empty($invoiceRows)): ?>
                <div class="table-wrap">
                    <table class="account-table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice number</th>
                            <th>Amount</th>
                            <th>PDF</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($invoiceRows as $invoice): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $invoice['date']) ?></td>
                                <td><?= htmlspecialchars((string) $invoice['number']) ?></td>
                                <td><?= htmlspecialchars((string) $invoice['amount']) ?></td>
                                <td>
                                    <?php if (!empty($invoice['pdf_url'])): ?>
                                        <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars((string) $invoice['pdf_url']) ?>">Download PDF</a>
                                    <?php else: ?>
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
                    <div class="empty-state__sub">Invoice records will appear here once subscription billing history is available.</div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
</div>
</body>
</html>
