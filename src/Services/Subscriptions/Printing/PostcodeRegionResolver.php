<?php

namespace App\Services\Subscriptions\Printing;

use App\Models\Territory;
use App\Repositories\Cms\TerritoryRepository;

/**
 * Resolves a delivery Territory from a postcode string by extracting its
 * two-character prefix and looking up the postcode_territory_mappings table.
 *
 * Returns null when the prefix has no territory mapping — callers must decide
 * whether to fall back to a default territory or produce a global batch.
 */
class PostcodeRegionResolver
{
    public function __construct(
        private readonly TerritoryRepository $territoryRepository,
    )
    {
    }

    public function resolve(string $postcode): ?Territory
    {
        $prefix = strtoupper(substr(trim($postcode), 0, 2));

        if ($prefix === '') {
            return null;
        }

        return $this->territoryRepository->findByPostcodePrefix($prefix);
    }
}