# Your offer is expiring in <?= $hoursRemaining ?> hours

Hi **<?= htmlspecialchars($merchant->name) ?>**,

Your offer on **<?= htmlspecialchars($productName) ?>** will expire on **<?= $expiresAt->format('j F Y \a\t H:i') ?> UTC**.

@promotion(⏰ <?= $hoursRemaining ?> hour<?= $hoursRemaining !== 1 ? 's' : '' ?> remaining)

@table(Detail|Value)
@row(Product|<?= htmlspecialchars($productName) ?>)
@row(Sale Price|@price(<?= number_format($salePrice, 2) ?>))
<?php if ($originalPrice): ?>
    @row(Original Price|~~$<?= number_format($originalPrice, 2) ?>~~)
    @row(Discount|<?= $discountPercentage ?>% OFF)
<?php endif; ?>
@row(Expires|<?= $expiresAt->format('j F Y \a\t H:i') ?> UTC)
@endtable

If you'd like to extend or update this offer before it expires, head to your dashboard.

@button(Manage Offer, <?= $manageUrl ?>)

@divider

@subcopy(You're receiving this because you own this offer on your merchant account. Contact support if you have any questions.)