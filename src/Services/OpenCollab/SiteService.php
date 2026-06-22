<?php

namespace App\Services\OpenCollab;

use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Services\Members\BadgeAccessService;

class SiteService
{
    function __construct(private readonly SiteRepository $siteRepository)
    {

    }

    public function updateSiteSettings(int $siteId, array $data): Site
    {
        $data['require_kyc_verification'] = (bool)($data['require_kyc_verification'] ?? false);

        $site = $this->siteRepository->find($siteId);
        $settings = is_string($site->settings ?? null)
            ? (json_decode($site->settings, true) ?: [])
            : ($site->settings ?? []);

        $settings[BadgeAccessService::REQUIRE_ACTIVE_SUBSCRIPTION_SETTING] = (bool)($data['badges_require_active_subscription'] ?? false);

        $allowed = [
            'guidelines_version',
            'require_payment_setup',
            'require_kyc_verification',
            'require_contracts',
            'require_guidelines_ack',
            'require_age_verification',
            'minimum_contributor_age',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));
        $payload['settings'] = $settings;

        return $this->siteRepository->update($siteId, $payload);
    }
}
