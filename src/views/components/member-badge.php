<?php
/**
 * Member Badge — header component
 *
 * Renders the account CTA or logged-in profile section in the header.
 *
 * On ARTICLE pages  → the hub trigger pill lives inside the utility bar
 *                     (rendered by member-hub.php). We do NOT add it here
 *                     to avoid duplicate #mh-hub-trigger IDs.
 *
 * On ALL OTHER pages → the utility bar is absent, so we inject the hub
 *                      trigger pill here so it's always reachable.
 *
 * The $isArticlePage flag is set by member-hub.php which runs first in the
 * layout. If member-hub.php has not run yet, we default to false (safe —
 * renders the trigger).
 */

// Pull the same auth state that member-hub.php already computed.
// These vars are in scope because both partials are included in the
// same layout template.
$isLoggedIn = \App\Framework\Authorization\MemberAuth::check();
$siteSlug = \App\Framework\Support\SiteContext::slug();
$member = $isLoggedIn ? \App\Framework\Authorization\MemberAuth::getMember() : null;

$memberFirstName = $member->first_name ?? $member->email ?? 'M';
$memberLastName = $member->last_name ?? '';
$memberDisplay = $member->displayName ?? trim("$memberFirstName $memberLastName");
$memberInitial = strtoupper(substr($memberFirstName, 0, 1));
$unreadCount = 0; // [TODO-ROUTES] match value from member-hub.php

// Is the utility bar already present on this page?
// member-hub.php sets $isArticlePage before this component is rendered.
$isArticlePage = $isArticlePage ?? false;
?>

<style>
    .account-cta-button {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-decoration: none;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 0.9375rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px -1px rgba(102, 126, 234, 0.3);
        position: relative;
        overflow: hidden;
    }

    .account-cta-button::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.2) 0%,
        rgba(255, 255, 255, 0) 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .account-cta-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.4);
    }

    .account-cta-button:hover::before {
        opacity: 1;
    }

    .account-cta-button svg {
        width: 20px;
        height: 20px;
        transition: transform 0.3s ease;
    }

    .account-cta-button:hover svg {
        transform: scale(1.1);
    }

    .account-cta-text {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.125rem;
    }

    .account-cta-main {
        font-size: 0.9375rem;
        font-weight: 600;
        line-height: 1;
    }

    .account-cta-sub {
        font-size: 0.75rem;
        opacity: 0.9;
        font-weight: 400;
    }

    @media (max-width: 768px) {
        .account-cta-button {
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
        }

        .account-cta-text {
            display: none;
        }
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.625rem 1.25rem;
        background: var(--bg-light);
        border-radius: 50px;
        transition: all 0.3s ease;
        margin-left: auto;
    }

    .user-profile:hover {
        background: #e5e7eb;
    }

    .user-avatar {
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 50%;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: black;
        font-weight: 700;
        font-size: 1.125rem;
        box-shadow: var(--shadow-sm);
        flex-shrink: 0;
    }

    .user-details {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .user-name {
        font-weight: 600;
        font-size: 0.9375rem;
        color: var(--text-primary);
        line-height: 1.2;
    }

    .user-role {
        font-size: 0.75rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        line-height: 1;
    }

    .btn-logout {
        padding: 0.625rem 1.25rem;
        background: white;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
    }

    .btn-logout:hover {
        border-color: var(--danger-color);
        color: var(--danger-color);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }
</style>

@include('components/subscription-button')

<?php if (!$isLoggedIn): ?>

    <?php /* ── Guest: show sign-in CTA ── */ ?>
    <div class="header-actions">

        <?php /* Hub trigger on non-article pages so guests can still
               access Deals/Community without being on an article */ ?>
        <?php if (!$isArticlePage): ?>
            @include('components/member-hub-trigger', ['isLoggedIn' => \App\Framework\Authorization\MemberAuth::check()])
        <?php endif; ?>

        <a href="/<?= $siteSlug ?>/member/dashboard" class="account-cta-button">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="3.2"/>
                <path d="M4.5 20c0-3.2 2.8-5.8 7.5-5.8s7.5 2.6 7.5 5.8"/>
            </svg>
            <div class="account-cta-text">
                <span class="account-cta-main">My Account</span>
                <span class="account-cta-sub">Login or Sign Up</span>
            </div>
        </a>
    </div>

<?php else: ?>

    <?php /* ── Logged in: show profile strip ── */ ?>
    <div class="header-actions">

        <?php /* Hub trigger on non-article pages — on article pages the
               utility bar provides it, so we skip it here to avoid
               duplicate #mh-hub-trigger IDs in the DOM */ ?>
        <?php if (!$isArticlePage): ?>
            @include('components/member-hub-trigger', ['isLoggedIn' => \App\Framework\Authorization\MemberAuth::check()])
        <?php endif; ?>

        <a href="/<?= $siteSlug ?>/member/dashboard">
            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($member->first_name ?? $member->email ?? 'M', 0, 1)) ?>
                </div>
                <div class="user-details">
                    <span class="user-name">
                        <?= htmlspecialchars($member->displayName ?? 'Member') ?>
                    </span>
                    <span class="user-role">Member</span>
                </div>
            </div>
        </a>

        <button class="mobile-menu-toggle"
                onclick="toggleMobileMenu()"
                aria-label="Toggle menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <form method="POST" action="/member/logout" style="display:inline">
            <button type="submit" class="btn-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                <span>Logout</span>
            </button>
        </form>
    </div>

<?php endif; ?>