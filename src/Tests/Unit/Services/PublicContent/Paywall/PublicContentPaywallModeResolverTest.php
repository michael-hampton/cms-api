<?php

namespace App\Tests\Unit\Services\PublicContent\Paywall;

use App\Models\Page;
use App\Services\PublicContent\Paywall\PublicContentPaywallModeResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentPaywallModeResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testPricedOpenCollabPageUsesArticlePurchase(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->is_public_contribution = true;
        $page->contributor_id = 42;
        $page->is_paid = true;
        $page->price = 499;
        $page->monetisation_disabled_at = null;

        self::assertSame(
            PublicContentPaywallModeResolver::MODE_ARTICLE_PURCHASE,
            (new PublicContentPaywallModeResolver())->resolve($page),
        );
    }

    public function testOrdinaryPremiumPageUsesSubscription(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->is_public_contribution = false;
        $page->contributor_id = null;
        $page->is_paid = true;
        $page->price = 499;
        $page->monetisation_disabled_at = null;

        self::assertSame(
            PublicContentPaywallModeResolver::MODE_SUBSCRIPTION,
            (new PublicContentPaywallModeResolver())->resolve($page),
        );
    }

    public function testFreeContributorPageDoesNotExposePurchaseForm(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->is_public_contribution = true;
        $page->contributor_id = 42;
        $page->is_paid = false;
        $page->price = 0;
        $page->monetisation_disabled_at = null;

        self::assertSame(
            PublicContentPaywallModeResolver::MODE_SUBSCRIPTION,
            (new PublicContentPaywallModeResolver())->resolve($page),
        );
    }
}
