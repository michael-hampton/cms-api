<?php

/** @var array|null $badgeModalData */

if (empty($badgeModalData) || empty($badgeModalData['member_badge_id'])) {
    return;
}

$memberBadgeId = (int) $badgeModalData['member_badge_id'];
$acknowledgeUrl = '/api/v1/'
    . rawurlencode(\App\Framework\Support\SiteContext::slug())
    . '/badge-modals/'
    . $memberBadgeId
    . '/viewed';
?>

<style>
    .badge-modal-overlay { position: fixed; inset: 0; z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(0, 0, 0, 0.8); }
    .badge-modal { width: min(100%, 500px); overflow: hidden; border-radius: 1.5rem; background: #fff; box-shadow: 0 24px 70px rgba(0, 0, 0, 0.35); }
    .badge-modal-header { position: relative; padding: 2rem; text-align: center; color: #fff; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .badge-modal-close { position: absolute; top: 1rem; right: 1rem; width: 36px; height: 36px; border: 0; border-radius: 50%; color: #fff; background: rgba(255, 255, 255, 0.2); cursor: pointer; font-size: 1.5rem; }
    .badge-modal-body { padding: 2rem; text-align: center; }
    .badge-icon { margin-bottom: 1rem; font-size: 5rem; }
    .badge-name { margin: 0 0 0.75rem; color: #2c3e50; font-size: 1.75rem; }
    .badge-description { margin: 0 0 1.5rem; color: #666; line-height: 1.6; }
    .badge-points { display: inline-block; margin-bottom: 1.5rem; padding: 0.75rem 1rem; border-radius: 0.75rem; color: #667eea; background: #f3f4ff; font-weight: 700; }
    .badge-modal-actions { display: flex; gap: 1rem; }
    .badge-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0.75rem 1rem; border-radius: 0.75rem; cursor: pointer; text-decoration: none; font-weight: 600; }
    .badge-btn-primary { border: 0; color: #fff; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .badge-btn-secondary { color: #667eea; background: #fff; border: 2px solid #667eea; }
    @media (max-width: 600px) { .badge-modal-actions { flex-direction: column; } }
</style>

<div class="badge-modal-overlay" id="badgeModal" role="dialog" aria-modal="true" aria-labelledby="badgeModalTitle">
    <div class="badge-modal">
        <div class="badge-modal-header">
            <button type="button" class="badge-modal-close" data-badge-modal-close aria-label="Close">&times;</button>
            <h2>Congratulations!</h2>
            <p>You've earned a badge!</p>
        </div>
        <div class="badge-modal-body">
            <div class="badge-icon"><?= htmlspecialchars((string) ($badgeModalData['icon'] ?? '🏆')) ?></div>
            <h3 class="badge-name" id="badgeModalTitle"><?= htmlspecialchars((string) ($badgeModalData['name'] ?? 'Achievement Unlocked')) ?></h3>
            <p class="badge-description"><?= htmlspecialchars((string) ($badgeModalData['description'] ?? '')) ?></p>
            <?php if (!empty($badgeModalData['points'])): ?>
                <div class="badge-points">+<?= (int) $badgeModalData['points'] ?> points</div>
            <?php endif; ?>
            <div class="badge-modal-actions">
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/activity/badges" class="badge-btn badge-btn-primary" data-badge-modal-view-all>View All Badges</a>
                <button type="button" class="badge-btn badge-btn-secondary" data-badge-modal-close>Continue</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const modal = document.getElementById('badgeModal');
    const acknowledgeUrl = <?= json_encode($acknowledgeUrl, JSON_THROW_ON_ERROR) ?>;
    const csrfToken = <?= json_encode(csrf_token(), JSON_THROW_ON_ERROR) ?>;
    let acknowledging = false;

    const acknowledge = async () => {
        if (acknowledging) return false;
        acknowledging = true;
        try {
            const response = await fetch(acknowledgeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            if (!response.ok) throw new Error(`Unable to mark badge modal as viewed (${response.status}).`);
            return true;
        } catch (error) {
            acknowledging = false;
            console.error(error);
            return false;
        }
    };

    const closeModal = async () => {
        if (await acknowledge()) modal.remove();
    };

    modal.querySelectorAll('[data-badge-modal-close]').forEach(button => button.addEventListener('click', closeModal));
    const viewAll = modal.querySelector('[data-badge-modal-view-all]');
    viewAll.addEventListener('click', async event => {
        event.preventDefault();
        const destination = viewAll.href;
        if (await acknowledge()) window.location.assign(destination);
    });
})();
</script>
