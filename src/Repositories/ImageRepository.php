<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Image;
use App\Models\ImageCategory;
use App\Models\Model;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class ImageRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('image');
        $this->searchEngine = new SearchEngine($config);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Image::active()->with(['tags', 'tags.tag']);
        return $this->searchEngine->search($query, $criteria);
    }

    protected function getModelClass(): string
    {
        return Image::class;
    }

    public function getRecentImages(int $limit = 10): Collection
    {
        return Image::active()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUnusedImages(?int $olderThanDays = null): Collection
    {
        $query = Image::active()
            ->doesntHave('usage')
            ->orderBy('created_at', 'asc');

        if ($olderThanDays) {
            $date = date('Y-m-d H:i:s', strtotime("-{$olderThanDays} days"));
            $query->where('created_at', '<=', $date);
        }

        return $query->get();
    }

    public function getImageStatistics(): array
    {
        $stats = [
            'total_images' => Image::active()->count(),
            'total_size' => Image::active()->sum('file_size'),
            'avg_size' => Image::active()->avg('file_size'),
            'recent_uploads' => Image::recent(7)->where('is_active', true)->count(),
            'unused_images' => Image::active()->doesntHave('usage')->count(),
        ];

        // Format total size
        $stats['formatted_total_size'] = $this->formatBytes($stats['total_size']);
        $stats['formatted_avg_size'] = $this->formatBytes($stats['avg_size']);

        // Get mime type breakdown
        $mimeTypes = Image::active()
            ->select('mime_type')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(file_size) as total_size')
            ->groupBy('mime_type')
            ->get()
            ->map(function($item) {
                return [
                    'mime_type' => $item->mime_type,
                    'count' => (int)$item->count,
                    'total_size' => (int)$item->total_size,
                    'formatted_size' => $this->formatBytes($item->total_size)
                ];
            });

        $stats['mime_types'] = $mimeTypes;

        return $stats;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));

        return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    public function syncCategories(Image $image, array $categoryIds): array
    {
        // Get the relationship handler by calling categories with relation=true
        $relationHandler = $image->categories(true);

        // Now we can call sync on the RelationBuilder
        return $relationHandler->sync($categoryIds);
    }

    public function getCategoriesForImage(Image $image): Collection
    {
        return $image->categories();
    }

    public function syncTags(Image $image, array $tagIds): void
    {
        $image->syncTags($tagIds);
    }

    public function getTagsForImage(Image $image): Collection
    {
        return $image->tags();
    }

    public function getImage(int $id) : ?Model
    {
        return Image::with(['categories', 'tags', 'tags.tag'])->find($id);
    }
}