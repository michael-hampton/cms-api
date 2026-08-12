<?php

namespace App\Repositories\Cms;

use App\Framework\Support\Collection;
use App\Models\Author;
use App\Models\Model;
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
        $query = Page::query()
            ->join('page_authors', 'pages.id', '=', 'page_authors.page_id')
            ->where('page_authors.author_id', $authorId)
            ->select('pages.*')
            ->orderBy('pages.created_at', 'desc');

        if (!empty($limit)) {
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
        $authors = Author::where('site_id', $siteId)
            ->withCount('pages')
            ->orderBy('name', 'asc')
            ->get();

        return $authors
            ->filter(static fn(Author $author): bool => (int)$author->pages_count > 0)
            ->toArray();
    }

    public function findOrCreateFromUser(object $user, int $siteId): Model
    {
        $author = $this->findByEmail($user->email);

        if ($author) {
            return $author;
        }

        return Author::create([
            'name' => $user->name ?? $user->email,
            'email' => $user->email,
            'slug' => $this->generateUniqueSlug($user->name ?? $user->email),
            'site_id' => $siteId,
            'status' => 'active',
            'bio' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = strtolower($name);
        $baseSlug = preg_replace('/[^a-z0-9]+/', '-', $baseSlug);
        $baseSlug = trim($baseSlug, '-');

        $slug = $baseSlug;
        $counter = 1;

        while ($this->where('slug', $slug)->first()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function isSlugTaken(string $slug): bool
    {
        return Author::where('slug', $slug)->exists();
    }
}
