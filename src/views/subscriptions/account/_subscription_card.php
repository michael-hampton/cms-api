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
$endDateRaw = !empty($sub['end_date']) ? $sub['end_date']->format('Y-m-d H:i:s') : '';
?>
<div class="sub-card-full <?= (!$isActive && ($isCancelled || $isExpired)) ? 'is-' . $status : '' ?>">
    <div class="sub-card-full__header">
        <div class="sub-card-full__icon"><?= $letter ?></div>

        <div>
            <div class="sub-card-full__plan"><?= htmlspecialchars($sub['plan_name']) ?></div>
            <div class="sub-card-full__meta">
                <?= ($sub['type'] ?? '') === 'digital' ? '📱 Digital' : '📰 Print' ?>
                <?php if (!empty($sub['auto_renew']) && $isActive): ?>
                    <span class="sub-card-full__meta-dot"></span> Auto-renews
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

            <?php if (($sub['should_show_renew'] ?? false)): ?>
                <a href="/subscriptions/<?= (int)($sub['plan_id'] ?? 0) ?>" class="btn btn--primary btn--sm">
                    Renew
                </a>
            <?php endif; ?>

            <?php if ($isCancelled || $isExpired): ?>
                <a href="/subscriptions" class="btn btn--primary btn--sm">Reactivate</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="sub-card-full__body">
        <?php if ($endDate): ?>
            <div class="sub-detail">
                <div class="sub-detail__label"><?= $isCancelled ? 'Access until' : ($sub['auto_renew'] ? 'Renews' : 'Expires') ?></div>
                <div class="sub-detail__value"><?= $endDate ?></div>
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