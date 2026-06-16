<?php if (!empty($claimedGift)): ?>
    <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
        <strong>🎁 Gift Claimed!</strong>
        This article was gifted to you by <?= htmlspecialchars($claimedGift->giftedBy->full_name ?? 'a friend') ?>.
        The gift has been automatically claimed and added to your account.
    </div>
<?php endif; ?>
