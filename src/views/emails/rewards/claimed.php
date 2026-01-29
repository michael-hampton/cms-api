# ✅ Your Reward Has Been Claimed!

Hello **<?= $member->first_name ?>**,

Success! Your reward has been claimed and is ready to use.

@promotion(🎉 Reward Successfully Claimed!)

@divider

## Your Reward Information

<?php if (isset($rewardData['voucher_code'])): ?>
    @panel(🎫 **Gift Voucher - Ready to Use**
    **Code:** `<?= $rewardData['voucher_code'] ?>`
    **Value:** <?= $rewardData['currency'] ?? 'GBP' ?> <?= number_format($rewardData['value'], 2) ?>
    **Provider:** <?= $rewardData['provider'] ?? 'N/A' ?>
    **Status:** Active & Ready)

    ## How to Redeem Your Voucher

    @table(Step|Instructions)
    @row(1|Visit <?= $rewardData['provider'] ?? 'the retailer' ?>)
    @row(2|Add items to your cart)
    @row(3|Enter code `<?= $rewardData['voucher_code'] ?>` at checkout)
    @row(4|Complete your purchase)
    @endtable

    @button(Start Shopping, <?= getProviderUrl($rewardData['provider'] ?? null) ?>)

<?php elseif (isset($rewardData['discount_type'])): ?>
    @panel(💰 **Discount Code - Active Now**
    **Type:** <?= ucfirst($rewardData['discount_type']) ?>
    **Value:** <?= $rewardData['discount_value'] ?><?= $rewardData['discount_type'] === 'percentage' ? '%' : ' ' . ($rewardData['currency'] ?? 'GBP') ?>
    **Status:** Ready to Use)

    ## How to Apply Your Discount

    @table(Step|Instructions)
    @row(1|Browse our product catalog)
    @row(2|Add items to cart)
    @row(3|Your discount will be applied automatically)
    @row(4|Or enter the code at checkout if required)
    @endtable

    @button(Shop Now & Save, <?= config('app.url') ?>/shop)

<?php elseif (isset($rewardData['points'])): ?>
    @panel(⭐ **Points Added to Your Account**
    **Points Credited:** <?= number_format($rewardData['points']) ?>
    **Status:** Added to your balance)

    ## Using Your Points

    Your points have been added to your account balance. Use them to:

    - Redeem for vouchers
    - Get discounts on future purchases
    - Unlock exclusive member benefits
    - Access premium features

    @button(View Points Balance, <?= config('app.url') ?>/rewards)

<?php endif; ?>

@divider

## Important Details

**Claimed On:** <?= $claimedAt->format('F j, Y \a\t g:i A') ?>
**Reward ID:** #<?= $reward->id ?>

<?php if (isset($reward->expires_at) && $reward->expires_at): ?>
    @promotion(⏰ Valid until: <?= $reward->expires_at->format('F j, Y') ?>)
<?php endif; ?>

@divider

## Tips for Maximum Value

<?php if (isset($rewardData['voucher_code'])): ?>
    ✓ **Check the balance** before making a large purchase
    ✓ **Combine with sales** if the provider allows it
    ✓ **Save the code** in your password manager or notes
    ✓ **Use it wisely** - treat yourself to something special!

<?php elseif (isset($rewardData['discount_type'])): ?>
    ✓ **Stack with sales** when possible for maximum savings
    ✓ **Plan ahead** - add items to your wishlist first
    ✓ **Share with friends** (if transferable)
    ✓ **Use before expiry** - set a reminder!

<?php elseif (isset($rewardData['points'])): ?>
    ✓ **Save up** for bigger rewards
    ✓ **Check redemption options** regularly
    ✓ **Track your balance** in your account dashboard
    ✓ **Earn more** through continued engagement

<?php endif; ?>

@divider

## Keep Earning More

@panel(🌟 **Want More Rewards?**
Keep engaging with us to unlock additional rewards:
✓ Make purchases • ✓ Refer friends • ✓ Write reviews • ✓ Stay active)

@buttonSecondary(View All Rewards, <?= config('app.url') ?>/rewards)

@divider

## Questions or Issues?

Having trouble using your reward? Contact our support team.

@panel(💬 **We're Here to Help**
Email: <?= config('mail.support_email', 'support@example.com') ?>
Live Chat: Available 9 AM - 9 PM daily)

@buttonSecondary(Contact Support, <?= config('app.url') ?>/support)

@subcopy(This reward has been claimed and is active in your account. Keep this email for your records.)

<?php
// Helper method for provider URLs
function getProviderUrl(?string $provider): string
{
    $urls = [
            'Amazon' => 'https://www.amazon.com',
            'Argos' => 'https://www.argos.co.uk',
            'John Lewis' => 'https://www.johnlewis.com',
            'M&S' => 'https://www.marksandspencer.com',
    ];

    return $urls[$provider] ?? config('app.url') . '/shop';
}

?>