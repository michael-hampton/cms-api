<?php

namespace App\Services\OpenCollab\Moderation;

use App\Models\ModerationQueueEntry;
use DateTimeImmutable;

/**
 * Pure calculation of priority_score from a queue entry's current state.
 * risk_score is computed separately by RiskScoreCalculator and passed in.
 */
class ModerationPriorityCalculator
{
    private const SCHEDULED_WITHIN_HOURS = 24;
    private const AGE_BOOST_AFTER_HOURS = 48;
    private const AGE_BOOST_AMOUNT = 20;

    public function calculate(
        int $riskScore,
        ?DateTimeImmutable $scheduledPublishAt,
        DateTimeImmutable $submittedAt,
        DateTimeImmutable $now,
        int $manualPriorityBoost = 0,
    ): int {
        $score = $riskScore;

        if ($scheduledPublishAt !== null) {
            $hoursUntilPublish = ($scheduledPublishAt->getTimestamp() - $now->getTimestamp()) / 3600;
            if ($hoursUntilPublish >= 0 && $hoursUntilPublish <= self::SCHEDULED_WITHIN_HOURS) {
                $score += 40;
            }
        }

        $ageHours = ($now->getTimestamp() - $submittedAt->getTimestamp()) / 3600;
        if ($ageHours > self::AGE_BOOST_AFTER_HOURS) {
            $score += self::AGE_BOOST_AMOUNT;
        }

        $score += $manualPriorityBoost;

        return $score;
    }

    public function forEntry(ModerationQueueEntry $entry, int $riskScore, int $manualPriorityBoost = 0): int
    {
        $now = new DateTimeImmutable();

        return $this->calculate(
            $riskScore,
            $entry->scheduled_publish_at ? DateTimeImmutable::createFromMutable($entry->scheduled_publish_at) : null,
            DateTimeImmutable::createFromMutable($entry->submitted_at),
            $now,
            $manualPriorityBoost,
        );
    }
}