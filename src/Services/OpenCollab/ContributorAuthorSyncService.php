<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\AuthorSyncActorType;
use App\Enums\OpenCollab\AuthorSyncAuditEvent;
use App\Enums\OpenCollab\AuthorSyncStatus;
use App\Framework\Database\Database;
use App\Framework\Support\Str;
use App\Models\Author;
use App\Models\ContributorProfile;
use App\Models\Model;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ContributorAuthorSyncAuditRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use InvalidArgumentException;

class ContributorAuthorSyncService
{
    private const FIELD_MAP = [
        'display_name' => 'name',
        'bio' => 'bio',
        'avatar' => 'avatar',
        'portfolio_url' => 'website',
        'linkedin_url' => 'linkedin',
        'twitter_url' => 'twitter',
        'instagram_url' => 'instagram',
        'tiktok_url' => 'tiktok',
        'expertise' => 'expertise',
    ];

    public function __construct(
        private readonly ContributorProfileRepository $profileRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly ContributorAuthorSyncAuditRepository $auditRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly Database $database,
    ) {
    }

    /**
     * Create or return the linked Author record for an approved contributor.
     *
     * 2-3 writes (create-or-nothing, profile update, audit log) wrapped in
     * a transaction so a failure partway through can't leave the profile
     * pointing at an author_id without a matching audit entry.
     */
    public function ensureAuthorForProfile(
        ContributorProfile $profile,
        int $siteId,
        AuthorSyncActorType $actorType = AuthorSyncActorType::System,
        ?int $actorId = null,
    ): ?Author {
        if (!$this->isApprovedForSync($profile)) {
            return null;
        }

        if (!empty($profile->author_id)) {
            return $this->authorRepository->find((int)$profile->author_id);
        }

        $user = $this->userRepository->find((int)$profile->user_id);
        $email = $user?->email;
        $author = $email ? $this->authorRepository->findByEmail($email) : null;

        return $this->database->transaction(function () use ($profile, $siteId, $actorType, $actorId, $user, $author): Author {
            $event = AuthorSyncAuditEvent::AuthorLinked;

            if (!$author) {
                $author = $this->authorRepository->create($this->initialAuthorData($profile, $siteId, $user));
                $event = AuthorSyncAuditEvent::AuthorCreated;
            }

            $this->profileRepository->update((int)$profile->id, [
                'author_id' => $author->id,
                'author_sync_status' => AuthorSyncStatus::Created->value,
                'author_last_synced_at' => date('Y-m-d H:i:s'),
                'author_last_synced_by' => $actorId,
            ]);

            $this->auditRepository->log(
                (int)$profile->id,
                (int)$author->id,
                $siteId,
                $actorType->value,
                $actorId,
                $event->value,
                array_values(self::FIELD_MAP),
            );
            return $author;
        });
    }

    /**
     * Sync contributor-owned public fields to the linked Author.
     *
     * @param string[]|null $changedProfileFields
     */
    public function syncProfileToAuthor(
        ContributorProfile $profile,
        int $siteId,
        AuthorSyncActorType $actorType = AuthorSyncActorType::Contributor,
        ?int $actorId = null,
        ?array $changedProfileFields = null,
    ): ?Author {
        $author = $this->ensureAuthorForProfile($profile, $siteId, $actorType, $actorId);

        if (!$author) {
            return null;
        }

        $changedProfileFields = $changedProfileFields !== null
            ? array_values(array_intersect($changedProfileFields, array_keys(self::FIELD_MAP)))
            : array_keys(self::FIELD_MAP);

        if (empty($changedProfileFields)) {
            return $author;
        }

        $overridden = $this->normaliseOverriddenFields($author->overridden_fields);
        $updates = [];
        $skipped = [];

        foreach ($changedProfileFields as $profileField) {
            $authorField = self::FIELD_MAP[$profileField];

            if (array_key_exists($authorField, $overridden)) {
                $skipped[] = $authorField;
                continue;
            }

            $updates[$authorField] = $this->profileValueForAuthor($profile, $profileField);
        }

        return $this->database->transaction(function () use ($profile, $siteId, $actorType, $actorId, $author, $updates, $skipped): Author {
            if (!empty($updates)) {
                $updates['last_updated_by_type'] = AuthorSyncActorType::Contributor->value;
                $updates['last_updated_by_id'] = $actorId;
                $author = $this->authorRepository->update((int)$author->id, $updates) ?? $author;

                $this->profileRepository->update((int)$profile->id, [
                    'author_sync_status' => empty($skipped)
                        ? AuthorSyncStatus::Synced->value
                        : AuthorSyncStatus::PartiallySynced->value,
                    'author_last_synced_at' => date('Y-m-d H:i:s'),
                    'author_last_synced_by' => $actorId,
                ]);
            }

            $this->auditRepository->log(
                (int)$profile->id,
                (int)$author->id,
                $siteId,
                $actorType->value,
                $actorId,
                empty($updates) ? AuthorSyncAuditEvent::SyncSkipped->value : AuthorSyncAuditEvent::ProfileSynced->value,
                array_keys($updates),
                ['skipped_overridden_fields' => $skipped],
            );

            return $author;
        });
    }

    /**
     * Mark directly edited Author public fields as administrator overrides.
     */
    public function recordAdminAuthorUpdate(
        Author $author,
        array $submittedData,
        ?int $adminId,
    ): Author {
        $overrideFields = $this->adminOverridableFields($submittedData);
        $overridden = $this->normaliseOverriddenFields($author->overridden_fields);

        if (empty($overrideFields)) {
            return $this->authorRepository->update((int)$author->id, [
                'last_updated_by_type' => AuthorSyncActorType::Admin->value,
                'last_updated_by_id' => $adminId,
            ]) ?? $author;
        }

        foreach ($overrideFields as $field) {
            $overridden[$field] = [
                'by_type' => AuthorSyncActorType::Admin->value,
                'by_id' => $adminId,
                'at' => date('Y-m-d H:i:s'),
            ];
        }

        return $this->database->transaction(function () use ($author, $overridden, $adminId, $overrideFields): Author {
            $updated = $this->authorRepository->update((int)$author->id, [
                'overridden_fields' => $overridden,
                'last_updated_by_type' => AuthorSyncActorType::Admin->value,
                'last_updated_by_id' => $adminId,
            ]);

            $profile = $this->profileRepository->findByAuthorId((int)$author->id);

            $this->auditRepository->log(
                $profile?->id,
                (int)$author->id,
                $author->site_id ? (int)$author->site_id : null,
                AuthorSyncActorType::Admin->value,
                $adminId,
                AuthorSyncAuditEvent::AdminOverride->value,
                $overrideFields,
            );

            return $updated ?? $author;
        });
    }

    public function overriddenFields(int $authorId): array
    {
        $author = $this->authorRepository->find($authorId);

        if (!$author) {
            throw new InvalidArgumentException('Author not found');
        }

        return $this->normaliseOverriddenFields($author->overridden_fields);
    }

    public function removeOverride(int $authorId, string $field, ?int $adminId): Author
    {
        $author = $this->authorRepository->find($authorId);

        if (!$author) {
            throw new InvalidArgumentException('Author not found');
        }

        $field = $this->normaliseAuthorField($field);
        $overridden = $this->normaliseOverriddenFields($author->overridden_fields);
        unset($overridden[$field]);

        $updates = [
            'overridden_fields' => $overridden,
            'last_updated_by_type' => AuthorSyncActorType::Admin->value,
            'last_updated_by_id' => $adminId,
        ];

        $profile = $this->profileRepository->findByAuthorId($authorId);
        $profileField = array_search($field, self::FIELD_MAP, true);

        if ($profile && $profileField !== false) {
            $updates[$field] = $this->profileValueForAuthor($profile, $profileField);
        }

        return $this->database->transaction(function () use ($author, $authorId, $updates, $profile, $field, $adminId): Author {
            $updated = $this->authorRepository->update($authorId, $updates);

            $this->auditRepository->log(
                $profile?->id,
                $authorId,
                $author->site_id ? (int)$author->site_id : null,
                AuthorSyncActorType::Admin->value,
                $adminId,
                AuthorSyncAuditEvent::OverrideRemoved->value,
                [$field],
            );

            return $updated ?? $author;
        });
    }

    /**
     * @return array<string, string>
     */
    public function syncableProfileFieldsFrom(array $fields): array
    {
        return array_values(array_intersect($fields, array_keys(self::FIELD_MAP)));
    }

    private function initialAuthorData(ContributorProfile $profile, int $siteId, ?Model $user): array
    {
        $name = trim((string)($profile->display_name ?: $user?->name ?: $user?->email ?: 'Contributor'));

        return array_merge($this->publicAuthorFieldsFromProfile($profile), [
            'name' => $name,
            'email' => $user?->email,
            'slug' => Str::slug($name, [$this->authorRepository, 'findBySlug']),
            'site_id' => $siteId,
            'status' => 'active',
            'overridden_fields' => [],
            'last_updated_by_type' => AuthorSyncActorType::Contributor->value,
            'last_updated_by_id' => $profile->user_id ? (int)$profile->user_id : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function publicAuthorFieldsFromProfile(ContributorProfile $profile): array
    {
        $fields = [];

        foreach (self::FIELD_MAP as $profileField => $authorField) {
            $fields[$authorField] = $this->profileValueForAuthor($profile, $profileField);
        }

        return $fields;
    }

    private function profileValueForAuthor(ContributorProfile $profile, string $profileField): mixed
    {
        $value = $profile->{$profileField};

        if ($profileField === 'display_name') {
            return trim((string)$value) ?: 'Contributor';
        }

        return $value;
    }

    private function isApprovedForSync(ContributorProfile $profile): bool
    {
        if (empty($profile->account_status)) {
            return true;
        }

        return in_array($profile->account_status, ['approved', 'active'], true);
    }

    private function normaliseOverriddenFields(mixed $fields): array
    {
        if (is_string($fields)) {
            $decoded = json_decode($fields, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($fields) ? $fields : [];
    }

    /**
     * @return string[]
     */
    private function adminOverridableFields(array $submittedData): array
    {
        $publicFields = array_merge(array_values(self::FIELD_MAP), ['slug']);

        return array_values(array_intersect(array_keys($submittedData), $publicFields));
    }

    private function normaliseAuthorField(string $field): string
    {
        if (array_key_exists($field, self::FIELD_MAP)) {
            return self::FIELD_MAP[$field];
        }

        if (!in_array($field, array_merge(array_values(self::FIELD_MAP), ['slug']), true)) {
            throw new InvalidArgumentException("Field [{$field}] cannot be synchronised.");
        }

        return $field;
    }
}
