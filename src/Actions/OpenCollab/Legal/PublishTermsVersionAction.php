<?php

namespace App\Actions\OpenCollab\Legal;

use App\Models\TermsVersion;
use App\Services\OpenCollab\TermsVersionService;

class PublishTermsVersionAction
{
    public function __construct(private readonly TermsVersionService $service)
    {
    }

    public function execute(TermsVersion $termsVersion, int $publishedByUserId): TermsVersion
    {
        return $this->service->publish($termsVersion, $publishedByUserId);
    }
}
