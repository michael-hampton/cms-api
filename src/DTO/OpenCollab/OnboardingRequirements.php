<?php

namespace App\DTO\OpenCollab;

class OnboardingRequirements
{
    public function __construct(
        public readonly int  $siteId,
        public readonly bool $requirePaymentSetup = true,
        public readonly bool $requireContracts = true,
        public readonly bool $requireGuidelines = true,
        public readonly int  $guidelinesVersion = 1,
        public readonly bool $requireAgeVerification = false,
        public readonly int  $minimumContributorAge = 18,
        public readonly bool $requireKycVerification = false
    )
    {
    }
}