<?php
/** @var \App\Models\Boost $boost */
/** @var \App\Models\Merchant $merchant */
$currency = htmlspecialchars($boost->currency);
$price = number_format($boost->price_paid, 2);
$type = ucfirst($boost->boostable_type);
$context = ucfirst($boost->context);
$starts = date('d M Y H:i', strtotime($boost->starts_at)) . ' UTC';
$ends = date('d M Y H:i', strtotime($boost->ends_at)) . ' UTC';
?>
# Boost Confirmed

Hi **<?= htmlspecialchars($merchant->name) ?>**,

Your boost has been created and is scheduled to go live automatically at its start time.

@panel(Boost #<?= $boost->id ?> — <?= $type ?> in <?= $context ?>)

@table(Detail|Value)
@row(Target|<?= $type ?> #<?= $boost->boostable_id ?>)
@row(Context|<?= $context ?>)
@row(Multiplier|<?= number_format($boost->multiplier, 2) ?>×)
@row(Starts|<?= $starts ?>)
@row(Ends|<?= $ends ?>)
@row(Price Paid|<?= $currency ?> <?= $price ?>)
@endtable

@divider

Your boost will activate automatically when the start time is reached. You can manage it from your dashboard at any time.

@subcopy(This is a transactional email related to your boost on <?= htmlspecialchars($merchant->name) ?>. If you believe this was sent in error, please contact support.)