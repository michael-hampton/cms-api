<?php
/**
 * View: account/overview.php
 *
 * Variables from ShopAccountController::overview():
 *   $member               – authenticated member
 *   $subscription_summary – ['total', 'active', 'expired', 'cancelled']
 *   $active_subscriptions – array (max 3 formatted subscriptions)
 *   $recent_orders        – Collection of last 5 orders
 *   $active_tab           – 'overview'
 */

?>
<style>
    .overview-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 28px;
    }

    @media (max-width: 640px) {
        .overview-grid {
            grid-template-columns: 1fr;
        }
    }

    .stat-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
    }

    .stat-card__label {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--ink-muted);
        margin-bottom: 8px;
    }

    .stat-card__value {
        font-family: var(--font-display);
        font-size: 36px;
        line-height: 1;
        color: var(--ink);
    }

    .stat-card__sub {
        font-size: 12px;
        color: var(--ink-muted);
        margin-top: 4px;
    }

    /* Subscription summary cards */
    .sub-card {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: start;
        gap: 12px;
        padding: 18px 0;
        border-bottom: 1px solid var(--border-soft);
    }

    .sub-card:last-child {
        border-bottom: none;
    }

    .sub-card__plan {
        font-weight: 600;
        font-size: 15px;
        margin-bottom: 3px;
    }

    .sub-card__meta {
        font-size: 13px;
        color: var(--ink-muted);
    }

    .sub-card__renewal {
        font-size: 12px;
        color: var(--ink-soft);
        margin-top: 4px;
    }

    .sub-card__price {
        font-family: var(--font-display);
        font-size: 20px;
        color: var(--ink);
        text-align: right;
    }

    .sub-card__price-period {
        font-size: 11px;
        color: var(--ink-muted);
    }

    /* Order rows */
    .order-row {
        display: grid;
        grid-template-columns: 1fr auto auto;
        align-items: center;
        gap: 16px;
        padding: 14px 0;
        border-bottom: 1px solid var(--border-soft);
        text-decoration: none;
        color: inherit;
        transition: var(--transition);
    }

    .order-row:last-child {
        border-bottom: none;
    }

    .order-row:hover {
        background: var(--surface);
        margin: 0 -24px;
        padding: 14px 24px;
        border-radius: var(--radius-sm);
    }

    .order-row__num {
        font-weight: 600;
        font-size: 14px;
    }

    .order-row__date {
        font-size: 12px;
        color: var(--ink-muted);
        margin-top: 2px;
    }

    .order-row__total {
        font-family: var(--font-display);
        font-size: 16px;
    }

    @media (max-width: 480px) {
        .order-row {
            grid-template-columns: 1fr auto;
        }

        .order-row .badge {
            display: none;
        }
    }

    .section-gap {
        margin-bottom: 24px;
    }

    .view-all-link {
        font-size: 13px;
        color: var(--ink-soft);
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: var(--transition);
    }

    .view-all-link:hover {
        color: var(--ink);
    }
</style>

<?php
$page_title = 'Overview';
?>


@include('subscriptions/account/_layout')

<main class="page-content">

    <div class="page-heading">
        <h1 class="page-heading__title">Overview</h1>
        <p class="page-heading__sub">Welcome
            back, <?= htmlspecialchars(explode(' ', $member->name ?? 'there')[0]) ?></p>
    </div>

    <!-- Stats row -->
    <div class="overview-grid section-gap">
        <div class="stat-card">
            <div class="stat-card__label">Active subscriptions</div>
            <div class="stat-card__value"><?= $subscription_summary['active'] ?? 0 ?></div>
            <div class="stat-card__sub">of <?= $subscription_summary['total'] ?? 0 ?> total</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Orders placed</div>
            <div class="stat-card__value"><?= $recent_orders->count() ?>+</div>
            <div class="stat-card__sub">See full history</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Member since</div>
            <div class="stat-card__value" style="font-size:22px; padding-top:7px;">
                <?php
                $created = $member->created_at ?? null;
                echo $created ? $created->format('M Y') : '—';
                ?>
            </div>
        </div>
    </div>

    <!-- Active subscriptions -->
    <div class="card section-gap">
        <div class="card__header">
            <span class="card__title">Active Subscriptions</span>
            <a href="/account/subscriptions" class="view-all-link">View all →</a>
        </div>
        <div class="card__body" style="padding-top:4px; padding-bottom:4px;">
            <?php if (empty($active_subscriptions)): ?>
                <div class="empty-state" style="padding:32px 0;">
                    <div class="empty-state__icon">📭</div>
                    <div class="empty-state__title">No active subscriptions</div>
                    <div class="empty-state__sub">You don't have any active subscriptions yet.</div>
                    <a href="/subscriptions" class="btn btn--primary">Browse magazines</a>
                </div>
            <?php else: ?>
                <?php foreach ($active_subscriptions as $sub): ?>
                    <div class="sub-card">
                        <div>
                            <div class="sub-card__plan"><?= htmlspecialchars($sub['plan_name']) ?></div>
                            <div class="sub-card__meta">
                                <?= $sub['type'] === 'digital' ? '📱 Digital' : '📰 Print' ?>
                                <?php if (!empty($sub['auto_renew'])): ?>
                                    · Auto-renews
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($sub['end_date'])): ?>
                                <div class="sub-card__renewal">
                                    <?= $sub['auto_renew'] ? 'Renews' : 'Expires' ?>
                                    <?= $sub['end_date']->format('j M Y') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align:right; flex-shrink:0;">
                            <div style="margin-bottom:6px;">
                                <span class="badge badge--<?= htmlspecialchars($sub['status']) ?>"><?= htmlspecialchars($sub['status']) ?></span>
                            </div>
                            <a href="/account/subscriptions" class="btn btn--ghost btn--sm">Manage</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent orders -->
    <div class="card">
        <div class="card__header">
            <span class="card__title">Recent Orders</span>
            <a href="/account/orders" class="view-all-link">View all →</a>
        </div>
        <div class="card__body" style="padding-top:4px; padding-bottom:4px;">
            <?php if ($recent_orders->isEmpty()): ?>
                <div class="empty-state" style="padding:32px 0;">
                    <div class="empty-state__icon">🛍️</div>
                    <div class="empty-state__title">No orders yet</div>
                    <div class="empty-state__sub">Your purchase history will appear here.</div>
                    <a href="/subscriptions" class="btn btn--primary">Start shopping</a>
                </div>
            <?php else: ?>
                <?php foreach ($recent_orders as $order): ?>
                    <a href="/account/orders/<?= $order->id ?>" class="order-row">
                        <div>
                            <div class="order-row__num">
                                #<?= htmlspecialchars($order->order_number ?? $order->id) ?></div>
                            <div class="order-row__date"><?= $order->created_at->format('j M Y') ?></div>
                        </div>
                        <span class="badge badge--<?= htmlspecialchars($order->payment_status ?? 'pending') ?>">
                        <?= htmlspecialchars(ucfirst($order->payment_status ?? 'pending')) ?>
                    </span>
                        <div class="order-row__total">£<?= number_format($order->total ?? 0, 2) ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

</body>
</html>
