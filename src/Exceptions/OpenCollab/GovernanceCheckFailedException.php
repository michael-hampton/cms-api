<?php

namespace App\Exceptions\OpenCollab;

use App\Services\OpenCollab\Moderation\Governance\GovernanceFailure;
use Exception;

class GovernanceCheckFailedException extends Exception
{
    /**
     * @param GovernanceFailure[] $failures
     */
    public function __construct(
        private readonly array $failures,
    ) {
        parent::__construct('Approval blocked by governance checks.');
    }

    /**
     * @return GovernanceFailure[]
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    public function toArray(): array
    {
        return array_map(
            fn(GovernanceFailure $f) => ['code' => $f->code, 'message' => $f->message],
            $this->failures
        );
    }
}