<?php

namespace App\Services\OpenCollab\Risk;

use App\Enums\OpenCollab\OpenCollabImageRights;
use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskSource;
use App\Enums\OpenCollab\RiskType;
use App\Models\ContentRiskMarker;
use App\Repositories\OpenCollab\RiskMarkerRepository;

class CreatorDeclarationRiskService
{
    public function __construct(
        private readonly RiskMarkerService $riskMarkerService,
        private readonly RiskMarkerRepository $riskMarkerRepository,
    ) {
    }

    public function recordForImageUpload(
        int $siteId,
        int $cmsImageId,
        int $contributorUserId,
        bool $aiGenerated = false,
        bool $containsMusic = false,
        bool $sponsoredContent = false,
        bool $affiliateContent = false,
        bool $unclearRights = false,
        ?OpenCollabImageRights $imageRights = null,
    ): void {
        if ($imageRights === OpenCollabImageRights::Unknown) {
            $unclearRights = true;
        }

        if ($aiGenerated) {
            $this->createImageMarkerOnce(
                $siteId,
                $cmsImageId,
                RiskType::AiGenerated,
                RiskSeverity::Medium,
                ['declaration' => 'Contributor declared this image is AI-generated.'],
                $contributorUserId,
            );
        }

        if ($containsMusic) {
            $this->createImageMarkerOnce(
                $siteId,
                $cmsImageId,
                RiskType::MusicRights,
                RiskSeverity::High,
                ['declaration' => 'Contributor declared this image/content contains music.'],
                $contributorUserId,
            );
        }

        if ($sponsoredContent) {
            $this->createImageMarkerOnce(
                $siteId,
                $cmsImageId,
                RiskType::SponsoredContent,
                RiskSeverity::Medium,
                ['declaration' => 'Contributor declared sponsored content.'],
                $contributorUserId,
            );
        }

        if ($affiliateContent) {
            $this->createImageMarkerOnce(
                $siteId,
                $cmsImageId,
                RiskType::AffiliateLinkAbuse,
                RiskSeverity::Medium,
                ['declaration' => 'Contributor declared affiliate content.'],
                $contributorUserId,
            );
        }

        if ($unclearRights) {
            $this->createImageMarkerOnce(
                $siteId,
                $cmsImageId,
                RiskType::UnclearOwnership,
                RiskSeverity::High,
                ['declaration' => 'Contributor declared unclear or unconfirmed image rights.'],
                $contributorUserId,
            );
        }
    }

    public function recordForPageSubmission(
        int $siteId,
        int $pageId,
        ?int $pageVersionId,
        int $contributorUserId,
        bool $aiGenerated = false,
        bool $containsMusic = false,
        bool $sponsoredContent = false,
        bool $affiliateContent = false,
        bool $unclearOwnership = false,
    ): void {
        if ($aiGenerated) {
            $this->createPageMarkerOnce(
                $siteId,
                $pageId,
                $pageVersionId,
                RiskType::AiGenerated,
                RiskSeverity::Medium,
                ['declaration' => 'Contributor declared AI-generated content.'],
                $contributorUserId,
            );
        }

        if ($containsMusic) {
            $this->createPageMarkerOnce(
                $siteId,
                $pageId,
                $pageVersionId,
                RiskType::MusicRights,
                RiskSeverity::High,
                ['declaration' => 'Contributor declared music rights risk.'],
                $contributorUserId,
            );
        }

        if ($sponsoredContent) {
            $this->createPageMarkerOnce(
                $siteId,
                $pageId,
                $pageVersionId,
                RiskType::SponsoredContent,
                RiskSeverity::Medium,
                ['declaration' => 'Contributor declared sponsored content.'],
                $contributorUserId,
            );
        }

        if ($affiliateContent) {
            $this->createPageMarkerOnce(
                $siteId,
                $pageId,
                $pageVersionId,
                RiskType::AffiliateLinkAbuse,
                RiskSeverity::Medium,
                ['declaration' => 'Contributor declared affiliate content.'],
                $contributorUserId,
            );
        }

        if ($unclearOwnership) {
            $this->createPageMarkerOnce(
                $siteId,
                $pageId,
                $pageVersionId,
                RiskType::UnclearOwnership,
                RiskSeverity::High,
                ['declaration' => 'Contributor declared unclear ownership.'],
                $contributorUserId,
            );
        }
    }

    private function createPageMarkerOnce(
        int $siteId,
        int $pageId,
        ?int $pageVersionId,
        RiskType $riskType,
        RiskSeverity $severity,
        array $details,
        int $createdByUserId,
    ): ?ContentRiskMarker {
        $existing = $this->riskMarkerRepository->findExistingForPage(
            $siteId,
            $pageId,
            $riskType->value,
            RiskSource::CreatorDeclaration->value,
        );

        if ($existing !== null) {
            return $existing;
        }

        return $this->riskMarkerService->create(
            siteId: $siteId,
            pageId: $pageId,
            pageVersionId: $pageVersionId,
            cmsImageId: null,
            riskType: $riskType,
            source: RiskSource::CreatorDeclaration,
            severity: $severity,
            details: $details,
            createdByUserId: $createdByUserId,
        );
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
            RiskSource::CreatorDeclaration->value,
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
            source: RiskSource::CreatorDeclaration,
            severity: $severity,
            details: $details,
            createdByUserId: $createdByUserId,
        );
    }
}