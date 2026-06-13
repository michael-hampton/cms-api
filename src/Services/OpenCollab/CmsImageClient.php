<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ImageSearchQuery;
use App\DTO\OpenCollab\ImageUploadData;
use App\Models\Image;
use App\Repositories\Cms\ImageRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use App\Services\Cms\ImageService;

/**
 * Internal CMS image client for OpenCollab.
 *
 * Delegates to App\Services\Cms\ImageService and App\Repositories\Cms\ImageRepository
 * directly — there is no HTTP layer. The interface allows controllers and other
 * services to depend on a stable contract without knowing the underlying implementation.
 */
class CmsImageClient implements CmsImageClientInterface
{
    public function __construct(
        private readonly ImageService    $imageService,
        private readonly ImageRepository $imageRepository,
    ) {
    }

    public function search(int $siteId, ImageSearchQuery $query): PaginatedResult
    {
        $filters = array_filter([
            'site_id'       => $siteId,
            'uploaded_by'   => $query->uploadedBy,
            'image_rights'  => $query->imageRights?->value,
            'uploaded_from' => $query->uploadedFrom,
            'uploaded_to'   => $query->uploadedTo,
            'query'         => $query->search,
        ], static fn($v) => $v !== null);

        $criteria = new SearchCriteria(
            filters:     $filters,
            sortBy:      $query->sort,
            sortOrder:   $query->direction,
            page:        $query->page,
            perPage:     min($query->perPage, 100),
            searchQuery: $query->search ?? '',
        );

        return $this->imageRepository->searchForSite($criteria);
    }

    public function find(int $siteId, int $imageId): ?Image
    {
        $image = $this->imageService->getImage($imageId);

        if (!$image) {
            return null;
        }

        // Enforce site scope — never return an image belonging to another site
        if ((int) $image->site_id !== $siteId) {
            return null;
        }

        if (!(bool) $image->is_active) {
            return null;
        }

        return $image;
    }

    public function findMany(int $siteId, array $imageIds): array
    {
        if (empty($imageIds)) {
            return [];
        }

        $images = $this->imageRepository->findManyForSite($siteId, array_unique($imageIds));

        $result = [];
        foreach ($images as $image) {
            if ((bool) $image->is_active) {
                $result[(int) $image->id] = $image;
            }
        }

        return $result;
    }

    public function upload(int $siteId, ImageUploadData $data): Image
    {
        return $this->imageService->uploadImage($data->file, [
            'name'               => $data->name,
            'image_rights'       => $data->imageRights->value,
            'alt_text'           => $data->altText,
            'credit'             => $data->credit,
            'source_context'     => $data->sourceContext,
            'external_reference' => $data->externalReference,
            'site_id'            => $siteId,
        ]);
    }
}