<?php
/**
 * Partial: account/_subscription_card.php
 *
 * Expects $sub from SubscriptionListingService::formatSubscriptionForListing():
 *   id, plan_name, type, status, is_active, start_date, end_date,
 *   next_billing_date, auto_renew, can_renew, should_show_renew,
 *   newsletters, archive_url, premium_access
 */

$isActive = $sub['is_active'] ?? false;
$status = $sub['status'] ?? 'unknown';
$isCancelled = $status === 'cancelled';
$isExpired = $status === 'expired';
$letter = strtoupper(substr($sub['plan_name'] ?? 'S', 0, 1));
$endDate = null;

if (!empty($sub['end_date'])) {
    $date = $sub['end_date'];
    if (is_string($date)) {
        $endDate = date('j M Y', strtotime($date));
    } elseif ($date instanceof DateTimeInterface) {
        $endDate = $date->format('j M Y');
    }
}
?>
<style>
    /* ── Subscription Card Full ────────────────────────────────────── */
    .sub-card-full {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-xs);
        transition: box-shadow .2s ease, border-color .2s ease;
    }

    .sub-card-full:hover {
        box-shadow: var(--shadow-sm);
        border-color: #ccc9be;
    }

    .sub-card-full.is-expired,
    .sub-card-full.is-cancelled {
        opacity: .72;
    }

    /* Card accent strip at top — coloured by status */
    .sub-card-full::before {
        content: '';
        display: block;
        height: 3px;
        background: linear-gradient(90deg, var(--gold) 0%, var(--gold-mid) 100%);
    }

    .sub-card-full.is-expired::before,
    .sub-card-full.is-cancelled::before {
        background: var(--border);
    }

    /* ── Header row ────────────────────────────────────────────────── */
    .sub-card-full__header {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: flex-start;
        gap: 16px;
        padding: 20px 22px 16px;
    }

    /* Icon */
    .sub-card-full__icon {
        width: 46px;
        height: 46px;
        border-radius: 10px;
        background: linear-gradient(140deg, var(--ink) 0%, #2a273f 100%);
        color: var(--gold-mid);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: var(--font-display);
        font-size: 21px;
        font-weight: 700;
        flex-shrink: 0;
        letter-spacing: -.01em;
    }

    .sub-card-full.is-expired .sub-card-full__icon,
    .sub-card-full.is-cancelled .sub-card-full__icon {
        background: var(--paper-dark);
        color: var(--ink-muted);
    }

    /* Plan title + meta */
    .sub-card-full__plan {
        font-family: var(--font-display);
        font-weight: 600;
        font-size: 17px;
        color: var(--ink);
        margin-bottom: 5px;
        line-height: 1.2;
    }

    .sub-card-full__meta {
        font-size: 12.5px;
        color: var(--ink-muted);
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        line-height: 1;
    }

    .sub-card-full__meta-dot {
        width: 3px;
        height: 3px;
        border-radius: 50%;
        background: var(--border);
        flex-shrink: 0;
    }

    /* Actions */
    .sub-card-full__actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    /* ── Detail row ────────────────────────────────────────────────── */
    .sub-card-full__body {
        padding: 0 22px 20px;
        display: flex;
        gap: 0;
        border-top: 1px solid var(--border-soft);
    }

    .sub-detail {
        flex: 1;
        padding: 16px 18px 0 0;
        border-right: 1px solid var(--border-soft);
    }

    .sub-detail:last-child {
        border-right: none;
        padding-right: 0;
        padding-left: 18px;
    }

    .sub-detail:first-child {
        padding-left: 0;
    }

    /* If only 1 detail block */
    .sub-detail:only-child {
        padding-left: 0;
    }

    .sub-detail__label {
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .09em;
        color: var(--ink-muted);
        margin-bottom: 4px;
    }

    .sub-detail__value {
        font-size: 14px;
        color: var(--ink);
        font-weight: 500;
    }

    @media (max-width: 580px) {
        .sub-card-full__header {
            grid-template-columns: auto 1fr;
        }

        .sub-card-full__actions {
            grid-column: 1/-1;
        }

        .sub-card-full__body {
            flex-wrap: wrap;
        }

        .sub-detail {
            min-width: 45%;
            border-right: none;
        }
    }

    /* ── Footer benefits strip ─────────────────────────────────────── */
    .sub-card-full__footer {
        padding: 10px 22px 12px;
        background: var(--paper);
        border-top: 1px solid var(--border-soft);
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .footer-benefit {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11.5px;
        color: var(--ink-soft);
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: 100px;
        padding: 4px 11px;
        font-weight: 500;
        text-decoration: none;
        transition: var(--transition);
    }

    .footer-benefit:hover {
        border-color: var(--gold);
        color: var(--gold);
    }
</style>

<div class="sub-card-full <?= (!$isActive && ($isCancelled || $isExpired)) ? 'is-' . $status : '' ?>">

    <!-- Header -->
    <div class="sub-card-full__header">

        <div class="sub-card-full__icon"><?= htmlspecialchars($letter) ?></div>

        <div>
            <div class="sub-card-full__plan"><?= htmlspecialchars($sub['plan_name']) ?></div>
            <div class="sub-card-full__meta">
                <?php if (($sub['type'] ?? '') === 'digital'): ?>
                    <span>📱 Digital</span>
                <?php else: ?>
                    <span>📰 Print</span>
                <?php endif; ?>

                <?php if (!empty($sub['auto_renew']) && $isActive): ?>
                    <span class="sub-card-full__meta-dot"></span>
                    <span>Auto-renews</span>
                <?php endif; ?>

                <span class="sub-card-full__meta-dot"></span>
                <span class="badge badge--<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
        </div>

        <div class="sub-card-full__actions">
            <?php if ($isActive && !$isCancelled): ?>
                <button class="btn btn--ghost btn--sm"
                        onclick='openCancelModal(<?= (int)$sub['id'] ?>, <?= json_encode($sub['plan_name']) ?>, <?= json_encode($endDate ?? '') ?>)'>
                    Cancel
                </button>
            <?php endif; ?>

            <?php if ($sub['should_show_renew'] ?? false): ?>
                <a href="/checkout?plan_id=<?= $sub['plan_id'] ?>&renewal=true&type=fixed&delivery=<?= $sub['type'] ?>"
                   class="btn btn--gold btn--sm">
                    Renew
                </a>
            <?php endif; ?>

            <?php if ($isCancelled || $isExpired): ?>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions"
                   class="btn btn--primary btn--sm">Reactivate</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detail facts -->
    <div class="sub-card-full__body">

        <?php if ($endDate): ?>
            <div class="sub-detail">
                <div class="sub-detail__label"><?= $isCancelled ? 'Access until' : ($sub['auto_renew'] ? 'Renews' : 'Expires') ?></div>
                <div class="sub-detail__value"><?= htmlspecialchars($endDate) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($sub['start_date'])): ?>
            <div class="sub-detail">
                <div class="sub-detail__label">Started</div>
                <div class="sub-detail__value"><?= $sub['start_date']->format('j M Y') ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($sub['next_billing_date']) && $isActive && $sub['auto_renew']): ?>
            <div class="sub-detail">
                <div class="sub-detail__label">Next billing</div>
                <div class="sub-detail__value"><?= $sub['next_billing_date']->format('j M Y') ?></div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Benefits footer -->
    <?php if (!empty($sub['newsletters']) || !empty($sub['archive_url'])): ?>
        <div class="sub-card-full__footer">
            <?php if (!empty($sub['archive_url'])): ?>
                <a href="<?= htmlspecialchars($sub['archive_url']) ?>" class="footer-benefit">
                    📚 Archive access
                </a>
            <?php endif; ?>
            <?php foreach ($sub['newsletters'] as $nl): ?>
                <span class="footer-benefit">
                    📧 <?= htmlspecialchars($nl['title']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>