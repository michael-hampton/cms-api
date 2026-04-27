<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\NewsletterBrandingConfiguration;
use App\Models\NewsletterBrandingVersion;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class NewsletterBrandingRepository extends Repository
{
    protected function getModelClass(): string
    {
        return NewsletterBrandingConfiguration::class;
    }

    // =========================================================================
    // Site-scoped theme library (email_template type)
    // =========================================================================

    /**
     * All email-template branding configs for a site, ordered by name.
     */
    public function getAllBySite(int $siteId): Collection
    {
        return NewsletterBrandingConfiguration::emailTemplates()
            ->bySite($siteId)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Active email-template configs for a site.
     */
    public function getActiveBySite(int $siteId): Collection
    {
        return NewsletterBrandingConfiguration::emailTemplates()
            ->bySite($siteId)
            ->active()
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * The default email-template config for a site.
     */
    public function getDefaultForSite(int $siteId): ?NewsletterBrandingConfiguration
    {
        return NewsletterBrandingConfiguration::emailTemplates()
            ->bySite($siteId)
            ->active()
            ->default()
            ->first();
    }

    /**
     * Active configs for a site excluding a given id (used for "alternatives").
     */
    public function getAlternatives(int $excludeId, int $siteId): Collection
    {
        return NewsletterBrandingConfiguration::emailTemplates()
            ->bySite($siteId)
            ->active()
            ->where('id', '!=', $excludeId)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Find by slug within a site.
     */
    public function findBySlug(string $slug, ?int $siteId = null): ?NewsletterBrandingConfiguration
    {
        return NewsletterBrandingConfiguration::emailTemplates()
            ->bySite($siteId)
            ->bySlug($slug)
            ->first();
    }

    /**
     * Returns true when a slug is already in use within a site (for uniqueness checks).
     */
    public function slugExistsForSite(string $slug, int $siteId, ?int $excludeId = null): bool
    {
        $query = NewsletterBrandingConfiguration::emailTemplates()
            ->bySite($siteId)
            ->bySlug($slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Set the default for a site: clears existing default, then sets the new one.
     * Returns false when the target record does not belong to the site.
     */
    public function setDefaultTheme(int $id, int $siteId): bool
    {
        // Clear current defaults for this site
        NewsletterBrandingConfiguration::emailTemplates()
            ->bySite($siteId)
            ->update(['is_default' => 0]);

        $record = NewsletterBrandingConfiguration::find($id);

        if (!$record || $record->site_id !== $siteId) {
            return false;
        }

        return (bool)$record->update(['is_default' => true]);
    }

    /**
     * Paginated search over email-template configs.
     */
    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $config = SearchConfigurationFactory::create('email-theme');
        $engine = new SearchEngine($config);

        $query = NewsletterBrandingConfiguration::emailTemplates();

        return $engine->search($query, $criteria);
    }

    // =========================================================================
    // Per-newsletter branding (newsletter type)
    // =========================================================================

    public function findByNewsletterId(int $newsletterId): ?NewsletterBrandingConfiguration
    {
        return NewsletterBrandingConfiguration::where('newsletter_id', $newsletterId)->first();
    }

    public function upsertForNewsletter(int $newsletterId, array $brandingData): Model
    {
        $existing = $this->findByNewsletterId($newsletterId);

        if ($existing) {
            foreach ($brandingData as $key => $value) {
                $existing->$key = $value;
            }
            $existing->save();
            return $existing->fresh();
        }

        return NewsletterBrandingConfiguration::create(array_merge(
            [
                'newsletter_id' => $newsletterId,
                'type' => NewsletterBrandingConfiguration::TYPE_NEWSLETTER,
            ],
            $brandingData
        ));
    }

    // =========================================================================
    // Versioning (shared by both types)
    // =========================================================================

    public function createVersion(int $brandingConfigId, array $snapshot): Model
    {
        $nextNumber = $this->nextVersionNumber($brandingConfigId);

        return NewsletterBrandingVersion::create([
            'branding_config_id' => $brandingConfigId,
            'version_number' => $nextNumber,
            'branding_json_snapshot' => $snapshot,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function nextVersionNumber(int $brandingConfigId): int
    {
        $latest = NewsletterBrandingVersion::where('branding_config_id', $brandingConfigId)
            ->orderBy('version_number', 'desc')
            ->first();

        return $latest ? ($latest->version_number + 1) : 1;
    }

    public function findVersion(int $brandingConfigId, int $versionNumber): ?NewsletterBrandingVersion
    {
        return NewsletterBrandingVersion::where('branding_config_id', $brandingConfigId)
            ->where('version_number', $versionNumber)
            ->first();
    }

    public function findVersionById(int $versionId): ?Model
    {
        return NewsletterBrandingVersion::find($versionId);
    }

    public function versionHistory(int $brandingConfigId): Collection
    {
        return NewsletterBrandingVersion::where('branding_config_id', $brandingConfigId)
            ->orderBy('version_number', 'desc')
            ->get();
    }
}