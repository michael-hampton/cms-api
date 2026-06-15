<?php

namespace App\Resources\OpenCollab;

use App\DTO\OpenCollab\TermsAcceptanceEvidence;

class TermsAcceptanceEvidenceResource
{
    public function __construct(private readonly TermsAcceptanceEvidence $evidence)
    {
    }

    public function toArray(): array
    {
        return $this->evidence->toArray();
    }
}
