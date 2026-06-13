<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ImageSearchQuery;
use App\DTO\OpenCollab\ImageUploadData;
use App\Models\Image;
use App\Search\PaginatedResult;

/**
 * Integration boundary between OpenCollab and the CMS image system.
 *
 * Implementations call App\Services\Cms\ImageService directly — there is no
 * HTTP boundary. The interface exists so controllers and services depend on a
 * stable contract, and so tests can inject a mock without touching the real
 * ImageService.
 */
interface CmsImageClientInterface
{
    /**
     * Search images for a site, filtered to what the contributor may see.
     */
    public function search(int $siteId, ImageSearchQuery $query): PaginatedResult;

    /**
     * Find a single image by ID, scoped to the site. Returns null when not found.
     */
    public function find(int $siteId, int $imageId): ?Image;

    /**
     * Batch-find images by IDs, scoped to the site.
     * Missing or inaccessible IDs are omitted — callers handle partial results.
     *
     * @param  int[] $imageIds
     * @return array<int, Image>  keyed by image ID
     */
    public function findMany(int $siteId, array $imageIds): array;

    /**
     * Upload a new image through the CMS image service.
     */
    public function upload(int $siteId, ImageUploadData $data): Image;
}