<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\EmailTemplate;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class EmailTemplateRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        $config = SearchConfigurationFactory::create('email-template');
        $this->searchEngine = new SearchEngine($config);

        parent::__construct();
    }

    // ── Read ──────────────────────────────────────────────────

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = EmailTemplate::query();
        return $this->searchEngine->search($query, $criteria);
    }

    public function getAllBySite(int $siteId, ?string $category = null): Collection
    {
        $query = EmailTemplate::bySite($siteId)->orderBy('name', 'asc');

        if ($category !== null) {
            $query->byCategory($category);
        }

        return $query->get();
    }

    public function getActiveBySite(int $siteId, ?string $category = null): Collection
    {
        $query = EmailTemplate::bySite($siteId)->active()->orderBy('name', 'asc');

        if ($category !== null) {
            $query->byCategory($category);
        }

        return $query->get();
    }

    // ── Slug uniqueness ───────────────────────────────────────

    public function slugExistsForSite(string $slug, int $siteId, ?int $excludeId = null): bool
    {
        $query = EmailTemplate::where('slug', $slug)->where('site_id', $siteId);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    protected function getModelClass(): string
    {
        return EmailTemplate::class;
    }
}
