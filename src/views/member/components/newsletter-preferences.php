<!-- Newsletter Preferences Component -->
<?php
// Get member's newsletter subscriptions
$memberEmail = $member->email;
$siteId = \App\Framework\Support\SiteContext::getId();

$subscriberRepo = new \App\Repositories\Subscriptions\SubscriberRepository();
$subscriptionRepo = new \App\Repositories\Subscriptions\SubscriptionRepository();

$newsletters = $subscriberRepo->getAllNewslettersForMember($memberEmail, $siteId);
$activeSubscription = $subscriptionRepo->getActiveSubscriptionForMember($member->id, $siteId);
?>

<style>
    .newsletter-prefs-section {
        background: white;
        border-radius: 12px;
        padding: 32px;
        margin-bottom: 32px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e2e8f0;
    }

    .section-title {
        font-size: 22px;
        font-weight: 700;
        color: #1a202c;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .newsletter-count {
        background: #e2e8f0;
        color: #4a5568;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
    }

    .newsletter-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .newsletter-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .newsletter-item:hover {
        background: #f1f3f5;
    }

    .newsletter-info {
        flex: 1;
    }

    .newsletter-name {
        font-size: 16px;
        font-weight: 600;
        color: #1a202c;
        margin-bottom: 4px;
    }

    .newsletter-meta {
        font-size: 14px;
        color: #718096;
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .newsletter-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .newsletter-status.active {
        background: #d1fae5;
        color: #065f46;
    }

    .newsletter-status.inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    .newsletter-status.locked {
        background: #fef3c7;
        color: #92400e;
    }

    .newsletter-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .btn-toggle {
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
    }

    .btn-toggle.subscribe {
        background: #667eea;
        color: white;
    }

    .btn-toggle.subscribe:hover:not(:disabled) {
        background: #5568d3;
        transform: translateY(-2px);
    }

    .btn-toggle.unsubscribe {
        background: #e2e8f0;
        color: #4a5568;
    }

    .btn-toggle.unsubscribe:hover:not(:disabled) {
        background: #cbd5e0;
    }

    .btn-toggle:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .locked-message {
        font-size: 13px;
        color: #92400e;
        font-style: italic;
        max-width: 300px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #718096;
    }

    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 16px;
    }

    .success-message {
        background: #d1fae5;
        border-left: 4px solid #10b981;
        color: #065f46;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 24px;
        display: none;
        animation: slideIn 0.3s ease;
    }

    .error-message {
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 24px;
        display: none;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 768px) {
        .newsletter-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .newsletter-actions {
            width: 100%;
            flex-direction: column;
        }

        .btn-toggle {
            width: 100%;
        }
    }
</style>

<div class="newsletter-prefs-section">
    <div class="section-header">
        <h2 class="section-title">
            📧 Newsletter Preferences
            <?php if ($newsletters->count() > 0): ?>
                <span class="newsletter-count"><?= $newsletters->count() ?></span>
            <?php endif; ?>
        </h2>
    </div>

    <div id="messageContainer"></div>

    <?php if ($newsletters->count() > 0): ?>
        <div class="newsletter-list">
            <?php foreach ($newsletters as $subscription): ?>
                <?php
                $isActive = $subscription->isActive();
                $newsletter = \App\Models\Newsletter::find($subscription->newsletter_id);
                $canToggle = true;
                $lockReason = '';

                // Check if newsletter requires active subscription
                if ($newsletter && $newsletter->isPremium()) {
                    if (!$activeSubscription) {
                        $canToggle = false;
                        $lockReason = 'Requires active subscription';
                    } elseif (!$activeSubscription->hasPremiumAccess('newsletter', $newsletter->slug)) {
                        $canToggle = false;
                        $lockReason = 'Not included in your plan';
                    }
                }
                ?>

                <div class="newsletter-item">
                    <div class="newsletter-info">
                        <div class="newsletter-name">
                            <?= htmlspecialchars($newsletter?->title ?? 'Newsletter') ?>
                        </div>
                        <div class="newsletter-meta">
                            <span class="newsletter-status <?= $isActive ? 'active' : ($canToggle ? 'inactive' : 'locked') ?>">
                                <?php if ($isActive): ?>
                                    ✓ Subscribed
                                <?php elseif ($canToggle): ?>
                                    ✗ Unsubscribed
                                <?php else: ?>
                                    🔒 Locked
                                <?php endif; ?>
                            </span>
                            <?php if ($newsletter): ?>
                                <span><?= ucfirst($newsletter->interval ?? 'periodic') ?> newsletter</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="newsletter-actions">
                        <?php if (!$canToggle && !$isActive): ?>
                            <div class="locked-message">
                                <?= htmlspecialchars($lockReason) ?>.
                                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions"
                                   style="color: #667eea; font-weight: 600;">
                                    Upgrade plan
                                </a>
                            </div>
                        <?php else: ?>
                            <button
                                    class="btn-toggle <?= $isActive ? 'unsubscribe' : 'subscribe' ?>"
                                    onclick="toggleNewsletter(<?= $subscription->id ?>, <?= $newsletter->id ?>, <?= $isActive ? 'false' : 'true' ?>, this)"
                                    <?= !$canToggle ? 'disabled' : '' ?>>
                                <?= $isActive ? 'Unsubscribe' : 'Subscribe' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <h3>No Newsletter Subscriptions</h3>
            <p>You haven't subscribed to any newsletters yet.</p>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters"
               style="color: #667eea; font-weight: 600; margin-top: 16px; display: inline-block;">
                Browse Available Newsletters →
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
    async function toggleNewsletter(subscriptionId, newsletterId, subscribe, button) {
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = subscribe ? 'Subscribing...' : 'Unsubscribing...';

        const messageContainer = document.getElementById('messageContainer');

        try {
            const response = await fetch('/<?= \App\Framework\Support\SiteContext::slug() ?>/member/newsletters/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    subscription_id: subscriptionId,
                    subscribe: subscribe,
                    newsletter_id: newsletterId
                })
            });

            const result = await response.json();

            if (result.success) {
                // Show success message
                messageContainer.innerHTML = `
                    <div class="success-message" style="display: block;">
                        ✓ ${result.message}
                    </div>
                `;

                // Update button
                button.textContent = subscribe ? 'Unsubscribe' : 'Subscribe';
                button.classList.toggle('subscribe');
                button.classList.toggle('unsubscribe');

                // Update status badge
                const item = button.closest('.newsletter-item');
                const statusBadge = item.querySelector('.newsletter-status');
                if (subscribe) {
                    statusBadge.textContent = '✓ Subscribed';
                    statusBadge.classList.add('active');
                    statusBadge.classList.remove('inactive');
                } else {
                    statusBadge.textContent = '✗ Unsubscribed';
                    statusBadge.classList.add('inactive');
                    statusBadge.classList.remove('active');
                }

                // Hide message after 3 seconds
                setTimeout(() => {
                    messageContainer.innerHTML = '';
                }, 3000);

            } else {
                throw new Error(result.message || 'Failed to update subscription');
            }

        } catch (error) {
            messageContainer.innerHTML = `
                <div class="error-message" style="display: block;">
                    ⚠ ${error.message}
                </div>
            `;

            button.textContent = originalText;
            button.disabled = false;

            setTimeout(() => {
                messageContainer.innerHTML = '';
            }, 5000);
        }

        button.disabled = false;
    }
</script>