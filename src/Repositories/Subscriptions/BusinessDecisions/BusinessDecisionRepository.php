<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions\BusinessDecisions;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Models\BusinessDecision;
use App\Repositories\Repository;

class BusinessDecisionRepository extends Repository
{
    protected function getModelClass(): string
    {
        return BusinessDecision::class;
    }

    /**
     * Lists decisions ordered by category then name, optionally filtered
     * to a single category and/or created/updated date ranges.
     * Used by the admin listing endpoint.
     */
    public function listAll(
        ?BusinessDecisionCategoryEnum $category = null,
        ?string $createdFrom = null,
        ?string $createdTo = null,
        ?string $updatedFrom = null,
        ?string $updatedTo = null,
    ): array {
        $query = BusinessDecision::query();

        if ($category !== null) {
            $query = $query->where('category', $category->value);
        }

        if (!empty($createdFrom)) {
            $query->where('created_at', '>=', $createdFrom . ' 00:00:00');
        }

        if (!empty($createdTo)) {
            $query->where('created_at', '<=', $createdTo . ' 23:59:59');
        }

        if (!empty($updatedFrom)) {
            $query->where('updated_at', '>=', $updatedFrom . ' 00:00:00');
        }

        if (!empty($updatedTo)) {
            $query->where('updated_at', '<=', $updatedTo . ' 23:59:59');
        }

        return $query->orderBy('category', 'asc')->orderBy('name', 'asc')->get()->all();
    }

    /**
     * The single active global default decision for a category. There
     * should only ever be one (enforced by BusinessDecisionService when
     * setting is_default — see clearDefaultForCategory()); if
     * configuration ever drifts, the first match wins here and the
     * ambiguity is left to be surfaced elsewhere, same precedent as
     * ReplacementPolicyRepository::findDefault().
     */
    public function findDefault(BusinessDecisionCategoryEnum $category): ?BusinessDecision
    {
        return BusinessDecision::where('category', $category->value)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    public function findActive(int $id): ?BusinessDecision
    {
        return BusinessDecision::where('id', $id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Unsets is_default on every other decision for a category.
     * Persistence only — when this is called belongs to
     * BusinessDecisionService.
     */
    public function clearDefaultForCategory(string $category, ?int $exceptId = null): void
    {
        $query = BusinessDecision::where('category', $category)
            ->where('is_default', true);

        if ($exceptId !== null) {
            $query = $query->where('id', '!=', $exceptId);
        }

        foreach ($query->get() as $decision) {
            $decision->fill(['is_default' => false]);
            $decision->save();
        }
    }
}
