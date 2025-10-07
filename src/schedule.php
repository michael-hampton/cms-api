<?php

use App\Framework\Schedule\Scheduler;
use App\Jobs\PublishScheduledPagesJob;

return function (Scheduler $scheduler) {
    // Schedule the publish pages job to run every minute
    $scheduler->job(new PublishScheduledPagesJob())
        ->everyMinute();

    // Add more scheduled jobs here
    // $scheduler->job(new AnotherJob())->hourly();
};