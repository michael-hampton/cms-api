<?php

namespace App\Services\OpenCollab;

use App\Enums\Cms\CustomFieldContext;
use App\Enums\Cms\CustomFieldStorageType;
use App\Models\Site;
use App\Repositories\Cms\CustomFieldDefinitionRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;

/**
 * Decides whether the profile onboarding step is complete for a contributor.
 *
 * Completion rule:
 *   All active + required CustomFieldDefinition rows for the site's
 *   contributor_profile context must have a non-empty value in the
 *   contributor's profile record.
 *
 * Inactive fields and optional fields never block completion.
 * Locked fields are treated identically to any other active-required field.
 */
class ContributorProfileCompletionService
{
    public function __construct(
        private readonly ContributorProfileRepository    $profileRepository,
        private readonly CustomFieldDefinitionRepository $definitionRepository,
    ) {}

    public function isComplete(int $userId, Site $site): bool
    {
        return $this->missingFields($userId, $site) === [];
    }

    /**
     * Returns descriptors for every required field that is currently empty.
     *
     * @return list<array{key: string, name: string, type: string, description: ?string, placeholder: ?string}>
     */
    public function missingFields(int $userId, Site $site): array
    {
        $profile     = $this->profileRepository->findByUserId($userId);
        $definitions = $this->definitionRepository->activeRequiredForSiteAndContext(
            (int) $site->id,
            CustomFieldContext::ContributorProfile->value,
        );

        $missing = [];

        foreach ($definitions as $definition) {
            if (!$definition->isProfileColumnField()) {
                continue;
            }

            $column = $definition->profileColumn();

            if (!$column) {
                continue;
            }

            $value = $profile?->{$column} ?? null;

            if ($this->isEmptyValue($value)) {
                $missing[] = [
                    'key'         => $definition->key,
                    'name'        => $definition->name,
                    'type'        => $definition->type,
                    'description' => $definition->description,
                    'placeholder' => $definition->placeholder,
                ];
            }
        }

        return $missing;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}