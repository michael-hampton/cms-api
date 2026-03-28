<?php

declare(strict_types=1);

namespace App\Services\Product\Fulfilment;

use App\Models\Territory;
use App\Services\Subscriptions\Printing\PostcodeRegionResolver;

/**
 * Resolves a delivery Territory purely from a postcode string.
 *
 * The existing RegionResolver (print pipeline) takes a Subscription as its
 * first argument to support the territory override flag. Orders do not have
 * that concept, so injecting the full RegionResolver would require passing a
 * null/fake Subscription — a leaky abstraction.
 *
 * This class wraps PostcodeRegionResolver directly, which is the part of the
 * print pipeline that actually performs the postcode lookup. PostcodeRegionResolver
 * is closed for modification and used here by composition.
 *
 * Single reason to change: the postcode-to-territory lookup rules for orders.
 */
class PostcodeOnlyRegionResolver
{
    public function __construct(
        private readonly PostcodeRegionResolver $postcodeRegionResolver,
    )
    {
    }

    /**
     * Returns the Territory for the given postcode, or null when no mapping
     * exists (caller falls back to global/default batch).
     */
    public function resolve(?string $postcode): ?Territory
    {
        if ($postcode === null || trim($postcode) === '') {
            return null;
        }

        return $this->postcodeRegionResolver->resolve($postcode);
    }
}