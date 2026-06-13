<?php

namespace App\Services\OpenCollab\Risk;

interface ContentRiskDetectorInterface
{
    /**
     * @return RiskDetectionFinding[]
     */
    public function detect(ContentRiskDetectionInput $input): array;
}