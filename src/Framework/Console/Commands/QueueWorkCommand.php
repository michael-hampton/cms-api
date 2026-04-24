<?php

namespace App\Framework\Console\Commands;

use App\Framework\Console\Command;
use App\Framework\Container;
use App\Framework\Queue\QueueInterface;

class QueueWorkCommand extends Command
{
    protected $signature = 'queue:work';
    public $description = 'Process jobs in the queue';

    public function handle(): int
    {
        $container = Container::getInstance();
        $queue = $container->resolve(QueueInterface::class);

        $this->info('Starting queue worker...');

        $queue->work();

        $this->info('Queue worker finished');

        return 0;
    }
}