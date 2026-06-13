<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ImageEvidenceData;
use App\Models\ImageSubmissionEvidence;
use App\Repositories\OpenCollab\ImageSubmissionEvidenceRepositoryInterface;

/**
 * Records immutable evidence of a contributor image upload.
 *
 * Evidence is written once — after successful CMS image creation — and
 * never updated. Retry safety is provided via the correlation ID.
 */
class ImageSubmissionEvidenceService implements ImageSubmissionEvidenceServiceInterface
{
    public function __construct(
        private readonly ImageSubmissionEvidenceRepositoryInterface $evidenceRepository,
    ) {
    }

    public function record(ImageEvidenceData $data): ImageSubmissionEvidence
    {
        // Idempotency: if this correlation ID was already recorded, return it
        if ($data->requestCorrelationId !== null) {
            $existing = $this->evidenceRepository->findByCorrelationId($data->requestCorrelationId);
            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->evidenceRepository->create([
            'site_id'                => $data->siteId,
            'cms_image_id'           => $data->cmsImageId,
            'contributor_user_id'    => $data->contributorUserId,
            'contributor_profile_id' => $data->contributorProfileId,
            'cms_image_rights_value' => $data->imageRights->value,
            'name_submitted'         => $data->nameSubmitted,
            'alt_text_submitted'     => $data->altTextSubmitted,
            'credit_submitted'       => $data->creditSubmitted,
            'rights_confirmation'    => $data->rightsConfirmation,
            'ai_generated'           => $data->aiGenerated,
            'sponsored_content'      => $data->sponsoredContent,
            'affiliate_content'      => $data->affiliateContent,
            'request_correlation_id' => $data->requestCorrelationId,
            'ip_address'             => $data->ipAddress,
            'user_agent'             => $data->userAgent,
            'submitted_at'           => date('Y-m-d H:i:s'),
        ]);
    }

    public function hasEvidence(int $cmsImageId, int $contributorId): bool
    {
        return $this->evidenceRepository->findByCmsImageAndContributor($cmsImageId, $contributorId) !== null;
    }
}