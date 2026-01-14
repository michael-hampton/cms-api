<?php if ($isAuthenticated && isset($nextCommentBadge) && $nextCommentBadge): ?>
    <div class="comment-badge-incentive">
        <div class="badge-incentive-content">
            <div class="badge-icon-wrapper">
                <div class="badge-progress-ring">
                    <svg width="120" height="120">
                        <circle cx="60" cy="60" r="54" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                        <circle cx="60" cy="60" r="54" fill="none" stroke="url(#gradient)" stroke-width="8"
                                stroke-dasharray="339.292"
                                stroke-dashoffset="<?= 339.292 * (1 - $commentBadgeProgress['percentage'] / 100) ?>"
                                stroke-linecap="round"
                                transform="rotate(-90 60 60)"
                                style="transition: stroke-dashoffset 0.5s ease;"/>
                        <defs>
                            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#667eea;stop-opacity:1"/>
                                <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="badge-icon-center">
                        <span class="badge-icon-emoji"><?= $nextCommentBadge->icon ?></span>
                    </div>
                </div>
            </div>
            <div class="badge-incentive-info">
                <h3 class="badge-incentive-title">
                    🎯 Unlock: <?= htmlspecialchars($nextCommentBadge->name) ?>
                </h3>
                <p class="badge-incentive-description">
                    <?= htmlspecialchars($nextCommentBadge->description) ?>
                </p>
                <div class="badge-progress-bar">
                    <div class="badge-progress-fill" style="width: <?= $commentBadgeProgress['percentage'] ?>%"></div>
                </div>
                <p class="badge-progress-text">
                    <?php
                    $current = $commentBadgeProgress['details'][0]['current'];
                    $target = $commentBadgeProgress['details'][0]['target'];
                    $remaining = $target - $current;
                    ?>
                    <strong><?= $current ?></strong> of <strong><?= $target ?></strong> comments
                    <?php if ($remaining > 0): ?>
                        • <span class="remaining"><?= $remaining ?> more to go!</span>
                    <?php endif; ?>
                </p>
                <?php if ($nextCommentBadge->points > 0): ?>
                    <div class="badge-points-reward">
                        ⭐ Earn <strong><?= $nextCommentBadge->points ?> points</strong> when unlocked
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>