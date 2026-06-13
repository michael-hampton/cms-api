<?php

namespace App\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\EscalationCategory;
use DateTimeImmutable;

/**
 * Pure SLA calculation. "Business day" is treated simply here (24h
 * increments skipping weekends) — adjust if you have a shared
 * business-calendar helper elsewhere.
 */
class EscalationSlaService
{
    private const SLA_BUSINESS_DAYS = [
        EscalationCategory::BrandSafety->value => 0,   // same business day
        EscalationCategory::Copyright->value => 1,
        EscalationCategory::Legal->value => 1,
        EscalationCategory::MusicRights->value => 2,
        EscalationCategory::AiGenerated->value => 1,
        EscalationCategory::AffiliateAbuse->value => 1,
        EscalationCategory::SponsoredContent->value => 1,
        EscalationCategory::Other->value => 2,
    ];

    public function dueAt(EscalationCategory $category, DateTimeImmutable $createdAt): DateTimeImmutable
    {
        $businessDays = self::SLA_BUSINESS_DAYS[$category->value] ?? 2;

        $due = $createdAt;
        $remaining = $businessDays;

        // "Same business day" -> end of the current day.
        if ($remaining === 0) {
            return $due->setTime(23, 59, 59);
        }

        while ($remaining > 0) {
            $due = $due->modify('+1 day');
            if (!in_array((int) $due->format('N'), [6, 7], true)) {
                $remaining--;
            }
        }

        return $due->setTime(23, 59, 59);
    }
}