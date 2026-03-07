<?php

namespace App\Listeners\Members;

use App\Events\Members\MemberAddressImported;
use App\Events\Members\MemberCreated;
use App\Events\Members\MemberPostcodeUpdated;
use App\Framework\Support\Logger;
use App\Models\Member;
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
class AssignMemberTerritoryListener
{
    public function __construct(
        private readonly TerritoryResolver $resolver,
        private readonly Logger            $logger,
    )
    {
    }

    public function handleMemberCreated(MemberCreated $event): void
    {
        $this->assignTerritory($event->member);
    }

    private function assignTerritory(Member $member, ?string $postcode = null): void
    {
        $address = $member->defaultShippingAddress?->first();

        if (empty($address)) {
            return;
        }

        $postcode = $postcode ?? $address->postcode;

        if (!$postcode || trim($postcode) === '') {
            return;
        }

        try {
            $territory = $this->resolver->resolve($postcode);

            if (!$territory) {
                $this->logger->info('AssignMemberTerritoryListener: no territory found for postcode prefix', [
                    'member_id' => $member->id,
                    'postcode' => $postcode,
                ]);
                return;
            }

            $member->update(['territory_id' => $territory->id]);

        } catch (\Throwable $e) {
            $this->logger->error('AssignMemberTerritoryListener: failed to assign territory', [
                'member_id' => $member->id,
                'postcode' => $postcode,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleMemberPostcodeUpdated(MemberPostcodeUpdated $event): void
    {
        $this->assignTerritory($event->member, $event->newPostcode);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    public function handleMemberAddressImported(MemberAddressImported $event): void
    {
        $this->assignTerritory($event->member);
    }
}