<div class="info-row">
    <span class="info-label">Digital Access</span>
    <span class="info-value">
            <a href="<?= htmlspecialchars($activeSubscription->download_url) ?>"
               style="color: #667eea; text-decoration: none; font-weight: 600;">
                Download Now →
            </a>
        </span>
</div>
<div class="info-row">
    <span class="info-label">Download Expires</span>
    <span class="info-value" style="font-size: 14px;">
            <?= $activeSubscription->download_expires_at->format('M d, Y') ?>
        </span>
</div>