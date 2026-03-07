<?php

namespace App\Services\Members;

use App\Models\Territory;
use App\Repositories\Cms\TerritoryRepository;

/**
 * Derives a Territory from a postcode by extracting its two-character prefix
 * and performing a lookup via postcode_territory_mappings.
 *
 * Rule: take the first two uppercase characters of the postcode as the area code.
 * This covers all UK postcode areas (CF, EH, SW, etc.).
 * International postcodes or unmapped prefixes return null — the caller decides
 * whether to treat that as an error or a no-op.
 */
class TerritoryResolver
{
    public function __construct(
        private readonly TerritoryRepository $territoryRepository,
    )
    {
    }

    /**
     * Resolve a territory from a postcode string.
     * Returns null when the prefix has no mapping.
     */
    public function resolve(string $postcode): ?Territory
    {
        $prefix = strtoupper(substr(trim($postcode), 0, 2));

        if ($prefix === '') {
            return null;
        }

        return $this->territoryRepository->findByPostcodePrefix($prefix);
    }
}