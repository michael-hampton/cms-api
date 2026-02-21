<?php
/**
 * Member Hub Trigger — shared partial
 *
 * Renders the "My Hub" / "Join / Sign in" pill button that opens the
 * slide-out panel. Included in two places:
 *
 *   1. member-hub.php          → inside the utility bar (article pages)
 *   2. member-badge.php        → inside the header (all other pages)
 *
 * Expects these variables already set by the parent include:
 *   $isLoggedIn     bool
 *   $memberInitial  string  e.g. "S"
 *   $unreadCount    int
 *   $siteSlug       string
 *
 * The button has id="mh-hub-trigger" — member-hub.js wires the click
 * handler to this ID. Only one instance should exist in the DOM at a time,
 * which is guaranteed because member-badge.php only renders its version
 * when the utility bar is NOT present (non-article pages).
 */
?>
<button
        class="mh-hub-pill <?= $isLoggedIn ? 'mh-hub-pill--member' : 'mh-hub-pill--guest' ?>"
        id="mh-hub-trigger"
        aria-haspopup="dialog"
        aria-expanded="false"
        aria-controls="mh-panel"
>
    <?php if ($isLoggedIn): ?>
        <div class="mh-hub-pill-avatar"><?= $memberInitial ?></div>
        <span class="mh-hub-pill-label">My Hub</span>
        <span
                class="mh-hub-pill-badge"
                id="mh-hub-badge"
                aria-label="<?= (int)$unreadCount ?> unread notifications"
                style="<?= $unreadCount > 0 ? '' : 'display:none' ?>"
        ><?= $unreadCount > 0 ? (int)$unreadCount : '' ?></span>
    <?php else: ?>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
        </svg>
        <span class="mh-hub-pill-label">Join / Sign in</span>
    <?php endif; ?>
</button>