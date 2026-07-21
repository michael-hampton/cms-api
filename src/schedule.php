<?php

use App\Framework\Schedule\Scheduler;
use App\Jobs\ProcessPendingFulfilmentSuspensionsJob;
use App\Jobs\PublishScheduledPagesJob;

return function (Scheduler $scheduler) {
    // Schedule the publish pages job to run every minute
    $scheduler->job(new PublishScheduledPagesJob())
        ->everyMinute();

    // Re-checks subscriptions whose fulfilment suspension was deferred by a
    // plan's days/issues FulfilmentSuspensionRule and applies it once due.
    $scheduler->job(new ProcessPendingFulfilmentSuspensionsJob())
        ->hourly();

    // Add more scheduled jobs here
    // $scheduler->job(new AnotherJob())->hourly();
};