<?php

namespace App\Services\OpenCollab\Moderation\Governance;

class GovernanceCheckResult
{
    /**
     * @param GovernanceFailure[] $failures
     */
    public function __construct(
        public readonly bool $passed,
        public readonly array $failures = [],
    ) {
    }

    public static function pass(): self
    {
        return new self(true, []);
    }

    /**
     * @param GovernanceFailure[] $failures
     */
    public static function fail(array $failures): self
    {
        return new self(false, $failures);
    }
}