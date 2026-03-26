<?php

namespace App\Listeners\Members;

use App\Events\Members\MemberPostcodeUpdated;
use App\Framework\Support\Logger;
use App\Services\Members\TerritoryResolver;

/**
 * Assigns (or re-assigns) a territory to a member based on their postcode.
 *
 * Handles:
 *   - MemberCreated
 *   - MemberPostcodeUpdated
 *   - MemberAddressImported
 *
 * Non-critical path: failures are caught and logged rather than bubbled,
 * so a bad postcode never blocks member creation or address updates.
 *
 * Skips silently when:
 *   - The member has no postcode.
 *   - The postcode prefix has no territory mapping (e.g. international).
 */
class MemberPostcodeUpdatedListener
{
    use AsssignTerritoryTrait;

    public function __construct(
        private readonly TerritoryResolver $resolver,
        private readonly Logger            $logger,
    )
    {
    }

    public function handle(MemberPostcodeUpdated $event): void
    {
        $this->assignTerritory($event->member, $event->newPostcode);
    }
}