<?php

namespace App\Services\OpenCollab;

use App\Repositories\Cms\SiteRepository;

class SiteService
{
    function __construct(private readonly SiteRepository $siteRepository)
    {

    }

    public function updateSiteSettings(int $siteId, array $data): \App\Models\Site
    {
        $allowed = [
            'guidelines_version',
            'require_payment_setup',
            'require_contracts',
            'require_guidelines_ack',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));

        $site = $this->siteRepository->find($siteId);

        return $this->siteRepository->update($siteId, $payload);
    }
}