<?php
/** @var \App\Models\Boost $boost */
/** @var \App\Models\Merchant $merchant */
$type = ucfirst($boost->boostable_type);
$context = ucfirst($boost->context);
$ends = date('d M Y H:i', strtotime($boost->ends_at)) . ' UTC';
?>
# Your Boost is Live 🚀

Hi **<?= htmlspecialchars($merchant->name) ?>**,

Great news — your boost is now active and your <?= strtolower($type) ?> is appearing in the **<?= $context ?>** context with a **<?= number_format($boost->multiplier, 2) ?>× visibility multiplier**.

@panel(Your boost is live until <?= $ends ?>)

Head to your dashboard to monitor impressions, clicks, and conversions in real time.

@divider

@subcopy(Boost #<?= $boost->id ?> · <?= $type ?> #<?= $boost->boostable_id ?> · <?= $context ?>)