<?php

namespace App\Services\Adverts\Boost;

use App\Enums\Boost\BoostEventType;
use App\Enums\Boost\BoostStatus;
use App\Exceptions\Boost\BoostNotFoundException;
use App\Models\Boost;
use App\Repositories\Adverts\Boost\BoostEventRepository;
use App\Repositories\Adverts\Boost\BoostRepository;

class BoostEventService
{
    public function __construct(
        private readonly BoostRepository      $boostRepository,
        private readonly BoostEventRepository $boostEventRepository,
    )
    {
    }

    /**
     * Record an impression for a boost. Deduplicates per session.
     * Non-critical — callers should not let a failure here block the main flow.
     */
    public function recordImpression(int $boostId, string $sessionHash, array $metadata = []): void
    {
        $boost = $this->findActiveBoost($boostId);

        if ($this->boostEventRepository->hasEvent($boostId, BoostEventType::Impression, $sessionHash)) {
            return;
        }

        $this->boostEventRepository->create([
            'boost_id' => $boostId,
            'type' => BoostEventType::Impression->value,
            'session_hash' => $sessionHash,
            'occurred_at' => now_datetime()->toDateTimeString(),
            'metadata' => $metadata,
        ]);
    }

    private function findActiveBoost(int $boostId): Boost
    {
        $boost = $this->boostRepository->find($boostId);

        if (!$boost) {
            throw BoostNotFoundException::forId($boostId);
        }

        if ($boost->status !== BoostStatus::Active->value) {
            throw new \RuntimeException("Boost #{$boostId} is not active.");
        }

        return $boost;
    }

    /**
     * Record a click for a boost. Deduplicates per session.
     */
    public function recordClick(int $boostId, string $sessionHash, array $metadata = []): void
    {
        $boost = $this->findActiveBoost($boostId);

        if ($this->boostEventRepository->hasEvent($boostId, BoostEventType::Click, $sessionHash)) {
            return;
        }

        $this->boostEventRepository->create([
            'boost_id' => $boostId,
            'type' => BoostEventType::Click->value,
            'session_hash' => $sessionHash,
            'occurred_at' => now_datetime()->toDateTimeString(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record a conversion for a boost. Not session-deduplicated —
     * a user can convert multiple times (repeat purchases).
     */
    public function recordConversion(int $boostId, string $sessionHash, array $metadata = []): void
    {
        $this->findActiveBoost($boostId);

        $this->boostEventRepository->create([
            'boost_id' => $boostId,
            'type' => BoostEventType::Conversion->value,
            'session_hash' => $sessionHash,
            'occurred_at' => now_datetime()->toDateTimeString(),
            'metadata' => $metadata,
        ]);
    }
}