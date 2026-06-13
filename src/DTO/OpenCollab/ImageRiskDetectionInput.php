<?php

namespace App\DTO\OpenCollab;

use App\Models\Image;

final readonly class ImageRiskDetectionInput
{
    public function __construct(
        public int $siteId,
        public int $cmsImageId,
        public Image $image,
    ) {
    }
}