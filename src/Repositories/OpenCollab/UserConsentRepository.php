<?php

namespace App\Repositories\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\UserConsent;

/**
 * Persistence layer for user notification preferences (user_consents table).
 *
 * All queries are scoped by user_id. The consent_type_id + channel pair
 * uniquely identifies a preference row.
 */
class UserConsentRepository
{
    /**
     * Returns all consent rows for a given user, with their consent_type relation.
     */
    public function allForUser(int $userId): Collection
    {
        return UserConsent::where('user_id', $userId)
            ->with('consentType')
            ->get();
    }

    /**
     * Returns all consent rows for a user filtered to contributor-scoped types only.
     */
    public function allContributorConsentsForUser(int $userId): Collection
    {
        return UserConsent::where('user_id', $userId)
            ->whereHas('consentType', fn($q) => $q->where('scope', 'contributor')->where('is_active', true))
            ->with('consentType')
            ->get();
    }

    /**
     * Find a single preference by (user, type, channel).
     */
    public function findByUserTypeAndChannel(int $userId, int $consentTypeId, string $channel): ?UserConsent
    {
        return UserConsent::where('user_id', $userId)
            ->where('consent_type_id', $consentTypeId)
            ->where('channel', $channel)
            ->first();
    }

    /**
     * Check whether a user has granted consent for a specific type on a channel.
     */
    public function isGranted(int $userId, string $consentTypeCode, string $channel): bool
    {
        return UserConsent::where('user_id', $userId)
            ->where('channel', $channel)
            ->where('is_granted', true)
            ->whereNull('revoked_at')
            ->whereHas('consentType', fn($q) => $q->where('code', $consentTypeCode)->where('is_active', true))
            ->exists();
    }

    /**
     * Bulk upsert — takes an array of [consent_type_id, channel, granted] tuples.
     *
     * @param array<int, array{consent_type_id: int, channel: string, granted: bool}> $preferences
     */
    public function bulkUpsert(int $userId, array $preferences): void
    {
        foreach ($preferences as $pref) {
            $this->upsert($userId, $pref['consent_type_id'], $pref['channel'], $pref['granted']);
        }
    }

    /**
     * Create or update a preference row.
     */
    public function upsert(int $userId, int $consentTypeId, string $channel, bool $granted): UserConsent
    {
        $consent = UserConsent::firstOrNew([
            'user_id' => $userId,
            'consent_type_id' => $consentTypeId,
            'channel' => $channel,
        ]);

        $consent->is_granted = $granted;
        $consent->granted_at = $granted ? now() : $consent->granted_at;
        $consent->revoked_at = $granted ? null : now();
        $consent->save();

        return $consent;
    }

    public function seedDefaults(int $userId, array $defaults)
    {
    }
}