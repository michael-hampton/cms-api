<?php
// src/Repositories/EmailThemeRepository.php

namespace App\Repositories\Cms;

use App\Framework\Support\Collection;
use App\Models\EmailTheme;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class EmailThemeRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        $config = SearchConfigurationFactory::create('email-theme');
        $this->searchEngine = new SearchEngine($config);

        parent::__construct();
    }

    public function getDefaultForSite(int $siteId): ?EmailTheme
    {
        return EmailTheme::default()
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->first();
    }

    public function getActiveBySite(int $siteId): Collection
    {
        return EmailTheme::active()
            ->where('site_id', $siteId)
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getAllBySite(int $siteId): Collection
    {
        return EmailTheme::bySite($siteId)
            ->orderBy('name', 'asc')
            ->get();
    }

    public function setDefaultTheme(int $themeId, int $siteId): bool
    {
        // Unset all defaults for this site
        EmailTheme::where('site_id', $siteId)
            ->update(['is_default' => 0]);

        // Set new default
        $theme = EmailTheme::find($themeId);
        if ($theme && $theme->site_id === $siteId) {
            return $theme->update(['is_default' => true]);
        }

        return false;
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = EmailTheme::with(['colors', 'fonts', 'assets', 'settings']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function getAlternatives(int $excludeId, int $siteId): Collection
    {
        return EmailTheme::active()
            ->where('site_id', $siteId)
            ->where('id', '!=', $excludeId)
            ->orderBy('name', 'asc')
            ->get();
    }

    protected function getModelClass(): string
    {
        return EmailTheme::class;
    }
}