<!-- Earnings transactions table section -->
<div class="oc-card" style="animation:fadeSlideIn .45s ease;">
    <div class="oc-card__header">
        <span class="oc-card__title">Transaction History</span>
        <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">
            <?= count($transactions) ?>
        </span>
    </div>

    <?php
    $txItems = is_object($transactions) && method_exists($transactions, 'all')
        ? $transactions->all()
        : (is_array($transactions) ? $transactions : []);
    ?>

    <?php if (empty($txItems)): ?>
        <div style="padding:40px 24px;text-align:center;color:var(--slate);">
            <svg viewBox="0 0 20 20" fill="currentColor" width="28" style="opacity:.2;display:block;margin:0 auto 12px;">
                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z" clip-rule="evenodd"/>
            </svg>
            <div style="font-weight:500;">No transactions yet</div>
            <div style="font-size:.82rem;margin-top:4px;">Publish a paid article and sales will appear here.</div>
        </div>
    <?php else: ?>
        <table class="oc-table">
            <thead>
            <tr>
                <th>Date</th>
                <th>Article</th>
                <th>Type</th>
                <th style="text-align:right;">Amount</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($txItems as $tx):
                $txArr = is_array($tx) ? $tx : (method_exists($tx, 'toArray') ? $tx->toArray() : (array)$tx);
                $isRefund = ($txArr['status'] ?? '') === 'refunded';
                $amount = (int)($txArr['amount'] ?? 0);
                $currency = strtoupper($txArr['currency'] ?? 'GBP');
                $symbol = $currency === 'GBP' ? '£' : '$';
                ?>
                <tr>
                    <td style="white-space:nowrap;color:var(--slate);font-size:.78rem;">
                        <?= !empty($txArr['created_at']) && is_object($txArr['created_at']) && method_exists($txArr['created_at'], 'format')
                            ? $txArr['created_at']->format('d M Y')
                            : (!empty($txArr['created_at']) ? htmlspecialchars((string)$txArr['created_at']) : '–') ?>
                    </td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($txArr['page_title'] ?? $txArr['title'] ?? '–') ?>
                    </td>
                    <td>
                        <span class="oc-badge <?= $isRefund ? 'oc-badge--revoked' : 'oc-badge--published' ?>" style="font-size:.65rem;">
                            <?= $isRefund ? 'Refund' : 'Sale' ?>
                        </span>
                    </td>
                    <td style="text-align:right;font-weight:600;color:<?= $isRefund ? 'var(--red)' : 'var(--green)' ?>;">
                        <?= $isRefund ? '−' : '+' ?><?= $symbol ?><?= number_format($amount / 100, 2) ?>
                    </td>
                    <td><span style="font-size:.75rem;color:var(--slate);"><?= ucfirst($txArr['status'] ?? 'succeeded') ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
