<?php

namespace App\DTO\Pricing;

final readonly class PeriodLabels
{
    public function __construct(
        public string $raw,
        public string $display,
        public string $numeric,
        public string $worded,
        public string $renewal,
    ) {
    }
}
