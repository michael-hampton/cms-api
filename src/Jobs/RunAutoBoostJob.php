<?php

namespace App\Jobs;

use App\Framework\Support\Logger;
use App\Services\Adverts\Boost\AutoBoostService;

class RunAutoBoostJob extends BaseJob
{
    private AutoBoostService $autoBoostService;

    public function __construct(
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