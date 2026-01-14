<?php


if ($activeSubscription && !$activeSubscription->auto_renew && $activeSubscription->end_date) {
    $daysUntilEnd = (new \DateTime())->diff($activeSubscription->end_date)->days;
    $showRenewalPrompt = $daysUntilEnd <= 30 && $daysUntilEnd > 0;

    if ($showRenewalPrompt):
        ?>
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    text-align: center;">
            <div style="font-size: 24px; margin-bottom: 8px;">⏰</div>
            <div style="font-weight: 700; font-size: 18px; margin-bottom: 8px;">
                Your subscription expires in <?= $daysUntilEnd ?> day<?= $daysUntilEnd > 1 ? 's' : '' ?>
            </div>
            <div style="font-size: 14px; margin-bottom: 16px; opacity: 0.9;">
                Renew now to continue enjoying uninterrupted access
            </div>
            <button onclick="openRenewalModal()" class="btn btn-primary"
                    style="background: white; color: #667eea;">
                Renew Subscription
            </button>
        </div>
    <?php
    endif;
}
?>