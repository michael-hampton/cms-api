<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe to Read - <?= htmlspecialchars($page->title) ?></title>
    @css('landing-page.css')
</head>
<body>

@include('header', ['menu' => $menu, 'title' => 'Subscribe to Read'])

<div class="paywall-container">
    <div class="paywall-content">
        <div class="paywall-icon">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>

        <h1>Premium Content</h1>
        <h2><?= htmlspecialchars($page->title) ?></h2>

        <?php if ($reason === 'published_after_subscription'): ?>
            <p class="paywall-message">
                You had access to this content during your previous subscription.
                <strong>Resubscribe to continue reading premium content.</strong>
            </p>
        <?php elseif ($reason === 'published_before_subscription'): ?>
            <p class="paywall-message">
                This content was published before your subscription started.
                It's not included in your historical access.
            </p>
        <?php elseif ($reason === 'member_required'): ?>
            <p class="paywall-message">
                This content is available to registered members.
                <strong>Sign up for free to continue reading.</strong>
            </p>
        <?php else: ?>
            <p class="paywall-message">
                This article is available exclusively to premium subscribers.
                <strong>Subscribe today to unlock unlimited access.</strong>
            </p>
        <?php endif; ?>

        <div class="paywall-benefits">
            <h3>Subscription Benefits</h3>
            <ul>
                <li>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    Unlimited access to all premium content
                </li>
                <li>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    Keep access to articles from your subscription period
                </li>
                <li>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    Support quality journalism
                </li>
                <li>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    Ad-free reading experience
                </li>
            </ul>
        </div>

        <div class="paywall-actions">
            <?php if ($member): ?>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscribe" class="btn btn-primary btn-lg">
                    View Subscription Plans
                </a>
            <?php else: ?>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscribe" class="btn btn-primary btn-lg">
                    Subscribe Now
                </a>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/login?redirect=<?= urlencode($page->getUrlAttribute()) ?>"
                   class="btn btn-secondary btn-lg">
                    Already a subscriber? Sign In
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .paywall-container {
        min-height: calc(100vh - 200px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    .paywall-content {
        max-width: 600px;
        text-align: center;
        background: white;
        padding: 3rem;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }

    .paywall-icon {
        color: #3b82f6;
        margin-bottom: 1.5rem;
    }

    .paywall-content h1 {
        font-size: 2rem;
        font-weight: 700;
        color: #212529;
        margin-bottom: 0.5rem;
    }

    .paywall-content h2 {
        font-size: 1.25rem;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 1.5rem;
    }

    .paywall-message {
        font-size: 1.125rem;
        line-height: 1.7;
        color: #495057;
        margin-bottom: 2rem;
    }

    .paywall-benefits {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: left;
    }

    .paywall-benefits h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #212529;
        margin-bottom: 1rem;
        text-align: center;
    }

    .paywall-benefits ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .paywall-benefits li {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 0;
        color: #495057;
        font-size: 1rem;
    }

    .paywall-benefits li svg {
        color: #10b981;
        flex-shrink: 0;
    }

    .paywall-actions {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .btn-lg {
        padding: 1rem 2rem;
        font-size: 1.125rem;
    }

    @media (max-width: 768px) {
        .paywall-content {
            padding: 2rem 1.5rem;
        }

        .paywall-content h1 {
            font-size: 1.5rem;
        }
    }
</style>

</body>
</html>