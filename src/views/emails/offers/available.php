# 🔥 Special Offer Just for You!

Hello **<?= $member->first_name ?>**,

We have an exclusive offer that we think you'll love!

@promotion(💎 <?= htmlspecialchars($offer->title) ?>)

@divider

## Featured Product

**<?= htmlspecialchars($product->name) ?>**

<?php if (!empty($product->description)): ?>
    <?= htmlspecialchars(substr($product->description, 0, 200)) ?>...
<?php endif; ?>

@divider

## Offer Details

@table(Detail|Information)
@row(Discount Type|<?= ucfirst($offer->discount_type) ?>)
@row(Discount Value|<?= $offer->discount_value ?><?= $offer->discount_type === 'percentage' ? '%' : ' GBP' ?>)
@row(Offer Starts|<?= $startDate->format('F j, Y \a\t g:i A') ?>)
@row(Offer Ends|<?= $endDate->format('F j, Y \a\t g:i A') ?>)
@endtable

<?php if ($offer->discount_type === 'percentage'): ?>
    @panel(💰 Save <?= $offer->discount_value ?>% on <?= htmlspecialchars($product->name) ?>!)
<?php else: ?>
    @panel(💰 Save £<?= number_format($offer->discount_value, 2) ?> on <?= htmlspecialchars($product->name) ?>!)
<?php endif; ?>

@button(Shop This Offer, <?= config('app.url') ?>/products/<?= $product->slug ?? $product->id ?>?offer=<?= $offer->id ?>)

@divider

## Why This Offer is Special

<?php if (!empty($offer->description)): ?>
    <?= htmlspecialchars($offer->description) ?>

<?php else: ?>
    We've selected this offer based on:
    - Your browsing history
    - Products you've shown interest in
    - Your previous purchases
    - Popular items in your favorite categories
<?php endif; ?>

@divider

## Limited Time Only

<?php
$now = new DateTime();
$end = $endDate;
$interval = $now->diff($end);
$daysRemaining = $interval->days;
?>

@promotion(⏰ <?= $daysRemaining ?> day<?= $daysRemaining > 1 ? 's' : '' ?> remaining!)

<?php if ($daysRemaining <= 2): ?>
    Don't miss out! This offer expires very soon.
<?php elseif ($daysRemaining <= 7): ?>
    Act fast - this special offer won't last long!
<?php else: ?>
    Mark your calendar - you have <?= $daysRemaining ?> days to take advantage of this offer.
<?php endif; ?>

@divider

## How to Claim

@table(Step|Action)
@row(1|Click the "Shop This Offer" button above)
@row(2|Add the product to your cart)
@row(3|Discount will be applied automatically)
@row(4|Complete your purchase)
@endtable

@buttonSecondary(View All Offers, <?= config('app.url') ?>/offers)

@divider

## Need Help?

Have questions about this offer? Our team is ready to assist!

@panel(📧 **Customer Support**
Email: <?= config('mail.support_email', 'support@example.com') ?>
Phone: <?= config('app.support_phone', '1-800-123-4567') ?>
Live Chat: Available 24/7)

@buttonSecondary(Contact Support, <?= config('app.url') ?>/support)

@subcopy(This offer is personalized for you based on your interests. Manage your email preferences in your account settings.)