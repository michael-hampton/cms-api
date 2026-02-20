<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\SubscriptionBundle;
use App\Repositories\Repository;

class SubscriptionBundleRepository extends Repository
{
    public function getActiveBundles(?int $siteId = null): \App\Framework\Support\Collection
    {
        $query = SubscriptionBundle::with([
            'items.subscriptionPlan.pricingTiers',
        ])->where('is_active', true);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->orderBy('bundle_price')->get();
    }

    public function getBundlesIncludingNewsletter(string $newsletterSlug, int $siteId): Collection
    {
        return SubscriptionBundle::where('site_id', $siteId)
            ->where('is_active', true)
            ->get()
            ->filter(fn($bundle) => $bundle->includesNewsletter($newsletterSlug));
    }

    protected function getModelClass(): string
    {
        return SubscriptionBundle::class;
    }
}