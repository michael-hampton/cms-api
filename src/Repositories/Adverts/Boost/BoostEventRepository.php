<?php

namespace App\Repositories\Adverts\Boost;

use App\Enums\Boost\BoostEventType;
use App\Models\BoostEvent;
use App\Repositories\Repository;

class BoostEventRepository extends Repository
{
    public function hasEvent(int $boostId, BoostEventType $type, string $sessionHash): bool
    {
        return BoostEvent::where('boost_id', $boostId)
            ->where('type', $type->value)
            ->where('session_hash', $sessionHash)
            ->exists();
    }

    public function countByType(int $boostId, BoostEventType $type): int
    {
        return BoostEvent::where('boost_id', $boostId)
            ->where('type', $type->value)
            ->count();
    }

    public function hasEventWithinWindow(
        int            $boostId,
        BoostEventType $type,
        string         $sessionHash,
        int            $withinHours
    ): bool
    {
        $cutoff = (new \DateTimeImmutable())->modify("-{$withinHours} hours");

        return BoostEvent::where('boost_id', $boostId)
            ->where('type', $type->value)
            ->where('session_hash', $sessionHash)
            ->where('occurred_at', '>=', $cutoff->format('Y-m-d H:i:s'))
            ->exists();
    }

    protected function getModelClass(): string
    {
        return BoostEvent::class;
    }
}