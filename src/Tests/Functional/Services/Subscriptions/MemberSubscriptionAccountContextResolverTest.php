<?php

namespace App\Tests\Functional\Services\Subscriptions;

use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Services\Subscriptions\MemberSubscriptionAccountContextResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use RuntimeException;

final class MemberSubscriptionAccountContextResolverTest extends FunctionalTestCase
{
    public function test_matching_active_context_is_reused(): void
    {
        $site = Site::find($this->siteId);
        SiteContext::set($site);

        $resolved = (new MemberSubscriptionAccountContextResolver())->resolve($this->siteSlug);

        self::assertSame($site->id, $resolved->id);
        self::assertSame($site->id, SiteContext::getId());
    }

    public function test_mismatched_context_is_replaced_by_route_site(): void
    {
        $other = Site::create([
            'name' => 'Other Site',
            'slug' => 'other-site-' . uniqid(),
            'is_active' => true,
            'is_default' => false,
        ]);
        SiteContext::set($other);

        $resolved = (new MemberSubscriptionAccountContextResolver())->resolve($this->siteSlug);

        self::assertSame($this->siteId, $resolved->id);
        self::assertSame($this->siteId, SiteContext::getId());
    }

    public function test_inactive_and_unknown_sites_are_rejected(): void
    {
        $inactive = Site::create([
            'name' => 'Inactive Site',
            'slug' => 'inactive-site-' . uniqid(),
            'is_active' => false,
            'is_default' => false,
        ]);
        $resolver = new MemberSubscriptionAccountContextResolver();

        foreach ([$inactive->slug, 'missing-site-' . uniqid()] as $slug) {
            try {
                $resolver->resolve($slug);
                self::fail('Expected site resolution to fail.');
            } catch (RuntimeException $exception) {
                self::assertSame('Site not found.', $exception->getMessage());
            }
        }
    }
}
