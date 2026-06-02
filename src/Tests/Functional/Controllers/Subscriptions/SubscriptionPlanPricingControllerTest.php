<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Framework\Container;
use App\Models\Model;
use App\Models\SubscriptionPlanPricing;
use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripeProductGatewayInterface;
use App\Services\Billing\Stripe\NullStripePriceGateway;
use App\Services\Billing\Stripe\NullStripeProductGateway;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionPlanPricingControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plan = $this->createSubscriptionPlan(['stripe_product_id' => 'test']);

        $container = Container::getInstance();

        $container->bind(StripePriceGatewayInterface::class, NullStripePriceGateway::class);
        $container->bind(StripeProductGatewayInterface::class, NullStripeProductGateway::class);
    }

    // =========================================================================
    // GET /api/subscription-plans/{planId}/pricing
    // =========================================================================

    public function testIndexReturnsPricingTiers(): void
    {
        $pricing1 = $this->createPricingTier(['label' => '1 Month', 'duration_months' => 1]);
        $pricing2 = $this->createPricingTier(['label' => '6 Months', 'duration_months' => 6, 'sort_order' => 1]);

        $response = $this->getForSite("/api/subscription-plans/{$this->plan->id}/pricing");

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertCount(2, $responseData['items']);
    }

    public function testIndexFiltersResultsByStatus(): void
    {
        $this->createPricingTier(['is_active' => true, 'duration_months' => 1]);
        $this->createPricingTier(['is_active' => false, 'duration_months' => 2, 'sort_order' => 1]);

        $response = $this->getForSite("/api/subscription-plans/{$this->plan->id}/pricing?status=active");

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertCount(1, $responseData['items']);
        $this->assertTrue($responseData['items'][0]['is_active']);
    }

    // =========================================================================
    // POST /api/subscription-plans/{planId}/pricing — happy path
    // =========================================================================

    public function testStoreCreatesPricingTier(): void
    {
        $data = [
            'duration_months' => 12,
            'issue_count' => 12,
            'price' => 99.99,
            'original_price' => 120.00,
            'discount_percentage' => 17,
            'label' => 'Annual',
            'period_description' => 'per year',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'currency' => 'GBP',
        ];

        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            $data
        );

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Annual', $responseData['data']['label']);

        $pricing = SubscriptionPlanPricing::where('plan_id', $this->plan->id)
            ->where('label', 'Annual')
            ->first();

        $this->assertNotNull($pricing);
        $this->assertEquals(99.99, $pricing->price);
        $this->assertEquals(12, $pricing->duration_months);
        $this->assertTrue($pricing->is_default);
    }

    // =========================================================================
    // POST — CreatePricingTierRequest validation
    // =========================================================================

    public function testStoreRequiresDurationMonths(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            ['issue_count' => 1, 'price' => 9.99]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRequiresIssueCount(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            ['duration_months' => 1, 'price' => 9.99]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRequiresPrice(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            ['duration_months' => 1, 'issue_count' => 1]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsDurationMonthsBelow1(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            ['duration_months' => 0, 'issue_count' => 1, 'price' => 9.99]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsIssueCountBelow1(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            ['duration_months' => 1, 'issue_count' => 0, 'price' => 9.99]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsPriceBelow0(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            ['duration_months' => 1, 'issue_count' => 1, 'price' => -1]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsNonNumericDurationMonths(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            ['duration_months' => 'invalid', 'issue_count' => 1, 'price' => 9.99]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsDiscountPercentageAbove100(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            ['duration_months' => 1, 'issue_count' => 1, 'price' => 9.99, 'discount_percentage' => 101]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreRejectsDiscountPercentageBelow0(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            ['duration_months' => 1, 'issue_count' => 1, 'price' => 9.99, 'discount_percentage' => -1]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreAcceptsNullableOptionalFields(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            [
                'duration_months' => 1,
                'issue_count' => 1,
                'price' => 9.99,
                'original_price' => null,
                'digital_price' => null,
                'discount_percentage' => null,
                'label' => 'test',
                'period_description' => 'test',
                'is_default' => null,
                'is_active' => null,
                'sort_order' => 1,
                'currency' => 'GBP',
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testStoreValidatesEmptyPayload(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            []
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    // =========================================================================
    // PUT /api/subscription-plans/{planId}/pricing/{id} — happy path
    // =========================================================================

    public function testUpdatePricingTier(): void
    {
        $pricing = $this->createPricingTier();

        $data = [
            'price' => 89.99,
            'discount_percentage' => 20,
            'label' => 'Updated Label',
            'duration_months' => 5,
            'issue_count' => 5,
            'currency' => 'GBP',
        ];

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            $data
        );

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Updated Label', $responseData['data']['label']);
        $this->assertEquals(89.99, $responseData['data']['price']);
        $this->assertEquals(20, $responseData['data']['discount_percentage']);
    }

    public function testUpdateAllowsNullableFieldsToBeNull(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            array_merge($this->validUpdatePayload(), [
                'original_price' => null,
                'digital_price' => null,
                'discount_percentage' => null,
                'label' => 'required',
                'period_description' => 'test',
                'is_default' => null,
                'is_active' => null,
                'sort_order' => 0,
                'currency' => 'GBP'
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUpdateRequiresDurationMonths(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            ['issue_count' => 3, 'price' => 29.99]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRequiresIssueCount(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            ['duration_months' => 3, 'price' => 29.99]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRequiresPrice(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            ['duration_months' => 3, 'issue_count' => 3]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsNullDurationMonths(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            $this->validUpdatePayload(['duration_months' => null])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsNullIssueCount(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            $this->validUpdatePayload(['issue_count' => null])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsNullPrice(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            $this->validUpdatePayload(['price' => null])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    // =========================================================================
    // PUT — UpdatePricingTierRequest validation
    // =========================================================================

    public function testUpdateRejectsDurationMonthsBelow1(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            ['duration_months' => 0]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsIssueCountBelow1(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            ['issue_count' => 0]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsPriceBelow0(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            ['price' => -1]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsDiscountPercentageAbove100(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            [
                'discount_percentage' => 101,
                'duration_months' => 12,
                'issue_count' => 2,
                'price' => 20,
                'currency' => 'GBP'
            ]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateRejectsDiscountPercentageBelow0(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            ['discount_percentage' => -1]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateLabelMaxLength100(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            ['label' => str_repeat('x', 101)]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdatePeriodDescriptionMaxLength255(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            ['period_description' => str_repeat('x', 256)]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateValidatesEmptyPayload(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}",
            []
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    // =========================================================================
    // DELETE /api/subscription-plans/{planId}/pricing/{id}
    // =========================================================================

    public function testDestroyDeletesPricingTier(): void
    {
        $pricing1 = $this->createPricingTier(['label' => 'Tier 1', 'duration_months' => 1]);
        $pricing2 = $this->createPricingTier(['label' => 'Tier 2', 'duration_months' => 2, 'sort_order' => 1]);

        $response = $this->deleteForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing1->id}"
        );

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertNull(SubscriptionPlanPricing::find($pricing1->id));
    }

    public function testDestroyFailsWhenOnlyActiveTier(): void
    {
        $pricing = $this->createPricingTier();

        $response = $this->deleteForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing->id}"
        );

        $this->assertEquals(500, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('only active pricing tier', $responseData['message']);
    }

    // =========================================================================
    // POST .../set-default
    // =========================================================================

    public function testSetDefaultPricingTier(): void
    {
        $pricing1 = $this->createPricingTier(['is_default' => true, 'duration_months' => 1]);
        $pricing2 = $this->createPricingTier(['is_default' => false, 'duration_months' => 2, 'sort_order' => 1]);

        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing2->id}/set-default"
        );

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);

        $this->assertFalse($pricing1->fresh()->is_default);
        $this->assertTrue($pricing2->fresh()->is_default);
    }

    // =========================================================================
    // POST .../toggle-active
    // =========================================================================

    public function testToggleActivePricingTier(): void
    {
        $pricing1 = $this->createPricingTier(['is_active' => true, 'duration_months' => 1]);
        $pricing2 = $this->createPricingTier(['is_active' => true, 'duration_months' => 2, 'sort_order' => 1]);

        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/{$pricing1->id}/toggle-active"
        );

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertFalse($pricing1->fresh()->is_active);
    }

    // =========================================================================
    // PUT .../sort-order
    // =========================================================================

    public function testUpdateSortOrder(): void
    {
        $pricing1 = $this->createPricingTier(['sort_order' => 0, 'duration_months' => 1]);
        $pricing2 = $this->createPricingTier(['sort_order' => 1, 'duration_months' => 2]);
        $pricing3 = $this->createPricingTier(['sort_order' => 2, 'duration_months' => 3]);

        $data = [
            'order' => [
                $pricing3->id => 0,
                $pricing1->id => 1,
                $pricing2->id => 2,
            ],
        ];

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/sort-order",
            $data
        );

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);

        $this->assertEquals(1, $pricing1->fresh()->sort_order);
        $this->assertEquals(2, $pricing2->fresh()->sort_order);
        $this->assertEquals(0, $pricing3->fresh()->sort_order);
    }

    private function validUpdatePayload(array $overrides = []): array
    {
        return array_merge([
            'duration_months' => 3,
            'issue_count' => 3,
            'price' => 29.99,
        ], $overrides);
    }
}