# 🔥 Hot Deals Just For You!

Hello **<?= $member->first_name ?>**,

We've found <?= $dealCount ?> amazing deal<?= $dealCount > 1 ? 's' : '' ?> based on your interests!

@promotion(⚡ Limited Time Offers - Act Fast!)

@divider

<?php foreach ($deals as $index => $deal): ?>
    ## <?= $index + 1 ?>. <?= htmlspecialchars($deal['product_name']) ?>

    <?php if (!empty($deal['description'])): ?>
        <?= htmlspecialchars(substr($deal['description'], 0, 150)) ?>...
    <?php endif; ?>

    **Original Price:** ~~$<?= number_format($deal['original_price'], 2) ?>~~
    **Deal Price:** @price(<?= number_format($deal['deal_price'], 2) ?>)
    **You Save:** $<?= number_format($deal['original_price'] - $deal['deal_price'], 2) ?> (<?= $deal['discount_percentage'] ?>% OFF)

    <?php if (!empty($deal['expires_at'])): ?>
        @panel(⏰ Hurry! This deal expires on <?= date('F j, Y \a\t g:i A', strtotime($deal['expires_at'])) ?>)
    <?php endif; ?>

    @button(Grab This Deal, <?= config('app.url') ?>/deals/<?= $deal['id'] ?>)

    @divider

<?php endforeach; ?>

## Why These Deals?

These offers were selected based on:
- Your browsing history
- Products in your wishlist
- Your purchase preferences
- Popular items in your favorite categories

@buttonSecondary(View All Deals, <?= config('app.url') ?>/deals)

@subcopy(Receiving too many deal alerts? Manage your email preferences in your account settings.)