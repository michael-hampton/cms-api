<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ImageEvidenceData;
use App\DTO\OpenCollab\ImageSearchQuery;
use App\DTO\OpenCollab\ImageUploadData;
use App\Models\Image;
use App\Models\Site;
use App\Search\PaginatedResult;
use App\Services\OpenCollab\Policies\ContributorImagePolicyInterface;
use App\Services\OpenCollab\Risk\CreatorDeclarationRiskService;
use App\Services\OpenCollab\Risk\ImageMetadataRiskService;

class ImageLibraryService
{
    public function __construct(
        private readonly CmsImageClientInterface                  $cmsImageClient,
        private readonly ContributorImagePolicyInterface          $imagePolicy,
        private readonly ImageSubmissionEvidenceServiceInterface $evidenceService,
        private readonly CreatorDeclarationRiskService            $creatorDeclarationRiskService,
        private readonly ImageMetadataRiskService                 $imageMetadataRiskService,
    ) {
    }

    public function search(int $userId, Site $site, ImageSearchQuery $query): PaginatedResult
    {
        if (!$this->imagePolicy->canBrowse($userId, $site)) {
            throw new \App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException();
        }

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

    public function findForContributor(int $userId, Site $site, int $imageId): ?Image
    {
        if (!$this->imagePolicy->canBrowse($userId, $site)) {
            throw new \App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException();
        }

        $image = $this->cmsImageClient->find((int) $site->id, $imageId);

        if ($image === null || !$this->imagePolicy->canUse($userId, $site, $image)) {
            return null;
        }

        return $image;
    }

    public function upload(
        int               $userId,
        Site              $site,
        ImageUploadData   $uploadData,
        ImageEvidenceData $evidenceData,
    ): Image {
        if (!$this->imagePolicy->canUpload($userId, $site)) {
            throw new \App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException();
        }

        $image = $this->cmsImageClient->upload((int) $site->id, $uploadData);

        // The shared CMS upload path does not know the authenticated contributor.
        // Persist the Open Collab ownership and canonical submitted metadata here,
        // then reload so the API resource is built from the stored values.
        $image->update([
            'created_by' => $userId,
            'name' => $uploadData->name,
            'alt_text' => $uploadData->altText,
            'credit' => $uploadData->credit,
            'image_rights' => $uploadData->imageRights->value,
        ]);
        $image = $image->fresh();

        $evidenceData = new ImageEvidenceData(
            siteId: $evidenceData->siteId,
            cmsImageId: (int) $image->id,
            contributorUserId: $evidenceData->contributorUserId,
            imageRights: $evidenceData->imageRights,
            nameSubmitted: $evidenceData->nameSubmitted,
            altTextSubmitted: $evidenceData->altTextSubmitted,
            creditSubmitted: $evidenceData->creditSubmitted,
            rightsConfirmation: $evidenceData->rightsConfirmation,
            aiGenerated: $evidenceData->aiGenerated,
            containsMusic: $evidenceData->containsMusic,
            sponsoredContent: $evidenceData->sponsoredContent,
            affiliateContent: $evidenceData->affiliateContent,
            unclearRights: $evidenceData->unclearRights,
            contributorProfileId: $evidenceData->contributorProfileId,
            requestCorrelationId: $evidenceData->requestCorrelationId,
            ipAddress: $evidenceData->ipAddress,
            userAgent: $evidenceData->userAgent,
        );

        $this->creatorDeclarationRiskService->recordForImageUpload(
            siteId: (int) $site->id,
            cmsImageId: (int) $image->id,
            contributorUserId: $userId,
            aiGenerated: $evidenceData->aiGenerated,
            containsMusic: $evidenceData->containsMusic,
            sponsoredContent: $evidenceData->sponsoredContent,
            affiliateContent: $evidenceData->affiliateContent,
            unclearRights: $evidenceData->unclearRights,
            imageRights: $evidenceData->imageRights,
        );

        $this->imageMetadataRiskService->inspectUploadedImage(
            siteId: (int) $site->id,
            cmsImageId: (int) $image->id,
            actorUserId: $userId,
            imageRights: $evidenceData->imageRights,
            altText: $evidenceData->altTextSubmitted,
            credit: $evidenceData->creditSubmitted,
            aiGenerated: $evidenceData->aiGenerated,
        );

        try {
            $this->evidenceService->record($evidenceData);
        } catch (\Throwable $e) {
            error_log('ImageLibraryService: evidence recording failed for image ' . $image->id . ': ' . $e->getMessage());
        }

        return $image;
    }

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
