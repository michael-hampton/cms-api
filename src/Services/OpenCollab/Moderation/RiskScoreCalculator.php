<?php

namespace App\Services\OpenCollab\Moderation;

use App\Framework\Support\Collection;
use App\Models\ContentRiskMarker;

/**
 * Pure calculation — no persistence, no side effects (per coding contract).
 */
class RiskScoreCalculator
{
    /**
     * @param Collection<ContentRiskMarker> $outstandingMarkers
     */
    public function calculate(Collection $outstandingMarkers): int
    {
        $score = 0;

        foreach ($outstandingMarkers as $marker) {
            $score += $marker->severity->score();
        }

        return $score;
    }

    public function hasBlockingRisk(Collection $outstandingMarkers): bool
    {
        foreach ($outstandingMarkers as $marker) {
            if ($marker->severity->isBlocking()) {
                return true;
            }
        }

        return false;
    }
}