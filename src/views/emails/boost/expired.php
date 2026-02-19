<?php
/** @var \App\Models\Boost $boost */
/** @var \App\Models\Merchant $merchant */
/** @var \App\Models\BoostStat|null $stat */
$type = ucfirst($boost->boostable_type);
$context = ucfirst($boost->context);
?>
# Your Boost Has Ended

Hi **<?= htmlspecialchars($merchant->name) ?>**,

Your boost for <?= $type ?> #<?= $boost->boostable_id ?> in the **<?= $context ?>** context has now ended.

<?php if ($stat): ?>
    ## Results Summary

    @table(Metric|Result)
    @row(Impressions|<?= number_format($stat->impressions) ?>)
    @row(Clicks|<?= number_format($stat->clicks) ?>)
    @row(Conversions|<?= number_format($stat->conversions) ?>)
    @row(CTR|<?= $stat->ctr() ?>%)
    @row(Conversion Rate|<?= $stat->conversionRate() ?>%)
    @row(Spend Attributed|<?= htmlspecialchars($boost->currency) ?> <?= number_format($stat->spend_attributed, 2) ?>)
    @endtable
<?php endif; ?>

@divider

Ready to boost again? Create a new boost from your dashboard to keep your visibility high.

@subcopy(Boost #<?= $boost->id ?> · Ran from <?= date('d M Y', strtotime($boost->starts_at)) ?> to <?= date('d M Y', strtotime($boost->ends_at)) ?>)