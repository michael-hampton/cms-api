@section('logic')
<?php
/**
 * Template: open-collab/dashboard/earnings.php
 * Variables: $earnings (total, breakdown, pending), $paymentDetails, $currentUser
 */

$pageTitle = 'Earnings';
$activeNav = 'earnings';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/contributor/dashboard'], ['label' => 'Earnings']];
$pageClass = 'oc-page--wide';

$totalPence = (int)($earnings['total'] ?? 0);
$pendingPence = (int)($earnings['pending'] ?? $totalPence);
$breakdown = $earnings['breakdown'] ?? [];
$transactions = $earnings['transactions'] ?? [];
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')
<div class="oc-stats" style="animation:fadeSlideIn .4s ease;">
    <div class="oc-stat oc-stat--accent">
        <div class="oc-stat__label">Lifetime Earnings</div>
        <div class="oc-stat__value">£<?= number_format($totalPence / 100, 2) ?></div>
        <div class="oc-stat__sub">All time gross revenue</div>
        </div>
    <div class="oc-stat oc-stat--green">
        <div class="oc-stat__label">Pending Payout</div>
        <div class="oc-stat__value">£<?= number_format($pendingPence / 100, 2) ?></div>
        <div class="oc-stat__sub">Awaiting transfer</div>
        </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Revenue Sources</div>
        <div class="oc-stat__value"><?= count($breakdown) ?></div>
        <div class="oc-stat__sub">Paid articles earning</div>
        </div>
    </div>

<div class="oc-grid-sidebar" style="align-items:start;">

    <!-- Revenue table -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Article breakdown -->
        <div class="oc-card" style="animation:fadeSlideIn .45s ease;">
            <div class="oc-card__header">
                <span class="oc-card__title">Revenue by Article</span>
            </div>
            <?php if (empty($breakdown)): ?>
                <div style="padding:48px 24px;text-align:center;color:var(--slate);">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="32"
                         style="opacity:.2;display:block;margin:0 auto 12px;">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                              clip-rule="evenodd"/>
                    </svg>
                    <div style="font-weight:500;margin-bottom:6px;">No earnings yet</div>
                    <div style="font-size:.85rem;">Publish a paid article to start earning.</div>
                </div>
            <?php else: ?>
                <table class="oc-table">
                    <thead>
                    <tr>
                        <th>Article</th>
                        <th style="text-align:right;">Revenue</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($breakdown as $item): ?>
                        <tr>
                            <td>
                                <a href="/articles/<?= (int)$item['page_id'] ?>/edit"
                                   style="font-weight:500;color:var(--navy);text-decoration:none;">
                                    <?= htmlspecialchars($item['title'] ?? 'Untitled') ?>
                                </a>
                            </td>
                            <td style="text-align:right;">
                <span style="font-family:var(--font-display);font-weight:700;font-size:1rem;color:var(--navy);">
                  £<?= number_format((int)$item['total'] / 100, 2) ?>
                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Transaction history -->
        <?php if (!empty($transactions)): ?>
            <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
                <div class="oc-card__header">
                    <span class="oc-card__title">Transaction History</span>
                </div>
                <table class="oc-table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Article</th>
                        <th>Type</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td style="white-space:nowrap;color:var(--slate);">
                                <?= htmlspecialchars(isset($tx['created_at']) ? date('d M Y', strtotime($tx['created_at'])) : '–') ?>
                            </td>
                            <td><?= htmlspecialchars($tx['page_title'] ?? '–') ?></td>
                            <td>
                                <?php $type = $tx['status'] ?? 'sale'; ?>
                                <span class="oc-badge oc-badge--<?= $type === 'refunded' ? 'revoked' : 'published' ?>">
                <?= ucfirst(htmlspecialchars($type)) ?>
              </span>
                            </td>
                            <td style="text-align:right;font-weight:600;color:<?= ($tx['status'] ?? '') === 'refunded' ? 'var(--red)' : 'var(--green)' ?>;">
                                <?= ($tx['status'] ?? '') === 'refunded' ? '-' : '' ?>
                                £<?= number_format((int)($tx['amount'] ?? 0) / 100, 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

    <!-- Payout sidebar -->
    <div style="position:sticky;top:calc(var(--header-h) + 20px);">
        <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.95rem;">Payout Method</span>
            </div>
            <div class="oc-card__body">
                <?php if (!empty($paymentDetails)): ?>
                    <div style="background:var(--slate-pale);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-bottom:16px;">
                        <div style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">
                            Connected account
                        </div>
                        <div style="font-weight:500;font-size:.9rem;color:var(--navy);margin-bottom:4px;">
                            <?= htmlspecialchars($paymentDetails['email'] ?? 'Stripe account') ?>
                        </div>
                        <div style="font-size:.75rem;color:var(--green);font-weight:600;">● Active via Stripe</div>
                    </div>
                    <a href="/contributor/settings#payment" class="oc-btn oc-btn--ghost oc-btn--sm oc-btn--block">
                        Update payout details
                    </a>
                <?php else: ?>
                    <div style="padding:16px;text-align:center;border:1.5px dashed var(--border);border-radius:var(--radius);margin-bottom:16px;">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="28"
                             style="color:var(--slate-light);display:block;margin:0 auto 8px;">
                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                            <path fill-rule="evenodd"
                                  d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"
                                  clip-rule="evenodd"/>
                        </svg>
                        <div style="font-size:.85rem;font-weight:500;margin-bottom:4px;">No payout method</div>
                        <div style="font-size:.78rem;color:var(--slate);margin-bottom:12px;">Set up to receive
                            payments
                        </div>
                        <a href="/onboarding" class="oc-btn oc-btn--amber oc-btn--sm">Set up now</a>
                    </div>
                <?php endif; ?>

                <div style="font-size:.75rem;color:var(--slate);line-height:1.6;padding-top:12px;border-top:1px solid var(--border);">
                    Payouts are processed automatically when your balance exceeds <strong>£50.00</strong>.
                    Funds typically arrive within 2–5 business days.
                </div>
            </div>
        </div>
    </div>

</div>
@endsection