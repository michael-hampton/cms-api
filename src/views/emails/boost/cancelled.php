<?php
/** @var \App\Models\Boost $boost */
/** @var \App\Models\Merchant $merchant */
$type = ucfirst($boost->boostable_type);
$context = ucfirst($boost->context);
?>
# Boost Cancelled

Hi **<?= htmlspecialchars($merchant->name) ?>**,

Your boost #<?= $boost->id ?> for **<?= $type ?> #<?= $boost->boostable_id ?>** in the **<?= $context ?>** context has been cancelled.

If you cancelled this by mistake or would like to create a new boost, you can do so from your dashboard at any time.

@divider

@subcopy(Boost #<?= $boost->id ?> · Cancelled · If you did not request this cancellation, please contact support immediately.)