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
        $config = SearchConfigurationFactory::createImageConfiguration();
        $this->searchEngine = new SearchEngine($config);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Image::active();
        return $this->searchEngine->search($query, $criteria);
    }

    protected function getModelClass(): string
    {
        return Image::class;
    }

    public function findByFilename(string $filename): ?Image
    {
        return Image::where('filename', $filename)->first();
    }

    public function getActiveImages(): Collection
    {
        return Image::active()->orderBy('created_at', 'desc')->get();
    }

    public function getRecentImages(int $limit = 10): Collection
    {
        return Image::active()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getImagesByCategory(int $categoryId, ?int $limit = null): Collection
    {
        $query = Image::active()
            ->whereHas('categories', function($q) use ($categoryId) {
                $q->where('image_categories.id', $categoryId);
            })
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
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

    public function getImagesByUsage(string $usableType, int $usableId): Collection
    {
        return Image::active()
            ->whereHas('usage', function($q) use ($usableType, $usableId) {
                $q->where('usable_type', $usableType)
                    ->where('usable_id', $usableId);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getImageStatistics(): array
    {
        $stats = [
            'total_images' => Image::active()->count(),
            'total_size' => Image::active()->sum('file_size'),
            'avg_size' => Image::active()->avg('file_size'),
            'recent_uploads' => Image::active()->recent(7)->count(),
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

    public function bulkUpdateMetadata(array $imageIds, array $metadata): int
    {
        $allowedFields = ['alt_text', 'caption', 'description'];
        $updateData = array_intersect_key($metadata, array_flip($allowedFields));

        if (empty($updateData) || empty($imageIds)) {
            return 0;
        }

        return Image::whereIn('id', $imageIds)->update($updateData);
    }

    public function bulkDelete(array $imageIds, bool $hardDelete = false): int
    {
        if (empty($imageIds)) {
            return 0;
        }

        if ($hardDelete) {
            return Image::whereIn('id', $imageIds)->delete();
        } else {
            return Image::whereIn('id', $imageIds)->update(['is_active' => false]);
        }
    }

    public function getDuplicateImages(): Collection
    {
        // Find images with same file_size and similar names
        return Image::active()
            ->select('file_size', 'original_name')
            ->selectRaw('COUNT(*) as duplicate_count')
            ->selectRaw('GROUP_CONCAT(id) as image_ids')
            ->groupBy('file_size', 'original_name')
            ->havingRaw('COUNT(*) > 1')
            ->get();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));

        return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    public function syncCategories(Image $image, array $categoryIds): void
    {
        $validCategories = ImageCategory::active()->whereIn('id', $categoryIds)->get();
        $validIds = $validCategories->pluck('id')->toArray();

        $image->categories()->sync($validIds);
    }

    public function getCategoriesForImage(Image $image): Collection
    {
        return $image->categories();
    }
}