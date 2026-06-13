<?php

namespace App\DTO\OpenCollab;

use App\Enums\OpenCollab\OpenCollabImageRights;

final class ImageEvidenceData
{
    public function __construct(
        public readonly int                   $siteId,
        public readonly int                   $cmsImageId,
        public readonly int                   $contributorUserId,
        public readonly OpenCollabImageRights $imageRights,
        public readonly string                $nameSubmitted,
        public readonly string                $altTextSubmitted,
        public readonly string                $creditSubmitted,
        public readonly bool                  $rightsConfirmation,
        public readonly bool                  $aiGenerated        = false,
        public readonly bool                  $sponsoredContent   = false,
        public readonly bool                  $affiliateContent   = false,
        public readonly ?int                  $contributorProfileId    = null,
        public readonly ?string               $requestCorrelationId    = null,
        public readonly ?string               $ipAddress               = null,
        public readonly ?string               $userAgent               = null,
    ) {
    }
}