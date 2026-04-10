<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Models\ActivityEvent;
use App\Repositories\Repository;

class ActivityRepository extends Repository
{
    public function record(
        int               $siteId,
        int               $userId,
        ActivityEventType $type,
        array             $payload = [],
    ): ActivityEvent
    {
        return $this->create([
            'site_id' => $siteId,
            'user_id' => $userId,
            'type' => $type->value,
            'payload' => json_encode($payload),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Latest activity for a contributor, newest first.
     */
    public function forContributor(int $userId, int $limit = 20): \App\Framework\Support\Collection
    {
        return ActivityEvent::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Latest activity for an entire site (admin feed).
     */
    public function forSite(int $siteId, int $limit = 50): \App\Framework\Support\Collection
    {
        return ActivityEvent::where('site_id', $siteId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    protected function getModelClass(): string
    {
        return ActivityEvent::class;
    }
}