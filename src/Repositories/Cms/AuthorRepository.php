<?php

namespace App\Repositories\Cms;

use App\Framework\Support\Collection;
use App\Models\Author;
use App\Models\Page;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class AuthorRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('author');
        $this->searchEngine = new SearchEngine($config);
    }

    protected function getModelClass(): string
    {
        return Author::class;
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Author::query()->withCount(['pages']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function findBySlug(string $slug): ?Author
    {
        return $this->where('slug', $slug)->first();
    }

    public function getActiveAuthors(): Collection
    {
        return Author::where('status', 'active')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function findByEmail(string $email): ?Author
    {
        return $this->where('email', $email)->first();
    }

    public function searchAuthors(string $query, ?int $limit = null): Collection
    {
        $queryBuilder = Author::where('name', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->orWhere('bio', 'LIKE', "%{$query}%")
            ->orderBy('name', 'asc');

        if ($limit) {
            $queryBuilder->limit($limit);
        }

        return $queryBuilder->get();
    }

    public function getPagesByAuthorId(int $authorId, ?int $limit = null): Collection
    {
        $query = Page::where('author_id', $authorId)
            ->orderBy('created_at', 'desc');

        if(!empty($limit)) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getAlternatives(int $excludeId): Collection
    {
        return Author::where('id', '!=', $excludeId)->get();
    }

    public function getBySiteId(int $siteId): array
    {
        $categories = Author::where('site_id', $siteId)
            ->withCount('pages', function ($query) use ($siteId) {
                $query->where('status', 'published')->where('site_id', $siteId);
            })
            ->orderBy('name', 'asc')
            ->get();


        return $categories->filter(function ($category) {
            return $category->pages_count > 0;
        })->toArray();

    }
}