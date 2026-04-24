<?php

namespace App\Framework\Console\Commands;

use App\Framework\Console\Command;
use App\Framework\Container;
use App\Framework\Schedule\Scheduler;

class ScheduleRunCommand extends Command
{
    protected $signature = 'schedule:run';
    public $description = 'Run the scheduled commands';

    public function handle(): int
    {
        $container = Container::getInstance();
        $scheduler = $container->resolve(Scheduler::class);

        // Load scheduled jobs
        $scheduleCallback = require 'schedule.php';

        $scheduleCallback($scheduler);

        // Run scheduler (queues due jobs)
        $scheduler->run();

        $this->info('Scheduled jobs have been queued');

        return 0;
    }
}