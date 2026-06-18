<!DOCTYPE html>
<html lang="en">
<?php

use App\DTO\PublicContent\PublicContentSeo;
use App\Framework\Support\SiteContext;

$cssFile = asset(SiteContext::css(), 'css');
$site = SiteContext::get();
$siteName = $site ? $site->name : 'Site';
$siteSlug = $site ? $site->slug : 'default';
$navigationTitle = isset($hasTitle) && $hasTitle === false
    ? ''
    : ($title ?? ($page->title ?? $siteName));

/** @var PublicContentSeo|null $seoData */
$seoData = ($seo ?? null) instanceof PublicContentSeo ? $seo : null;
$documentTitle = trim((string) ($seoData?->title ?? $title ?? $page->title ?? $siteName));
$documentDescription = trim((string) ($seoData?->description ?? $description ?? ''));
$documentKeywords = trim((string) ($seoData?->keywords ?? ''));
$canonicalUrl = trim((string) ($seoData?->canonical ?? ''));
$robots = trim((string) ($seoData?->robots ?? ''));
$ogType = trim((string) ($seoData?->ogType ?? 'website'));
$ogTitle = trim((string) ($seoData?->ogTitle ?? $documentTitle));
$ogDescription = trim((string) ($seoData?->ogDescription ?? $documentDescription));
$ogImage = trim((string) ($seoData?->ogImage ?? ''));
$twitterCard = trim((string) ($seoData?->twitterCard ?? ($ogImage ? 'summary_large_image' : 'summary')));
$schema = $seoData?->schema;

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($documentTitle !== '' ? $documentTitle : $siteName) ?></title>

    <?php if ($documentDescription !== ''): ?>
        <meta name="description" content="<?= $escape($documentDescription) ?>">
    <?php endif; ?>

    <?php if ($documentKeywords !== ''): ?>
        <meta name="keywords" content="<?= $escape($documentKeywords) ?>">
    <?php endif; ?>

    <?php if ($canonicalUrl !== ''): ?>
        <link rel="canonical" href="<?= $escape($canonicalUrl) ?>">
    <?php endif; ?>

    <?php if ($robots !== ''): ?>
        <meta name="robots" content="<?= $escape($robots) ?>">
    <?php endif; ?>

    <?php if ($seoData !== null): ?>
        <meta property="og:type" content="<?= $escape($ogType) ?>">
        <meta property="og:title" content="<?= $escape($ogTitle) ?>">
        <?php if ($ogDescription !== ''): ?>
            <meta property="og:description" content="<?= $escape($ogDescription) ?>">
        <?php endif; ?>
        <?php if ($canonicalUrl !== ''): ?>
            <meta property="og:url" content="<?= $escape($canonicalUrl) ?>">
        <?php endif; ?>
        <?php if ($ogImage !== ''): ?>
            <meta property="og:image" content="<?= $escape($ogImage) ?>">
        <?php endif; ?>
        <meta property="og:site_name" content="<?= $escape($siteName) ?>">

        <meta name="twitter:card" content="<?= $escape($twitterCard) ?>">
        <meta name="twitter:title" content="<?= $escape($ogTitle) ?>">
        <?php if ($ogDescription !== ''): ?>
            <meta name="twitter:description" content="<?= $escape($ogDescription) ?>">
        <?php endif; ?>
        <?php if ($ogImage !== ''): ?>
            <meta name="twitter:image" content="<?= $escape($ogImage) ?>">
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($schema)): ?>
        <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>

    <meta data-site-name="<?= $escape((string) SiteContext::get()->slug) ?>">
    @css('base-blocks.css')
    @js('product-interactions.js')
    @css('member-hub.css')

    <script>
        site = '<?= \App\Framework\Support\SiteContext::slug() ?>';
        SITE = '<?= \App\Framework\Support\SiteContext::slug() ?>';

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('newsletter-form');
            if (!form) return;

            const emailInput = document.getElementById('newsletter-email');
            const submitBtn = document.getElementById('newsletter-submit');
            const messageDiv = document.getElementById('newsletter-message');
            const siteName = document.querySelector('[data-site-name]')?.dataset.siteName
                || window.location.hostname.split('.')[0];

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const email = emailInput.value.trim();
                if (!email) return;

                submitBtn.disabled = true;
                submitBtn.textContent = 'Subscribing...';
                messageDiv.className = 'newsletter-message';
                messageDiv.textContent = '';

                try {
                    const response = await fetch(`/api/${siteName}/newsletter/web/signup`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({email: email})
                    });

                    const data = await response.json();

                    if (response.ok && data.data.success) {
                        messageDiv.className = 'newsletter-message success';
                        messageDiv.textContent = data.message || 'Successfully subscribed! Please check your email.';
                        emailInput.value = '';
                    } else {
                        messageDiv.className = 'newsletter-message error';
                        messageDiv.textContent = data.error || 'Subscription failed. Please try again.';
                    }
                } catch (error) {
                    messageDiv.className = 'newsletter-message error';
                    messageDiv.textContent = 'Network error. Please try again.';
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Subscribe';
                }
            });

            const urlParams = new URLSearchParams(window.location.search);
            const unsubToken = urlParams.get('unsubscribe');

            if (unsubToken) {
                handleUnsubscribe(unsubToken);
            }

            async function handleUnsubscribe(token) {
                try {
                    const response = await fetch(`/api/${siteName}/newsletter/unsubscribe`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({token: token})
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        alert('Successfully unsubscribed from newsletter.');
                    } else {
                        alert('Unsubscribe failed: ' + (data.error || 'Unknown error'));
                    }
                } catch (error) {
                    alert('Network error during unsubscribe.');
                }
            }
        });
    </script>

    <link rel="stylesheet" href="<?= $cssFile ?>">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        .header-member-bar {
            border-top: 1px solid rgba(148, 163, 184, .18);
            background: rgba(248, 250, 252, .82);
        }

        .header-member-bar__container {
            min-height: 4.25rem;
            justify-content: flex-end;
        }

        .header-member-bar__actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .75rem;
            width: 100%;
            flex-wrap: wrap;
        }

        .header-member-bar__actions > .header-actions {
            margin: 0;
        }

        @media (max-width: 768px) {
            .header-member-bar__container {
                min-height: auto;
                padding-top: .75rem;
                padding-bottom: .75rem;
            }

            .header-member-bar__actions {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
<header class="site-header" data-site="<?= $siteSlug ?>">
    <div class="header-container">
        <a href="/" class="site-logo">
            <div class="logo-wrapper">
                <span class="logo-icon">
                    <?php if ($siteSlug === 'gocompare'): ?>
                        🔍
                    <?php elseif ($siteSlug === 'musicsite'): ?>
                        🎵
                    <?php else: ?>
                        ⭐
                    <?php endif; ?>
                </span>
                <div class="logo-text">
                    <span class="logo-main"><?= $siteName ?></span>
                    <span class="logo-tagline">
                        <?php if ($siteSlug === 'gocompare'): ?>
                            Compare & Save
                        <?php elseif ($siteSlug === 'musicsite'): ?>
                            Your Music Journey
                        <?php else: ?>
                            Excellence Delivered
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </a>

        <?php echo $menuRenderer->render($menu, [
            'layout' => 'mega',
            'css_classes' => 'main-navigation',
            'logo' => false,
            'title' => $navigationTitle,
        ]); ?>

        <div class="header-actions">
            <button class="header-search-toggle" onclick="toggleSearch()" aria-label="Search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </button>
            <?php if (\App\Framework\Authorization\MemberAuth::check()): ?>
                <button class="icon-btn" id="wishlist-btn" onclick="location.href=`/${SITE}/member/wishlist`"
                        aria-label="Wishlist">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span class="badge" id="wishlist-count" style="display: block;">4</span>
                </button>
            <?php endif; ?>
            <button class="icon-btn" id="cart-btn" onclick="MiniCart.open()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <span class="badge" id="cart-count" style="display: none;">0</span>
            </button>
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>

    <div class="header-member-bar">
        <div class="header-container header-member-bar__container">
            <div class="header-member-bar__actions">
                @include('components/member-badge')
            </div>
        </div>
    </div>

    @include('components/search-overlay')
</header>

@include('components/member-hub')

@js('member-hub.js')

<script>
    function toggleSearch() {
        document.getElementById('searchOverlay').classList.toggle('active');
    }

    function toggleMobileMenu() {
        document.querySelector('.mega-menu-nav').classList.toggle('mobile-active');
        document.querySelector('.mobile-menu-toggle').classList.toggle('active');
    }
</script>