<?php

namespace App\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\RiskSeverity;
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
            $score += $this->severityFor($marker)->score();
        }

        return $score;
    }

    public function hasBlockingRisk(Collection $outstandingMarkers): bool
    {
        foreach ($outstandingMarkers as $marker) {
            if ($this->severityFor($marker)->isBlocking()) {
                return true;
            }
        }

        return false;
    }

    private function severityFor(ContentRiskMarker $marker): RiskSeverity
    {
        return $marker->severity instanceof RiskSeverity
            ? $marker->severity
            : RiskSeverity::from((string) $marker->severity);
    }
}
