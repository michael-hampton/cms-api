<?php

namespace App\Listeners\Members;

use App\Models\Member;

trait AsssignTerritoryTrait
{
    protected function assignTerritory(Member $member, ?string $postcode = null): void
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
}