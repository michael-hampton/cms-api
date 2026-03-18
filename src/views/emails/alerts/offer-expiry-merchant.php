# Your <?= $entityTypeLabel ?> is expiring in <?= $hoursRemaining ?> hours

Hi **<?= htmlspecialchars($merchant->name) ?>**,

This is a reminder that your **<?= $entityTypeLabel ?>** — **<?= htmlspecialchars($entityName) ?>** — will expire on **<?= $expiresAt->format('j F Y \a\t H:i') ?> UTC**.

@promotion(⏰ <?= $hoursRemaining ?> hours remaining)

If you'd like to extend or renew it, head to your dashboard now.

@button(Manage <?= ucfirst($entityTypeLabel) ?>, <?= $manageUrl ?>)

@divider

@subcopy(You're receiving this because you own this <?= $entityTypeLabel ?> on your merchant account. If you have questions, contact support.)