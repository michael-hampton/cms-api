<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Video;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;

class VideoRepository
{
    public function create(array $data): Model
    {
        return Video::create($data);
    }

    public function find(int $id): ?Model
    {
        return Video::find($id);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Video::query();

        // Apply search
        if ($criteria->getSearchQuery()) {
            $searchTerm = $criteria->getSearchQuery();
            $query->where(function ($q) use ($searchTerm) {
                $q->where('original_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('title', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('filename', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Apply filters
        foreach ($criteria->getFilters() as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        // Exclude soft deleted
        $query->whereNull('deleted_at');

        // Count total before pagination
        $total = $query->count();

        // Apply sorting
        if (!empty($criteria->getSortBy()) && !empty($criteria->getSortOrder())) {
            $query->orderBy(
                $criteria->getSortBy(),
                $criteria->getSortOrder()
            );
        }

        // Apply pagination
        $offset = ($criteria->getPage() - 1) * $criteria->getPerPage();
        $query->limit($criteria->getPerPage())->offset($offset);

        $videos = $query->get();

        return new PaginatedResult(
            data: $videos->toArray(),
            total: $total,
            page: $criteria->getPage(),
            perPage: $criteria->getPerPage()
        );
    }

    public function getRecentVideos(int $limit = 10): Collection
    {
        return Video::query()
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}