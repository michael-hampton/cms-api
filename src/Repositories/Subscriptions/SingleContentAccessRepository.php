<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Model;
use App\Models\SingleContentAccess;
use App\Repositories\Repository;

class SingleContentAccessRepository extends Repository
{
    /**
     * Check if member has active access to content
     */
    public function hasActiveAccess(int $memberId, string $contentType, int $contentId, int $siteId): bool
    {
        $access = $this->getActiveAccess($memberId, $contentType, $contentId, $siteId);
        return $access !== null && $access->isValid();
    }

    /**
     * Get active access record
     */
    public function getActiveAccess(int $memberId, string $contentType, int $contentId, ?int $siteId = null): ?SingleContentAccess
    {
        $siteId = $siteId ?? SiteContext::getId();
        return SingleContentAccess::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->first();
    }

    /**
     * Create new access grant
     */
    public function createAccess(array $data): Model
    {
        // Generate unique token
        $data['access_token'] = SingleContentAccess::generateToken();
        $data['purchased_at'] = $data['purchased_at'] ?? date('Y-m-d H:i:s');

        // Don't override is_active if it's explicitly set to false (for pending payments)
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        return SingleContentAccess::create($data);
    }

    /**
     * Get all active access for a member
     */
    public function getMemberActiveAccess(int $memberId, int $siteId): Collection
    {
        return SingleContentAccess::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->orderBy('purchased_at', 'desc')
            ->get();
    }

    /**
     * Get expired access records
     */
    public function getExpiredAccess(int $siteId, int $limit = 100): Collection
    {
        return SingleContentAccess::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', date('Y-m-d H:i:s'))
            ->limit($limit)
            ->get();
    }

    /**
     * Cleanup expired access records by deactivating them
     */
    public function cleanupExpired(int $siteId): int
    {
        return SingleContentAccess::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', date('Y-m-d H:i:s'))
            ->update(['is_active' => 0]);
    }

    /**
     * Get access by token
     */
    public function findByToken(string $token): ?SingleContentAccess
    {
        return SingleContentAccess::findByToken($token);
    }

    /**
     * Revoke access
     */
    public function revokeAccess(int $accessId): bool
    {
        $access = $this->find($accessId);
        if (!$access) {
            return false;
        }

        return $access->revoke();
    }

    /**
     * Get content purchases statistics
     */
    public function getContentStatistics(string $contentType, int $contentId, int $siteId): array
    {
        $total = SingleContentAccess::where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('site_id', $siteId)
            ->count();

        $active = SingleContentAccess::where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->count();

        $revenue = SingleContentAccess::where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('site_id', $siteId)
            ->sum('price');

        return [
            'total_purchases' => $total,
            'active_access' => $active,
            'total_revenue' => $revenue
        ];
    }

    /**
     * Get member's purchase history
     */
    public function getMemberPurchaseHistory(int $memberId, int $siteId, int $limit = 50): Collection
    {
        return SingleContentAccess::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->orderBy('purchased_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find access by payment intent ID
     */
    public function findByPaymentIntent(string $paymentIntentId): ?SingleContentAccess
    {
        $allAccess = SingleContentAccess::where('is_active', false)
            ->whereNotNull('metadata')
            ->get();

        foreach ($allAccess as $access) {
            $metadata = is_string($access->metadata) ? json_decode($access->metadata, true) : $access->metadata;
            if (isset($metadata['payment_intent_id']) && $metadata['payment_intent_id'] === $paymentIntentId) {
                return $access;
            }
        }

        return null;
    }

    protected function getModelClass(): string
    {
        return SingleContentAccess::class;
    }
}