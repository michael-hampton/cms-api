<?php

namespace App\Services\OpenCollab\Risk;

use App\DTO\OpenCollab\ImageRiskDetectionInput;

interface ImageRiskDetectorInterface
{
    /**
     * @return RiskDetectionFinding[]
     */
    public function detect(ImageRiskDetectionInput $input): array;
}