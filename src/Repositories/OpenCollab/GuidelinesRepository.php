<?php

namespace App\Repositories\OpenCollab;

use App\Models\Guideline;
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

    public function hasAcknowledgedGuideline(int $userId, int $guidelineId): bool
    {
        return UserGuidelinesAcknowledgement::where('user_id', $userId)
            ->where('guideline_id', $guidelineId)
            ->exists();
    }

    /**
     * Returns the highest version acknowledged by this user for this site.
     * Returns 0 if none.
     */
    public function latestAcknowledgedVersion(int $userId, int $siteId): ?int
    {
        return (int)UserGuidelinesAcknowledgement::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->max('version');
    }

    public function latestVersion(int $siteId): ?int
    {
        return (int)Guideline::where('site_id', $siteId)
            ->max('version');
    }

    public function getForUser(int $userId, int $siteId): ?UserGuidelinesAcknowledgement
    {
        return UserGuidelinesAcknowledgement::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->orderByDesc('version')
            ->first();
    }

    public function record(
        int $userId,
        int $siteId,
        int $version,
        ?int $guidelineId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): UserGuidelinesAcknowledgement
    {
        $ack = new UserGuidelinesAcknowledgement();
        $ack->user_id = $userId;
        $ack->site_id = $siteId;
        $ack->guideline_id = $guidelineId;
        $ack->guideline_version = $version;
        $ack->version = $version;
        $ack->acknowledged_at = date('Y-m-d H:i:s');
        $ack->accepted_at = $ack->acknowledged_at;
        $ack->accepted_ip = $ipAddress;
        $ack->accepted_user_agent = $userAgent;
        $ack->save();

        return $ack;
    }

    protected function getModelClass(): string
    {
        return UserGuidelinesAcknowledgement::class;
    }
}
