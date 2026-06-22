<?php
$currentSubscriptions = $grouped['current'] ?? [];
$actionRequiredSubscriptions = $grouped['action_required'] ?? [];
$previousSubscriptions = $grouped['previous'] ?? [];
$accountContext = $account_context ?? [];
$canAcquire = (bool) ($accountContext['can_acquire_subscription'] ?? false);
$cancelBase = (string) ($accountContext['cancel_endpoint_template'] ?? '');
$loginUrl = (string) ($accountContext['login_url'] ?? '/member/login');
$hasLiveSubscriptions = !empty($currentSubscriptions) || !empty($actionRequiredSubscriptions);
$expiredSummaryLabel = $hasLiveSubscriptions ? 'Show expired subscriptions' : 'Expired subscriptions';
?>

<link rel="stylesheet" href="/public/css/subscription-account-pause.css">

<div class="subscription-account subscription-account--<?= htmlspecialchars($accountContext['theme'] ?? 'press_stack') ?>">
    <div class="page-heading">
        <div>
            <div class="page-heading__eyebrow"><?= ($accountContext['mode'] ?? null) === 'member' ? 'Member area' : 'Account' ?></div>
            <h1 class="page-heading__title">Subscriptions</h1>
            <p class="page-heading__sub">
                <?= count($currentSubscriptions) ?> current · <?= count($previousSubscriptions) ?> previous
            </p>
        </div>

        <?php if ($canAcquire): ?>
            <button class="btn btn--primary" type="button" data-open-subscription-modal>
                Add subscription
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($currentSubscriptions) && empty($actionRequiredSubscriptions) && empty($previousSubscriptions)): ?>
        <div class="card"><div class="card__body"><div class="empty-state">
            <div class="empty-state__title">No subscriptions</div>
            <div class="empty-state__sub">Subscriptions linked to your account will appear here.</div>
        </div></div></div>
    <?php else: ?>
        <?php foreach ([
            'current-subscriptions-heading' => ['Current subscriptions', $currentSubscriptions],
            'action-required-heading' => ['Action required', $actionRequiredSubscriptions],
        ] as $headingId => [$heading, $subscriptions]): ?>
            <?php if (!empty($subscriptions)): ?>
                <section class="subscription-section" aria-labelledby="<?= $headingId ?>">
                    <h2 class="section-label" id="<?= $headingId ?>"><?= $heading ?></h2>
                    <div class="sub-grid">
                        <?php foreach ($subscriptions as $sub): ?>
                            @include('subscriptions/account/_subscription_card', ['sub' => $sub])
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!$hasLiveSubscriptions && !empty($previousSubscriptions)): ?>
            <div class="card subscription-reactivation-promo" role="status">
                <div class="card__body">
                    <div class="empty-state">
                        <div class="empty-state__title">Your subscriptions have ended</div>
                        <div class="empty-state__sub">Reactivate an expired subscription below to start a new term from today.</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($previousSubscriptions)): ?>
            <details class="subscription-section previous-subscriptions" <?= !$hasLiveSubscriptions ? 'open' : '' ?>>
                <summary><?= htmlspecialchars($expiredSummaryLabel) ?> · <?= count($previousSubscriptions) ?></summary>
                <div class="previous-subscriptions__content">
                    <?php foreach ($previousSubscriptions as $sub): ?>
                        @include('subscriptions/account/_subscription_card', ['sub' => $sub])
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($faqs)): ?>
        <section class="subscription-section faq-section" aria-labelledby="subscription-faq-heading">
            <h2 class="section-label" id="subscription-faq-heading">Subscription FAQs</h2>
            <div class="faq-list">
                <?php foreach ($faqs as $faq): ?>
                    <details class="faq-item">
                        <summary><?= htmlspecialchars($faq['question']) ?></summary>
                        <p><?= htmlspecialchars($faq['answer']) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    @include('subscriptions/account/_subscription_manage_drawer')

    <div class="modal-overlay" id="subscription-pause-modal" role="dialog" aria-modal="true"
         aria-labelledby="subscription-pause-title" data-login-url="<?= htmlspecialchars($loginUrl) ?>" hidden>
        <div class="modal">
            <div class="modal__header">
                <div>
                    <div class="page-heading__eyebrow">Subscription settings</div>
                    <h2 class="modal__title" id="subscription-pause-title">Pause subscription</h2>
                </div>
                <button class="modal__close" type="button" data-subscription-pause-close aria-label="Close">×</button>
            </div>
            <div class="modal__body">
                <p class="cancel-copy" id="subscription-pause-review"></p>
                <ul class="pause-impact-list" id="subscription-pause-impact"></ul>
                <div class="account-message" id="subscription-pause-message" role="alert" aria-live="polite"></div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" data-subscription-pause-close id="subscription-pause-cancel">Keep subscription active</button>
                <button type="button" class="btn btn--danger" id="subscription-pause-confirm">Confirm pause</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="renewal-offer-modal" role="dialog" aria-modal="true"
         aria-labelledby="renewal-offer-title" hidden>
        <div class="modal">
            <div class="modal__header">
                <div>
                    <div class="page-heading__eyebrow">Renewal offer</div>
                    <h2 class="modal__title" id="renewal-offer-title">Accepted offer</h2>
                </div>
                <button class="modal__close" type="button" data-renewal-offer-close aria-label="Close">×</button>
            </div>
            <div class="modal__body">
                <p class="cancel-copy" id="renewal-offer-description"></p>
                <div class="sub-card-full__body">
                    <div class="sub-detail">
                        <div class="sub-detail__label">Price</div>
                        <div class="sub-detail__value" id="renewal-offer-price"></div>
                    </div>
                    <div class="sub-detail">
                        <div class="sub-detail__label">Term</div>
                        <div class="sub-detail__value" id="renewal-offer-term"></div>
                    </div>
                    <div class="sub-detail">
                        <div class="sub-detail__label">Renewal date</div>
                        <div class="sub-detail__value" id="renewal-offer-date"></div>
                    </div>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--gold" data-renewal-offer-close>Done</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="cancel-modal" role="dialog" aria-modal="true"
         aria-labelledby="cancel-modal-title"
         data-cancel-endpoint="<?= htmlspecialchars($cancelBase) ?>"
         data-login-url="<?= htmlspecialchars($loginUrl) ?>">
        <div class="modal">
            <div class="modal__header">
                <h2 class="modal__title" id="cancel-modal-title">Cancel subscription renewal</h2>
                <button class="modal__close" type="button" data-cancel-action="close" aria-label="Close">×</button>
            </div>
            <div class="modal__body">
                <div id="step-indicator"></div>
                <div class="cancel-step active" id="cancel-step-1">
                    <p class="cancel-copy" id="cancel-review-copy">You’re about to cancel <strong id="cancel-plan-name">your subscription</strong>. You’ll keep access until <strong id="cancel-end-date">the end of your current term</strong>.</p>
                    <ul class="benefit-list" id="cancel-lost-benefits"></ul>
                </div>
                <div class="cancel-step" id="cancel-step-2">
                    <p class="cancel-copy">Help us improve — why are you cancelling?</p>
                    <div class="reason-list">
                        <?php foreach (($cancellation_reasons ?? []) as $reason): ?>
                            <label class="reason-radio"><input type="radio" name="cancel_reason" value="<?= htmlspecialchars($reason['value']) ?>"> <?= htmlspecialchars($reason['label']) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <textarea id="other-reason-text" class="reason-other-textarea" placeholder="Tell us more (optional)" hidden></textarea>
                </div>
                <div class="cancel-step" id="cancel-step-3">
                    <div class="confirm-danger-box" id="cancel-confirmation-copy">Your renewal will be cancelled. Access continues until <strong id="confirm-end-date">the end of your current term</strong>.</div>
                    <p class="cancel-copy cancel-copy--muted" id="cancel-refund-copy">Refund eligibility depends on your subscription terms.</p>
                </div>
                <div class="account-message" id="cancel-message" role="alert" aria-live="polite"></div>
            </div>
            <div class="modal__footer" id="cancel-modal-footer"></div>
        </div>
    </div>

    <?php if ($canAcquire && ($accountContext['show_subscription_modal'] ?? false)): ?>
        @include('components/subscription-modal', [
            'subscriptionModalData' => array_merge(
                ['member' => $member ?? null],
                $subscription_modal_data ?? []
            ),
        ])
    <?php endif; ?>
</div>

<script src="/public/js/subscription-account-pause-controller.js" defer></script>
<script src="/public/js/subscription-account-renewal-offer.js" defer></script>
