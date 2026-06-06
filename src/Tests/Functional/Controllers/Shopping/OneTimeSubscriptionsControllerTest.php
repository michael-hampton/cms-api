<?php

namespace App\Tests\Functional\Controllers\Shopping;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OneTimeSubscriptionsControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_search_uses_effective_pricing_tier_price_instead_of_stale_plan_price(): void
    {
        $plan = $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'name' => 'Tier Priced Magazine',
            'slug' => 'tier-priced-magazine-' . uniqid(),
            'price' => 99.99,
            'is_active' => true,
            'plan_type' => 'onetime',
            'delivery_type' => 'digital',
        ]);

        $this->createPricingTier([
            'plan_id' => $plan->id,
            'price' => 12.50,
            'digital_price' => 12.50,
            'is_default' => true,
            'is_active' => true,
            'currency' => 'GBP',
        ]);

        $response = $this->get(
            '/subscriptions/onetime/search?site_id=' . $this->siteId . '&search=Tier%20Priced%20Magazine',
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']['plans']);
        $this->assertSame(12.50, (float)$data['data']['plans'][0]['price']);
        $this->assertNotSame(99.99, (float)$data['data']['plans'][0]['price']);
    }
}
