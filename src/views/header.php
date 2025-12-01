<!DOCTYPE html>
<html lang="en">
<?php

use App\Framework\Support\SiteContext;

$cssFile = asset(SiteContext::css(), 'css');
$site = SiteContext::get();
$siteName = $site ? $site->name : 'Site';
$siteSlug = $site ? $site->slug : 'default';

?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SiteContext::name() ?></title>
    <meta data-site-name="<?= SiteContext::get()->slug?>">
    @css('base-blocks.css')

    <script>
        site = '<?= \App\Framework\Support\SiteContext::slug() ?>';

        // Newsletter signup functionality
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('newsletter-form');
            if (!form) return;

            const emailInput = document.getElementById('newsletter-email');
            const submitBtn = document.getElementById('newsletter-submit');
            const messageDiv = document.getElementById('newsletter-message');

            // Get site name from URL or data attribute
            const siteName = document.querySelector('[data-site-name]')?.dataset.siteName
                || window.location.hostname.split('.')[0];

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const email = emailInput.value.trim();
                if (!email) return;

                // Disable form
                submitBtn.disabled = true;
                submitBtn.textContent = 'Subscribing...';
                messageDiv.className = 'newsletter-message';
                messageDiv.textContent = '';

                try {
                    const response = await fetch(`/api/${siteName}/newsletter/web/signup`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ email: email })
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

            // Handle unsubscribe if token in URL
            const urlParams = new URLSearchParams(window.location.search);
            const unsubToken = urlParams.get('unsubscribe');

            if (unsubToken) {
                handleUnsubscribe(unsubToken);
            }

            async function handleUnsubscribe(token) {
                try {
                    const response = await fetch(`/api/${siteName}/newsletter/unsubscribe`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ token: token })
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
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<!-- ===================================
     HEADER
     =================================== -->
<header class="site-header" data-site="<?= $siteSlug ?>">
    <div class="header-container">
        <!-- Logo Section -->
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

        <!-- Navigation -->
        <?php echo $menuRenderer->render($menu, [
                'layout' => 'mega',
                'css_classes' => 'main-navigation',
                'logo' => false,
                'title' => isset($hasTitle) && $hasTitle === false ? '' : $title ?? $page->title
        ]); ?>

        <!-- Header Actions -->
        <div class="header-actions">
            <button class="header-search-toggle" onclick="toggleSearch()" aria-label="Search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </button>
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>

    <!-- Search Overlay (existing code) -->
    <div class="search-overlay" id="searchOverlay">
        <div class="search-container">
            <button class="search-close" onclick="toggleSearch()">×</button>
            <form class="search-form" action="/search" method="GET">
                <input type="search" name="q" placeholder="Search..." class="search-input" autofocus>
                <button type="submit" class="search-submit">Search</button>
            </form>
        </div>
    </div>
</header>

<script>
    function toggleSearch() {
        document.getElementById('searchOverlay').classList.toggle('active');
    }

    function toggleMobileMenu() {
        document.querySelector('.mega-menu-nav').classList.toggle('mobile-active');
        document.querySelector('.mobile-menu-toggle').classList.toggle('active');
    }
</script>