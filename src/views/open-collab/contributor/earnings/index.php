@section('logic')
<?php
/**
 * Template: open-collab/contributor/earnings/index.php
 * Variables:
 *   $totalEarnings    — int  (pence)
 *   $availableBalance — int  (pence)
 *   $totalPaid        — int  (pence)
 *   $totalInFlight    — int  (pence)
 *   $breakdown        — array of [ page_id, title, total(pence) ]
 *   $transactions     — Collection of ArticlePayment rows (joined to pages)
 *   $payouts          — Collection of Payout models
 *   $site             — string
 *   $currentUser      — AuthenticatedUser
 */
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<!-- Summary stats -->
<div class="oc-stats" style="animation:fadeSlideIn .4s ease;margin-bottom:24px;">
    <div class="oc-stat oc-stat--accent">
        <div class="oc-stat__label">Lifetime Earnings</div>
        <div class="oc-stat__value">£<?= number_format($totalEarnings / 100, 2) ?></div>
        <div class="oc-stat__sub">Gross revenue all time</div>
    </div>
    <div class="oc-stat oc-stat--green">
        <div class="oc-stat__label">Available Balance</div>
        <div class="oc-stat__value">£<?= number_format($availableBalance / 100, 2) ?></div>
        <div class="oc-stat__sub">Ready to withdraw</div>
    </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Total Paid Out</div>
        <div class="oc-stat__value">£<?= number_format($totalPaid / 100, 2) ?></div>
        <div class="oc-stat__sub">Received to date</div>
    </div>
    <?php if ($totalInFlight > 0): ?>
        <div class="oc-stat">
            <div class="oc-stat__label">In Progress</div>
            <div class="oc-stat__value">£<?= number_format($totalInFlight / 100, 2) ?></div>
            <div class="oc-stat__sub">Pending or approved</div>
        </div>
    <?php endif; ?>
</div>

<div class="oc-grid-sidebar" style="align-items:start;gap:24px;">

    <!-- Left: transactions + payouts -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Transaction history -->
        <div class="oc-card" style="animation:fadeSlideIn .45s ease;">
            <div class="oc-card__header">
                <span class="oc-card__title">Transaction History</span>
                <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                             padding:2px 8px;border-radius:10px;font-weight:600;">
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
                    <svg viewBox="0 0 20 20" fill="currentColor" width="28"
                         style="opacity:.2;display:block;margin:0 auto 12px;">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                              clip-rule="evenodd"/>
                    </svg>
                    <div style="font-weight:500;">No transactions yet</div>
                    <div style="font-size:.82rem;margin-top:4px;">
                        Publish a paid article and sales will appear here.
                    </div>
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
                                <?= !empty($txArr['created_at'])
                                        ? (is_string($txArr['created_at'])
                                                ? date('d M Y', strtotime($txArr['created_at']))
                                                : $txArr['created_at']->format('d M Y'))
                                        : '–' ?>
                            </td>
                            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= htmlspecialchars($txArr['page_title'] ?? $txArr['title'] ?? '–') ?>
                            </td>
                            <td>
                                <span class="oc-badge <?= $isRefund ? 'oc-badge--revoked' : 'oc-badge--published' ?>"
                                      style="font-size:.65rem;">
                                    <?= $isRefund ? 'Refund' : 'Sale' ?>
                                </span>
                            </td>
                            <td style="text-align:right;font-weight:600;
                                    color:<?= $isRefund ? 'var(--red)' : 'var(--green)' ?>;">
                                <?= $isRefund ? '−' : '+' ?><?= $symbol ?><?= number_format($amount / 100, 2) ?>
                            </td>
                            <td>
                                <span style="font-size:.75rem;color:var(--slate);">
                                    <?= ucfirst($txArr['status'] ?? 'succeeded') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Payout history -->
        <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
            <div class="oc-card__header">
                <span class="oc-card__title">Payout History</span>
                <a href="/contributor/payouts" class="oc-btn oc-btn--ghost oc-btn--sm">
                    Request payout
                </a>
            </div>

            <?php if ($payouts->isEmpty()): ?>
                <div style="padding:40px 24px;text-align:center;color:var(--slate);">
                    <div style="font-weight:500;margin-bottom:4px;">No payouts yet</div>
                    <div style="font-size:.82rem;">
                        Once your balance reaches the minimum threshold, you can
                        <a href="/contributor/payouts" style="color:var(--navy);">request a payout</a>.
                    </div>
                </div>
            <?php else: ?>
                <table class="oc-table">
                    <thead>
                    <tr>
                        <th>Payout ID</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Download</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payouts as $payout):
                        $status = $payout->status ?? 'pending';
                        $statusClass = match ($status) {
                            'paid' => 'oc-badge--published',
                            'approved' => 'oc-badge--free',
                            'pending' => 'oc-badge--waiting-approval',
                            'rejected' => 'oc-badge--revoked',
                            default => 'oc-badge--draft',
                        };
                        $currency = strtoupper($payout->currency ?? 'GBP');
                        $symbol = $currency === 'GBP' ? '£' : '$';
                        ?>
                        <tr>
                            <td style="font-family:monospace;font-size:.78rem;color:var(--slate);">
                                PAY-<?= str_pad($payout->id, 6, '0', STR_PAD_LEFT) ?>
                            </td>
                            <td style="font-weight:600;color:var(--navy);">
                                <?= $symbol ?><?= number_format((int)$payout->amount / 100, 2) ?>
                            </td>
                            <td>
                                <span class="oc-badge <?= $statusClass ?>">
                                    <?= ucfirst($status) ?>
                                </span>
                            </td>
                            <td style="font-size:.78rem;color:var(--slate);">
                                <?= $payout->created_at ? date('d M Y', strtotime($payout->created_at)) : '–' ?>
                            </td>
                            <td>
                                <?php if (in_array($status, ['paid', 'approved'])): ?>
                                    <a href="/api/<?= htmlspecialchars($site) ?>/open-collab/payouts/<?= (int)$payout->id ?>/statement"
                                       class="oc-btn oc-btn--ghost oc-btn--sm"
                                       download
                                       title="Download payout statement PDF">
                                        <svg viewBox="0 0 20 20" fill="currentColor" width="12"
                                             style="margin-right:3px;">
                                            <path fill-rule="evenodd"
                                                  d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                        PDF
                                    </a>
                                <?php else: ?>
                                    <span style="font-size:.75rem;color:var(--slate);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($payout->rejection_reason)): ?>
                        <tr>
                            <td colspan="5"
                                style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;">
                                <strong>Declined reason:</strong> <?= htmlspecialchars($payout->rejection_reason) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right: breakdown + quick actions -->
    <div style="display:flex;flex-direction:column;gap:20px;position:sticky;top:calc(var(--header-h) + 20px);">

        <!-- Revenue by article -->
        <div class="oc-card" style="animation:fadeSlideIn .45s ease;">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.9rem;">Revenue by Article</span>
            </div>

            <?php if (empty($breakdown)): ?>
                <div style="padding:24px;text-align:center;color:var(--slate);font-size:.85rem;">
                    No revenue yet.
                </div>
            <?php else: ?>
                <div style="padding:4px 0;">
                    <?php foreach ($breakdown as $item):
                        $itemTotal = (int)($item['total'] ?? 0);
                        $pct = $totalEarnings > 0 ? min(100, round($itemTotal / $totalEarnings * 100)) : 0;
                        ?>
                        <div style="padding:12px 20px;border-bottom:1px solid var(--border);">
                            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;">
                                <span style="font-size:.82rem;color:var(--navy);font-weight:500;
                                             max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                      title="<?= htmlspecialchars($item['title'] ?? '') ?>">
                                    <?= htmlspecialchars($item['title'] ?? 'Untitled') ?>
                                </span>
                                <span style="font-size:.875rem;font-weight:700;color:var(--navy);">
                                    £<?= number_format($itemTotal / 100, 2) ?>
                                </span>
                            </div>
                            <!-- Revenue bar -->
                            <div style="height:3px;background:var(--border);border-radius:2px;overflow:hidden;">
                                <div style="height:100%;width:<?= $pct ?>%;background:var(--amber);border-radius:2px;transition:width .4s;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick actions -->
        <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
            <div class="oc-card__body" style="padding:18px 20px;display:flex;flex-direction:column;gap:10px;">
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
                             color:var(--slate);margin-bottom:4px;">Finance links
                </div>
                <a href="/contributor/payouts"
                   class="oc-btn oc-btn--amber oc-btn--block">
                    Request a payout
                </a>
                <a href="/contributor/disputes"
                   class="oc-btn oc-btn--ghost oc-btn--block">
                    Earnings disputes
                </a>
                <a href="/contributor/settings#payment"
                   class="oc-btn oc-btn--ghost oc-btn--block">
                    Payout method settings
                </a>
            </div>
        </div>

    </div>

</div>

@endsection