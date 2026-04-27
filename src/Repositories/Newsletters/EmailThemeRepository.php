<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\NewsletterBrandingConfiguration;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;

/**
 * Thin adapter — all persistence is delegated to NewsletterBrandingRepository.
 *
 * The frontend and service layer continue to call methods on this class;
 * internally every call is forwarded to the branding repository so there
 * is a single source of truth.
 */
class EmailThemeRepository extends Repository
{
    public function __construct(
        private readonly NewsletterBrandingRepository $brandingRepository,
    )
    {
        parent::__construct();
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public function getAllBySite(int $siteId): Collection
    {
        return $this->brandingRepository->getAllBySite($siteId);
    }

    public function getActiveBySite(int $siteId): Collection
    {
        return $this->brandingRepository->getActiveBySite($siteId);
    }

    public function getDefaultForSite(int $siteId): ?NewsletterBrandingConfiguration
    {
        return $this->brandingRepository->getDefaultForSite($siteId);
    }

    public function getAlternatives(int $excludeId, int $siteId): Collection
    {
        return $this->brandingRepository->getAlternatives($excludeId, $siteId);
    }

    public function findBySlug(string $slug, ?int $siteId = null): ?NewsletterBrandingConfiguration
    {
        return $this->brandingRepository->findBySlug($slug, $siteId);
    }

    public function slugExistsForSite(string $slug, int $siteId, ?int $excludeId = null): bool
    {
        return $this->brandingRepository->slugExistsForSite($slug, $siteId, $excludeId);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        return $this->brandingRepository->search($criteria);
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public function setDefaultTheme(int $themeId, int $siteId): bool
    {
        return $this->brandingRepository->setDefaultTheme($themeId, $siteId);
    }

    // ── Base Repository overrides ─────────────────────────────────────────────

    /**
     * find() is inherited from Repository and uses getModelClass() directly,
     * so it works without delegation.
     */
    protected function getModelClass(): string
    {
        return NewsletterBrandingConfiguration::class;
    }
}