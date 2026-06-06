<?php

namespace App\Framework\Console\Commands;

use App\Framework\Console\Command;
use App\Framework\Support\Cache\Drivers\DatabaseCacheDriver;

class PruneCacheCommand extends Command
{
    protected $signature = 'cache:prune';
    public $description = 'Remove expired cache entries';

    public function __construct(
        private readonly DatabaseCacheDriver $cache
    ) {
    }

    public function handle(): int
    {
        $count = $this->cache->prune();

        $this->info("Pruned {$count} expired cache entries.");

        return 0;
    }
}
