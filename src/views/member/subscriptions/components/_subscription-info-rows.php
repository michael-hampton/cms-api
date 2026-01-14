<div class="info-row">
    <span class="info-label">Status</span>
    <span class="badge badge-success">Active</span>
</div>

<div class="info-row">
    <span class="info-label">Price</span>
    <span class="info-value">
                        <?= htmlspecialchars($activeSubscription->currency) ?>
                        <?= number_format($activeSubscription->price, 2) ?>
                    </span>
</div>

<div class="info-row">
    <span class="info-label">Start Date</span>
    <span class="info-value">
                        <?= $activeSubscription->start_date->format('M d, Y') ?>
                    </span>
</div>

<?php if ($activeSubscription->next_billing_date): ?>
    <div class="info-row">
        <span class="info-label">Next Billing Date</span>
        <span class="info-value" style="font-weight: 800; color: #667eea;">
                <?= $activeSubscription->next_billing_date->format('M d, Y') ?>
            </span>
    </div>
<?php endif; ?>

<?php if ($activeSubscription->end_date): ?>
    <div class="info-row">
        <span class="info-label">End Date</span>
        <span class="info-value">
                            <?= $activeSubscription->end_date->format('M d, Y') ?>
                        </span>
    </div>
<?php endif; ?>

<div class="info-row">
    <span class="info-label">Auto Renew</span>
    <span class="info-value">
                        <?= $activeSubscription->auto_renew ? '✓ Yes' : '✗ No' ?>
                    </span>
</div>

<div class="info-row">
    <span class="info-label">Delivery Type</span>
    <span class="info-value">
        <?= $activeSubscription->isPrint() ? '📦 Print' : '💻 Digital' ?>
    </span>
</div>