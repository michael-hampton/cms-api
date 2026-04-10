<?php

namespace App\Repositories\OpenCollab;

use App\Models\UserGuidelinesAcknowledgement;
use App\Repositories\Repository;

class GuidelinesRepository extends Repository
{
    public function hasAcknowledged(int $userId, int $siteId, int $version): bool
    {
        return UserGuidelinesAcknowledgement::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->where('version', $version)
            ->exists();
    }

    /**
     * Returns the highest version acknowledged by this user for this site.
     * Returns 0 if none.
     */
    public function latestAcknowledgedVersion(int $userId, int $siteId): int
    {
        return (int)UserGuidelinesAcknowledgement::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->max('version');
    }

    public function record(int $userId, int $siteId, int $version): UserGuidelinesAcknowledgement
    {
        $ack = new UserGuidelinesAcknowledgement();
        $ack->user_id = $userId;
        $ack->site_id = $siteId;
        $ack->version = $version;
        $ack->acknowledged_at = date('Y-m-d H:i:s');
        $ack->save();

        return $ack;
    }

    protected function getModelClass(): string
    {
        return UserGuidelinesAcknowledgement::class;
    }
}