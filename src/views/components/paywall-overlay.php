<?php
$metadataVisibility = $page->metadata->visibility ?? null;
$isPurchasablePremium = (bool)$page->is_paid
    && (int)$page->price > 0
    && !empty($page->premium_approved_at)
    && empty($page->monetisation_disabled_at)
    && !empty($page->contributor_id)
    && $metadataVisibility === 'premium';
$siteSlug = \App\Framework\Support\SiteContext::slug();
$canonical = '/' . $siteSlug . '/' . ltrim((string)$page->slug, '/');
?>
<div class="paywall-overlay" data-paywall-overlay role="dialog" aria-modal="true" aria-labelledby="paywall-title" aria-describedby="paywall-message">
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
                <a class="btn btn-primary btn-lg" href="/<?= rawurlencode($siteSlug) ?>/article-purchase/<?= (int)$page->id ?>">
                    Buy Article — £<?= number_format(((int)$page->price) / 100, 2) ?>
                </a>
            <?php else: ?>
                <a class="btn btn-primary btn-lg" href="/<?= rawurlencode($siteSlug) ?>/subscribe">View Subscription Plans</a>
            <?php endif; ?>

            <?php if (!$member): ?>
                <a class="btn btn-secondary btn-lg" href="/<?= rawurlencode($siteSlug) ?>/member/login?redirect=<?= rawurlencode($canonical) ?>">
                    Already a subscriber? Sign In
                </a>
            <?php endif; ?>
        </div>
    </section>
</div>
