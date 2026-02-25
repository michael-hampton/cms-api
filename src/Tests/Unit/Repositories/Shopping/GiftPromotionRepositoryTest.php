<?php

namespace App\Tests\Unit\Repositories\Shopping;


use App\Enums\Gifts\GiftTriggerOperator;
use App\Enums\Gifts\GiftTriggerType;
use App\Enums\Gifts\GiftType;
use App\Models\GiftPromotion;
use App\Models\GiftPromotionTrigger;
use App\Repositories\Shopping\GiftPromotionRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class GiftPromotionRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private GiftPromotionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = app(GiftPromotionRepository::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Candidate Promotion Queries
    |--------------------------------------------------------------------------
    */

    public function test_can_find_candidate_promotions_for_cart(): void
    {
        $product = $this->createProduct();

        $promotion = GiftPromotion::create([
            'gift_type' => GiftType::PRODUCT->value,
            'active' => true,
            'merchant_id' => null,
            'gift_product_id' => $product->id,
            'gift_subscription_plan_id' => null
        ]);

        GiftPromotionTrigger::create([
            'type' => GiftTriggerType::PRODUCT->value,
            'operator' => GiftTriggerOperator::EQUALS->value,
            'reference_id' => 1,
            'promotion_id' => $promotion->id
        ]);

        $results = $this->repo->findCandidatesForCart(
            productIds: [1],
            subscriptionPlanIds: [],
            categoryIds: [],
            merchantIds: []
        );

        $this->assertNotEmpty($results);
        $this->assertEquals($promotion->id, $results->first()->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Creation With Triggers
    |--------------------------------------------------------------------------
    */

    public function test_can_create_promotion_with_triggers(): void
    {
        $product = $this->createProduct();

        $id = $this->repo->createWithTriggers(
            [
                'gift_type' => GiftType::PRODUCT->value,
                'gift_product_id' => $product->id
            ],
            [
                [
                    'type' => GiftTriggerType::PRODUCT->value,
                    'operator' => GiftTriggerOperator::EQUALS->value,
                    'reference_id' => 1
                ]
            ]
        );

        $this->assertDatabaseHas('gift_promotions', [
            'id' => $id
        ]);

        $this->assertDatabaseCount('gift_promotion_triggers', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Tests
    |--------------------------------------------------------------------------
    */

    public function test_cannot_create_promotion_with_invalid_target(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repo->createWithTriggers(
            [
                'gift_type' => GiftType::PRODUCT->value
                // Missing target product_id intentionally
            ],
            []
        );
    }

    public function test_first_time_buyer_trigger_must_use_equals_operator(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repo->createWithTriggers(
            [
                'gift_type' => GiftType::PRODUCT->value,
                'gift_product_id' => 1
            ],
            [
                [
                    'type' => GiftTriggerType::FIRST_TIME_BUYER->value,
                    'operator' => GiftTriggerOperator::GREATER_THAN_OR_EQUAL->value
                ]
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Lifecycle Tests
    |--------------------------------------------------------------------------
    */

    public function test_can_deactivate_promotion(): void
    {
        $promotion = $this->createPromotion([
            'active' => true
        ]);

        $this->repo->deactivate($promotion->id);

        $this->assertDatabaseHas('gift_promotions', [
            'id' => $promotion->id,
            'active' => false
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Trigger Loading Tests
    |--------------------------------------------------------------------------
    */

    public function test_can_load_triggers_grouped_by_promotion(): void
    {
        $promotion = $this->createPromotion();

        GiftPromotionTrigger::createMany([
            [
                'type' => GiftTriggerType::PRODUCT->value,
                'operator' => GiftTriggerOperator::EQUALS->value,
                'promotion_id' => $promotion->id
            ],
            [
                'type' => GiftTriggerType::CART_TOTAL->value,
                'operator' => GiftTriggerOperator::GREATER_THAN_OR_EQUAL->value,
                'value' => 100,
                'promotion_id' => $promotion->id
            ]
        ]);

        $triggers = $this->repo->findTriggersForPromotions([$promotion->id])->toArray();

        $this->assertArrayHasKey($promotion->id, $triggers);
        $this->assertCount(2, $triggers[$promotion->id]);
    }
}