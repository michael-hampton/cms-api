# 🎉 Congratulations! You've Earned a Reward!

Hello **<?= $member->first_name ?>**,

Great news! You've earned a new reward for being an awesome member.

@promotion(🎁 <?= $rewardType ?> Unlocked!)

@divider

## Your Reward Details

<?php if (isset($rewardData['voucher_code'])): ?>
    @panel(🎫 **Gift Voucher**
    **Code:** `<?= $rewardData['voucher_code'] ?>`
    **Value:** <?= $rewardData['currency'] ?? 'GBP' ?> <?= number_format($rewardData['value'], 2) ?>
    **Provider:** <?= $rewardData['provider'] ?? 'N/A' ?>)

    @button(Claim Your Voucher, <?= config('app.url') ?>/rewards/<?= $reward->id ?>/claim)

<?php elseif (isset($rewardData['discount_type'])): ?>
    @panel(💰 **Discount Code**
    **Type:** <?= ucfirst($rewardData['discount_type']) ?>
    **Value:** <?= $rewardData['discount_value'] ?><?= $rewardData['discount_type'] === 'percentage' ? '%' : ' ' . ($rewardData['currency'] ?? 'GBP') ?>
    **How to Use:** Apply at checkout)

    @button(Start Shopping, <?= config('app.url') ?>/shop)

<?php elseif (isset($rewardData['points'])): ?>
    @panel(⭐ **Points Reward**
    **Points Added:** <?= number_format($rewardData['points']) ?> points
    **Your Balance:** Check your account for updated total)

    @button(View Points Balance, <?= config('app.url') ?>/rewards)

<?php endif; ?>

@divider

## How to Redeem

@table(Step|Action)
@row(1|Click the button above to view your reward)
@row(2|Follow the redemption instructions)
@row(3|Enjoy your reward!)
@endtable

<?php if ($expiresAt): ?>
    @promotion(⏰ Expires: <?= $expiresAt->format('F j, Y \a\t g:i A') ?>)

    Make sure to claim and use your reward before it expires!
<?php endif; ?>

@divider

## Keep Earning More Rewards

Want to unlock even more rewards? Keep engaging with us:

✓ Make purchases
✓ Refer friends
✓ Leave product reviews
✓ Complete your profile
✓ Join our loyalty program

@buttonSecondary(View All Rewards, <?= config('app.url') ?>/rewards)

@divider

## Questions?

If you have any questions about this reward or how to redeem it, we're here to help.

@panel(📧 **Support Team**
Reply to this email or visit our help center)

@buttonSecondary(Contact Support, <?= config('app.url') ?>/support)

@subcopy(You earned this reward based on your activity and loyalty. Check your rewards dashboard for more opportunities to earn!)