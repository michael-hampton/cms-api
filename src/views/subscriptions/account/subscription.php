<?php
/**
 * Variables:
 * $member, $subscription, $subscription_data, $active_tab.
 */

$page_title = 'Manage subscription';
$state = $subscription_data['display_state'] ?? [];
$isPrevious = ($state['group'] ?? '') === 'previous';
$canManageAutoRenew = !$isPrevious && !$subscription->isExpired();
?>

@include('subscriptions/account/_layout')

<main class="page-content">
    <div class="page-heading">
        <div class="page-heading__eyebrow">Subscriptions</div>
        <h1 class="page-heading__title">
            <?= htmlspecialchars($subscription_data['plan_name'] ?? 'Subscription') ?>
        </h1>
        <p class="page-heading__sub">
            <?= htmlspecialchars($state['label'] ?? 'Subscription') ?>
        </p>
    </div>

    <p>
        <a href="/press-stack/account/subscriptions" class="footer-benefit">
            ← Back to subscriptions
        </a>
    </p>

    <section class="card" aria-labelledby="subscription-overview-heading">
        <div class="card__body">
            <h2 id="subscription-overview-heading">Subscription overview</h2>

            <?php if (!empty($state['copy'])): ?>
                <p><?= htmlspecialchars($state['copy']) ?></p>
            <?php endif; ?>

            <dl>
                <div class="sub-detail">
                    <dt class="sub-detail__label">Access type</dt>
                    <dd class="sub-detail__value">
                        <?= htmlspecialchars($subscription_data['access_type'] ?? $subscription_data['type'] ?? '—') ?>
                    </dd>
                </div>

                <?php if (!empty($subscription_data['plan_descriptor'])): ?>
                    <div class="sub-detail">
                        <dt class="sub-detail__label">Plan</dt>
                        <dd class="sub-detail__value">
                            <?= htmlspecialchars($subscription_data['plan_descriptor']) ?>
                        </dd>
                    </div>
                <?php endif; ?>

                <?php foreach (($subscription_data['facts'] ?? []) as $fact): ?>
                    <div class="sub-detail">
                        <dt class="sub-detail__label"><?= htmlspecialchars($fact['label']) ?></dt>
                        <dd class="sub-detail__value"><?= htmlspecialchars($fact['value'] ?? '—') ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>

    <?php if ($canManageAutoRenew): ?>
        <section class="card" aria-labelledby="auto-renew-heading">
            <div class="card__body">
                <h2 id="auto-renew-heading">Automatic renewal</h2>
                <p>
                    Automatically renew this subscription at the end of the current billing period.
                </p>

                <form id="auto-renew-form"
                      data-endpoint="/press-stack/account/subscriptions/<?= htmlspecialchars((string)$subscription->id) ?>/auto-renew">
                    <label>
                        <input type="checkbox"
                               id="auto-renew-toggle"
                               <?= $subscription->auto_renew ? 'checked' : '' ?>>
                        Automatically renew this subscription
                    </label>

                    <div id="auto-renew-consent" <?= $subscription->auto_renew ? 'hidden' : '' ?>>
                        <label>
                            <input type="checkbox" id="auto-renew-consent-checkbox">
                            I agree to automatic renewal and future renewal charges.
                        </label>
                    </div>

                    <div class="sub-card-full__actions">
                        <button type="submit" class="btn btn--gold btn--sm">
                            Save renewal preference
                        </button>
                    </div>

                    <div class="account-message"
                         id="auto-renew-message"
                         role="alert"
                         aria-live="polite"></div>
                </form>
            </div>
        </section>
    <?php endif; ?>
</main>
</div><!-- /.shell -->

<script src="/public/js/subscription-manage.js" defer></script>
</body>
</html>
