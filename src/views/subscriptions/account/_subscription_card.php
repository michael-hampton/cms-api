<?php
/**
 * Expects a view-ready subscription array from SubscriptionListingService.
 */

$state = $sub['display_state'] ?? null;
if (!is_array($state)) {
    throw new \LogicException('Subscription account cards require backend display_state data.');
}
$isHistorical = ($state['group'] ?? '') === 'previous';
$letter = strtoupper(substr($sub['plan_name'] ?? 'S', 0, 1));
$managePayload = [
    'id' => $sub['id'],
    'plan_name' => $sub['plan_name'] ?? 'Subscription',
    'status_label' => $state['label'] ?? '',
    'facts' => $sub['facts'] ?? [],
    'auto_renew' => !empty($sub['auto_renew']),
    'can_manage_auto_renew' => !$isHistorical && ($state['key'] ?? '') !== 'expired',
    'auto_renew_endpoint' => "/press-stack/account/subscriptions/{$sub['id']}/auto-renew",
];
?>

<article class="sub-card-full state-<?= htmlspecialchars($state['accent'] ?? 'neutral') ?> <?= $isHistorical ? 'is-historical' : '' ?>">
    <div class="sub-card-full__header">
        <div class="sub-card-full__icon" aria-hidden="true"><?= htmlspecialchars($letter) ?></div>

        <div>
            <h2 class="sub-card-full__plan"><?= htmlspecialchars($sub['plan_name'] ?? 'Subscription') ?></h2>
            <div class="sub-card-full__meta">
                <span><?= ($sub['type'] ?? '') === 'digital' ? 'Digital' : 'Print' ?></span>
                <?php if (!empty($sub['auto_renew']) && ($state['group'] ?? '') === 'current'): ?>
                    <span class="sub-card-full__meta-dot" aria-hidden="true"></span>
                    <span>Auto-renews</span>
                <?php endif; ?>
                <span class="sub-card-full__meta-dot" aria-hidden="true"></span>
                <span class="badge badge--<?= htmlspecialchars($state['tone'] ?? 'neutral') ?>">
                    <?= htmlspecialchars($state['label']) ?>
                </span>
            </div>
            <?php if (!empty($state['copy'])): ?>
                <p class="sub-card-full__copy"><?= htmlspecialchars($state['copy']) ?></p>
            <?php endif; ?>
        </div>

        <div class="sub-card-full__actions">
            <?php foreach (($sub['actions'] ?? []) as $action): ?>
                <?php if (($action['key'] ?? '') === 'manage'): ?>
                    <button type="button"
                            class="btn btn--gold btn--sm"
                            data-open-subscription-manage
                            data-subscription-manage="<?= htmlspecialchars(json_encode($managePayload), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($action['label']) ?>
                    </button>
                <?php elseif (($action['type'] ?? '') === 'modal' && ($action['modal'] ?? '') === 'cancel'): ?>
                    <button class="btn btn--ghost btn--sm"
                            type="button"
                            data-open-cancel
                            data-subscription-id="<?= (int)$sub['id'] ?>"
                            data-plan-name="<?= htmlspecialchars($sub['plan_name'] ?? 'Subscription') ?>"
                            data-end-date="<?= htmlspecialchars($sub['cancellation_flow']['effective_date'] ?? '') ?>"
                            data-cancellation-flow="<?= htmlspecialchars(json_encode($sub['cancellation_flow']), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($action['label']) ?>
                    </button>
                <?php elseif (($action['type'] ?? '') === 'redirect'): ?>
                    <a href="<?= htmlspecialchars($action['url']) ?>" class="btn btn--gold btn--sm">
                        <?= htmlspecialchars($action['label']) ?>
                    </a>
                <?php elseif (($action['type'] ?? '') === 'api'): ?>
                    <button type="button"
                            class="btn btn--gold btn--sm"
                            data-account-action="api"
                            data-endpoint="<?= htmlspecialchars($action['endpoint']) ?>">
                        <?= htmlspecialchars($action['label']) ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($sub['payment_recovery'])): ?>
        <div class="action-required-panel">
            <strong>We could not collect your payment</strong>
            <span><?= htmlspecialchars($sub['payment_recovery']['amount']) ?> is outstanding. <?= htmlspecialchars($sub['payment_recovery']['access_copy']) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($sub['facts'])): ?>
        <div class="sub-card-full__body">
            <?php foreach ($sub['facts'] as $fact): ?>
                <div class="sub-detail">
                    <div class="sub-detail__label"><?= htmlspecialchars($fact['label']) ?></div>
                    <div class="sub-detail__value"><?= htmlspecialchars($fact['value'] ?? '—') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($sub['benefits'])): ?>
        <div class="sub-card-full__footer" aria-label="Included benefits">
            <?php foreach ($sub['benefits'] as $benefit): ?>
                <?php if (!empty($benefit['url'])): ?>
                    <a href="<?= htmlspecialchars($benefit['url']) ?>" class="footer-benefit"><?= htmlspecialchars($benefit['label']) ?></a>
                <?php else: ?>
                    <span class="footer-benefit"><?= htmlspecialchars($benefit['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($sub['management_links'])): ?>
        <div class="sub-card-full__footer" aria-label="Subscription management">
            <?php foreach ($sub['management_links'] as $link): ?>
                <a href="<?= htmlspecialchars($link['url']) ?>" class="footer-benefit">
                    <?= htmlspecialchars($link['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
