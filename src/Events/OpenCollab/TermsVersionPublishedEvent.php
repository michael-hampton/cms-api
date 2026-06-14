<?php

namespace App\Events\OpenCollab;

class TermsVersionPublishedEvent
{
    public function __construct(
        public readonly int $termsVersionId,
        public readonly int $siteId,
        public readonly int $publishedByUserId,
        public readonly bool $requiresReacceptance,
    ) {
    }
}
