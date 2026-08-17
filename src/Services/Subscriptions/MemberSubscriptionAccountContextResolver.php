<?php

namespace App\Services\Subscriptions;

use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use RuntimeException;

final class MemberSubscriptionAccountContextResolver
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
    ) {
    }

    public function resolve(string $siteSlug): Site
    {
        $site = SiteContext::get();

        if ($site && $site->slug === $siteSlug && (bool) $site->is_active) {
            return $site;
        }

        $site = $this->siteRepository->findActiveBySlug($siteSlug);

        if (!$site) {
            throw new RuntimeException('Site not found.');
        }

        SiteContext::set($site);

        return $site;
    }
}
