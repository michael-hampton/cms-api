# Your bundle is expiring in <?= $hoursRemaining ?> hours

Hi **<?= htmlspecialchars($merchant->name) ?>**,

Your bundle **<?= htmlspecialchars($bundleName) ?>** will expire on **<?= $expiresAt->format('j F Y \a\t H:i') ?> UTC**.

@promotion(⏰ <?= $hoursRemaining ?> hour<?= $hoursRemaining !== 1 ? 's' : '' ?> remaining)

@table(Detail|Value)
@row(Bundle|<?= htmlspecialchars($bundleName) ?>)
@row(Bundle Price|@price(<?= number_format($bundlePrice, 2) ?>))
@row(Total Value|~~$<?= number_format($totalValue, 2) ?>~~)
@row(Discount|<?= $discountPercentage ?>% OFF)
@row(Items|<?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?>)
@row(Expires|<?= $expiresAt->format('j F Y \a\t H:i') ?> UTC)
@endtable

Once this bundle expires, customers will no longer be able to purchase it at the bundled price. If you'd like to extend it, visit your dashboard.

@button(Manage Bundle, <?= $manageUrl ?>)

@divider

@subcopy(You're receiving this because you own this bundle on your merchant account. Contact support if you have any questions.)