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
    /* ── Stats grid ──────────────────────────────────────────────── */
    .overview-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 24px;
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
        padding: 22px 22px 18px;
        box-shadow: var(--shadow-xs);
        position: relative;
        overflow: hidden;
    }

    /* Gold underline accent */
    .stat-card::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2.5px;
        background: linear-gradient(90deg, var(--gold) 0%, transparent 80%);
    }

    .stat-card__label {
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--ink-muted);
        margin-bottom: 10px;
    }

    .stat-card__value {
        font-family: var(--font-display);
        font-size: 40px;
        line-height: 1;
        color: var(--ink);
        letter-spacing: -.02em;
    }

    .stat-card__sub {
        font-size: 12px;
        color: var(--ink-muted);
        margin-top: 5px;
    }

    /* ── Subscription mini-rows ──────────────────────────────────── */
    .sub-row {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: start;
        gap: 12px;
        padding: 16px 0;
        border-bottom: 1px solid var(--border-soft);
    }

    .sub-row:last-child {
        border-bottom: none;
    }

    .sub-row__plan {
        font-weight: 600;
        font-size: 15px;
        color: var(--ink);
        margin-bottom: 4px;
    }

    .sub-row__meta {
        font-size: 12.5px;
        color: var(--ink-muted);
    }

    .sub-row__renewal {
        font-size: 12px;
        color: var(--ink-soft);
        margin-top: 4px;
    }

    /* ── Order rows ──────────────────────────────────────────────── */
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
        background: var(--paper);
        margin: 0 -24px;
        padding: 14px 24px;
        border-radius: var(--radius-sm);
    }

    .order-row__num {
        font-weight: 600;
        font-size: 14px;
        color: var(--ink);
    }

    .order-row__date {
        font-size: 12px;
        color: var(--ink-muted);
        margin-top: 2px;
    }

    .order-row__total {
        font-family: var(--font-display);
        font-size: 17px;
        color: var(--ink);
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
        color: var(--ink-muted);
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

<?php $page_title = 'Overview'; ?>

@include('subscriptions/account/_layout')

<main class="page-content">

    <div class="page-heading">
        <div class="page-heading__eyebrow">Dashboard</div>
        <h1 class="page-heading__title">Overview</h1>
        <p class="page-heading__sub">Welcome
            back, <?= htmlspecialchars(explode(' ', $member->name ?? 'there')[0]) ?></p>
    </div>

    <!-- Stats -->
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
            <div class="stat-card__value" style="font-size:22px; padding-top:8px;">
                <?php
                $created = $member->created_at ?? null;
                echo $created ? $created->format('M Y') : '—';
                ?>
            </div>
            <?php if ($created): ?>
                <div class="stat-card__sub">
                    <?php
                    $now = now_datetime(); // assuming this returns DateTime

                    $diff = $created->diff($now);

                    $months = ($diff->y * 12) + $diff->m;
                    $years = floor($months / 12);
                    $rem = $months % 12;
                    echo $years > 0
                            ? $years . 'y ' . ($rem > 0 ? $rem . 'm' : '')
                            : $months . ' months';
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active subscriptions -->
    <div class="card section-gap">
        <div class="card__header">
            <span class="card__title">Active Subscriptions</span>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/subscriptions"
               class="view-all-link">View all →</a>
        </div>
        <div class="card__body" style="padding-top:4px; padding-bottom:4px;">
            <?php if (empty($active_subscriptions)): ?>
                <div class="empty-state" style="padding:36px 0;">
                    <div class="empty-state__icon">📭</div>
                    <div class="empty-state__title">No active subscriptions</div>
                    <div class="empty-state__sub">You don't have any active subscriptions yet.</div>
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions" class="btn btn--primary">Browse
                        publications</a>
                </div>
            <?php else: ?>
                <?php foreach ($active_subscriptions as $sub): ?>
                    <div class="sub-row">
                        <div>
                            <div class="sub-row__plan"><?= htmlspecialchars($sub['plan_name']) ?></div>
                            <div class="sub-row__meta">
                                <?= $sub['type'] === 'digital' ? '📱 Digital' : '📰 Print' ?>
                                <?php if (!empty($sub['auto_renew'])): ?>· Auto-renews<?php endif; ?>
                            </div>
                            <?php if (!empty($sub['end_date'])): ?>
                                <div class="sub-row__renewal">
                                    <?= $sub['auto_renew'] ? 'Renews' : 'Expires' ?>
                                    <?= $sub['end_date']->format('j M Y') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                            <span class="badge badge--<?= htmlspecialchars($sub['status']) ?>"><?= htmlspecialchars(ucfirst($sub['status'])) ?></span>
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/subscriptions"
                               class="btn btn--ghost btn--sm">Manage</a>
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
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/orders"
               class="view-all-link">View all →</a>
        </div>
        <div class="card__body" style="padding-top:4px; padding-bottom:4px;">
            <?php if ($recent_orders->isEmpty()): ?>
                <div class="empty-state" style="padding:36px 0;">
                    <div class="empty-state__icon">🛍️</div>
                    <div class="empty-state__title">No orders yet</div>
                    <div class="empty-state__sub">Your purchase history will appear here.</div>
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions" class="btn btn--primary">Start
                        shopping</a>
                </div>
            <?php else: ?>
                <?php foreach ($recent_orders as $order): ?>
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/orders/<?= $order->id ?>"
                       class="order-row">
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
</div><!-- /.shell -->
</body>
</html>