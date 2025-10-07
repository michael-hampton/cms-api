<?php

namespace App\Framework\ServiceProvider;

use App\Framework\Database\Database;
use App\Framework\Queue\DatabaseQueue;
use App\Framework\Queue\QueueInterface;
use App\Framework\Schedule\Scheduler;

class ScheduleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Queue
        $this->container->singleton(QueueInterface::class, function ($container) {
            return new DatabaseQueue($container->resolve(Database::class));
        });

        // Register Scheduler
        $this->container->singleton(Scheduler::class, function ($container) {
            return new Scheduler($container->resolve(QueueInterface::class));
        });
    }

    public function boot(): void
    {
        // Schedule is configured in schedule() method
    }
}