<?php

namespace App\Tests\Unit\Services\PublicContent\Paywall;

use App\Enums\Pages\PageStatus;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageMetadataRepository;
use App\Services\Cms\Pages\PremiumPagePurchaseEligibilityService;
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
        $page->status = PageStatus::PUBLISHED->value;
        $page->is_public_contribution = true;
        $page->contributor_id = 42;
        $page->is_paid = true;
        $page->price = 499;
        $page->premium_approved_at = '2026-01-01 00:00:00';
        $page->monetisation_disabled_at = null;
        $page->metadata = (object) ['visibility' => 'premium'];

        $repository = Mockery::mock(PageMetadataRepository::class);
        $eligibilityService = new PremiumPagePurchaseEligibilityService($repository);

        self::assertSame(
            PublicContentPaywallModeResolver::MODE_ARTICLE_PURCHASE,
            (new PublicContentPaywallModeResolver($eligibilityService))->resolve($page),
        );
    }

    public function testOrdinaryPremiumPageUsesSubscription(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->status = PageStatus::PUBLISHED->value;
        $page->is_public_contribution = false;
        $page->contributor_id = null; // Will cause isPurchasable to return false
        $page->is_paid = true;
        $page->price = 499;
        $page->premium_approved_at = '2026-01-01 00:00:00';
        $page->monetisation_disabled_at = null;
        $page->metadata = (object) ['visibility' => 'premium'];

        $repository = Mockery::mock(PageMetadataRepository::class);
        $eligibilityService = new PremiumPagePurchaseEligibilityService($repository);

        self::assertSame(
            PublicContentPaywallModeResolver::MODE_SUBSCRIPTION,
            (new PublicContentPaywallModeResolver($eligibilityService))->resolve($page),
        );
    }

    public function testFreeContributorPageDoesNotExposePurchaseForm(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->status = PageStatus::PUBLISHED->value;
        $page->is_public_contribution = true;
        $page->contributor_id = 42;
        $page->is_paid = false; // Will cause isPurchasable to return false
        $page->price = 0;        // Will cause isPurchasable to return false
        $page->premium_approved_at = '2026-01-01 00:00:00';
        $page->monetisation_disabled_at = null;
        $page->metadata = (object) ['visibility' => 'premium'];

        $repository = Mockery::mock(PageMetadataRepository::class);
        $eligibilityService = new PremiumPagePurchaseEligibilityService($repository);

        self::assertSame(
            PublicContentPaywallModeResolver::MODE_SUBSCRIPTION,
            (new PublicContentPaywallModeResolver($eligibilityService))->resolve($page),
        );
    }
}