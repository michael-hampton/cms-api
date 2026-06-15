<?php
$metadataVisibility = $page->metadata->visibility ?? null;
$isContributorCreated = !empty($page->contributor_id);
$isPurchasablePremium = $isContributorCreated
    && (bool)$page->is_paid
    && (int)$page->price > 0
    && !empty($page->premium_approved_at)
    && empty($page->monetisation_disabled_at)
    && $metadataVisibility === 'premium';
$siteSlug = \App\Framework\Support\SiteContext::slug();
$canonical = '/' . $siteSlug . '/' . ltrim((string)$page->slug, '/');
$memberEmail = $member?->email ?? '';
$priceFormatted = '£' . number_format(((int)$page->price) / 100, 2);
$stripeKey = $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key');
?>
<div
    class="paywall-overlay"
    data-paywall-overlay
    data-page-id="<?= (int)$page->id ?>"
    data-site-slug="<?= htmlspecialchars($siteSlug, ENT_QUOTES, 'UTF-8') ?>"
    data-purchase-endpoint="/api/<?= rawurlencode($siteSlug) ?>/open-collab/pages/<?= (int)$page->id ?>/purchase"
    data-stripe-key="<?= htmlspecialchars((string)$stripeKey, ENT_QUOTES, 'UTF-8') ?>"
    data-contributor-created="<?= $isContributorCreated ? 'true' : 'false' ?>"
    role="dialog"
    aria-modal="true"
    aria-labelledby="paywall-title"
    aria-describedby="paywall-message"
>
    <div class="paywall-overlay__backdrop" aria-hidden="true"></div>

    <section class="paywall-overlay__dialog" tabindex="-1">
        <div class="paywall-icon" aria-hidden="true">
            <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>

        <h1 id="paywall-title">Premium Content</h1>
        <h2><?= htmlspecialchars((string)$page->title, ENT_QUOTES, 'UTF-8') ?></h2>

        <div id="paywall-message" class="paywall-message">
            <?php if ($isPurchasablePremium): ?>
                <p>Purchase instant, permanent access to this article. No subscription required.</p>
            <?php elseif ($reason === 'published_after_subscription'): ?>
                <p>You had access during your previous subscription. <strong>Resubscribe to continue reading.</strong></p>
            <?php elseif ($reason === 'published_before_subscription'): ?>
                <p>This article predates your subscription and is not included in your historical access.</p>
            <?php elseif ($reason === 'member_required'): ?>
                <p>This content is available to registered members. <strong>Sign up for free to continue.</strong></p>
            <?php else: ?>
                <p>This article is available exclusively to premium subscribers. <strong>Subscribe to unlock access.</strong></p>
            <?php endif; ?>
        </div>

        <div class="paywall-benefits">
            <h3>Membership benefits</h3>
            <ul>
                <li>Unlimited premium content</li>
                <li>Access to articles from your subscription period</li>
                <li>Support quality journalism</li>
                <li>Ad-free reading experience</li>
            </ul>
        </div>

        <div class="paywall-actions">
            <?php if ($isPurchasablePremium): ?>
                <button class="btn btn-primary btn-lg" type="button" data-paywall-open-purchase>
                    Buy Article — <?= $priceFormatted ?>
                </button>
            <?php else: ?>
                <button class="btn btn-primary btn-lg" type="button" data-paywall-open-subscription>
                    View Subscription Plans
                </button>
            <?php endif; ?>

            <?php if (!$member): ?>
                <a class="btn btn-secondary btn-lg" href="/<?= rawurlencode($siteSlug) ?>/member/login?redirect=<?= rawurlencode($canonical) ?>">
                    Already a subscriber? Sign In
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($isPurchasablePremium): ?>
        <section class="paywall-purchase" data-paywall-purchase hidden role="dialog" aria-modal="true" aria-labelledby="paywall-purchase-title">
            <div class="paywall-purchase__dialog" tabindex="-1">
                <header class="paywall-purchase__header">
                    <div>
                        <h2 id="paywall-purchase-title">Complete Purchase</h2>
                        <p><?= htmlspecialchars((string)$page->title, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <button type="button" class="paywall-purchase__close" data-paywall-close-purchase aria-label="Close">&times;</button>
                </header>

                <div class="paywall-purchase__body" data-paywall-payment-form>
                    <div class="paywall-order-summary">
                        <span>Total</span>
                        <strong><?= $priceFormatted ?></strong>
                    </div>

                    <?php if (!$memberEmail): ?>
                        <label class="paywall-field">
                            <span>Email address</span>
                            <input type="email" data-paywall-email autocomplete="email" required>
                            <small data-paywall-email-error></small>
                        </label>
                    <?php else: ?>
                        <input type="hidden" data-paywall-email value="<?= htmlspecialchars($memberEmail, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>

                    <label class="paywall-field">
                        <span>Card details</span>
                        <div data-paywall-card></div>
                        <small data-paywall-card-error role="alert" aria-live="polite"></small>
                    </label>

                    <button type="button" class="btn btn-primary" data-paywall-submit-payment>
                        Pay <?= $priceFormatted ?>
                    </button>
                    <p class="paywall-security-note">Secured by Stripe — card details go directly to Stripe.</p>
                </div>

                <div class="paywall-purchase__success" data-paywall-payment-success hidden>
                    <div class="paywall-success-icon">✓</div>
                    <h3>Payment successful</h3>
                    <p>You now have permanent access. Reloading the article…</p>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
