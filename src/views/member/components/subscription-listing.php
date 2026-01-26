<?php
/**
 * Subscription Listing Component
 * @var array $groupedSubscriptions - Subscriptions grouped by status and type
 * @var string $siteSlug - Site slug for URLs
 */

$hasActiveSubscriptions = !empty($groupedSubscriptions['active']['print']) || !empty($groupedSubscriptions['active']['digital']);
$hasExpiredSubscriptions = !empty($groupedSubscriptions['expired']['print']) || !empty($groupedSubscriptions['expired']['digital']);
?>

<style>
    .subscriptions-section {
        background: white;
        border-radius: 1rem;
        padding: 2rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .subscriptions-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .subscriptions-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
    }

    .subscription-tabs {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .tab-button {
        padding: 0.75rem 1.5rem;
        background: none;
        border: none;
        font-weight: 600;
        cursor: pointer;
        color: #6b7280;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        transition: all 0.3s ease;
    }

    .tab-button:hover {
        color: #667eea;
    }

    .tab-button.active {
        color: #667eea;
        border-bottom-color: #667eea;
    }

    .subscription-grid {
        display: grid;
        gap: 1.5rem;
    }

    .subscription-card {
        background: #f9fafb;
        border: 2px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.5rem;
        transition: all 0.3s ease;
    }

    .subscription-card:hover {
        border-color: #667eea;
        box-shadow: 0 4px 6px rgba(102, 126, 234, 0.1);
    }

    .subscription-card.expired {
        opacity: 0.7;
    }

    .subscription-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .subscription-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .subscription-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .icon-print {
        background: linear-gradient(135deg, #f59e0b20 0%, #d9770620 100%);
    }

    .icon-digital {
        background: linear-gradient(135deg, #3b82f620 0%, #2563eb20 100%);
    }

    .subscription-name h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }

    .subscription-type {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .subscription-status {
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .status-active {
        background: #d1fae5;
        color: #065f46;
    }

    .status-expired {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-cancelled {
        background: #fef3c7;
        color: #92400e;
    }

    .subscription-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 1rem;
        background: white;
        border-radius: 0.5rem;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .detail-label {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
    }

    .detail-value {
        font-size: 0.875rem;
        color: #1f2937;
        font-weight: 500;
    }

    .subscription-newsletters {
        margin-bottom: 1rem;
    }

    .newsletters-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }

    .newsletter-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .newsletter-tag {
        padding: 0.375rem 0.75rem;
        background: #e0e7ff;
        color: #3730a3;
        border-radius: 0.375rem;
        font-size: 0.8125rem;
        font-weight: 500;
    }

    .subscription-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .btn {
        padding: 0.625rem 1.25rem;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }

    .btn-secondary:hover {
        background: #667eea;
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #6b7280;
    }

    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
        color: #4b5563;
    }

    .auto-renew-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.625rem;
        background: #d1fae5;
        color: #065f46;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        margin-top: 0.5rem;
    }

    @media (max-width: 768px) {
        .subscriptions-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .subscription-tabs {
            width: 100%;
            overflow-x: auto;
        }

        .subscription-header {
            flex-direction: column;
            gap: 1rem;
        }

        .subscription-details {
            grid-template-columns: 1fr;
        }

        .subscription-actions {
            flex-direction: column;
        }

        .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="subscriptions-section">
    <div class="subscriptions-header">
        <h2>📰 My Subscriptions</h2>
        <?php if ($hasActiveSubscriptions || $hasExpiredSubscriptions): ?>
            <a href="/<?= htmlspecialchars($siteSlug) ?>/member/subscriptions" class="btn btn-secondary">
                View All Subscriptions →
            </a>
        <?php endif; ?>
    </div>

    <?php if (!$hasActiveSubscriptions && !$hasExpiredSubscriptions): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <h3>No Subscriptions Yet</h3>
            <p>Start a subscription to get access to premium content and exclusive newsletters.</p>
        </div>
    <?php else: ?>
        <div class="subscription-tabs">
            <button class="tab-button active" onclick="switchSubscriptionTab('active')" id="activeTab">
                Active Subscriptions
            </button>
            <?php if ($hasExpiredSubscriptions): ?>
                <button class="tab-button" onclick="switchSubscriptionTab('expired')" id="expiredTab">
                    Expired Subscriptions
                </button>
            <?php endif; ?>
        </div>

        <!-- Active Subscriptions -->
        <div id="activeSubscriptions" class="subscription-grid">
            <?php
            $allActive = array_merge(
                    $groupedSubscriptions['active']['print'] ?? [],
                    $groupedSubscriptions['active']['digital'] ?? []
            );

            if (empty($allActive)):
                ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💤</div>
                    <h3>No Active Subscriptions</h3>
                    <p>Your subscriptions have expired or been cancelled.</p>
                </div>
            <?php else:
                foreach ($allActive as $subscription):
                    ?>
                    <div class="subscription-card">
                        <div class="subscription-header">
                            <div class="subscription-title">
                                <div class="subscription-icon <?= $subscription['type'] === 'print' ? 'icon-print' : 'icon-digital' ?>">
                                    <?= $subscription['type'] === 'print' ? '📦' : '💻' ?>
                                </div>
                                <div class="subscription-name">
                                    <h3><?= htmlspecialchars($subscription['plan_name']) ?></h3>
                                    <div class="subscription-type"><?= ucfirst($subscription['type']) ?>Subscription
                                    </div>
                                </div>
                            </div>
                            <span class="subscription-status status-<?= htmlspecialchars($subscription['status']) ?>">
                                <?= ucfirst($subscription['status']) ?>
                            </span>
                        </div>

                        <div class="subscription-details">
                            <div class="detail-item">
                                <span class="detail-label">Start Date</span>
                                <span class="detail-value"><?= $subscription['start_date']->format('M d, Y') ?></span>
                            </div>
                            <?php if ($subscription['end_date']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">End Date</span>
                                    <span class="detail-value"><?= $subscription['end_date']->format('M d, Y') ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($subscription['next_billing_date']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Next Billing</span>
                                    <span class="detail-value"><?= $subscription['next_billing_date']->format('M d, Y') ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($subscription['auto_renew']): ?>
                            <div class="auto-renew-badge">
                                <span>🔄</span>
                                Auto-Renew Enabled
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($subscription['newsletters'])): ?>
                            <div class="subscription-newsletters">
                                <div class="newsletters-label">Included Newsletters:</div>
                                <div class="newsletter-tags">
                                    <?php foreach ($subscription['newsletters'] as $newsletter): ?>
                                        <span class="newsletter-tag"><?= htmlspecialchars($newsletter['title']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="subscription-actions">
                            <?php if ($subscription['archive_url']): ?>
                                <a href="<?= htmlspecialchars($subscription['archive_url']) ?>"
                                   class="btn btn-secondary">
                                    📚 View Archive
                                </a>
                            <?php endif; ?>
                            <?php if ($subscription['should_show_renew']): ?>
                                <a href="/<?= htmlspecialchars($siteSlug) ?>/member/subscriptions/<?= $subscription['id'] ?>/renew"
                                   class="btn btn-primary">
                                    🔄 Renew Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php
                endforeach;
            endif;
            ?>
        </div>

        <!-- Expired Subscriptions -->
        <?php if ($hasExpiredSubscriptions): ?>
            <div id="expiredSubscriptions" class="subscription-grid" style="display: none;">
                <?php
                $allExpired = array_merge(
                        $groupedSubscriptions['expired']['print'] ?? [],
                        $groupedSubscriptions['expired']['digital'] ?? []
                );

                foreach ($allExpired as $subscription):
                    ?>
                    <div class="subscription-card expired">
                        <div class="subscription-header">
                            <div class="subscription-title">
                                <div class="subscription-icon <?= $subscription['type'] === 'print' ? 'icon-print' : 'icon-digital' ?>">
                                    <?= $subscription['type'] === 'print' ? '📦' : '💻' ?>
                                </div>
                                <div class="subscription-name">
                                    <h3><?= htmlspecialchars($subscription['plan_name']) ?></h3>
                                    <div class="subscription-type"><?= ucfirst($subscription['type']) ?>Subscription
                                    </div>
                                </div>
                            </div>
                            <span class="subscription-status status-<?= htmlspecialchars($subscription['status']) ?>">
                                <?= ucfirst($subscription['status']) ?>
                            </span>
                        </div>

                        <div class="subscription-details">
                            <div class="detail-item">
                                <span class="detail-label">Start Date</span>
                                <span class="detail-value"><?= $subscription['start_date']->format('M d, Y') ?></span>
                            </div>
                            <?php if ($subscription['end_date']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Ended</span>
                                    <span class="detail-value"><?= $subscription['end_date']->format('M d, Y') ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($subscription['can_renew']): ?>
                            <div class="subscription-actions">
                                <a href="/<?= htmlspecialchars($siteSlug) ?>/member/subscriptions/<?= $subscription['id'] ?>/renew"
                                   class="btn btn-primary">
                                    🔄 Renew Subscription
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    function switchSubscriptionTab(tab) {
        // Hide all tabs
        document.getElementById('activeSubscriptions').style.display = 'none';
        const expiredSubs = document.getElementById('expiredSubscriptions');
        if (expiredSubs) {
            expiredSubs.style.display = 'none';
        }

        // Remove active class from all buttons
        document.getElementById('activeTab').classList.remove('active');
        const expiredTab = document.getElementById('expiredTab');
        if (expiredTab) {
            expiredTab.classList.remove('active');
        }

        // Show selected tab
        if (tab === 'active') {
            document.getElementById('activeSubscriptions').style.display = 'grid';
            document.getElementById('activeTab').classList.add('active');
        } else if (tab === 'expired' && expiredSubs) {
            expiredSubs.style.display = 'grid';
            if (expiredTab) {
                expiredTab.classList.add('active');
            }
        }
    }
</script>