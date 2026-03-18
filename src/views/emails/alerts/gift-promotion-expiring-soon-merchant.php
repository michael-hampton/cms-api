# Your promotion is expiring in <?= $hoursRemaining ?> hours

Hi **<?= htmlspecialchars($merchant->name) ?>**,

Your gift promotion **<?= htmlspecialchars($promotionName) ?>** will expire on **<?= $expiresAt->format('j F Y \a\t H:i') ?> UTC**.

@promotion(⏰ <?= $hoursRemaining ?> hour<?= $hoursRemaining !== 1 ? 's' : '' ?> remaining)

@table(Detail|Value)
@row(Promotion|<?= htmlspecialchars($promotionName) ?>)
@row(Gift Type|<?= htmlspecialchars($giftType) ?>)
@row(Quantity Rule|<?= htmlspecialchars($quantityRule) ?>)
@row(Triggers|<?= $triggerCount ?> configured)
@row(Expires|<?= $expiresAt->format('j F Y \a\t H:i') ?> UTC)
@endtable

Once this promotion expires, customers will no longer receive gifts through it. If you'd like to extend it, visit your dashboard.

@button(Manage Promotion, <?= $manageUrl ?>)

@divider

@subcopy(You're receiving this because you own this promotion on your merchant account. Contact support if you have any questions.)