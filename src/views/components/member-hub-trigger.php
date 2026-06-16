<?php
/**
 * Member Hub Trigger — shared partial
 *
 * Expects:
 *   $isLoggedIn     bool
 *   $memberInitial  string
 *   $unreadCount    int
 *   $siteSlug       string
 */

$unreadCount = (int)($unreadCount ?? 0);
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
                aria-label="<?= $unreadCount ?> unread notifications"
                style="<?= $unreadCount > 0 ? '' : 'display:none' ?>"
        ><?= $unreadCount > 0 ? $unreadCount : '' ?></span>
    <?php else: ?>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
        </svg>
        <span class="mh-hub-pill-label">Join / Sign in</span>
    <?php endif; ?>
</button>
