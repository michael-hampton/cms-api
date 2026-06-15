<?php

namespace App\DTO\OpenCollab;

class TermsAcceptanceEvidence
{
    public function __construct(
        public readonly int $acceptanceId,
        public readonly int $siteId,
        public readonly int $userId,
        public readonly int $termsVersionId,
        public readonly string $semanticVersion,
        public readonly string $renderedHash,
        public readonly bool $hashValid,
        public readonly string $acceptedAt,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly string $acceptedVia,
        public readonly string $renderedContent,
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
