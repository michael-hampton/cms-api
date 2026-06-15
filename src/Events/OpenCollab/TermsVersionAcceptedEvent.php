<?php

namespace App\Events\OpenCollab;

class TermsVersionAcceptedEvent
{
    public function __construct(
        public readonly int $termsVersionId,
        public readonly int $siteId,
        public readonly int $userId,
        public readonly string $acceptedVia,
    ) {
    }
}
