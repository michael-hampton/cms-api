# ⏰ Last Chance! Offer Ending Soon

Hello **<?= $member->first_name ?>**,

This is your final reminder - an offer you've shown interest in is about to expire!

@promotion(🚨 Only <?= $hoursRemaining ?> hour<?= $hoursRemaining > 1 ? 's' : '' ?> left!)

@divider

## Expiring Offer

**<?= htmlspecialchars($offer->title) ?>**

<?php if (!empty($offer->description)): ?>
    <?= htmlspecialchars($offer->description) ?>
<?php endif; ?>

@divider

## Featured Product

**<?= htmlspecialchars($product->name) ?>**

<?php if ($offer->discount_type === 'percentage'): ?>
    @panel(💰 **<?= $offer->discount_value ?>% OFF** - Save Big Before Time Runs Out!)
<?php else: ?>
    @panel(💰 **£<?= number_format($offer->discount_value, 2) ?> OFF** - Don't Miss This Deal!)
<?php endif; ?>

@button(Claim This Offer Now, <?= config('app.url') ?>/products/<?= $product->slug ?? $product->id ?>?offer=<?= $offer->id ?>)

@divider

## Urgency Alert

<?php if ($hoursRemaining <= 6): ?>
    @promotion(🔥 FINAL HOURS: Less than <?= $hoursRemaining ?> hours remaining!)

    This offer expires **TODAY** at <?= $endDate->format('g:i A') ?>!

    ⚠️ **Act immediately** - this is your last chance to save!

<?php elseif ($hoursRemaining <= 24): ?>
    @promotion(⚡ ENDING TODAY: <?= $hoursRemaining ?> hours left!)

    The clock is ticking! This offer expires at <?= $endDate->format('g:i A') ?> today.

    🏃 **Don't wait** - secure your discount now!

<?php else: ?>
    @promotion(⏰ <?= $hoursRemaining ?> hours remaining)

    This offer ends on <?= $endDate->format('F j \a\t g:i A') ?>.

    ⚡ **Limited time** - grab it while you can!

<?php endif; ?>

@divider

## Why You Should Act Now

@table(Reason|Benefit)
@row(💰 Save Money|Get <?= $offer->discount_type === 'percentage' ? $offer->discount_value . '%' : '£' . number_format($offer->discount_value, 2) ?> off)
@row(⏰ Exclusive Deal|This pricing won't be repeated)
@row(🎯 Perfect Match|Selected based on your interests)
@row(✅ Easy Checkout|One click to claim)
@endtable

@divider

## Quick Redemption Steps

1. **Click** the button above
2. **Review** product details
3. **Add to cart** with discount applied
4. **Checkout** before <?= $endDate->format('g:i A') ?>

@panel(💡 **Pro Tip:** Items in your cart are NOT reserved. Complete your purchase to secure this discount!)

@divider

## What Happens After It Expires?

Once this offer ends:
- ❌ The discount will no longer be available
- ❌ The product returns to regular pricing
- ❌ You'll miss out on these savings
- ❌ We can't extend or re-activate expired offers

<?php if ($hoursRemaining <= 12): ?>
    @promotion(🚨 The price goes back up in <?= $hoursRemaining ?> hours!)
<?php endif; ?>

@buttonSecondary(View Other Active Offers, <?= config('app.url') ?>/offers)

@divider

## Still Have Questions?

Need help before the offer expires? We're here!

@panel(💬 **Urgent Support**
Live Chat: Instant assistance
Phone: <?= config('app.support_phone', '1-800-123-4567') ?>
Email: <?= config('mail.support_email', 'support@example.com') ?>)

@buttonSecondary(Get Quick Help, <?= config('app.url') ?>/support)

@divider

## Don't Miss Out!

This is our final reminder about this offer. We don't want you to miss out on this opportunity to save.

**Ends:** <?= $endDate->format('F j, Y \a\t g:i A') ?>
**Time Left:** <?= $hoursRemaining ?> hour<?= $hoursRemaining > 1 ? 's' : '' ?>

@button(Claim Before It's Gone, <?= config('app.url') ?>/products/<?= $product->slug ?? $product->id ?>?offer=<?= $offer->id ?>)

@subcopy(This is an automated reminder for an offer ending soon. Once expired\, this offer cannot be extended or reactivated.)