<?php

namespace App\Actions\OpenCollab\Legal;

use App\Models\TermsVersion;
use App\Models\UserTermsAcceptance;
use App\Services\OpenCollab\TermsVersionService;

class AcceptTermsVersionAction
{
    public function __construct(private readonly TermsVersionService $service)
    {
    }

    public function execute(
        TermsVersion $termsVersion,
        int $userId,
        string $ipAddress,
        ?string $userAgent,
        string $acceptedVia = 'onboarding',
    ): UserTermsAcceptance {
        return $this->service->accept(
            $termsVersion,
            $userId,
            $ipAddress,
            $userAgent,
            $acceptedVia,
        );
    }
}
