<?php

namespace App\Jobs;

use App\Framework\Support\Logger;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Services\Adverts\Boost\BoostService;

class ExpireBoostsJob extends BaseJob
{
    private BoostRepository $boostRepository;
    private BoostService $boostService;

    public function __construct(
        private readonly ?int $nowUnix = null,
    )
    {
    }

    public function handle(): void
    {
        $now = $this->nowUnix !== null
            ? (new \DateTimeImmutable('@' . $this->nowUnix))->setTimezone(new \DateTimeZone('UTC'))
            : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
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