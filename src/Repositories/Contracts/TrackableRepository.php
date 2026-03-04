<?php

namespace App\Repositories\Contracts;

interface TrackableRepository
{
    /**
     * Returns true if an event of the given action type has already been
     * recorded for this member, entity, and surface combination.
     *
     * Used exclusively for all-time deduplication before writes.
     *
     * @param int $entityId The offer, product, or reward ID
     * @param int $memberId Always a real member — guests bypass dedup entirely
     * @param string $action e.g. 'render', 'click', 'claim'
     * @param string $surfaceType e.g. 'page', 'email'
     * @param int $surfaceId The ID of the surface
     */
    public function hasTracked(
        int    $entityId,
        int    $memberId,
        string $action,
        string $surfaceType,
        int    $surfaceId,
    ): bool;
}