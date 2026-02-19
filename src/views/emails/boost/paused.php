<?php
/** @var \App\Models\Boost $boost */
/** @var \App\Models\Merchant $merchant */
$type = ucfirst($boost->boostable_type);
$context = ucfirst($boost->context);
$ends = date('d M Y H:i', strtotime($boost->ends_at)) . ' UTC';
?>
# Your Boost Has Been Paused

Hi **<?= htmlspecialchars($merchant->name) ?>**,

Your boost #<?= $boost->id ?> for **<?= $type ?> #<?= $boost->boostable_id ?>** has been paused.

@panel(The boost period is still open until <?= $ends ?>. You can resume it from your dashboard at any time.)

When you are ready, simply log in and click **Resume** on the boost. No new boost period will be created — it will continue from where it left off.

@divider

@subcopy(Boost #<?= $boost->id ?> · <?= $type ?> #<?= $boost->boostable_id ?> · <?= $context ?>)