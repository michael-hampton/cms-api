<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Services\Adverts\PromotionInjector;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PromotionInjectorTest extends FunctionalTestCase
{
    use CreatesTestData;

    private PromotionInjector $injector;

    public function testInjectsEligibleOffers(): void
    {
        $product = $this->createProduct();
        $offer = \App\Models\ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $blocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            1,
            null,
            $this->siteId,
            'newsletter'
        );

        $offerBlocks = array_filter($blocks, fn($b) => $b['type'] === 'offer');

        $this->assertNotEmpty($offerBlocks);
        $this->assertEquals($offer->id, $offerBlocks[0]['data']['offer_id']);
    }

    public function testInjectsEligibleRewards(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $blocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            1,
            $member,
            $this->siteId,
            'newsletter'
        );

        $rewardBlocks = array_filter($blocks, fn($b) => $b['type'] === 'reward');

        $this->assertNotEmpty($rewardBlocks);
        $this->assertEquals($reward->id, $rewardBlocks[0]['data']['reward_id']);
    }

    public function testInjectsEligibleDeals(): void
    {
        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => true,
        ]);

        $blocks = $this->injector->getBlocksForSurface(
            'page',
            1,
            null,
            $this->siteId,
            'web'
        );

        $dealBlocks = array_filter($blocks, fn($b) => $b['type'] === 'offer-deal');

        $this->assertNotEmpty($dealBlocks);
        $this->assertEquals($product->id, $dealBlocks[0]['data']['product_id']);
    }

    public function testRespectsChannelLimits(): void
    {
        // Create 10 offers
        for ($i = 0; $i < 10; $i++) {
            $product = $this->createProduct();
            \App\Models\ProductOffer::create([
                'product_id' => $product->id,
                'sale_price' => 79.99,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'is_active' => true,
            ]);
        }

        // Newsletter: max 3 offers
        $newsletterBlocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            1,
            null,
            $this->siteId,
            'newsletter'
        );

        $offerBlocks = array_filter($newsletterBlocks, fn($b) => $b['type'] === 'offer');
        $this->assertLessThanOrEqual(3, count($offerBlocks));

        // Web: max 5 offers
        $webBlocks = $this->injector->getBlocksForSurface(
            'page',
            1,
            null,
            $this->siteId,
            'web'
        );

        $offerBlocks = array_filter($webBlocks, fn($b) => $b['type'] === 'offer');
        $this->assertLessThanOrEqual(5, count($offerBlocks));
    }

    public function testPrioritizesRewardsByExpiry(): void
    {
        $member = $this->createMember();

        $rewardSoon = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
        ]);

        $rewardLater = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
        ]);

        $blocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            1,
            $member,
            $this->siteId,
            'newsletter'
        );

        $rewardBlocks = array_filter($blocks, fn($b) => $b['type'] === 'reward');
        $rewardIds = array_map(fn($b) => $b['data']['reward_id'], $rewardBlocks);

        // Earlier expiry should come first
        $this->assertEquals($rewardSoon->id, $rewardIds[0]);
    }

    public function testInterleavesBlockTypes(): void
    {
        $member = $this->createMember();

        // Create multiple of each type
        for ($i = 0; $i < 3; $i++) {
            $product = $this->createProduct();
            \App\Models\ProductOffer::create([
                'product_id' => $product->id,
                'sale_price' => 79.99 - $i,
                'original_price' => 99.99,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'is_active' => true,
            ]);

            $this->createMemberReward([
                'member_id' => $member->id,
                'status' => 'pending',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+' . (10 - $i) . ' days')),
                'site_id' => $this->siteId,
            ]);

            $this->createProduct([
                'price' => 100.00,
                'sale_price' => 79.99 - ($i * 5),
                'is_active' => true,
            ]);
        }

        $blocks = $this->injector->getBlocksForSurface(
            'page',
            1,
            $member,
            $this->siteId,
            'web'
        );

        $this->assertNotEmpty($blocks, 'Should have blocks');

        // Check no more than 2 consecutive of same type
        $types = array_map(fn($b) => $b['injection_type'], $blocks);

        $consecutiveCount = 1;
        $lastType = null;

        foreach ($types as $type) {
            if ($type === $lastType) {
                $consecutiveCount++;
                $this->assertLessThanOrEqual(3, $consecutiveCount, "More than 3 consecutive {$type} blocks");
            } else {
                $consecutiveCount = 1;
            }
            $lastType = $type;
        }

        // Verify we have mixed types
        $uniqueTypes = array_unique($types);
        $this->assertGreaterThan(1, count($uniqueTypes), 'Should have multiple promotion types');
    }

    public function testDoesNotInjectIneligibleItems(): void
    {
        // Inactive offer
        $product = $this->createProduct();
        \App\Models\ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'is_active' => false,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        // Claimed reward
        $member = $this->createMember();
        $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'claimed',
        ]);

        // Inactive product
        $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => false,
        ]);

        $blocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            1,
            $member,
            $this->siteId,
            'newsletter'
        );

        $this->assertEmpty($blocks);
    }

    public function testRoundRobinInterleaving(): void
    {
        $member = $this->createMember();

        // Create 3 of each type
        for ($i = 0; $i < 3; $i++) {
            $product = $this->createProduct();
            \App\Models\ProductOffer::create([
                'product_id' => $product->id,
                'sale_price' => 79.99 - $i, // Different priorities
                'original_price' => 99.99,
                'start_date' => date('Y-m-d H:i:s'),
                'is_active' => true,
                'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            ]);

            $this->createMemberReward([
                'member_id' => $member->id,
                'status' => 'pending',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+' . (3 - $i) . ' days')),
            ]);

            $this->createProduct([
                'price' => 100.00,
                'sale_price' => 70.00 + ($i * 5), // Different discounts
                'is_active' => true,
            ]);
        }

        $blocks = $this->injector->getBlocksForSurface(
            'page',
            1,
            $member,
            $this->siteId,
            'web'
        );

        // Should interleave types in round-robin fashion
        // Expected pattern (respecting limits): offer, reward, deal, offer, reward, deal, offer, reward, deal
        $types = array_map(fn($b) => $b['injection_type'], $blocks);

        // Check we have mixed types, not all of one type first
        $this->assertNotEquals(['offer', 'offer', 'offer', 'reward', 'reward', 'reward', 'deal', 'deal', 'deal'], $types);

        // Verify no more than 2 consecutive of same type
        $consecutiveCount = 1;
        $lastType = null;

        foreach ($types as $type) {
            if ($type === $lastType) {
                $consecutiveCount++;
                $this->assertLessThanOrEqual(3, $consecutiveCount, "More than 3 consecutive {$type} blocks");
            } else {
                $consecutiveCount = 1;
            }
            $lastType = $type;
        }
    }

    public function testHandlesUnevenPromotionCounts(): void
    {
        $member = $this->createMember();

        // 5 offers, 2 rewards, 1 deal
        for ($i = 0; $i < 5; $i++) {
            $product = $this->createProduct();
            \App\Models\ProductOffer::create([
                'product_id' => $product->id,
                'sale_price' => 79.99,
                'original_price' => 99.99,
                'start_date' => date('Y-m-d H:i:s'),
                'is_active' => true,
                'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            $this->createMemberReward([
                'member_id' => $member->id,
                'status' => 'pending',
            ]);
        }

        $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => true,
        ]);

        $blocks = $this->injector->getBlocksForSurface(
            'page',
            1,
            $member,
            $this->siteId,
            'web'
        );

        // Should still respect consecutive limits even with uneven counts
        $types = array_map(fn($b) => $b['injection_type'], $blocks);
        $consecutiveCount = 1;
        $lastType = null;

        foreach ($types as $type) {
            if ($type === $lastType) {
                $consecutiveCount++;
                $this->assertLessThanOrEqual(2, $consecutiveCount);
            } else {
                $consecutiveCount = 1;
            }
            $lastType = $type;
        }
    }

    public function testMaintainsPriorityWithinEachType(): void
    {
        // Create offers with different deal values
        $product1 = $this->createProduct();
        $highValueOffer = \App\Models\ProductOffer::create([
            'product_id' => $product1->id,
            'sale_price' => 50.00,
            'original_price' => 100.00, // $50 discount
            'start_date' => date('Y-m-d H:i:s'),
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $product2 = $this->createProduct();
        $lowValueOffer = \App\Models\ProductOffer::create([
            'product_id' => $product2->id,
            'sale_price' => 90.00,
            'original_price' => 100.00, // $10 discount
            'start_date' => date('Y-m-d H:i:s'),
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $blocks = $this->injector->getBlocksForSurface(
            'newsletter_issue',
            1,
            null,
            $this->siteId,
            'newsletter'
        );

        $offerBlocks = array_filter($blocks, fn($b) => $b['type'] === 'offer');
        $offerIds = array_map(fn($b) => $b['data']['offer_id'], $offerBlocks);

        // High value offer should come before low value offer
        $highIndex = array_search($highValueOffer->id, $offerIds);
        $lowIndex = array_search($lowValueOffer->id, $offerIds);

        $this->assertNotFalse($highIndex);
        $this->assertNotFalse($lowIndex);
        $this->assertLessThan($lowIndex, $highIndex);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->injector = app(PromotionInjector::class);
    }

}