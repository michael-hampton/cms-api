<?php
use App\Framework\Authorization\MemberAuth;
use App\Services\Members\BadgeAccessService;

$canAccessBadges ??= MemberAuth::check()
    ? app(BadgeAccessService::class)->canAccessBadges(MemberAuth::getMember(), $site)
    : false;
?>
<style>
    /* Sidebar Navigation */
    .layout {
        display: flex;
        max-width: 1400px;
        margin: 0 auto;
    }

    .sidebar {
        width: 250px;
        background: white;
        min-height: calc(100vh - 80px);
        padding: 1.5rem 0;
        box-shadow: var(--shadow);
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1.5rem;
        color: var(--text-primary);
        text-decoration: none;
        transition: all 0.2s ease;
        border-left: 3px solid transparent;
    }

    .nav-item:hover {
        background: var(--bg-light);
        border-left-color: var(--primary-color);
    }

    .nav-item.active {
        background: #fef2f2;
        border-left-color: var(--primary-color);
        color: var(--primary-color);
        font-weight: 600;
    }

    .nav-item svg {
        width: 20px;
        height: 20px;
    }

    /* Main Content */
    .main-content {
        flex: 1;
        padding: 2rem;
    }
</style>

<aside class="sidebar">
    <a href="/<?= $site->slug ?>/member/dashboard" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"/>
            <rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/>
            <rect x="3" y="14" width="7" height="7"/>
        </svg>
        Dashboard
    </a>
    <a href="/<?= $site->slug ?>/member/subscriptions" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
        </svg>
        Subscriptions
    </a>
    <a href="/<?= $site->slug ?>/member/activity" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2v20M2 12h20"/>
        </svg>
        Rewards
    </a>
    <a href="/<?= $site->slug ?>/member/newsletters" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
        </svg>
        Newsletters
    </a>
    <?php if ($canAccessBadges): ?>
        <a href="/<?= $site->slug ?>/member/activity/badges" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="8" r="7"/>
                <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
            </svg>
            Badges
        </a>
    <?php endif; ?>
    <a href="/<?= $site->slug ?>/member/orders" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 0 1-8 0"/>
        </svg>
        My Orders
    </a>
    <a href="/<?= $site->slug ?>/member/addresses" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
            <circle cx="12" cy="10" r="3"/>
        </svg>
        Addresses
    </a>
    <a href="/<?= $site->slug ?>/member/comments" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Comments
    </a>
    <a href="/<?= $site->slug ?>/member/reading-history" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
        </svg>
        Reading History
    </a>
    <a href="/<?= $site->slug ?>/member/liked-pages" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
        Liked Pages
    </a>
    <a href="/<?= $site->slug ?>/member/wishlist" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon
                    points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
        My Favorites
    </a>
    <a href="/<?= $site->slug ?>/member/payment-methods" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
            <line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
        Payment Methods
    </a>
    <a href="/<?= $site->slug ?>/member/account-details" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
        </svg>
        Account
    </a>
    <a href="/<?= $site->slug ?>/member/settings" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/>
            <path d="M12 1v6m0 6v6M5.6 5.6l4.2 4.2m4.2 4.2l4.2 4.2M1 12h6m6 0h6M5.6 18.4l4.2-4.2m4.2-4.2l4.2-4.2"/>
        </svg>
        Settings
    </a>
    <a href="/<?= $site->slug ?>/member/help" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3m.08 4h.01"/>
        </svg>
        Help
    </a>
</aside>
