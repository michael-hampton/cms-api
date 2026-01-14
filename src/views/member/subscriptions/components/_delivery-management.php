<?php
// Get next upcoming delivery
$issueDeliveryRepo = new \App\Repositories\Subscriptions\IssueDeliveryRepository();
$nextDelivery = $issueDeliveryRepo->getUpcomingDeliveries($activeSubscription->id, 1)->first();
?>

<div class="info-row">
    <span class="info-label">Next Issue Delivery</span>
    <span class="info-value" style="display: flex; align-items: center; gap: 8px;">
            <?php if ($nextDelivery): ?>
                <span style="font-weight: 800; color: #667eea; font-size: 18px;">
                    <?= $nextDelivery->estimated_delivery_date->format('M d, Y') ?>
                </span>
                <span class="badge"
                      style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; font-size: 11px;">
                    Issue #<?= $nextDelivery->issue_number ?>
                </span>
                <?php
                $daysUntil = (new \DateTime())->diff($nextDelivery->estimated_delivery_date)->days;
                if ($daysUntil <= 7 && $daysUntil >= 0):
                    ?>
                    <span style="font-size: 13px; color: #f59e0b; font-weight: 600;">
                        (<?= $daysUntil ?> day<?= $daysUntil != 1 ? 's' : '' ?> away)
                    </span>
                <?php endif; ?>
            <?php else: ?>
                <span style="color: #64748b; font-size: 14px;">
                    <?php if ($activeSubscription->end_date && $activeSubscription->end_date < new \DateTime()): ?>
                        Subscription ended
                    <?php else: ?>
                        Schedule being prepared
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </span>
</div>

<?php if ($nextDelivery && $nextDelivery->tracking_info && !empty($nextDelivery->tracking_info['tracking_number'])): ?>
    <div class="info-row">
        <span class="info-label">Tracking</span>
        <span class="info-value">
                <a href="<?= htmlspecialchars($nextDelivery->tracking_info['tracking_url'] ?? '#') ?>"
                   target="_blank"
                   style="color: #667eea; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                    <?= htmlspecialchars($nextDelivery->tracking_info['tracking_number']) ?>
                    <span style="font-size: 12px;">↗</span>
                </a>
            </span>
    </div>
<?php endif; ?>

<?php if ($activeSubscription->isDeliveryPaused()): ?>
    <div style="background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;">
        <span style="font-size: 24px;">⏸️</span>
        <div>
            <div style="font-weight: 600; color: #92400e;">
                Delivery Paused
            </div>
            <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                Your deliveries are paused
                until <?= $activeSubscription->delivery_pause_end->format('F d, Y') ?>
                (<?= $activeSubscription->getDaysUntilPauseEnds() ?> days remaining)
            </div>
        </div>
    </div>

    <div class="btn-group">
        <button class="btn btn-primary" onclick="resumeDeliveryNow(<?= $activeSubscription->id ?>)">
            ▶️ Resume Delivery Now
        </button>
    </div>
<?php else: ?>
    <div class="btn-group">
        <button class="btn btn-secondary"
                onclick="openPauseDeliveryModal(<?= $activeSubscription->id ?>)">
            ⏸️ Pause Delivery
        </button>
    </div>
<?php endif; ?>

@include('member.subscriptions.components._shipping-address')