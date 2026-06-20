<?php
/**
 * Variables from ShopAccountController::subscriptions():
 * $member, $grouped, $summary, $cancellation_reasons, $active_tab.
 */

$page_title = 'Subscriptions';

$currentSubscriptions = $grouped['current'] ?? [];
$actionRequiredSubscriptions = $grouped['action_required'] ?? [];
$previousSubscriptions = $grouped['previous'] ?? [];
$hasCurrent = !empty($currentSubscriptions);
$hasPrevious = !empty($previousSubscriptions);
?>

@include('subscriptions/account/_layout')

<main class="page-content">
    <div class="page-heading">
        <div class="page-heading__eyebrow">Account</div>
        <h1 class="page-heading__title">Subscriptions</h1>
        <p class="page-heading__sub">
            <?= count($currentSubscriptions) ?> active
            · <?= count($previousSubscriptions) ?> previous
        </p>
    </div>

    <?php if (!$hasCurrent && !$hasPrevious): ?>
        <div class="card">
            <div class="card__body">
                <div class="empty-state">
                    <div class="empty-state__line-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/>
                            <path d="M4 5.5v16M8 7h8M8 11h6"/>
                        </svg>
                    </div>
                    <div class="empty-state__title">No subscriptions</div>
                    <div class="empty-state__sub">Subscriptions linked to your account will appear here.</div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php if (!$hasCurrent): ?>
            <div class="card">
                <div class="card__body">
                    <div class="empty-state">
                        <div class="empty-state__line-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/>
                                <path d="M4 5.5v16M8 7h8M8 11h6"/>
                            </svg>
                        </div>
                        <div class="empty-state__title">No active subscriptions</div>
                        <div class="empty-state__sub">Your previous subscriptions are still available below.</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="sub-grid">
            <?php if ($hasCurrent): ?>
                <section class="subscription-section" aria-labelledby="current-subscriptions-heading">
                    <h2 class="section-label" id="current-subscriptions-heading">Current subscriptions</h2>
                    <div class="sub-grid">
                        <?php foreach ($currentSubscriptions as $sub): ?>
                            @include('subscriptions/account/_subscription_card', ['sub' => $sub])
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($actionRequiredSubscriptions)): ?>
                <section class="subscription-section" aria-labelledby="action-required-heading">
                    <h2 class="section-label" id="action-required-heading">Action required</h2>
                    <div class="sub-grid">
                        <?php foreach ($actionRequiredSubscriptions as $sub): ?>
                            @include('subscriptions/account/_subscription_card', ['sub' => $sub])
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($hasPrevious): ?>
                <details class="subscription-section previous-subscriptions" <?= $hasCurrent ? '' : 'open' ?>>
                    <summary>Previous subscriptions · <?= count($previousSubscriptions) ?></summary>
                    <div class="previous-subscriptions__content">
                        <?php foreach ($previousSubscriptions as $sub): ?>
                            @include('subscriptions/account/_subscription_card', ['sub' => $sub])
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
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

    <div class="modal-overlay"
         id="cancel-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="cancel-modal-title"
         data-cancel-endpoint="/press-stack/account/subscriptions/__SUBSCRIPTION_ID__/cancel"
         data-login-url="/member/login">
        <div class="modal">
            <div class="modal__header">
                <div>
                    <h2 class="modal__title" id="cancel-modal-title">Cancel subscription renewal</h2>
                    <div id="step-indicator"></div>
                </div>
                <button class="modal__close" type="button" data-cancel-action="close" aria-label="Close">×</button>
            </div>

            <div class="modal__body">
                <div class="cancel-step active" id="cancel-step-1">
                    <p class="cancel-copy" id="cancel-review-copy">
                        You’re about to cancel <strong id="cancel-plan-name">your subscription</strong>.
                        You’ll keep access until <strong id="cancel-end-date">the end of your current term</strong>.
                    </p>
                    <p class="cancel-copy cancel-copy--muted">After cancelling you’ll lose:</p>
                    <ul class="benefit-list" id="cancel-lost-benefits">
                        <li><span class="benefit-list__icon">×</span>Access to future issues</li>
                        <li><span class="benefit-list__icon">×</span>Member renewal pricing</li>
                        <li><span class="benefit-list__icon">×</span>Digital archive access</li>
                    </ul>
                </div>

                <div class="cancel-step" id="cancel-step-2">
                    <p class="cancel-copy">Help us improve — why are you cancelling?</p>
                    <div class="reason-list">
                        <?php foreach (($cancellation_reasons ?? []) as $reason): ?>
                            <label class="reason-radio">
                                <input type="radio"
                                       name="cancel_reason"
                                       value="<?= htmlspecialchars($reason['value']) ?>">
                                <?= htmlspecialchars($reason['label']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <textarea id="other-reason-text"
                              class="reason-other-textarea"
                              placeholder="Tell us more (optional)"
                              hidden></textarea>
                </div>

                <div class="cancel-step" id="cancel-step-3">
                    <div class="confirm-danger-box" id="cancel-confirmation-copy">
                        Your renewal will be cancelled. Access continues until
                        <strong id="confirm-end-date">the end of your current term</strong>,
                        and no further renewal payment will be taken.
                    </div>
                    <p class="cancel-copy cancel-copy--muted" id="cancel-refund-copy">
                        Refund eligibility depends on your subscription terms. Contact support if you need help.
                    </p>
                </div>

                <div class="account-message"
                     id="cancel-message"
                     role="alert"
                     aria-live="polite"></div>
            </div>

            <div class="modal__footer" id="cancel-modal-footer"></div>
        </div>
    </div>
</main>
</div><!-- /.shell -->

<script src="/public/js/subscription-account.js" defer></script>
<script src="/public/js/subscription-account-management.js" defer></script>
<script src="/public/js/subscription-account-history-delivery.js" defer></script>
<script src="/public/js/subscription-account-upgrade.js" defer></script>
<script src="/public/js/subscription-account-preferences.js" defer></script>
<script src="/public/js/subscription-account-delivery-address.js" defer></script>
<script src="/public/js/subscription-account-digital-access.js" defer></script>
<script src="/public/js/subscription-account-issue-deliveries.js" defer></script>
</body>
</html>