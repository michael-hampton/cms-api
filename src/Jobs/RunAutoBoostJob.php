<?php

namespace App\Jobs;

use App\Framework\Support\Logger;
use App\Services\Adverts\Boost\AutoBoostService;

class RunAutoBoostJob
{
    public function __construct(
        private readonly AutoBoostService $autoBoostService,
    )
    {
    }

    public function handle(): void
    {
        Logger::info('AutoBoostJob started');
        $this->autoBoostService->runForAll();
        Logger::info('AutoBoostJob completed');
    }
}