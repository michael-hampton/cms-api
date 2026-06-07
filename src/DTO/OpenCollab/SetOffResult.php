<?php

namespace App\DTO\OpenCollab;

class SetOffResult
{
    public function __construct(
        public readonly int $grossAmount,
        public readonly int $deductedAmount,
        public readonly int $netAmount,
        public readonly array $deductions = [],
    ) {
    }
}