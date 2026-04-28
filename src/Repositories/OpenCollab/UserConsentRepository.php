<?php

namespace App\Repositories\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\UserConsent;
use App\Repositories\Repository;

/**
 * Persistence layer for user notification preferences (user_consents table).
 *
 * All queries are scoped by user_id. The consent_type_id + channel pair
 * uniquely identifies a preference row.
 */
class UserConsentRepository extends Repository
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
    public function bulkUpsert(int $userId, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $payload = array_map(fn(array $row) => [
            'user_id' => $userId,
            'consent_type_id' => $row['consent_type_id'],
            'channel' => $row['channel'],
            'is_granted' => (int)(bool)$row['granted'],
            'granted_at' => $row['granted'] ? $now : null,
            'revoked_at' => $row['granted'] ? null : $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        $updateColumns = [
            'is_granted',
            'granted_at',
            'revoked_at',
            'updated_at',
        ];

        UserConsent::upsert(
            $payload,
            ['user_id', 'consent_type_id', 'channel'],
            $updateColumns
        );
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

    /**
     * Insert default preference rows, ignoring any that already exist.
     *
     * Used during contributor onboarding to seed all types to opted-in.
     * Caller is responsible for wrapping in a transaction.
     *
     * @param array<int, array{consent_type_id: int, channel: string, granted: bool}> $defaults
     */
    public function seedDefaults(int $userId, array $defaults): void
    {
        $now = now();
        $payload = [];

        foreach ($defaults as $row) {
            $payload[] = [
                'user_id' => $userId,
                'consent_type_id' => $row['consent_type_id'],
                'channel' => $row['channel'],
                'is_granted' => $row['granted'],
                'granted_at' => $row['granted'] ? $now : null,
                'revoked_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // insertOrIgnore — existing rows survive untouched
        $this->database->table('user_consents')->insertOrIgnore($payload);
    }

    protected function getModelClass(): string
    {
        return UserConsent::class;
    }
}