<?php

namespace App\Services\Cms\Pages;

use App\DTO\Pages\PremiumPageEligibilityResult;
use App\Enums\Pages\PageStatus;
use App\Models\Page;

class PremiumPageEligibilityService
{
    public function check(Page $page, ?int $approvedPrice = null): PremiumPageEligibilityResult
    {
        $failures = [];
        $warnings = [];

        if (empty($page->contributor_id)) {
            $failures[] = 'Page must have a contributor before it can be approved as premium.';
        }

        if (empty(trim((string) $page->title))) {
            $failures[] = 'Page title is required.';
        }

        $allowedStatuses = [
            PageStatus::WAITING_APPROVAL->value,
            PageStatus::PUBLISHED->value,
        ];

        if (!in_array((string) $page->status, $allowedStatuses, true)) {
            $failures[] = sprintf(
                'Page status [%s] cannot be approved as premium.',
                (string) $page->status
            );
        }

        if (!empty($page->monetisation_disabled_at)) {
            $failures[] = 'Page monetisation is disabled.';
        }

        if ($approvedPrice !== null && $approvedPrice <= 0) {
            $failures[] = 'Approved premium price must be greater than zero.';
        }

        if (empty($page->blocks) && empty($page->content)) {
            $warnings[] = 'Page appears to have no body content or blocks.';
        }

        return empty($failures)
            ? PremiumPageEligibilityResult::eligible($warnings)
            : PremiumPageEligibilityResult::ineligible($failures, $warnings);
    }

    public function assertEligible(Page $page, ?int $approvedPrice = null): void
    {
        $result = $this->check($page, $approvedPrice);

        if (!$result->eligible) {
            throw new \InvalidArgumentException(
                'Page cannot be approved as premium: ' . implode(' ', $result->failures)
            );
        }
    }
}