<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ImageEvidenceData;
use App\DTO\OpenCollab\ImageSearchQuery;
use App\DTO\OpenCollab\ImageUploadData;
use App\Models\Image;
use App\Models\Site;
use App\Search\PaginatedResult;
use App\Services\OpenCollab\Policies\ContributorImagePolicyInterface;

/**
 * Orchestrates contributor image library operations.
 *
 * Responsibilities:
 *  - Enforce contributor image policy before any CMS call
 *  - Delegate search and upload to CmsImageClientInterface
 *  - Record upload evidence after successful CMS upload
 *  - Never expose raw Image objects without a policy check
 */
class ImageLibraryService
{
    public function __construct(
        private readonly CmsImageClientInterface                $cmsImageClient,
        private readonly ContributorImagePolicyInterface        $imagePolicy,
        private readonly ImageSubmissionEvidenceServiceInterface $evidenceService,
    ) {
    }

    /**
     * @throws \App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException
     */
    public function search(int $userId, Site $site, ImageSearchQuery $query): PaginatedResult
    {
        if (!$this->imagePolicy->canBrowse($userId, $site)) {
            throw new \App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException();
        }

        // Always scope search to images uploaded by this contributor
        // unless they have broader access (checked inside the client via policy)
        $scopedQuery = new ImageSearchQuery(
            page:         $query->page,
            perPage:      $query->perPage,
            search:       $query->search,
            uploadedBy:   $query->uploadedBy ?? $userId,
            imageRights:  $query->imageRights,
            uploadedFrom: $query->uploadedFrom,
            uploadedTo:   $query->uploadedTo,
            sort:         $query->sort,
            direction:    $query->direction,
        );

        return $this->cmsImageClient->search((int) $site->id, $scopedQuery);
    }

    /**
     * @throws \App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException
     */
    public function findForContributor(int $userId, Site $site, int $imageId): ?Image
    {
        if (!$this->imagePolicy->canBrowse($userId, $site)) {
            throw new \App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException();
        }

        $image = $this->cmsImageClient->find((int) $site->id, $imageId);

        if ($image === null) {
            return null;
        }

        if (!$this->imagePolicy->canUse($userId, $site, $image)) {
            return null; // Return null not 403 — caller decides presentation
        }

        return $image;
    }

    /**
     * Upload a new image, record evidence, and return the created Image.
     *
     * Evidence is recorded only after a successful CMS upload.
     * If evidence recording fails the upload still succeeds — the failure is
     * logged but not propagated (the image exists in CMS).
     *
     * @throws \App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException
     * @throws \App\Framework\Exceptions\ValidationException  on invalid file/metadata
     */
    public function upload(
        int             $userId,
        Site            $site,
        ImageUploadData $uploadData,
        ImageEvidenceData $evidenceData,
    ): Image {
        if (!$this->imagePolicy->canUpload($userId, $site)) {
            throw new \App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException();
        }

        $image = $this->cmsImageClient->upload((int) $site->id, $uploadData);

        // Record evidence — non-critical to the upload succeeding but important for compliance.
        // Failure here must not roll back the already-created CMS image.
        try {
            $this->evidenceService->record($evidenceData);
        } catch (\Throwable $e) {
            error_log('ImageLibraryService: evidence recording failed for image ' . $image->id . ': ' . $e->getMessage());
        }

        return $image;
    }

    /**
     * Batch-resolve images for article editor loading (Ticket 10).
     * Missing or inaccessible IDs silently produce null entries.
     *
     * @param  int[] $imageIds
     * @return array<int, Image|null>
     */
    public function resolveForEditor(int $userId, Site $site, array $imageIds): array
    {
        if (empty($imageIds)) {
            return [];
        }

        $images = $this->cmsImageClient->findMany((int) $site->id, $imageIds);

        $result = [];
        foreach ($imageIds as $id) {
            $image = $images[$id] ?? null;
            if ($image !== null && !$this->imagePolicy->canUse($userId, $site, $image)) {
                $image = null;
            }
            $result[$id] = $image;
        }

        return $result;
    }
}