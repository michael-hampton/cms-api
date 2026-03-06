<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Adverts;

use App\Models\Merchant;
use App\Models\Product;
use App\Repositories\Adverts\MerchantAnalyticsRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Integration tests for MerchantAnalyticsRepository.
 *
 * Uses the real test database (via FunctionalTestCase) so that every query
 * runs against actual SQL — no mocks, no stubs on the repository itself.
 *
 * offer_clicks  schema assumed:
 *   id, offer_id, action ('click'|'render'), clicked_at
 *
 * deal_clicks schema assumed:
 *   id, product_id, action ('click'|'render'), created_at
 *
 * product_views schema assumed:
 *   id, product_id, user_id, session_id, ip_address, site_id, viewed_at
 */
class MerchantAnalyticsRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private MerchantAnalyticsRepository $repository;
    private Merchant $merchant;
    private Product $product;

    public function test_offerClickTotals_returns_zeros_when_no_data(): void
    {
        $result = $this->repository->offerClickTotals($this->merchant->id, days: 30);

        $this->assertSame(0, $result['click']);
        $this->assertSame(0, $result['render']);
        $this->assertSame(0, $result['total']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function test_offerClickTotals_counts_clicks_and_renders_separately(): void
    {
        // createProductOffer MUST receive merchant_id — without it the join
        // on po.merchant_id will exclude all rows.
        $offer = $this->createProductOffer($this->product->id, [], $this->merchant->id);

        $this->insertOfferClick($offer->id, 'click');
        $this->insertOfferClick($offer->id, 'click');
        $this->insertOfferClick($offer->id, 'render');

        $result = $this->repository->offerClickTotals($this->merchant->id, days: 30);

        $this->assertSame(2, $result['click']);
        $this->assertSame(1, $result['render']);
        $this->assertSame(3, $result['total']);
    }

    /**
     * Insert a row into offer_clicks.
     * $daysAgo = 0 means "right now", 1 means "yesterday", etc.
     */
    private function insertOfferClick(int $offerId, string $action = 'click', int $daysAgo = 0): void
    {
        $ts = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
        $this->database->query(
            'INSERT INTO offer_clicks (offer_id, action, clicked_at) VALUES (?, ?, ?)',
            [$offerId, $action, $ts]
        );
    }

    public function test_offerClickTotals_ignores_clicks_outside_window(): void
    {
        $offer = $this->createProductOffer($this->product->id, [], $this->merchant->id);

        $this->insertOfferClick($offer->id, 'click', daysAgo: 0);  // inside
        $this->insertOfferClick($offer->id, 'click', daysAgo: 31); // outside 30d window

        $result = $this->repository->offerClickTotals($this->merchant->id, days: 30);

        $this->assertSame(1, $result['click']);
    }

    // ─── offerClickTotals ─────────────────────────────────────────────────────

    public function test_offerClickTotals_ignores_other_merchants_offers(): void
    {
        $otherMerchant = $this->createMerchant();
        $otherProduct = $this->createProduct();
        $otherOffer = $this->createProductOffer($otherProduct->id, [], $otherMerchant->id);

        $this->insertOfferClick($otherOffer->id, 'click');

        $result = $this->repository->offerClickTotals($this->merchant->id, days: 30);

        $this->assertSame(0, $result['click']);
    }

    public function test_offerClicksByDay_returns_zero_filled_series_for_full_window(): void
    {
        $days = 7;
        $result = $this->repository->offerClicksByDay($this->merchant->id, days: $days);

        // Must be a full series even with no data
        $this->assertCount($days, $result);
        foreach ($result as $entry) {
            $this->assertArrayHasKey('date', $entry);
            $this->assertArrayHasKey('clicks', $entry);
            $this->assertArrayHasKey('renders', $entry);
            $this->assertSame(0, $entry['clicks']);
            $this->assertSame(0, $entry['renders']);
        }
    }

    public function test_offerClicksByDay_buckets_clicks_on_correct_date(): void
    {
        $offer = $this->createProductOffer($this->product->id, [], $this->merchant->id);

        $this->insertOfferClick($offer->id, 'click', daysAgo: 0);
        $this->insertOfferClick($offer->id, 'click', daysAgo: 0);
        $this->insertOfferClick($offer->id, 'render', daysAgo: 0);
        $this->insertOfferClick($offer->id, 'click', daysAgo: 2);

        $result = $this->repository->offerClicksByDay($this->merchant->id, days: 7);

        $byDate = array_column($result->toArray(), null, 'date');
        $today = date('Y-m-d');
        $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

        $this->assertSame(2, $byDate[$today]['clicks']);
        $this->assertSame(1, $byDate[$today]['renders']);
        $this->assertSame(1, $byDate[$twoDaysAgo]['clicks']);
    }

    public function test_offerClicksByDay_excludes_data_outside_window(): void
    {
        $offer = $this->createProductOffer($this->product->id, [], $this->merchant->id);
        $this->insertOfferClick($offer->id, 'click', daysAgo: 31);

        $result = $this->repository->offerClicksByDay($this->merchant->id, days: 30);

        $totalClicks = array_sum(array_column($result->toArray(), 'clicks'));
        $this->assertSame(0, $totalClicks);
    }

    // ─── offerClicksByDay ─────────────────────────────────────────────────────

    public function test_offerClicksByOffer_returns_per_offer_breakdown_with_ctr(): void
    {
        $offer = $this->createProductOffer($this->product->id, [], $this->merchant->id);

        $this->insertOfferClick($offer->id, 'click');
        $this->insertOfferClick($offer->id, 'click');
        $this->insertOfferClick($offer->id, 'render');
        $this->insertOfferClick($offer->id, 'render');
        $this->insertOfferClick($offer->id, 'render');
        $this->insertOfferClick($offer->id, 'render');

        $result = $this->repository->offerClicksByOffer($this->merchant->id, days: 30);
        $rows = $result->toArray();

        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertSame($offer->id, $row['offer_id']);
        $this->assertSame(2, $row['clicks']);
        $this->assertSame(4, $row['renders']);
        // CTR = (2 / 4) * 100 = 50.0
        $this->assertEqualsWithDelta(50.0, $row['ctr'], 0.01);
    }

    public function test_offerClicksByOffer_orders_by_clicks_descending(): void
    {
        $productA = $this->createProduct();
        $offerA = $this->createProductOffer($productA->id, [], $this->merchant->id);

        $productB = $this->createProduct();
        $offerB = $this->createProductOffer($productB->id, [], $this->merchant->id);

        // offerB gets more clicks
        $this->insertOfferClick($offerA->id, 'click');
        $this->insertOfferClick($offerB->id, 'click');
        $this->insertOfferClick($offerB->id, 'click');

        $result = $this->repository->offerClicksByOffer($this->merchant->id, days: 30);
        $rows = $result->toArray();

        $this->assertSame($offerB->id, $rows[0]['offer_id']);
    }

    public function test_dealClickTotals_returns_zeros_when_no_data(): void
    {
        $result = $this->repository->dealClickTotals($this->merchant->id, days: 30);

        $this->assertSame(0, $result['click']);
        $this->assertSame(0, $result['render']);
        $this->assertSame(0, $result['total']);
    }

    // ─── offerClicksByOffer ───────────────────────────────────────────────────

    public function test_dealClickTotals_counts_clicks_for_merchant_products(): void
    {
        // product_merchants row already created in setUp() for $this->product
        $this->insertDealClick($this->product->id, 'click');
        $this->insertDealClick($this->product->id, 'click');
        $this->insertDealClick($this->product->id, 'render');

        $result = $this->repository->dealClickTotals($this->merchant->id, days: 30);

        $this->assertSame(2, $result['click']);
        $this->assertSame(1, $result['render']);
        $this->assertSame(3, $result['total']);
    }

    /**
     * Insert a row into deal_clicks.
     */
    private function insertDealClick(int $productId, string $action = 'click', int $daysAgo = 0): void
    {
        $ts = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
        $this->database->query(
            'INSERT INTO deal_clicks (product_id, action, created_at, site_id, channel, surface_type, surface_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$productId, $action, $ts, $this->siteId, 'listing', 'web', 1]
        );
    }

    // ─── dealClickTotals ─────────────────────────────────────────────────────

    public function test_dealClickTotals_ignores_clicks_outside_window(): void
    {
        $this->insertDealClick($this->product->id, 'click', daysAgo: 0);  // inside
        $this->insertDealClick($this->product->id, 'click', daysAgo: 91); // outside 90d window

        $result = $this->repository->dealClickTotals($this->merchant->id, days: 90);

        $this->assertSame(1, $result['click']);
    }

    public function test_dealClickTotals_ignores_other_merchants_products(): void
    {
        $otherMerchant = $this->createMerchant();
        $otherProduct = $this->createProduct();
        $this->createProductMerchant($otherProduct->id, ['merchant_id' => $otherMerchant->id]);

        $this->insertDealClick($otherProduct->id, 'click');

        $result = $this->repository->dealClickTotals($this->merchant->id, days: 30);

        $this->assertSame(0, $result['click']);
    }

    public function test_dealClicksByDay_returns_zero_filled_series(): void
    {
        $days = 7;
        $result = $this->repository->dealClicksByDay($this->merchant->id, days: $days);

        $this->assertCount($days, $result);
        foreach ($result as $entry) {
            $this->assertArrayHasKey('clicks', $entry);
            $this->assertArrayHasKey('renders', $entry);
        }
    }

    public function test_dealClicksByDay_buckets_on_correct_date(): void
    {
        $this->insertDealClick($this->product->id, 'click', daysAgo: 0);
        $this->insertDealClick($this->product->id, 'click', daysAgo: 0);
        $this->insertDealClick($this->product->id, 'render', daysAgo: 1);

        $result = $this->repository->dealClicksByDay($this->merchant->id, days: 7);
        $byDate = array_column($result->toArray(), null, 'date');
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $this->assertSame(2, $byDate[$today]['clicks']);
        $this->assertSame(1, $byDate[$yesterday]['renders']);
    }

    // ─── dealClicksByDay ─────────────────────────────────────────────────────

    public function test_dealClicksByProduct_returns_per_product_breakdown(): void
    {
        $this->insertDealClick($this->product->id, 'click');
        $this->insertDealClick($this->product->id, 'click');
        $this->insertDealClick($this->product->id, 'render');
        $this->insertDealClick($this->product->id, 'render');

        $result = $this->repository->dealClicksByProduct($this->merchant->id, days: 30);
        $rows = $result->toArray();

        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertSame($this->product->id, $row['product_id']);
        $this->assertSame(2, $row['clicks']);
        $this->assertSame(2, $row['renders']);
        $this->assertEqualsWithDelta(100, $row['ctr'], 0.01);
    }

    public function test_productViewTotals_returns_zeros_when_no_data(): void
    {
        $result = $this->repository->productViewTotals($this->merchant->id, days: 30);

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['unique_users']);
    }

    // ─── dealClicksByProduct ──────────────────────────────────────────────────

    public function test_productViewTotals_counts_views_for_merchant_products(): void
    {
        $this->insertProductView($this->product->id);
        $this->insertProductView($this->product->id);
        $this->insertProductView($this->product->id);

        $result = $this->repository->productViewTotals($this->merchant->id, days: 30);

        $this->assertSame(3, $result['total']);
    }

    // ─── productViewTotals ────────────────────────────────────────────────────

    /**
     * Insert a row into product_views using createProductView so that the
     * required columns (session_id, ip_address, site_id) are populated.
     */
    private function insertProductView(int $productId, int $daysAgo = 0): void
    {
        $ts = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
        $this->createProductView([
            'product_id' => $productId,
            'viewed_at' => $ts,
        ]);
    }

    public function test_productViewTotals_counts_unique_users(): void
    {
        $memberA = $this->createMember();
        $memberB = $this->createMember();

        $this->createProductView(['product_id' => $this->product->id, 'user_id' => $memberA->id]);
        $this->createProductView(['product_id' => $this->product->id, 'user_id' => $memberA->id]);
        $this->createProductView(['product_id' => $this->product->id, 'user_id' => $memberB->id]);

        $result = $this->repository->productViewTotals($this->merchant->id, days: 30);

        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $result['unique_users']);
    }

    public function test_productViewTotals_ignores_views_outside_window(): void
    {
        $this->insertProductView($this->product->id, daysAgo: 0);  // inside
        $this->insertProductView($this->product->id, daysAgo: 31); // outside

        $result = $this->repository->productViewTotals($this->merchant->id, days: 30);

        $this->assertSame(1, $result['total']);
    }

    public function test_productViewTotals_ignores_other_merchants_products(): void
    {
        $otherMerchant = $this->createMerchant();
        $otherProduct = $this->createProduct();
        $this->createProductMerchant($otherProduct->id, ['merchant_id' => $otherMerchant->id]);

        $this->insertProductView($otherProduct->id);

        $result = $this->repository->productViewTotals($this->merchant->id, days: 30);

        $this->assertSame(0, $result['total']);
    }

    public function test_productViewsByDay_returns_zero_filled_series(): void
    {
        $days = 14;
        $result = $this->repository->productViewsByDay($this->merchant->id, days: $days);

        $this->assertCount($days, $result);
        foreach ($result as $entry) {
            $this->assertArrayHasKey('views', $entry);
            $this->assertArrayHasKey('unique', $entry);
        }
    }

    // ─── productViewsByDay ────────────────────────────────────────────────────

    public function test_productViewsByDay_buckets_views_on_correct_date(): void
    {
        $this->insertProductView($this->product->id, daysAgo: 0);
        $this->insertProductView($this->product->id, daysAgo: 0);
        $this->insertProductView($this->product->id, daysAgo: 3);

        $result = $this->repository->productViewsByDay($this->merchant->id, days: 7);
        $byDate = array_column($result->toArray(), null, 'date');
        $today = date('Y-m-d');
        $threeDaysAgo = date('Y-m-d', strtotime('-3 days'));

        $this->assertSame(2, $byDate[$today]['views']);
        $this->assertSame(1, $byDate[$threeDaysAgo]['views']);
    }

    public function test_productViewsByProduct_returns_per_product_breakdown(): void
    {
        $this->insertProductView($this->product->id);
        $this->insertProductView($this->product->id);

        $result = $this->repository->productViewsByProduct($this->merchant->id, days: 30);
        $rows = $result->toArray();

        $this->assertCount(1, $rows);
        $this->assertSame($this->product->id, $rows[0]['product_id']);
        $this->assertSame(2, $rows[0]['views']);
    }

    // ─── productViewsByProduct ────────────────────────────────────────────────

    public function test_productViewsByProduct_orders_by_views_descending(): void
    {
        $productA = $this->createProduct();
        $this->createProductMerchant($productA->id, ['merchant_id' => $this->merchant->id]);

        $productB = $this->createProduct();
        $this->createProductMerchant($productB->id, ['merchant_id' => $this->merchant->id]);

        $this->insertProductView($productA->id);

        $this->insertProductView($productB->id);
        $this->insertProductView($productB->id);
        $this->insertProductView($productB->id);

        $result = $this->repository->productViewsByProduct($this->merchant->id, days: 30);
        $rows = $result->toArray();

        $this->assertSame($productB->id, $rows[0]['product_id']);
    }

    public function test_periodComparison_returns_zero_deltas_with_no_data(): void
    {
        $result = $this->repository->periodComparison($this->merchant->id, days: 30);

        $this->assertArrayHasKey('deltas', $result);
        $this->assertSame(0.0, $result['deltas']['offer_clicks']);
        $this->assertSame(0.0, $result['deltas']['deal_clicks']);
        $this->assertSame(0.0, $result['deltas']['product_views']);
    }

    // ─── periodComparison ─────────────────────────────────────────────────────

    public function test_periodComparison_calculates_positive_delta_when_current_exceeds_prior(): void
    {
        $offer = $this->createProductOffer($this->product->id, [], $this->merchant->id);

        // Prior period (31–60 days ago): 1 click
        $this->insertOfferClick($offer->id, 'click', daysAgo: 35);
        // Current period (0–30 days ago): 3 clicks → +200%
        $this->insertOfferClick($offer->id, 'click', daysAgo: 0);
        $this->insertOfferClick($offer->id, 'click', daysAgo: 5);
        $this->insertOfferClick($offer->id, 'click', daysAgo: 10);

        $result = $this->repository->periodComparison($this->merchant->id, days: 30);

        $this->assertGreaterThan(0.0, $result['deltas']['offer_clicks']);
    }

    public function test_periodComparison_calculates_negative_delta_when_current_is_lower(): void
    {
        $offer = $this->createProductOffer($this->product->id, [], $this->merchant->id);

        // Prior period: 4 clicks
        foreach ([35, 36, 37, 38] as $daysAgo) {
            $this->insertOfferClick($offer->id, 'click', daysAgo: $daysAgo);
        }
        // Current period: 1 click → negative delta
        $this->insertOfferClick($offer->id, 'click', daysAgo: 1);

        $result = $this->repository->periodComparison($this->merchant->id, days: 30);

        $this->assertLessThan(0.0, $result['deltas']['offer_clicks']);
    }

    public function test_periodComparison_includes_deal_clicks_and_product_views(): void
    {
        // current period deal clicks
        $this->insertDealClick($this->product->id, 'click', daysAgo: 1);

        // current period product views
        $this->insertProductView($this->product->id, daysAgo: 1);

        $result = $this->repository->periodComparison($this->merchant->id, days: 30);

        // Just assert structure and that current values are captured
        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('previous', $result);
        $this->assertSame(1, $result['current']['deal_clicks']);
        $this->assertSame(1, $result['current']['product_views']);
    }

    public function test_multiple_products_are_all_included_for_same_merchant(): void
    {
        $productB = $this->createProduct();
        $this->createProductMerchant($productB->id, ['merchant_id' => $this->merchant->id]);

        $this->insertProductView($this->product->id);
        $this->insertProductView($this->product->id);
        $this->insertProductView($productB->id);

        $result = $this->repository->productViewTotals($this->merchant->id, days: 30);

        $this->assertSame(3, $result['total']);
    }

    // ─── Multiple products / cross-merchant isolation ─────────────────────────

    public function test_data_from_two_merchants_does_not_bleed_across(): void
    {
        $merchantB = $this->createMerchant();
        $productB = $this->createProduct();
        $this->createProductMerchant($productB->id, ['merchant_id' => $merchantB->id]);

        $offerB = $this->createProductOffer($productB->id, [], $merchantB->id);

        $this->insertOfferClick($offerB->id, 'click');
        $this->insertDealClick($productB->id, 'click');
        $this->insertProductView($productB->id);

        $offerTotals = $this->repository->offerClickTotals($this->merchant->id, days: 30);
        $dealTotals = $this->repository->dealClickTotals($this->merchant->id, days: 30);
        $viewTotals = $this->repository->productViewTotals($this->merchant->id, days: 30);

        $this->assertSame(0, $offerTotals['click']);
        $this->assertSame(0, $dealTotals['click']);
        $this->assertSame(0, $viewTotals['total']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MerchantAnalyticsRepository($this->database);

        // A merchant and a product linked to that merchant.
        // createProductMerchant wires product_merchants.merchant_id.
        $this->merchant = $this->createMerchant();
        $this->product = $this->createProduct();
        $this->createProductMerchant($this->product->id, [
            'merchant_id' => $this->merchant->id,
        ]);
    }
}