<?php

namespace App\DTO\OpenCollab;

use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskType;

final readonly class RiskDetectionFinding
{
    public function __construct(
        public RiskType $riskType,
        public RiskSeverity $severity,
        public string $message,
        public ?float $confidence = null,
    ) {
    }
}