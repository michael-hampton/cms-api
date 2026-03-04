<?php

namespace App\Repositories\Offers;

use App\Models\DealClick;
use App\Models\Model;
use App\Repositories\Contracts\TrackableRepository;

class DealClickRepository implements TrackableRepository
{
    public function trackClick(
        int     $productId,
        ?int    $memberId,
        int     $siteId,
        string  $action,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array   $metadata = []
    ): Model
    {
        return DealClick::create([
            'product_id' => $productId,
            'member_id' => $memberId,
            'site_id' => $siteId,
            'action' => $action,
            'channel' => $metadata['channel'] ?? 'web',
            'surface_type' => $metadata['surface_type'] ?? 'page',
            'surface_id' => $metadata['surface_id'] ?? 0,
            'deal_id' => $metadata['deal_id'] ?? null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
            'created_at' => now_datetime(),
        ]);
    }

    public function getClicks(int $productId, ?string $action = null)
    {
        $query = DealClick::where('product_id', $productId);

        if ($action) {
            $query->where('action', $action);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getClickCount(int $productId, string $action): int
    {
        return DealClick::where('product_id', $productId)
            ->where('action', $action)
            ->count();
    }

    public function getClicksByMember(int $memberId, ?string $action = null)
    {
        $query = DealClick::where('member_id', $memberId);

        if ($action) {
            $query->where('action', $action);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function hasTracked(
        int    $entityId,
        int    $memberId,
        string $action,
        string $surfaceType,
        int    $surfaceId,
    ): bool
    {
        return DealClick::where('product_id', $entityId)
            ->where('member_id', $memberId)
            ->where('action', $action)
            ->where('surface_type', $surfaceType)
            ->where('surface_id', $surfaceId)
            ->exists();
    }
}