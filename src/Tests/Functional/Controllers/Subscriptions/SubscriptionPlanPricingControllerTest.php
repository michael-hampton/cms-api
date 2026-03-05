<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\Model;
use App\Models\SubscriptionPlanPricing;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionPlanPricingControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $plan;

    public function testIndexReturnsPricingTiers(): void
    {
        $pricing1 = $this->createPricingTier(['label' => '1 Month', 'duration_months' => 1]);
        $pricing2 = $this->createPricingTier(['label' => '6 Months', 'duration_months' => 6, 'sort_order' => 1]);

        $response = $this->getForSite("/api/subscription-plans/{$this->plan->id}/pricing");

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertCount(2, $responseData['data']);
    }

    private function createPricingTier(array $overrides = []): Model
    {
        return SubscriptionPlanPricing::create(array_merge([
            'plan_id' => $this->plan->id,
            'duration_months' => 1,
            'issue_count' => 1,
            'price' => 9.99,
            'label' => 'Standard',
            'period_description' => 'per month',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0
        ], $overrides));
    }

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

    public function testStoreValidatesRequiredFields(): void
    {
        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            []
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreValidatesNumericFields(): void
    {
        $data = [
            'duration_months' => 'invalid',
            'issue_count' => 12,
            'price' => 99.99
        ];

        $response = $this->postForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing",
            $data
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdatePricingTier(): void
    {
        $pricing = $this->createPricingTier();

        $data = [
            'price' => 89.99,
            'discount_percentage' => 20,
            'label' => 'Updated Label',
            'duration_months' => 5,
            'issue_count' => 5,
            'currency' => 'GBP'
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

        $pricing1 = $pricing1->fresh();
        $pricing2 = $pricing2->fresh();

        $this->assertFalse($pricing1->is_default);
        $this->assertTrue($pricing2->is_default);
    }

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

        $pricing1 = $pricing1->fresh();
        $this->assertFalse($pricing1->is_active);
    }

    public function testUpdateSortOrder(): void
    {
        $pricing1 = $this->createPricingTier(['sort_order' => 0, 'duration_months' => 1]);
        $pricing2 = $this->createPricingTier(['sort_order' => 1, 'duration_months' => 2]);
        $pricing3 = $this->createPricingTier(['sort_order' => 2, 'duration_months' => 3]);

        $data = [
            'order' => [
                $pricing3->id => 0,
                $pricing1->id => 1,
                $pricing2->id => 2
            ]
        ];

        $response = $this->putForSite(
            "/api/subscription-plans/{$this->plan->id}/pricing/sort-order",
            $data
        );

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);

        $this->assertTrue($responseData['success']);

        $pricing1 = $pricing1->fresh();
        $pricing2 = $pricing2->fresh();
        $pricing3 = $pricing3->fresh();

        $this->assertEquals(1, $pricing1->sort_order);
        $this->assertEquals(2, $pricing2->sort_order);
        $this->assertEquals(0, $pricing3->sort_order);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->plan = $this->createSubscriptionPlan(['stripe_product_id' => 'test']);
    }
}