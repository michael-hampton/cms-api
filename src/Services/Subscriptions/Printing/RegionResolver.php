<?php

namespace App\Services\Subscriptions\Printing;

use App\Models\Subscription;
use App\Models\Territory;
use App\Repositories\Cms\TerritoryRepository;

/**
 * Resolves the delivery territory for a print subscription.
 *
 * Priority order (per ticket spec):
 *   1. Subscription territory override (territory_id set AND territory_override_flag = true)
 *   2. Postcode-derived territory — postcode → postcode_territory_mappings → territory
 *   3. null (caller falls back to global batch)
 *
 * This class has a single reason to change: the territory resolution priority rules.
 * It does not read sessions, globals, or perform any writes.
 */
class RegionResolver
{
    public function __construct(
        private readonly TerritoryRepository    $territoryRepository,
        private readonly PostcodeRegionResolver $postcodeRegionResolver,
    )
    {
    }

    /**
     * Resolve the delivery territory for a subscription.
     *
     * @param Subscription $subscription The subscription being fulfilled.
     * @param string|null $postcode The delivery postcode from the resolved
     *                                   address. Passed explicitly so this class
     *                                   does not need an address repository.
     */
    public function resolve(Subscription $subscription, ?string $postcode = null): ?Territory
    {
        // 1. Explicit override — support team has manually pinned this subscription
        //    to a specific territory. Both the flag AND the id must be set.
        if ($subscription->territory_override_flag && $subscription->territory_id) {
            return $this->territoryRepository->find($subscription->territory_id);
        }

        // 2. Derive from postcode when a postcode is available.
        if ($postcode && trim($postcode) !== '') {
            $territory = $this->postcodeRegionResolver->resolve($postcode);
            if ($territory) {
                return $territory;
            }
        }

        // 3. No territory can be determined — caller uses global batch.
        return null;
    }
}