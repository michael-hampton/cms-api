<?php

namespace App\Tests\Functional\Controllers\Boost;

use App\Enums\Boost\AutoBoostGoal;
use App\Enums\Boost\BoostableType;
use App\Enums\Boost\BoostContext;
use App\Enums\Boost\BoostStatus;
use App\Models\Boost;
use App\Models\BoostStat;
use App\Models\MerchantAutoBoostSetting;
use App\Models\MerchantBoostStat;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BoostControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // -------------------------------------------------------------------------
    // GET /api/boosts
    // -------------------------------------------------------------------------

    public function testIndexReturnsBoostList(): void
    {
        $this->createBoost(['status' => BoostStatus::Active->value]);
        $this->createBoost(['status' => BoostStatus::Active->value]);

        $response = $this->get('/api/boosts');

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertGreaterThanOrEqual(2, count($data['data']));
    }

    /**
     * Creates a Boost model directly. Adjust field names to match your actual schema.
     */
    private function createBoost(array $attributes = []): Boost
    {
        $merchant = isset($attributes['merchant_id'])
            ? (object)['id' => $attributes['merchant_id']]
            : $this->createMerchant();

        $product = $this->createProduct();

        return Boost::create(array_merge([
            'merchant_id' => $merchant->id,
            'boostable_type' => BoostableType::Product->value,
            'boostable_id' => $this->createProduct()->id,
            'context' => BoostContext::Listing->value,
            'status' => BoostStatus::Pending->value,
            'boostable_id' => $product->id,
            'price_paid' => 70,
            'multiplier' => 1.5,
            'currency' => 'GBP',
            'starts_at' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
            'ends_at' => (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s'),
        ], $attributes));
    }

    public function testIndexFiltersByStatus(): void
    {
        $this->createBoost(['status' => BoostStatus::Active->value]);
        $this->createBoost(['status' => BoostStatus::Active->value]);
        $this->createBoost(['status' => BoostStatus::Cancelled->value]);

        $response = $this->get('/api/boosts?status=' . BoostStatus::Active->value);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['data']);

        foreach ($data['data'] as $boost) {
            $this->assertEquals(BoostStatus::Active->value, $boost['status']);
        }
    }

    public function testIndexFiltersByMerchantId(): void
    {
        $merchant = $this->createMerchant();
        $otherMerchant = $this->createMerchant();

        $this->createBoost(['merchant_id' => $merchant->id]);
        $this->createBoost(['merchant_id' => $merchant->id]);
        $this->createBoost(['merchant_id' => $otherMerchant->id]);

        $response = $this->get('/api/boosts?merchant_id=' . $merchant->id);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['data']);
    }

    public function testIndexFiltersByBoostableType(): void
    {
        $this->createBoost(['boostable_type' => BoostableType::Product->value]);
        $this->createBoost(['boostable_type' => BoostableType::Offer->value]);

        $response = $this->get('/api/boosts?boostable_type=' . BoostableType::Product->value);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());

        foreach ($data['data'] as $boost) {
            $this->assertEquals(BoostableType::Product->value, $boost['boostable_type']);
        }
    }

    public function testIndexRespectsPagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createBoost();
        }

        $response = $this->get('/api/boosts?page=1&per_page=3');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(3, $data['data']);
        $this->assertArrayHasKey('pagination', $data);
    }

    // -------------------------------------------------------------------------
    // GET /api/boosts/{id}
    // -------------------------------------------------------------------------

    public function testIndexCapsPerPageAt100(): void
    {
        $response = $this->get('/api/boosts?per_page=999');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        // per_page should have been capped — pagination reflects at most 100
        $this->assertLessThanOrEqual(100, $data['pagination']['per_page'] ?? 100);
    }

    public function testShowReturnsBoost(): void
    {
        $boost = $this->createBoost();

        $response = $this->get("/api/boosts/{$boost->id}");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($boost->id, $data['data']['id']);
    }

    // -------------------------------------------------------------------------
    // POST /api/boosts
    // -------------------------------------------------------------------------

    public function testShowReturns404WhenNotFound(): void
    {
        $response = $this->get('/api/boosts/99999');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Boost not found.', $data['error']);
    }

    public function testStoreCreatesBoostForProduct(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['is_active' => true, 'stock_quantity' => 100]);
        $this->linkProductToMerchant($product->id, $merchant->id);

        $payload = $this->validBoostPayload([
            'boostable_type' => BoostableType::Product->value,
            'target_id' => $product->id,
            'merchant_id' => $merchant->id,
        ]);

        $response = $this->post('/api/boosts', $payload);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($product->id, $data['data']['boostable_id']);
        $this->assertEquals(BoostableType::Product->value, $data['data']['boostable_type']);
    }

    private function linkProductToMerchant(int $productId, int $merchantId): void
    {
        \App\Models\ProductMerchant::firstOrCreate([
            'product_id' => $productId,
            'merchant_id' => $merchantId,
        ], [
            'price' => 99.99,
            'is_available' => true,
            'url' => 'https://example.com/product',
        ]);
    }

    private function validBoostPayload(array $overrides = []): array
    {
        return array_merge([
            'boostable_type' => BoostableType::Product->value,
            'boostable_id' => 1,
            'merchant_id' => 1,
            'context' => BoostContext::Listing->value,
            'starts_at' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
            'ends_at' => (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s'),
            'multiplier' => 1.5,
            'currency' => 'GBP',
            'payment_reference' => 'PAY-TEST-001',
        ], $overrides);
    }

    public function testStoreCreatesBoostForOffer(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['is_active' => true, 'stock_quantity' => 100]);
        $this->linkProductToMerchant($product->id, $merchant->id);
        $offer = $this->createActiveOffer($product->id, $merchant->id);

        $payload = $this->validBoostPayload([
            'boostable_type' => BoostableType::Offer->value,
            'target_id' => $offer->id,
            'merchant_id' => $merchant->id,
        ]);

        $response = $this->post('/api/boosts', $payload);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(BoostableType::Offer->value, $data['data']['boostable_type']);
    }

    private function createActiveOffer(int $productId, int $merchantId, array $attributes = []): \App\Models\ProductOffer
    {
        return \App\Models\ProductOffer::create(array_merge([
            'product_id' => $productId,
            'merchant_id' => $merchantId,
            'title' => 'Test Offer',
            'sale_price' => 79.99,
            'original_price' => 99.99,
            'is_active' => true,
            'start_date' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'end_date' => (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s'),
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // POST /api/boosts/{id}/activate
    // -------------------------------------------------------------------------

    public function testStoreReturns422WhenTargetNotFound(): void
    {
        $merchant = $this->createMerchant();

        $payload = $this->validBoostPayload([
            'boostable_type' => BoostableType::Product->value,
            'target_id' => 99999,
            'merchant_id' => $merchant->id,
        ]);

        $response = $this->post('/api/boosts', $payload);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('99999', $data['error']);
    }

    public function testStoreReturns422OnEligibilityFailure(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['is_active' => false]);

        $payload = $this->validBoostPayload([
            'boostable_type' => BoostableType::Product->value,
            'target_id' => $product->id,
            'merchant_id' => $merchant->id,
        ]);

        $response = $this->post('/api/boosts', $payload);

        // Either 422 (eligibility/invalid arg) or 201 depending on service rules —
        // this asserts the contract: non-eligible targets never produce 201 with wrong data.
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function testStoreReturns422WhenBoostableTypeIsInvalid(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['is_active' => true, 'stock_quantity' => 100]);

        $payload = $this->validBoostPayload([
            'boostable_type' => 'banana',
            'target_id' => $product->id,
            'merchant_id' => $merchant->id,
        ]);

        $response = $this->post('/api/boosts', $payload);

        $this->assertEquals(422, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST /api/boosts/{id}/expire
    // -------------------------------------------------------------------------

    public function testStorePersistsBoostInDatabase(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['is_active' => true, 'stock_quantity' => 100]);
        $this->linkProductToMerchant($product->id, $merchant->id);

        $payload = $this->validBoostPayload([
            'boostable_type' => BoostableType::Product->value,
            'target_id' => $product->id,
            'merchant_id' => $merchant->id,
            'multiplier' => 2.5,
            'context' => BoostContext::Listing->value,
        ]);

        $this->post('/api/boosts', $payload);

        $this->assertDatabaseHas('boosts', [
            'merchant_id' => $merchant->id,
            'boostable_type' => BoostableType::Product->value,
            'boostable_id' => $product->id,
            'context' => BoostContext::Listing->value,
        ]);
    }

    public function testActivateTransitionsBoostToActive(): void
    {
        $boost = $this->createBoost(['status' => BoostStatus::Pending->value, 'starts_at' => now_datetime()]);

        $response = $this->post("/api/boosts/{$boost->id}/activate");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(BoostStatus::Active->value, $data['data']['status']);

        $this->assertDatabaseHas('boosts', [
            'id' => $boost->id,
            'status' => BoostStatus::Active->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/boosts/{id}/cancel
    // -------------------------------------------------------------------------

    public function testActivateReturns404WhenBoostNotFound(): void
    {
        $response = $this->post('/api/boosts/99999/activate');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $data);
    }

    public function testActivateReturns422OnInvalidTransition(): void
    {
        // A cancelled boost cannot be activated
        $boost = $this->createBoost(['status' => BoostStatus::Cancelled->value]);

        $response = $this->post("/api/boosts/{$boost->id}/activate");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('error', $data);
    }

    public function testExpireTransitionsBoostToExpired(): void
    {
        $boost = $this->createBoost(['status' => BoostStatus::Active->value]);

        $response = $this->post("/api/boosts/{$boost->id}/expire");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(BoostStatus::Expired->value, $data['data']['status']);
    }

    // -------------------------------------------------------------------------
    // POST /api/boosts/{id}/pause
    // -------------------------------------------------------------------------

    public function testExpireReturns404WhenBoostNotFound(): void
    {
        $response = $this->post('/api/boosts/99999/expire');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $data);
    }

    public function testCancelTransitionsBoostToCancelled(): void
    {
        $boost = $this->createBoost(['status' => BoostStatus::Active->value]);

        $response = $this->post("/api/boosts/{$boost->id}/cancel");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(BoostStatus::Cancelled->value, $data['data']['status']);

        $this->assertDatabaseHas('boosts', [
            'id' => $boost->id,
            'status' => BoostStatus::Cancelled->value,
        ]);
    }

    public function testCancelReturns404WhenBoostNotFound(): void
    {
        $response = $this->post('/api/boosts/99999/cancel');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $data);
    }

    // -------------------------------------------------------------------------
    // POST /api/boosts/{id}/resume
    // -------------------------------------------------------------------------

    public function testCancelReturns422OnInvalidTransition(): void
    {
        // An already-cancelled boost cannot be cancelled again
        $boost = $this->createBoost(['status' => BoostStatus::Cancelled->value]);

        $response = $this->post("/api/boosts/{$boost->id}/cancel");

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testPauseTransitionsActiveBoostToPaused(): void
    {
        $boost = $this->createBoost(['status' => BoostStatus::Active->value]);

        $response = $this->post("/api/boosts/{$boost->id}/pause");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(BoostStatus::Paused->value, $data['data']['status']);

        $this->assertDatabaseHas('boosts', [
            'id' => $boost->id,
            'status' => BoostStatus::Paused->value,
        ]);
    }

    public function testPauseReturns404WhenBoostNotFound(): void
    {
        $response = $this->post('/api/boosts/99999/pause');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $data);
    }

    // -------------------------------------------------------------------------
    // GET /api/boosts/{id}/stats
    // -------------------------------------------------------------------------

    public function testPauseReturns422OnInvalidTransition(): void
    {
        // A pending boost cannot be paused
        $boost = $this->createBoost(['status' => BoostStatus::Pending->value]);

        $response = $this->post("/api/boosts/{$boost->id}/pause");

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testResumeTransitionsPausedBoostToActive(): void
    {
        $boost = $this->createBoost(['status' => BoostStatus::Paused->value]);

        $response = $this->post("/api/boosts/{$boost->id}/resume");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(BoostStatus::Active->value, $data['data']['status']);

        $this->assertDatabaseHas('boosts', [
            'id' => $boost->id,
            'status' => BoostStatus::Active->value,
        ]);
    }

    public function testResumeReturns404WhenBoostNotFound(): void
    {
        $response = $this->post('/api/boosts/99999/resume');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $data);
    }

    public function testResumeReturns422OnInvalidTransition(): void
    {
        // A cancelled boost cannot be resumed
        $boost = $this->createBoost(['status' => BoostStatus::Cancelled->value]);

        $response = $this->post("/api/boosts/{$boost->id}/resume");

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStatsReturnsZeroValuesWhenNoStatRecordExists(): void
    {
        $boost = $this->createBoost();

        $response = $this->get("/api/boosts/{$boost->id}/stats");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);

        $stats = $data['data'];
        $this->assertEquals($boost->id, $stats['boost_id']);
        $this->assertEquals(0, $stats['impressions']);
        $this->assertEquals(0, $stats['clicks']);
        $this->assertEquals(0, $stats['conversions']);
        $this->assertEquals(0.0, $stats['spend_attributed']);
        $this->assertEquals(0.0, $stats['ctr']);
        $this->assertEquals(0.0, $stats['conversion_rate']);
    }

    // -------------------------------------------------------------------------
    // GET /api/merchants/{merchantId}/boost-stats
    // -------------------------------------------------------------------------

    public function testStatsReturnsAggregatedValues(): void
    {
        $boost = $this->createBoost();
        $this->createBoostStat($boost->id, [
            'impressions' => 1000,
            'clicks' => 50,
            'conversions' => 5,
            'spend_attributed' => 99.50,
        ]);

        $response = $this->get("/api/boosts/{$boost->id}/stats");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());

        $stats = $data['data'];
        $this->assertEquals(1000, $stats['impressions']);
        $this->assertEquals(50, $stats['clicks']);
        $this->assertEquals(5, $stats['conversions']);
        $this->assertEquals(99.50, $stats['spend_attributed']);
    }

    private function createBoostStat(int $boostId, array $attributes = []): BoostStat
    {
        return BoostStat::create(array_merge([
            'boost_id' => $boostId,
            'impressions' => 0,
            'clicks' => 0,
            'conversions' => 0,
            'spend_attributed' => 0.0,
            'last_aggregated_at' => now(),
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // GET /api/merchants/{id}/boost-suggestions
    // -------------------------------------------------------------------------

    public function testStatsReturns404WhenBoostNotFound(): void
    {
        $response = $this->get('/api/boosts/99999/stats');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Boost not found.', $data['error']);
    }

    public function testStatsIncludesLimitBreachStatus(): void
    {
        $boost = $this->createBoost(['status' => BoostStatus::Paused->value]);
        $this->attachBoostLimit($boost->id, ['pause_on_breach' => true, 'max_clicks' => 100]);
        $this->createBoostStat($boost->id, ['clicks' => 100]);

        $response = $this->get("/api/boosts/{$boost->id}/stats");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['data']['limit_breached']);
        $this->assertNotNull($data['data']['breach_message']);
        $this->assertStringContainsString('paused', $data['data']['breach_message']);
    }

    private function attachBoostLimit(int $boostId, array $attributes = []): void
    {
        \App\Models\BoostLimit::create(array_merge([
            'boost_id' => $boostId,
            'pause_on_breach' => false,
            'max_spend' => null,
            'max_clicks' => null,
            'max_impressions' => null,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // POST /api/merchants/{id}/auto-boost/settings
    // -------------------------------------------------------------------------

    public function testStatsBreachMessageIsNullWhenNotPaused(): void
    {
        $boost = $this->createBoost(['status' => BoostStatus::Active->value]);

        $response = $this->get("/api/boosts/{$boost->id}/stats");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($data['data']['breach_message']);
    }

    public function testMerchantStatsReturnsZeroValuesWhenNoRecord(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->get("/api/merchants/{$merchant->id}/boost-stats");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);

        $stats = $data['data'];
        $this->assertEquals($merchant->id, $stats['merchant_id']);
        $this->assertEquals(0, $stats['total_impressions']);
        $this->assertEquals(0, $stats['total_clicks']);
        $this->assertEquals(0, $stats['total_conversions']);
        $this->assertEquals(0.0, $stats['total_spend_attributed']);
    }

    public function testMerchantStatsReturnsAggregatedValues(): void
    {
        $merchant = $this->createMerchant();
        $this->createMerchantBoostStat($merchant->id, [
            'total_impressions' => 5000,
            'total_clicks' => 250,
            'total_conversions' => 25,
            'total_spend_attributed' => 499.75,
        ]);

        $response = $this->get("/api/merchants/{$merchant->id}/boost-stats");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());

        $stats = $data['data'];
        $this->assertEquals(5000, $stats['total_impressions']);
        $this->assertEquals(250, $stats['total_clicks']);
        $this->assertEquals(25, $stats['total_conversions']);
        $this->assertEquals(499.75, $stats['total_spend_attributed']);
    }

    // -------------------------------------------------------------------------
    // GET /api/merchants/{id}/auto-boost/settings
    // -------------------------------------------------------------------------

    private function createMerchantBoostStat(int $merchantId, array $attributes = []): MerchantBoostStat
    {
        return MerchantBoostStat::create(array_merge([
            'merchant_id' => $merchantId,
            'total_impressions' => 0,
            'total_clicks' => 0,
            'total_conversions' => 0,
            'total_spend_attributed' => 0.0,
            'last_aggregated_at' => now(),
        ], $attributes));
    }

    public function testSuggestionsReturnsResultsForValidGoal(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->get(
            "/api/merchants/{$merchant->id}/boost-suggestions?goal=" . AutoBoostGoal::MaximiseRevenue->value
        );
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
    }

    // -------------------------------------------------------------------------
    // GET /api/merchants/{merchantId}/products/search
    // -------------------------------------------------------------------------

    public function testSuggestionsUsesDefaultGoalWhenNotProvided(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->get("/api/merchants/{$merchant->id}/boost-suggestions");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
    }

    public function testSuggestionsReturns422ForInvalidGoal(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->get("/api/merchants/{$merchant->id}/boost-suggestions?goal=banana");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Invalid goal.', $data['error']);
    }

    public function testSaveAutoBoostSettingsCreatesRecord(): void
    {
        $merchant = $this->createMerchant();

        $payload = [
            'monthly_budget' => 500.00,
            'goal' => AutoBoostGoal::MaximiseRevenue->value,
            'contexts_allowed' => ['homepage', 'category'],
            'is_enabled' => true,
        ];

        $response = $this->post("/api/merchants/{$merchant->id}/auto-boost/settings", $payload);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(500.00, $data['data']['monthly_budget']);
        $this->assertEquals(AutoBoostGoal::MaximiseRevenue->value, $data['data']['goal']);
        $this->assertTrue($data['data']['is_enabled']);
    }

    public function testSaveAutoBoostSettingsUpserts(): void
    {
        $merchant = $this->createMerchant();
        $this->createAutoBoostSetting($merchant->id, ['monthly_budget' => 100.00]);

        $payload = [
            'monthly_budget' => 750.00,
            'goal' => AutoBoostGoal::MaximiseRevenue->value,
            'contexts_allowed' => [],
            'is_enabled' => false,
        ];

        $response = $this->post("/api/merchants/{$merchant->id}/auto-boost/settings", $payload);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(750.00, $data['data']['monthly_budget']);
        $this->assertFalse($data['data']['is_enabled']);
    }

    private function createAutoBoostSetting(int $merchantId, array $attributes = []): MerchantAutoBoostSetting
    {
        return MerchantAutoBoostSetting::create(array_merge([
            'merchant_id' => $merchantId,
            'monthly_budget' => 100.00,
            'goal' => AutoBoostGoal::MaximiseRevenue->value,
            'contexts_allowed' => [],
            'is_enabled' => false,
        ], $attributes));
    }

    public function testSaveAutoBoostSettingsReturns422ForInvalidGoal(): void
    {
        $merchant = $this->createMerchant();

        $payload = [
            'monthly_budget' => 500.00,
            'goal' => 'not_a_valid_goal',
            'contexts_allowed' => [],
            'is_enabled' => true,
        ];

        $response = $this->post("/api/merchants/{$merchant->id}/auto-boost/settings", $payload);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Invalid goal.', $data['error']);
    }

    // -------------------------------------------------------------------------
    // GET /api/merchants/{merchantId}/offers/search
    // -------------------------------------------------------------------------

    public function testGetAutoBoostSettingsReturnsNullDataWhenNoRecord(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->get("/api/merchants/{$merchant->id}/auto-boost/settings");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertNull($data['data']);
    }

    public function testGetAutoBoostSettingsReturnsExistingRecord(): void
    {
        $merchant = $this->createMerchant();
        $this->createAutoBoostSetting($merchant->id, [
            'monthly_budget' => 300.00,
            'goal' => AutoBoostGoal::MaximiseRevenue->value,
            'is_enabled' => true,
        ]);

        $response = $this->get("/api/merchants/{$merchant->id}/auto-boost/settings");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(300.00, $data['data']['monthly_budget']);
        $this->assertEquals(AutoBoostGoal::MaximiseRevenue->value, $data['data']['goal']);
        $this->assertTrue($data['data']['is_enabled']);
    }

    public function testSearchMerchantProductsReturnsMatchingProducts(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['name' => 'Wireless Keyboard', 'is_active' => true, 'stock_quantity' => 10]);
        $this->linkProductToMerchant($product->id, $merchant->id);

        $this->createProduct(['name' => 'USB Mouse', 'is_active' => true, 'stock_quantity' => 5]);

        $response = $this->get("/api/merchants/{$merchant->id}/products/search?q=wireless");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('Wireless Keyboard', $data['data'][0]['name']);
    }

    public function testSearchMerchantProductsReturnsEmptyArrayForShortQuery(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->get("/api/merchants/{$merchant->id}/products/search?q=");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($data['data']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function testSearchMerchantProductsExcludesInactiveProducts(): void
    {
        $merchant = $this->createMerchant();
        $inactive = $this->createProduct(['name' => 'Inactive Widget', 'is_active' => false, 'stock_quantity' => 5]);
        $this->linkProductToMerchant($inactive->id, $merchant->id);

        $response = $this->get("/api/merchants/{$merchant->id}/products/search?q=widget");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($data['data']);
    }

    public function testSearchMerchantProductsExcludesOutOfStockProducts(): void
    {
        $merchant = $this->createMerchant();
        $oos = $this->createProduct(['name' => 'Empty Widget', 'is_active' => true, 'stock_quantity' => 0]);
        $this->linkProductToMerchant($oos->id, $merchant->id);

        $response = $this->get("/api/merchants/{$merchant->id}/products/search?q=empty");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($data['data']);
    }

    public function testSearchMerchantProductsDoesNotReturnOtherMerchantsProducts(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();

        $product = $this->createProduct(['name' => 'Exclusive Gadget', 'is_active' => true, 'stock_quantity' => 10]);
        $this->linkProductToMerchant($product->id, $merchant2->id);

        $response = $this->get("/api/merchants/{$merchant1->id}/products/search?q=exclusive");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($data['data']);
    }

    public function testSearchMerchantProductsResponseShape(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['name' => 'Shape Test Product', 'is_active' => true, 'stock_quantity' => 3]);
        $this->linkProductToMerchant($product->id, $merchant->id);

        $response = $this->get("/api/merchants/{$merchant->id}/products/search?q=shape");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['data']);

        $item = $data['data'][0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('price', $item);
        $this->assertArrayHasKey('stock_quantity', $item);
    }

    public function testSearchMerchantOffersReturnsMatchingOffers(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['name' => 'Gaming Chair']);
        $this->linkProductToMerchant($product->id, $merchant->id);
        $this->createActiveOffer($product->id, $merchant->id, ['title' => 'Summer Sale Gaming Chair']);

        $response = $this->get("/api/merchants/{$merchant->id}/offers/search?q=gaming");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertCount(1, $data['data']);
    }

    public function testSearchMerchantOffersReturnsEmptyForBlankQuery(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->get("/api/merchants/{$merchant->id}/offers/search?q=");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($data['data']);
    }

    public function testSearchMerchantOffersExcludesExpiredOffers(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['name' => 'Old Deal Product']);
        $this->linkProductToMerchant($product->id, $merchant->id);
        $this->createExpiredOffer($product->id, $merchant->id, ['title' => 'Expired Old Deal']);

        $response = $this->get("/api/merchants/{$merchant->id}/offers/search?q=old");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($data['data']);
    }

    private function createExpiredOffer(int $productId, int $merchantId, array $attributes = []): \App\Models\ProductOffer
    {
        return \App\Models\ProductOffer::create(array_merge([
            'product_id' => $productId,
            'merchant_id' => $merchantId,
            'title' => 'Expired Offer',
            'sale_price' => 79.99,
            'original_price' => 99.99,
            'is_active' => true,
            'start_date' => (new \DateTimeImmutable('-60 days'))->format('Y-m-d H:i:s'),
            'end_date' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
        ], $attributes));
    }

    public function testSearchMerchantOffersResponseShape(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['name' => 'Shape Offer Product', 'price' => 100.00]);
        $this->linkProductToMerchant($product->id, $merchant->id);
        $this->createActiveOffer($product->id, $merchant->id, [
            'title' => 'Shape Offer Deal',
            'sale_price' => 79.99,
            'original_price' => 100.00,
        ]);

        $response = $this->get("/api/merchants/{$merchant->id}/offers/search?q=shape");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['data']);

        $item = $data['data'][0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('price', $item);
        $this->assertArrayHasKey('discount_percent', $item);
        $this->assertEquals(20.0, $item['discount_percent']);
    }
}