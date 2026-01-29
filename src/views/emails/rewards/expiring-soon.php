# ⏰ Your Reward is Expiring Soon!

Hello **<?= $member->first_name ?>**,

Don't let this reward go to waste! You have a reward that's about to expire.

@promotion(⚠️ <?= $daysUntilExpiry ?> day<?= $daysUntilExpiry > 1 ? 's' : '' ?> remaining!)

@divider

## Expiring Reward Details

<?php if (isset($rewardData['voucher_code'])): ?>
    @panel(🎫 **Gift Voucher - Act Now!**
    **Code:** `<?= $rewardData['voucher_code'] ?>`
    **Value:** <?= $rewardData['currency'] ?? 'GBP' ?> <?= number_format($rewardData['value'], 2) ?>
    **Provider:** <?= $rewardData['provider'] ?? 'N/A' ?>
    **Expires:** <?= $expiresAt->format('F j, Y \a\t g:i A') ?>)

<?php elseif (isset($rewardData['discount_type'])): ?>
    @panel(💰 **Discount Code - Last Chance!**
    **Type:** <?= ucfirst($rewardData['discount_type']) ?>
    **Value:** <?= $rewardData['discount_value'] ?><?= $rewardData['discount_type'] === 'percentage' ? '%' : ' ' . ($rewardData['currency'] ?? 'GBP') ?>
    **Expires:** <?= $expiresAt->format('F j, Y \a\t g:i A') ?>)

<?php elseif (isset($rewardData['points'])): ?>
    @panel(⭐ **Points Reward - Use Soon!**
    **Points:** <?= number_format($rewardData['points']) ?>
    **Expires:** <?= $expiresAt->format('F j, Y \a\t g:i A') ?>)

<?php endif; ?>

@button(Claim Now Before It Expires, <?= config('app.url') ?>/rewards/<?= $reward->id ?>/claim)

@divider

## Why You Should Act Now

<?php if ($daysUntilExpiry <= 1): ?>
    @promotion(🚨 URGENT: Less than 24 hours remaining!)

    Your reward expires **today**! Don't miss out on this opportunity.
<?php elseif ($daysUntilExpiry <= 3): ?>
    @promotion(⚡ Only <?= $daysUntilExpiry ?> days left!)

    Time is running out fast. Make sure to claim and use your reward before it's gone.
<?php else: ?>
    @promotion(📅 <?= $daysUntilExpiry ?> days remaining)

    You still have some time, but don't wait too long!
<?php endif; ?>

@divider

## Quick Redemption Guide

@table(Step|What to Do)
@row(1️⃣|Click the "Claim Now" button above)
@row(2️⃣|Review your reward details)
@row(3️⃣|Use it before <?= $expiresAt->format('M j') ?>)
@endtable

@divider

## Popular Ways to Use Your Reward

<?php if (isset($rewardData['voucher_code'])): ?>
    - Order from your favorite retailer
    - Stock up on essentials
    - Treat yourself to something special
    - Share with family or friends
<?php elseif (isset($rewardData['discount_type'])): ?>
    - Save on your next purchase
    - Buy that item you've been eyeing
    - Stock up during this discount
    - Combine with other offers (if allowed)
<?php endif; ?>

@buttonSecondary(Browse Products, <?= config('app.url') ?>/shop)

@divider

## Need Help?

Having trouble redeeming your reward? We're here to assist!

@panel(💬 **Quick Support**
Email us or use live chat
Response within 2 hours during business hours)

@buttonSecondary(Get Help, <?= config('app.url') ?>/support)

@subcopy(This is a friendly reminder about your expiring reward. Once expired\, this reward cannot be recovered or extended.)