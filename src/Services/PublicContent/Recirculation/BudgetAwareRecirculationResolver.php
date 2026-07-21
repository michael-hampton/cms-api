<?php

namespace App\Services\PublicContent\Recirculation;

use App\DTO\PublicContent\Sources\SourceResult;
use App\Models\Page;
use App\Services\PublicContent\CompositionDeadline;

/**
 * Starts recirculation only when the remaining composition budget can afford it.
 * Otherwise returns typed-empty degraded so the page still serves.
 */
final class BudgetAwareRecirculationResolver
{
    public function __construct(
        private readonly RecirculationSourceInterface $source,
        private readonly int $requiredBudgetMilliseconds = 300,
    ) {
    }

    public function resolve(Page $page, int $siteId, CompositionDeadline $deadline, int $limit = 4): SourceResult
    {
        if (!$deadline->hasBudget($this->requiredBudgetMilliseconds)) {
            return SourceResult::degraded('budget_exhausted');
        }

        return $this->source->resolve($page, $siteId, $limit);
    }
}
