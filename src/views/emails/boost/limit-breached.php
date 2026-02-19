<?php
/** @var \App\Models\Boost $boost */
/** @var \App\Models\Merchant $merchant */
/** @var string $limitType */
/** @var float|int $limitValue */

/** @var float|int $currentValue */

use App\Framework\Support\Config;

$type = ucfirst($boost->boostable_type);
$context = ucfirst($boost->context);
$ends = date('d M Y H:i', strtotime($boost->ends_at)) . ' UTC';
$limitLabel = ucfirst($limitType);
$formattedLimit = $limitType === 'spend'
        ? htmlspecialchars($boost->currency) . ' ' . number_format($limitValue, 2)
        : number_format($limitValue);
$formattedCurrent = $limitType === 'spend'
        ? htmlspecialchars($boost->currency) . ' ' . number_format($currentValue, 2)
        : number_format($currentValue);
$enforcementNote = Config::get('boost.soft_enforcement_note');
?>
# Your Boost Has Been Paused — Limit Reached

Hi **<?= htmlspecialchars($merchant->name) ?>**,

Your boost #<?= $boost->id ?> for **<?= $type ?> #<?= $boost->boostable_id ?>** in the **<?= $context ?>** context has been automatically paused because it reached its **<?= $limitLabel ?>** limit.

@table(Detail|Value)
@row(Limit Type|<?= $limitLabel ?>)
@row(Your Limit|<?= $formattedLimit ?>)
@row(Current Value|<?= $formattedCurrent ?>)
@endtable

@panel(The boost period is still open until <?= $ends ?>. You can review your limits and resume the boost from your dashboard.)

**What you can do:**
- Resume the boost as-is (it will run until the period ends or a limit is hit again)
- Adjust your limits before resuming

@divider

**Please note:** <?= htmlspecialchars($enforcementNote) ?>

@subcopy(Boost #<?= $boost->id ?> · Auto-paused by limit enforcement · If you have questions, please contact support.)