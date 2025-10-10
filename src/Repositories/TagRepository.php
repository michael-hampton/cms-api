<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Model;
use App\Models\PageCategory;
use App\Models\PageTag;
use App\Models\Tag;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class TagRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::createTagConfiguration();
        $this->searchEngine = new SearchEngine($config);
    }

    protected function getModelClass(): string
    {
        return Tag::class;
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Tag::query();
        return $this->searchEngine->search($query, $criteria);
    }

    public function findBySlug(string $slug): ?Tag
    {
        $query = Tag::bySlug($slug);
        return $this->applySiteFilter($query)->first();
    }

    public function findOrCreateByName(string $name, int $siteId): Model
    {
        $slug = Str::slug($name, [$this, 'findBySlug']);
        $existing = $this->findBySlug($slug);

        if ($existing) {
            $existing->incrementUsage();
            return $existing;
        }

        return $this->create([
            'name' => $name,
            'slug' => $slug,
            'usage_count' => 1
        ]);
    }

    public function getPopularTags(int $limit = 20, string $searchQuery = ''): Collection
    {
        $query = Tag::popular($limit);

        if ($searchQuery) {
            $query->where('name', 'LIKE', "%{$searchQuery}%");
        }

        return $this->applySiteFilter($query)->get();
    }

    public function getFeaturedTags(): Collection
    {
        return Tag::featured()->get();
    }

    public function searchTags(string $searchQuery = '', int $limit = 10): Collection
    {
        $query = Tag::orderBy('usage_count', 'desc')
            ->orderBy('name', 'asc')
            ->limit($limit);

        if ($searchQuery) {
            $query->where('name', 'LIKE', "%{$searchQuery}%");
        }

        return $query->get();
    }

    public function getTagCloud(int $limit = 50): Collection
    {
        $models = $this->where('usage_count', '>', 0)
            ->orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get();

        return $this->addRelativeSizes($models);
    }

    public function cleanupUnusedTags(): int
    {
        return $this->database->delete('tags', ['usage_count' => 0]);
    }

    public function mergeTags(int $fromTagId, int $toTagId): bool
    {
        return $this->database->transaction(function () use ($fromTagId, $toTagId) {
            // Update all references to point to the target tag
            $this->database->update(
                'page_tags',
                ['tag_id' => $toTagId],
                ['tag_id' => $fromTagId]
            );

            // Update usage count
            $fromTag = $this->find($fromTagId);
            $toTag = $this->find($toTagId);

            if ($fromTag && $toTag) {
                $toTag->usage_count += $fromTag->usage_count;
                $toTag->save();

                $fromTag->delete();
            }

            return true;
        });
    }

    private function addRelativeSizes(Collection $tags): Collection
    {
        $maxCount = 0;

        // Find maximum usage count
        foreach ($tags as $tag) {
            if ($tag->usage_count > $maxCount) {
                $maxCount = $tag->usage_count;
            }
        }

        // Calculate relative size for tag cloud
        foreach ($tags as $tag) {
            $tag->relative_size = $maxCount > 0 ? ($tag->usage_count / $maxCount) * 100 : 0;
        }

        return $tags;
    }

    public function getAlternatives(int $excludeId): Collection
    {
        return Tag::where('id', '!=', $excludeId)->get();
    }

    public function getPagesByTagId(int $tagId, ?int $limit = null): Collection
    {
        $query = PageTag::where('tag_id', $tagId)
            ->orderBy('created_at', 'desc');

        if(!empty($limit)) {
            $query->limit($limit);
        }

        return $query->get();
    }
}