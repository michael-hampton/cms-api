<!-- Earnings stats section -->
<div class="oc-stats" style="animation:fadeSlideIn .4s ease;margin-bottom:24px;">
    <div class="oc-stat oc-stat--accent">
        <div class="oc-stat__label">Lifetime Earnings</div>
        <div class="oc-stat__value">£<?= number_format($totalEarnings / 100, 2) ?></div>
        <div class="oc-stat__sub">Gross revenue all time</div>
    </div>
    <div class="oc-stat oc-stat--green">
        <div class="oc-stat__label">Available Balance</div>
        <div class="oc-stat__value">£<?= number_format($availableBalance / 100, 2) ?></div>
        <div class="oc-stat__sub">Ready to withdraw</div>
    </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Total Paid Out</div>
        <div class="oc-stat__value">£<?= number_format($totalPaid / 100, 2) ?></div>
        <div class="oc-stat__sub">Received to date</div>
    </div>
    <?php if ($totalInFlight > 0): ?>
        <div class="oc-stat">
            <div class="oc-stat__label">In Progress</div>
            <div class="oc-stat__value">£<?= number_format($totalInFlight / 100, 2) ?></div>
            <div class="oc-stat__sub">Pending or approved</div>
        </div>
    <?php endif; ?>
</div>
