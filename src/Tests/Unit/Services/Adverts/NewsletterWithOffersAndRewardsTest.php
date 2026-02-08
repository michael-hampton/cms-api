<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Models\Block;
use App\Models\Newsletter;
use App\Models\Page;
use App\Models\ProductOffer;
use App\Services\Newsletter\NewsletterPageBuilderService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterWithOffersAndRewardsTest extends FunctionalTestCase
{
    use CreatesTestData;

    private NewsletterPageBuilderService $service;

    public function testRendersOfferBlockInNewsletter(): void
    {
        $product = $this->createProduct();
        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'original_price' => 99.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'description' => 'Limited time offer',
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'offer',
            'page_id' => $page->id,
            'data' => json_encode(['offer_id' => $offer->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        // PASS NULL FOR MEMBER (offers don't require authentication by default)
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null);

        $this->assertStringContainsString('Partner Offer', $html);
        $this->assertStringContainsString($product->name, $html);
        $this->assertStringContainsString('79.99', $html);
        $this->assertStringContainsString('Partner Offer', $html);
    }

    public function testSuppressesInactiveOffer(): void
    {
        $product = $this->createProduct();
        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => false,
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'offer',
            'page_id' => $page->id,
            'data' => json_encode(['offer_id' => $offer->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $member = $this->createMember();

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, $member);

        $this->assertStringNotContainsString('Partner Offer', $html);
        $this->assertStringNotContainsString($product->name, $html);
    }

    public function testRendersRewardBlockForAuthenticatedMember(): void
    {
        $member = $this->createMember();
        $definition = $this->createRewardDefinition([
            'name' => 'Welcome Reward',
            'description' => 'Thank you for being a member',
        ]);

        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition->id,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'reward',
            'page_id' => $page->id,
            'data' => json_encode(['reward_id' => $reward->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        // PASS THE MEMBER
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, $member, $this->siteId);

        $this->assertStringContainsString('Member Reward', $html);
        $this->assertStringContainsString('Welcome Reward', $html);
        $this->assertStringContainsString('Thank you for being a member', $html);
    }

    public function testSuppressesRewardForUnauthenticated(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'reward',
            'page_id' => $page->id,
            'data' => json_encode(['reward_id' => $reward->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        // No member context
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        $this->assertStringNotContainsString('Member Reward', $html);
    }

    public function testTracksOfferRender(): void
    {
        $product = $this->createProduct();
        $member = $this->createMember();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'offer',
            'page_id' => $page->id,
            'data' => json_encode(['offer_id' => $offer->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page);

        // Should have tracked render
        $this->assertDatabaseHas('offer_clicks', [
            'offer_id' => $offer->id,
            'action' => 'render',
        ]);
    }

    public function testRendersDealBlockInNewsletter(): void
    {
        $product = $this->createProduct([
            'name' => 'Special Product',
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => true,
            'description' => 'Limited time offer',
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'deal',
            'page_id' => $page->id,
            'data' => json_encode(['product_id' => $product->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        $this->assertStringContainsString('Special Product', $html);
        $this->assertStringContainsString('79.99', $html);
        $this->assertStringContainsString('100', $html);
    }

    public function testSuppressesInactiveDeal(): void
    {
        $product = $this->createProduct([
            'name' => 'Inactive Product',
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => false,
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'deal',
            'page_id' => $page->id,
            'data' => json_encode(['product_id' => $product->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        $this->assertStringNotContainsString('Inactive Product', $html);
    }

    public function testSuppressesDealWithNoSale(): void
    {
        $product = $this->createProduct([
            'name' => 'No Sale Product',
            'price' => 100.00,
            'sale_price' => 100.00, // No discount
            'is_active' => true,
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'deal',
            'page_id' => $page->id,
            'data' => json_encode(['product_id' => $product->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        $this->assertStringNotContainsString('No Sale Product', $html);
    }

    public function testTracksDealRender(): void
    {
        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => true,
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'deal',
            'page_id' => $page->id,
            'data' => json_encode(['product_id' => $product->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Should have tracked render
        $this->assertDatabaseHas('deal_clicks', [
            'product_id' => $product->id,
            'action' => 'render',
        ]);
    }

    public function testRendersDealBlockWithBrandAndImage(): void
    {
        $brand = \App\Models\Brand::create([
            'name' => 'TechBrand',
            'slug' => 'techbrand',
            'site_id' => $this->siteId,
        ]);

        $product = $this->createProduct([
            'name' => 'Premium Headphones',
            'price' => 299.99,
            'sale_price' => 199.99,
            'is_active' => true,
            'brand_id' => $brand->id,
            'image' => 'https://example.com/headphones.jpg',
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'deal',
            'page_id' => $page->id,
            'data' => json_encode([
                'product_id' => $product->id,
                'title' => 'Black Friday Deal',
            ]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Deal Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        $this->assertStringContainsString('Premium Headphones', $html);
        $this->assertStringContainsString('TechBrand', $html);
        $this->assertStringContainsString('199.99', $html);
        $this->assertStringContainsString('299.99', $html);
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NewsletterPageBuilderService::class);
    }
}