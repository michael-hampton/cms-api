<?php

/** @var array|null $badgeModalData */

if (empty($badgeModalData) || empty($badgeModalData['member_badge_id'])) {
    return;
}

$memberBadgeId = (int)$badgeModalData['member_badge_id'];
?>

<style>
    .badge-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.8);
        animation: badgeModalFadeIn 0.3s ease;
    }

    .badge-modal-overlay.is-closing {
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .badge-modal {
        width: min(100%, 500px);
        overflow: hidden;
        border-radius: 1.5rem;
        background: #fff;
        box-shadow: 0 24px 70px rgba(0, 0, 0, 0.35);
        animation: badgeModalSlideUp 0.45s ease;
    }

    .badge-modal-header {
        position: relative;
        padding: 2rem;
        text-align: center;
        color: #fff;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .badge-modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        display: inline-flex;
        width: 36px;
        height: 36px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 50%;
        color: #fff;
        background: rgba(255, 255, 255, 0.2);
        cursor: pointer;
        font-size: 1.5rem;
    }

    .badge-modal-body {
        padding: 2rem;
        text-align: center;
    }

    .badge-icon {
        margin-bottom: 1rem;
        font-size: 5rem;
        line-height: 1;
    }

    .badge-name {
        margin: 0 0 0.75rem;
        color: #2c3e50;
        font-size: 1.75rem;
    }

    .badge-description {
        margin: 0 0 1.5rem;
        color: #666;
        line-height: 1.6;
    }

    .badge-points {
        display: inline-block;
        margin-bottom: 1.5rem;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        color: #667eea;
        background: #f3f4ff;
        font-weight: 700;
    }

    .badge-modal-actions {
        display: flex;
        gap: 1rem;
    }

    .badge-btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        padding: 0.75rem 1rem;
        border: 0;
        border-radius: 0.75rem;
        cursor: pointer;
        text-decoration: none;
        font-weight: 600;
    }

    .badge-btn-primary {
        color: #fff;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .badge-btn-secondary {
        color: #667eea;
        background: #fff;
        border: 2px solid #667eea;
    }

    @keyframes badgeModalFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes badgeModalSlideUp {
        from { opacity: 0; transform: translateY(40px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 600px) {
        .badge-modal-actions {
            flex-direction: column;
        }
    }
</style>

<div class="badge-modal-overlay" id="badgeModal" role="dialog" aria-modal="true" aria-labelledby="badgeModalTitle">
    <div class="badge-modal">
        <div class="badge-modal-header">
            <button type="button" class="badge-modal-close" data-badge-modal-close aria-label="Close">&times;</button>
            <h2>🎉 Congratulations! 🎉</h2>
            <p>You've earned a badge!</p>
        </div>

        <div class="badge-modal-body">
            <div class="badge-icon"><?= htmlspecialchars((string)($badgeModalData['icon'] ?? '🏆')) ?></div>
            <h3 class="badge-name" id="badgeModalTitle">
                <?= htmlspecialchars((string)($badgeModalData['name'] ?? 'Achievement Unlocked')) ?>
            </h3>
            <p class="badge-description">
                <?= htmlspecialchars((string)($badgeModalData['description'] ?? '')) ?>
            </p>

            <?php if (!empty($badgeModalData['points'])): ?>
                <div class="badge-points">+<?= (int)$badgeModalData['points'] ?> points</div>
            <?php endif; ?>

            <div class="badge-modal-actions">
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/activity/badges"
                   class="badge-btn badge-btn-primary">
                    View All Badges
                </a>
                <button type="button" class="badge-btn badge-btn-secondary" data-badge-modal-close>
                    Continue
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (() => {
        const modal = document.getElementById('badgeModal');
        const memberBadgeId = <?= $memberBadgeId ?>;
        let closing = false;

        const closeModal = async () => {
            if (!modal || closing) {
                return;
            }

            closing = true;
            modal.classList.add('is-closing');

            try {
                const response = await fetch('/<?= \App\Framework\Support\SiteContext::slug() ?>/member/badge-modal-shown', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({member_badge_id: memberBadgeId})
                });

                if (!response.ok) {
                    throw new Error(`Unable to mark badge modal as viewed (${response.status}).`);
                }

                window.setTimeout(() => modal.remove(), 300);
            } catch (error) {
                closing = false;
                modal.classList.remove('is-closing');
                console.error(error);
            }
        };

        modal.querySelectorAll('[data-badge-modal-close]').forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        window.setTimeout(closeModal, 10000);
    })();
</script>
