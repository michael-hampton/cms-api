<?php

namespace App\Services\OpenCollab;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\UploadedFile;
use App\Models\ContributorProfile;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Services\Cms\ImageUploadService;

/**
 * Manages contributor self-service profile updates:
 *   - Avatar upload / removal
 *   - Expertise topic list
 *   - Bio / display-name
 *
 * Intentionally kept separate from the onboarding flow so each concern
 * can evolve independently.
 */
class ContributorProfileService
{
    private const MAX_EXPERTISE_TAGS = 8;
    private const MAX_TAG_LENGTH = 40;
    private const AVATAR_MAX_BYTES = 2 * 1024 * 1024; // 2 MB
    private const AVATAR_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly ContributorProfileRepository $profileRepository,
        private readonly ImageUploadService           $imageUploadService,
    )
    {
    }

    // ── Avatar ────────────────────────────────────────────────────────────────

    /**
     * Upload a new avatar for the contributor.
     *
     * Replaces any existing avatar file on disk and updates the DB record.
     * Returns the new relative URL.
     *
     * @throws ValidationException
     */
    public function uploadAvatar(int $userId, int $siteId, UploadedFile $file): string
    {
        $this->validateAvatarFile($file);

        $profile = $this->profileRepository->findByUserId($userId);

        $oldPath = $profile?->avatar ?? null;

        // Use the shared upload service so file handling is consistent everywhere
        $relativePath = $this->imageUploadService->uploadToPath(
            $file,
            'open-collab/avatars',
            $oldPath,
        );

        $url = str_starts_with($relativePath, '/uploads/')
            ? '/' . ltrim($relativePath, '/')
            : '/uploads/' . ltrim($relativePath, '/');

        if ($profile) {
            $this->profileRepository->update($profile->id, ['avatar' => $url]);
        } else {
            $this->profileRepository->createForUser($userId, $siteId, ['avatar' => $url]);
        }

        return $url;
    }

    /**
     * @throws ValidationException
     */
    private function validateAvatarFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new ValidationException('Invalid file upload.');
        }

        if (!in_array($file->getMimeType(), self::AVATAR_MIME_TYPES, true)) {
            throw new ValidationException('Only JPG, PNG, and WebP images are accepted.');
        }

        if ($file->getSize() > self::AVATAR_MAX_BYTES) {
            throw new ValidationException('Avatar image must be under 2 MB.');
        }
    }

    // ── Expertise ─────────────────────────────────────────────────────────────

    /**
     * Remove the avatar for the contributor (sets DB field to null, deletes file).
     */
    public function removeAvatar(int $userId, int $siteId): void
    {
        $profile = $this->profileRepository->findByUserAndSite($userId, $siteId);

        if (!$profile || empty($profile->avatar)) {
            return;
        }

        $this->imageUploadService->delete(ltrim($profile->avatar, '/'));
        $this->profileRepository->update($profile->id, ['avatar' => null]);
    }

    // ── Profile (bio / avatar URL) ────────────────────────────────────────────

    /**
     * Persist the contributor's expertise tag list.
     *
     * @param string[] $tags Already-trimmed tag strings from the request.
     * @throws ValidationException
     */
    public function saveExpertise(int $userId, int $siteId, array $tags): ContributorProfile
    {
        $tags = $this->validateAndNormaliseTags($tags);

        $profile = $this->profileRepository->findByUserId($userId);

        if ($profile) {
            $this->profileRepository->update($profile->id, ['expertise' => implode(',', $tags)]);

            return $profile->fresh();
        }

        return $this->profileRepository->createForUser($userId, $siteId, [
            'expertise' => implode(',', $tags),
        ]);
    }

    // ── Validation helpers ────────────────────────────────────────────────────

    /**
     * @param string[] $tags
     * @return string[]
     * @throws ValidationException
     */
    private function validateAndNormaliseTags(array $tags): array
    {
        $normalised = [];

        foreach ($tags as $raw) {
            $tag = trim((string)$raw);
            if ($tag === '') {
                continue;
            }
            if (mb_strlen($tag) > self::MAX_TAG_LENGTH) {
                throw new ValidationException("Tag \"{$tag}\" exceeds the maximum length of " . self::MAX_TAG_LENGTH . ' characters.');
            }
            $normalised[] = $tag;
        }

        $normalised = array_values(array_unique($normalised));

        if (count($normalised) > self::MAX_EXPERTISE_TAGS) {
            throw new ValidationException('You may specify a maximum of ' . self::MAX_EXPERTISE_TAGS . ' expertise topics.');
        }

        return $normalised;
    }

    /**
     * Update profile fields that the contributor can self-edit: bio and avatar.
     *
     * $data keys:
     *   bio    (string|null)
     *   avatar (string|null|'')  — '' means "remove existing avatar"
     *
     * @throws ValidationException
     */
    public function updateProfile(int $userId, int $siteId, array $data): ContributorProfile
    {
        $fields = [];

        if (array_key_exists('bio', $data)) {
            $bio = $data['bio'];
            if ($bio !== null && mb_strlen($bio) > 1000) {
                throw new ValidationException('Bio must be 1000 characters or fewer.');
            }
            $fields['bio'] = $bio;
        }

        if (array_key_exists('avatar', $data)) {
            $avatar = $data['avatar'];

            if ($avatar === '' || $avatar === null) {
                // Explicit removal — delete file if we have a path
                $existing = $this->profileRepository->findByUserAndSite($userId, $siteId);
                if ($existing?->avatar) {
                    $this->imageUploadService->delete(ltrim($existing->avatar, '/'));
                }
                $fields['avatar'] = null;
            } else {
                // A URL returned from a prior uploadAvatar() call
                $fields['avatar'] = $avatar;
            }
        }

        if (empty($fields)) {
            // Nothing to update; return or create the profile as-is
            return $this->profileRepository->findByUserAndSite($userId, $siteId)
                ?? $this->profileRepository->createForUser($userId, $siteId, []);
        }

        $profile = $this->profileRepository->findByUserAndSite($userId, $siteId);

        if ($profile) {
            $this->profileRepository->update($profile->id, $fields);

            return $profile->fresh();
        }

        return $this->profileRepository->createForUser($userId, $siteId, $fields);
    }
}