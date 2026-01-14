<div class="card">
    <h2>
        <span class="icon">✉️</span>
        Email Preferences
    </h2>

    <div class="subscription-status">
        <div class="status-icon <?= $subscriptionSummary['is_active'] ? 'active' : 'inactive' ?>">
            <?= $subscriptionSummary['is_active'] ? '✓' : '✗' ?>
        </div>
        <div>
            <div style="font-weight: 700; font-size: 20px; color: #1e293b;">
                <?= $subscriptionSummary['is_active'] ? 'Subscribed' : 'Unsubscribed' ?>
            </div>
            <div style="color: #64748b; font-size: 15px; font-weight: 500;">Email notifications</div>
        </div>
    </div>

    <div class="info-row">
        <span class="info-label">Email Notifications</span>
        <span class="badge <?= $subscriptionSummary['email_notifications'] ? 'badge-success' : 'badge-danger' ?>">
                <?= $subscriptionSummary['email_notifications'] ? 'Enabled' : 'Disabled' ?>
            </span>
    </div>

    <div class="info-row">
        <span class="info-label">Frequency</span>
        <span class="info-value">
                <?= ucfirst(htmlspecialchars($subscriptionSummary['frequency'])) ?>
            </span>
    </div>

    <div class="info-row">
        <span class="info-label">Content Types</span>
        <span class="info-value">
                <?= empty($subscriptionSummary['content_types']) ? 'All' : count($subscriptionSummary['content_types']) ?>
            </span>
    </div>

    <div class="info-row">
        <span class="info-label">Categories</span>
        <span class="info-value">
                <?= empty($subscriptionSummary['category_preferences']) ? 'All' : count($subscriptionSummary['category_preferences']) ?>
            </span>
    </div>

    <div class="btn-group">
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/preferences"
           class="btn btn-primary">
            Manage Preferences
        </a>
    </div>
</div>