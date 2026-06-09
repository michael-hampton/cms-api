<?php

namespace App\Services\OpenCollab;

use App\Enums\Cms\CustomFieldContext;
use App\Framework\Support\Collection;
use App\Models\CustomFieldDefinition;
use App\Models\Site;
use App\Repositories\Cms\CustomFieldDefinitionRepository;
use DomainException;

/**
 * Manages contributor profile field configuration for a site.
 *
 * Admin users may update visibility, required flags, labels, descriptions,
 * placeholders, sort order, options, and validation rules for unlocked fields.
 *
 * Invariants enforced here:
 *   - Locked fields cannot be disabled or made optional.
 *   - Inactive fields cannot be required.
 *   - Protected structural columns (key, context, storage_type, profile_column,
 *     is_locked, site_id) cannot be changed through this service.
 */
final class ContributorProfileFieldConfigService
{
    /**
     * Columns an admin may update on an unlocked definition.
     */
    private const ALLOWED_UPDATE_KEYS = [
        'name',
        'description',
        'placeholder',
        'is_active',
        'is_required',
        'sort_order',
        'options',
        'validation_rules',
    ];

    public function __construct(
        private readonly CustomFieldDefinitionRepository $definitionRepository,
    ) {}

    // ── Reads ─────────────────────────────────────────────────────────────────

    /**
     * All contributor profile field definitions for the site (active or not).
     */
    public function fieldsForSite(Site $site): Collection
    {
        return $this->definitionRepository->forSiteAndContext(
            (int) $site->id,
            CustomFieldContext::ContributorProfile->value,
        );
    }

    /**
     * Only active (visible) contributor profile field definitions for the site.
     */
    public function activeFieldsForSite(Site $site): Collection
    {
        return $this->definitionRepository->activeForSiteAndContext(
            (int) $site->id,
            CustomFieldContext::ContributorProfile->value,
        );
    }

    // ── Writes ────────────────────────────────────────────────────────────────

    /**
     * Update allowed attributes on an unlocked contributor profile field definition.
     *
     * @param array<string, mixed> $data
     *
     * @throws DomainException if the field is not found, is locked, or the
     *                         update would create an invalid state.
     */
    public function updateDefinition(Site $site, string $fieldKey, array $data): CustomFieldDefinition
    {
        $definition = $this->definitionRepository->findForSiteContextAndKey(
            (int) $site->id,
            CustomFieldContext::ContributorProfile->value,
            $fieldKey,
        );

        if (!$definition) {
            throw new DomainException("Contributor profile field [{$fieldKey}] not found for site [{$site->id}].");
        }

        if ($definition->isLocked()) {
            throw new DomainException(
                "Field [{$fieldKey}] is required by the system and cannot be changed."
            );
        }

        $isActive   = (bool) ($data['is_active']   ?? $definition->is_active);
        $isRequired = (bool) ($data['is_required'] ?? $definition->is_required);

        if (!$isActive && $isRequired) {
            throw new DomainException(
                "An inactive profile field cannot be required. Disable [{$fieldKey}] or keep it active."
            );
        }

        $safeData = array_intersect_key($data, array_flip(self::ALLOWED_UPDATE_KEYS));

        $definition->update($safeData);

        return $definition->fresh();
    }
}