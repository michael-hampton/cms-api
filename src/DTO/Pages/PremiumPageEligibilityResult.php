<?php

namespace App\DTO\Pages;

final class PremiumPageEligibilityResult
{
    /**
     * @param array<int, string> $failures
     * @param array<int, string> $warnings
     */
    public function __construct(
        public readonly bool $eligible,
        public readonly array $failures = [],
        public readonly array $warnings = [],
    ) {
    }

    public static function eligible(array $warnings = []): self
    {
        return new self(true, [], $warnings);
    }

    public static function ineligible(array $failures, array $warnings = []): self
    {
        return new self(false, $failures, $warnings);
    }

    /**
     * @return array{eligible: bool, failures: array<int, string>, warnings: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'failures' => $this->failures,
            'warnings' => $this->warnings,
        ];
    }
}