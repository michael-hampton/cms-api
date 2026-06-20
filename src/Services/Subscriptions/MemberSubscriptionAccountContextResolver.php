<?php

namespace App\Services\Subscriptions;

use App\Framework\Support\SiteContext;
use App\Models\Site;
use RuntimeException;

final class MemberSubscriptionAccountContextResolver
{
    public function resolve(string $siteSlug): Site
    {
        $site = SiteContext::get();

        if ($site && $site->slug === $siteSlug && (bool) $site->is_active) {
            return $site;
        }

        $site = Site::where('slug', $siteSlug)
            ->where('is_active', 1)
            ->first();

        if (!$site) {
            throw new RuntimeException('Site not found.');
        }

        SiteContext::set($site);

        return $site;
    }
}
