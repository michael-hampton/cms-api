<?php

namespace App\Jobs;

use App\Framework\Support\Logger;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Services\Adverts\Boost\BoostService;

class ExpireBoostsJob
{
    public function __construct(
        private readonly BoostRepository $boostRepository,
        private readonly BoostService    $boostService,
    )
    {
    }

    public function handle(\DateTimeImmutable $now): void
    {
        $expiredBoosts = $this->boostRepository->getExpiredBoosts($now);

        foreach ($expiredBoosts as $boost) {
            try {
                $this->boostService->expireBoost($boost->id, $now);
            } catch (\Exception $e) {
                Logger::error('Failed to expire boost', [
                    'boost_id' => $boost->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}