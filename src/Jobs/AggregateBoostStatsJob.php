<?php

namespace App\Jobs;

use App\Enums\Boost\BoostStatus;
use App\Framework\Support\Logger;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Services\Adverts\Boost\BoostLimitEnforcer;
use App\Services\Adverts\Boost\BoostStatAggregator;

class AggregateBoostStatsJob
{
    public function __construct(
        private readonly BoostRepository     $boostRepository,
        private readonly BoostStatAggregator $aggregator,
        private readonly BoostLimitEnforcer  $enforcer,
    )
    {
    }

    public function handle(): void
    {
        // Only aggregate active boosts — paused boosts keep their last stats frozen.
        $boosts = $this->boostRepository->getByStatus(BoostStatus::Active);

        foreach ($boosts as $boost) {
            try {
                $this->aggregator->aggregate($boost->id);
                $this->enforcer->enforce($boost->id);
            } catch (\Exception $e) {
                Logger::error('Failed to aggregate boost stats', [
                    'boost_id' => $boost->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}