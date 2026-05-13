<?php

namespace App\Exceptions\OpenCollab;

use RuntimeException;

/**
 * Thrown when a contributor does not meet the minimum age requirement.
 */
class ContributorUnderageException extends RuntimeException
{
    public function __construct(
        public readonly int $contributorAge,
        public readonly int $minimumAge,
    )
    {
        parent::__construct(
            "Contributor is {$contributorAge} years old; minimum required age is {$minimumAge}."
        );
    }
}