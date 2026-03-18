# ⏰ Bundle ending soon — don't miss out!

Hello **<?= $member->first_name ?? 'there' ?>**,

A bundle you saved is expiring in **<?= $hoursRemaining ?> hour<?= $hoursRemaining !== 1 ? 's' : '' ?>**.

@promotion(⏰ <?= $hoursRemaining ?> hour<?= $hoursRemaining !== 1 ? 's' : '' ?> left on **<?= htmlspecialchars($bundle->name) ?>**)

## Bundle Details

**<?= htmlspecialchars($bundle->name) ?>**

<?php if (!empty($bundle->description)): ?>
    <?= htmlspecialchars($bundle->description) ?>
<?php endif; ?>

@table(Detail|Value)
@row(Bundle Price|@price(<?= number_format($bundle->bundle_price, 2) ?>))
@row(Total Value|~~$<?= number_format($bundle->total_price, 2) ?>~~)
@row(You Save|$<?= number_format($bundle->total_price - $bundle->bundle_price, 2) ?> (<?= $bundle->discount_percentage ?>% OFF))
@row(Expires|<?= $expiresAt->format('j F Y \a\t H:i') ?> UTC)
@endtable

@button(View Bundle, <?= $bundleUrl ?>)

@divider

@subcopy(You're receiving this because you saved this bundle to your wishlist. You can manage your saved items from your account.)