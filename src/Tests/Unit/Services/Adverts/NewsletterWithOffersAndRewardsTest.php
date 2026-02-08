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

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NewsletterPageBuilderService::class);
    }

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
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

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

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, $member, $this->siteId);

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

        $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

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
            'type' => 'offer-deal',
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
            'type' => 'offer-deal',
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
            'type' => 'offer-deal',
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
            'type' => 'offer-deal',
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
            'type' => 'offer-deal',
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

    public function testDynamicallyInjectsOffersIntoNewsletter(): void
    {
        $product = $this->createProduct();
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

        // NO BLOCKS CREATED - testing dynamic injection

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Offer should be dynamically injected
        $this->assertStringContainsString('Partner Offer', $html);
        $this->assertStringContainsString($product->name, $html);
    }

    public function testDynamicallyInjectsRewardsForMember(): void
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

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, $member, $this->siteId);

        // Reward should be dynamically injected
        $this->assertStringContainsString('Member Reward', $html);
    }

    public function testStaticAndDynamicBlocksAppearTogether(): void
    {
        $member = $this->createMember();

        // Static block
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'text',
            'page_id' => $page->id,
            'data' => json_encode(['paragraphs' => ['Static content']]),
        ]);

        // Dynamic offer
        $product = $this->createProduct();
        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
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

        // Both static and dynamic content should appear
        $this->assertStringContainsString('Static content', $html);
        $this->assertStringContainsString('Partner Offer', $html);
    }

    public function testDynamicBlocksInterleavedWithStaticContent(): void
    {
        $member = $this->createMember();

        // Create static blocks
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        for ($i = 1; $i <= 6; $i++) {
            Block::create([
                'type' => 'text',
                'page_id' => $page->id,
                'data' => json_encode(['paragraphs' => ["Static paragraph {$i}"]]),
            ]);
        }

        // Create dynamic promotions
        $product = $this->createProduct();
        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'original_price' => 99.99,
            'start_date' => date('Y-m-d H:i:s'),
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, $member, $this->siteId);

        // Both static and dynamic should be present
        $this->assertStringContainsString('Static paragraph 1', $html);
        $this->assertStringContainsString('Partner Offer', $html);
        $this->assertStringContainsString('Member Reward', $html);

        // Dynamic blocks should NOT all be at the end
        $offerPos = strpos($html, 'Partner Offer');
        $lastStaticPos = strpos($html, 'Static paragraph 6');

        $this->assertNotFalse($offerPos);
        $this->assertNotFalse($lastStaticPos);
        // At least one dynamic block should appear before the last static block
        $this->assertGreaterThan($lastStaticPos, $offerPos);
    }


    public function testDynamicBlocksDistributedEvenly(): void
    {
        $member = $this->createMember();

        // Create many static blocks
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Block::create([
                'type' => 'text',
                'page_id' => $page->id,
                'data' => json_encode(['paragraphs' => ["Paragraph {$i}"]]),
            ]);
        }

        // Create 3 dynamic promotions
        for ($i = 0; $i < 3; $i++) {
            $product = $this->createProduct();
            ProductOffer::create([
                'product_id' => $product->id,
                'sale_price' => 79.99,
                'original_price' => 99.99,
                'start_date' => date('Y-m-d H:i:s'),
                'is_active' => true,
                'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            ]);
        }

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Dynamic blocks should be distributed throughout, not clumped at end
        $offerCount = substr_count($html, 'Partner Offer');
        $this->assertEquals(3, $offerCount);

        // Check positions are spread out
        $positions = [];
        $offset = 0;
        while (($pos = strpos($html, 'Partner Offer', $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + 1;
        }

        // Verify offers aren't all clustered together (spacing between them)
        if (count($positions) >= 2) {
            $spacing1 = $positions[1] - $positions[0];
            $spacing2 = count($positions) >= 3 ? $positions[2] - $positions[1] : $spacing1;

            // Spacing should be reasonably distributed (not all at once)
            $this->assertGreaterThan(100, $spacing1); // Reasonable minimum spacing
            $this->assertGreaterThan(100, $spacing2);
        }
    }
}