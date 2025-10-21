<!DOCTYPE html>
<html lang="en">
<?php
use App\Framework\Support\SiteContext;

$cssFile = asset(SiteContext::css(), 'css');

?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SiteContext::name() ?></title>
    <meta data-site-name="<?= SiteContext::get()->slug?>">

    <script>
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
<header class="header">
    <div class="header-container">
        <a href="/" class="logo">
            <span class="logo-main"><?= SiteContext::name() ?></span>
            <span class="logo-sub">BEAT</span>
        </a>

        <nav class="header-nav">
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <span></span><span></span><span></span>
            </button>

            <ul id="mainNav">
                <?php if (isset($menu) && !empty($menu)): ?>
                    <?php foreach ($menu->items as $item): ?>
                        <li class="nav-item">
                            <a href="<?= htmlspecialchars($item->url) ?>" class="nav-link">
                                <?= htmlspecialchars($item->label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="header-actions">
            <a href="https://instagram.com" class="social-icon">Instagram</a>
            <a href="https://twitter.com" class="social-icon">Twitter</a>
            <button class="header-search-toggle" onclick="toggleSearch()">Search</button>
        </div>
    </div>

    <div class="search-overlay" id="searchOverlay">
        <div class="search-container">
            <button class="search-close" onclick="toggleSearch()">×</button>
            <form class="search-form" action="/search" method="GET">
                <input type="search" name="q" placeholder="Search tracks, artists, albums..." class="search-input" autofocus="">
                <button type="submit" class="search-submit">Search</button>
            </form>
        </div>
    </div>
</header>