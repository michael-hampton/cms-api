<?php

namespace App\Services\OpenCollab\Risk;

use App\Enums\OpenCollab\OpenCollabImageRights;
use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskSource;
use App\Enums\OpenCollab\RiskType;
use App\Models\ContentRiskMarker;
use App\Repositories\OpenCollab\RiskMarkerRepository;

class ImageMetadataRiskService
{
    public function __construct(
        private readonly RiskMarkerService $riskMarkerService,
        private readonly RiskMarkerRepository $riskMarkerRepository,
    ) {
    }

    public function inspectUploadedImage(
        int $siteId,
        int $cmsImageId,
        int $actorUserId,
        OpenCollabImageRights $imageRights,
        ?string $altText,
        ?string $credit,
        bool $aiGenerated = false,
    ): void {
        if (trim((string) $altText) === '') {
            $this->createImageMarkerOnce(
                siteId: $siteId,
                cmsImageId: $cmsImageId,
                riskType: RiskType::MissingProvenance,
                severity: RiskSeverity::Medium,
                details: [
                    'reason' => 'missing_alt_text',
                    'message' => 'Uploaded image is missing alt text.',
                ],
                createdByUserId: $actorUserId,
            );
        }

        $requiresCredit = in_array(
            $imageRights,
            [
                OpenCollabImageRights::ContributorOwned,
                OpenCollabImageRights::ThirdPartyLicensed,
                OpenCollabImageRights::Agency,
                OpenCollabImageRights::EditorialUseOnly,
            ],
            true
        );

        if ($requiresCredit && trim((string) $credit) === '') {

            if ($requiresCredit && trim((string) $credit) === '') {
                $this->createImageMarkerOnce(
                    siteId: $siteId,
                    cmsImageId: $cmsImageId,
                    riskType: RiskType::MissingProvenance,
                    severity: RiskSeverity::Medium,
                    details: [
                        'reason' => 'missing_credit',
                        'message' => 'Uploaded image requires attribution but credit is missing.',
                        'image_rights' => $imageRights->value,
                    ],
                    createdByUserId: $actorUserId,
                );
            }
        }

        if ($imageRights === OpenCollabImageRights::Unknown) {
            $this->createImageMarkerOnce(
                siteId: $siteId,
                cmsImageId: $cmsImageId,
                riskType: RiskType::UnclearOwnership,
                severity: RiskSeverity::High,
                details: [
                    'reason' => 'unknown_image_rights',
                    'message' => 'Uploaded image has unknown or unconfirmed rights.',
                    'image_rights' => $imageRights->value,
                ],
                createdByUserId: $actorUserId,
            );
        }

        if ($aiGenerated) {
            $this->createImageMarkerOnce(
                siteId: $siteId,
                cmsImageId: $cmsImageId,
                riskType: RiskType::AiGenerated,
                severity: RiskSeverity::Medium,
                details: [
                    'reason' => 'ai_generated_flag',
                    'message' => 'Uploaded image was flagged as AI-generated.',
                ],
                createdByUserId: $actorUserId,
            );
        }
    }

    private function createImageMarkerOnce(
        int $siteId,
        int $cmsImageId,
        RiskType $riskType,
        RiskSeverity $severity,
        array $details,
        int $createdByUserId,
    ): ?ContentRiskMarker {
        $existing = $this->riskMarkerRepository->findExistingForImage(
            $siteId,
            $cmsImageId,
            $riskType->value,
            RiskSource::AutomatedCheck->value,
        );

        if ($existing !== null) {
            return $existing;
        }

        return $this->riskMarkerService->create(
            siteId: $siteId,
            pageId: null,
            pageVersionId: null,
            cmsImageId: $cmsImageId,
            riskType: $riskType,
            source: RiskSource::AutomatedCheck,
            severity: $severity,
            details: $details,
            createdByUserId: $createdByUserId,
        );
    }
}