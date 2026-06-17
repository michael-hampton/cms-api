<?php

/**
 * Badge Earned Celebration Modal
 * Shows when a member earns a new badge OR has unviewed badges
 *
 * @var \App\Models\Member|null $member
 */

$newBadge = $_SESSION['new_badge_data'] ?? null;
$shouldShowModal = !empty($_SESSION['show_badge_modal']) && $newBadge !== null;

// Fall back to the member's latest badge when the modal has never been shown.
if (!$shouldShowModal && isset($member) && empty($_SESSION['badge_modal_ever_shown'])) {
    $memberBadges = $member->badges ?? collect();
    $latestBadge = $memberBadges->sortByDesc('earned_at')->first();

    if ($latestBadge?->badge) {
        $newBadge = [
            'id' => $latestBadge->badge->id,
            'name' => $latestBadge->badge->name,
            'description' => $latestBadge->badge->description,
            'icon' => $latestBadge->badge->icon ?? '🏆',
            'points' => $latestBadge->badge->points,
            'earned_at' => $latestBadge->earned_at?->format('Y-m-d H:i:s'),
        ];

        $_SESSION['show_badge_modal'] = true;
        $_SESSION['new_badge_data'] = $newBadge;
        $shouldShowModal = true;
    }
}

if (!$shouldShowModal) {
    return;
}

?>

    <style>
        .badge-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .badge-modal {
            background: white;
            border-radius: 1.5rem;
            max-width: 500px;
            width: 90%;
            padding: 0;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .badge-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .badge-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .badge-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .badge-congrats {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            animation: bounceIn 0.6s ease 0.3s both;
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .badge-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
        }

        .badge-icon-container {
            width: 140px;
            height: 140px;
            margin: -70px auto 0;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            animation: pulse 2s infinite;
            position: relative;
            z-index: 1;
        }

        .badge-icon {
            font-size: 5rem;
            animation: rotate 1s ease 0.5s both;
        }

        @keyframes rotate {
            from {
                transform: rotate(-180deg) scale(0);
            }
            to {
                transform: rotate(0) scale(1);
            }
        }

        .badge-sparkles {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .sparkle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: #ffd700;
            border-radius: 50%;
            animation: sparkle 1.5s ease-out infinite;
        }

        .sparkle:nth-child(1) {
            top: 20%;
            left: 20%;
            animation-delay: 0s;
        }

        .sparkle:nth-child(2) {
            top: 20%;
            right: 20%;
            animation-delay: 0.2s;
        }

        .sparkle:nth-child(3) {
            bottom: 20%;
            left: 20%;
            animation-delay: 0.4s;
        }

        .sparkle:nth-child(4) {
            bottom: 20%;
            right: 20%;
            animation-delay: 0.6s;
        }

        .sparkle:nth-child(5) {
            top: 50%;
            left: 10%;
            animation-delay: 0.8s;
        }

        .sparkle:nth-child(6) {
            top: 50%;
            right: 10%;
            animation-delay: 1s;
        }

        @keyframes sparkle {
            0%, 100% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .badge-modal-body {
            padding: 4rem 2rem 2rem;
            text-align: center;
        }

        .badge-name {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .badge-description {
            font-size: 1rem;
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .badge-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 1rem;
        }

        .badge-stat {
            text-align: center;
        }

        .badge-stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #667eea;
            display: block;
            margin-bottom: 0.25rem;
        }

        .badge-stat-label {
            font-size: 0.8125rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-modal-actions {
            display: flex;
            gap: 1rem;
        }

        .badge-btn {
            flex: 1;
            padding: 1rem;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .badge-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .badge-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .badge-btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .badge-btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        @media (max-width: 600px) {
            .badge-modal {
                width: 95%;
            }

            .badge-modal-header {
                padding: 1.5rem;
            }

            .badge-congrats {
                font-size: 1.5rem;
            }

            .badge-icon-container {
                width: 120px;
                height: 120px;
                margin-top: -60px;
            }

            .badge-icon {
                font-size: 4rem;
            }

            .badge-modal-body {
                padding: 3rem 1.5rem 1.5rem;
            }

            .badge-stats {
                flex-direction: column;
                gap: 1rem;
            }

            .badge-modal-actions {
                flex-direction: column;
            }
        }
    </style>

    <div class="badge-modal-overlay" id="badgeModal">
        <div class="badge-modal">
            <div class="badge-modal-header">
                <button class="badge-modal-close" onclick="closeBadgeModal()">&times;</button>
                <h2 class="badge-congrats">🎉 Congratulations! 🎉</h2>
                <p class="badge-subtitle">You've earned a new badge!</p>
            </div>

            <div class="badge-modal-body">
                <div class="badge-icon-container">
                    <div class="badge-sparkles">
                        <div class="sparkle"></div>
                        <div class="sparkle"></div>
                        <div class="sparkle"></div>
                        <div class="sparkle"></div>
                        <div class="sparkle"></div>
                        <div class="sparkle"></div>
                    </div>
                    <div class="badge-icon"><?= htmlspecialchars($newBadge['icon'] ?? '🏆') ?></div>
                </div>

                <h3 class="badge-name"><?= htmlspecialchars($newBadge['name'] ?? 'Achievement Unlocked') ?></h3>
                <p class="badge-description"><?= htmlspecialchars($newBadge['description'] ?? '') ?></p>

                <?php if (isset($newBadge['points']) && $newBadge['points'] > 0): ?>
                    <div class="badge-stats">
                        <div class="badge-stat">
                            <span class="badge-stat-value">+<?= htmlspecialchars($newBadge['points']) ?></span>
                            <span class="badge-stat-label">Points Earned</span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="badge-modal-actions">
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/activity/badges"
                       class="badge-btn badge-btn-primary">
                        View All Badges
                    </a>
                    <button onclick="closeBadgeModal()" class="badge-btn badge-btn-secondary">
                        Continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function closeBadgeModal() {
            const modal = document.getElementById('badgeModal');
            modal.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
                modal.remove();

                // Mark as shown
                fetch('/<?= \App\Framework\Support\SiteContext::slug() ?>/member/badge-modal-shown', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'}
                });
            }, 300);
        }

        // Auto-close after 10 seconds
        setTimeout(() => {
            if (document.getElementById('badgeModal')) {
                closeBadgeModal();
            }
        }, 10000);
    </script>