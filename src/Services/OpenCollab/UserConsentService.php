<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\NotificationChannel;
use App\Framework\Database\Database;
use App\Repositories\Members\Consents\ConsentTypeRepository;
use App\Repositories\OpenCollab\UserConsentRepository;

/**
 * Manages contributor notification channel preferences.
 *
 * Preferences are stored in user_consents, keyed by (user_id, consent_type_id, channel).
 * Each consent_type with scope='contributor' represents a notification event type.
 *
 * Channels: email | in_app | push
 *
 * Design decisions:
 *   - Service owns the transaction boundary for bulk saves.
 *   - Repository::bulkUpsert() uses a single DB upsert, not looped inserts.
 *   - Required consent types (is_required = true) cannot be revoked — the service
 *     enforces this and silently coerces granted=true for required types.
 *   - On first load, if a user has no stored preferences, defaults are generated
 *     from the consent type registry (default_granted = true for almost everything).
 */
class UserConsentService
{
    public function __construct(
        private readonly UserConsentRepository $userConsentRepository,
        private readonly ConsentTypeRepository $consentTypeRepository,
        private readonly Database              $database,
    )
    {
    }

    /**
     * Returns the contributor's notification preferences as a structured snapshot.
     *
     * Groups by category, then by event type, with per-channel granted status.
     * Hydrates defaults for any types not yet stored.
     *
     * @return array<string, array<int, array{
     *   consent_type_id: int,
     *   code: string,
     *   name: string,
     *   category: string,
     *   required: bool,
     *   channels: array<string, bool>
     * }>>
     */
    public function getPreferences(int $userId): array
    {
        $types = $this->consentTypeRepository->findByScope('contributor');
        $stored = $this->userConsentRepository->allContributorConsentsForUser($userId);

        // Build a lookup: [consent_type_id => [channel => is_granted]]
        $storedMap = [];
        foreach ($stored as $row) {
            $storedMap[$row->consent_type_id][$row->channel] = $row->is_granted;
        }

        $grouped = [];

        foreach ($types as $type) {
            $channels = [];
            foreach (NotificationChannel::cases() as $channel) {
                $value = $channel->value;

                // Fall back to opted-in if no stored preference exists yet
                $granted = $storedMap[$type->id][$value] ?? true;

                // Required types are always granted — enforce regardless of stored value
                $channels[$value] = $type->isRequired() ? true : $granted;
            }

            $grouped[$type->category][] = [
                'consent_type_id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'category' => $type->category,
                'required' => $type->isRequired(),
                'channels' => $channels,
            ];
        }

        return $grouped;
    }

    /**
     * Returns all contributor-scoped consent types paired with the user's
     * current preference for each type+channel combination.
     *
     * Shape: [
     *   ['type' => ConsentType, 'channel' => string, 'is_granted' => bool]
     * ]
     */
    public function preferencesForUser(int $userId): array
    {
        $consents = $this->userConsentRepository->allContributorConsentsForUser($userId);

        // Index existing rows by type+channel for O(1) lookup
        $index = [];
        foreach ($consents as $consent) {
            $index[$consent->consent_type_id . ':' . $consent->channel] = $consent;
        }

        $types = $this->consentTypeRepository->findByScope('contributor');

        $result = [];
        foreach ($types as $type) {
            foreach (['email', 'in_app'] as $channel) {
                $key = $type->id . ':' . $channel;
                $row = $index[$key] ?? null;
                $result[] = [
                    'consent_type_id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'channel' => $channel,
                    'is_granted' => $row ? $row->isActive() : true, // default ON
                ];
            }
        }

        return $result;
    }


    /**
     * Save a contributor's notification preferences in bulk.
     *
     * Input format:
     *   [
     *     ['consent_type_id' => 5, 'channel' => 'email',  'granted' => true],
     *     ['consent_type_id' => 5, 'channel' => 'in_app', 'granted' => false],
     *     ...
     *   ]
     *
     * Required consent types are silently coerced to granted=true before saving.
     * All writes are wrapped in a single transaction — either all succeed or none do.
     *
     * @param array<int, array{consent_type_id: int, channel: string, granted: bool}> $preferences
     *
     * @throws \InvalidArgumentException if any channel value is not in CHANNELS
     * @throws \RuntimeException on write failure (transaction rolls back)
     */
    public function savePreferences(int $userId, array $preferences): void
    {
        if (empty($preferences)) {
            return;
        }

        $this->validateChannels($preferences);

        // Load required type IDs once so we can coerce without N+1
        $requiredTypeIds = $this->consentTypeRepository
            ->findByScope('contributor')
            ->filter(fn($t) => $t->isRequired())
            ->pluck('id')
            ->toArray();

        // Coerce required types — they must always be granted
        $coerced = array_map(function (array $pref) use ($requiredTypeIds): array {
            if (in_array($pref['consent_type_id'], $requiredTypeIds, true)) {
                $pref['granted'] = true;
            }
            return $pref;
        }, $preferences);

        $this->database->transaction(function () use ($userId, $coerced): void {
            $this->userConsentRepository->bulkUpsert($userId, $coerced);
        });
    }

    /**
     * @param array<int, array{channel: string}> $preferences
     * @throws \InvalidArgumentException
     */
    private function validateChannels(array $preferences): void
    {
        $valid = NotificationChannel::values();

        foreach ($preferences as $pref) {
            if (!in_array($pref['channel'], $valid, true)) {
                throw new \InvalidArgumentException(
                    "Invalid channel [{$pref['channel']}]. Allowed: " . implode(', ', $valid) . '.'
                );
            }
        }
    }

    /**
     * Check whether a contributor has granted consent for a specific event + channel.
     *
     * Required types always return true without hitting the DB.
     * For optional types with no stored preference, returns the default (true).
     */
    public function hasConsent(int $userId, string $consentTypeCode, string $channel): bool
    {
        $type = $this->consentTypeRepository->findActiveByCode($consentTypeCode);

        if (!$type || $type->scope !== 'contributor') {
            return false;
        }

        if ($type->isRequired()) {
            return true;
        }

        return $this->userConsentRepository->isGranted($userId, $type->id, $channel);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Seed default (opted-in) preferences for all contributor consent types.
     *
     * Called once when a contributor completes onboarding.
     * Uses insert-or-ignore semantics — existing rows are never overwritten.
     */
    public function seedDefaultsForUser(int $userId): void
    {
        $types = $this->consentTypeRepository->findByScope('contributor');

        $defaults = [];
        foreach ($types as $type) {
            foreach (NotificationChannel::cases() as $channel) {
                $defaults[] = [
                    'consent_type_id' => $type->id,
                    'channel' => $channel->value,
                    'granted' => true,
                ];
            }
        }

        if (empty($defaults)) {
            return;
        }

        $this->database->transaction(function () use ($userId, $defaults): void {
            $this->userConsentRepository->seedDefaults($userId, $defaults);
        });
    }
}