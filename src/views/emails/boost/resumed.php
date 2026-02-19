<?php
/** @var \App\Models\Boost $boost */
/** @var \App\Models\Merchant $merchant */
$type = ucfirst($boost->boostable_type);
$context = ucfirst($boost->context);
$ends = date('d M Y H:i', strtotime($boost->ends_at)) . ' UTC';
?>
# Your Boost is Active Again

Hi **<?= htmlspecialchars($merchant->name) ?>**,

Your boost #<?= $boost->id ?> for **<?= $type ?> #<?= $boost->boostable_id ?>** in the **<?= $context ?>** context has been resumed and is now live again.

@panel(Running until <?= $ends ?>)

@divider

@subcopy(Boost #<?= $boost->id ?> · <?= $type ?> #<?= $boost->boostable_id ?> · <?= $context ?>)