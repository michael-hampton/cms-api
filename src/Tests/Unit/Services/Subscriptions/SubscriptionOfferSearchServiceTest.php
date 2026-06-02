<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionOfferData;
use App\DTO\Subscriptions\SubscriptionOfferFilters;
use App\Enums\Subscriptions\OfferType;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Models\Voucher;
use App\Repositories\Subscriptions\SubscriptionOfferRepository;
use App\Services\Subscriptions\SubscriptionOfferSearchService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SubscriptionOfferSearchService.
 *
 * All repository interactions are mocked. Tests focus exclusively on the
 * offer-derivation rules defined in the epic specification.
 */
class SubscriptionOfferSearchServiceTest extends TestCase
{
    private MockInterface|SubscriptionOfferRepository $repository;
    private SubscriptionOfferSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SubscriptionOfferRepository::class);
        $this->service    = new SubscriptionOfferSearchService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Print offer derivation
    // =========================================================================

    public function test_it_converts_print_discount_pricing_into_a_print_offer(): void
    {
        $tier = $this->makeTier([
            'price'      => 100.00,
            'sale_price' => 80.00,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(1, $result['items']);
        $this->assertSame(OfferType::PRINT, $result['items'][0]->offerType);
    }

    public function test_it_does_not_produce_a_print_offer_when_sale_price_is_absent(): void
    {
        $tier = $this->makeTier([
            'price'      => 100.00,
            'sale_price' => null,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(0, $result['items']);
    }

    public function test_it_does_not_produce_a_print_offer_when_sale_price_equals_price(): void
    {
        $tier = $this->makeTier([
            'price'      => 100.00,
            'sale_price' => 100.00,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(0, $result['items']);
    }

    public function test_it_does_not_produce_a_print_offer_when_sale_price_is_greater_than_price(): void
    {
        $tier = $this->makeTier([
            'price'      => 80.00,
            'sale_price' => 100.00,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(0, $result['items']);
    }

    // =========================================================================
    // Digital offer derivation
    // =========================================================================

    public function test_it_converts_digital_discount_pricing_into_a_digital_offer(): void
    {
        $tier = $this->makeTier([
            'digital_price'      => 75.00,
            'digital_sale_price' => 60.00,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(1, $result['items']);
        $this->assertSame(OfferType::DIGITAL, $result['items'][0]->offerType);
    }

    public function test_it_does_not_produce_a_digital_offer_when_digital_sale_price_is_absent(): void
    {
        $tier = $this->makeTier([
            'digital_price'      => 75.00,
            'digital_sale_price' => null,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(0, $result['items']);
    }

    public function test_it_does_not_produce_a_digital_offer_when_digital_sale_price_equals_digital_price(): void
    {
        $tier = $this->makeTier([
            'digital_price'      => 75.00,
            'digital_sale_price' => 75.00,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(0, $result['items']);
    }

    // =========================================================================
    // Intro offer derivation
    // =========================================================================

    public function test_it_converts_intro_pricing_into_an_intro_offer(): void
    {
        $tier = $this->makeTier([
            'price'        => 100.00,
            'intro_price'  => 1.00,
            'intro_cycles' => 3,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(1, $result['items']);
        $this->assertSame(OfferType::INTRO, $result['items'][0]->offerType);
        $this->assertSame(3, $result['items'][0]->introCycles);
        $this->assertSame(1.00, $result['items'][0]->offerPrice);
    }

    public function test_it_does_not_produce_an_intro_offer_when_intro_price_is_absent(): void
    {
        $tier = $this->makeTier([
            'intro_price'  => null,
            'intro_cycles' => 3,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(0, $result['items']);
    }

    public function test_it_does_not_produce_an_intro_offer_when_intro_cycles_is_zero(): void
    {
        $tier = $this->makeTier([
            'intro_price'  => 1.00,
            'intro_cycles' => 0,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(0, $result['items']);
    }

    // =========================================================================
    // Voucher offer derivation
    // =========================================================================

    public function test_it_converts_voucher_data_into_a_voucher_offer(): void
    {
        $voucher = $this->makeVoucher('SAVE20', 20.00);
        $tier    = $this->makeTier(['price' => 100.00], vouchers: [$voucher]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertCount(1, $result['items']);
        $this->assertSame(OfferType::VOUCHER, $result['items'][0]->offerType);
        $this->assertSame('SAVE20', $result['items'][0]->voucherCode);
    }

    // =========================================================================
    // Multiple offers per tier
    // =========================================================================

    public function test_it_produces_multiple_offers_from_one_pricing_tier(): void
    {
        $tier = $this->makeTier([
            'price'        => 100.00,
            'sale_price'   => 80.00,
            'intro_price'  => 1.00,
            'intro_cycles' => 3,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $offerTypes = array_map(fn($o) => $o->offerType, $result['items']);

        $this->assertCount(2, $result['items']);
        $this->assertContains(OfferType::PRINT, $offerTypes);
        $this->assertContains(OfferType::INTRO, $offerTypes);
    }

    // =========================================================================
    // Virtual offer identity
    // =========================================================================

    public function test_it_generates_correct_virtual_offer_identity_using_pricing_id_and_offer_type(): void
    {
        $tier = $this->makeTier([
            'id'         => 42,
            'price'      => 100.00,
            'sale_price' => 80.00,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertSame('42:print', $result['items'][0]->virtualId());
    }

    public function test_print_and_intro_offers_from_same_tier_have_distinct_virtual_identities(): void
    {
        $tier = $this->makeTier([
            'id'           => 12,
            'price'        => 100.00,
            'sale_price'   => 80.00,
            'intro_price'  => 1.00,
            'intro_cycles' => 3,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());
        $ids    = array_map(fn($o) => $o->virtualId(), $result['items']);

        $this->assertContains('12:print', $ids);
        $this->assertContains('12:intro', $ids);
        $this->assertCount(count(array_unique($ids)), $ids, 'Virtual IDs must be unique');
    }

    // =========================================================================
    // Saving calculations
    // =========================================================================

    public function test_it_calculates_saving_amount_correctly(): void
    {
        $tier = $this->makeTier([
            'price'      => 120.00,
            'sale_price' => 99.00,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        $this->assertSame(21.0, $result['items'][0]->savingAmount);
    }

    public function test_it_calculates_saving_percentage_correctly(): void
    {
        $tier = $this->makeTier([
            'price'      => 120.00,
            'sale_price' => 99.00,
        ]);

        $this->repositoryReturns([$tier]);

        $result = $this->service->search($this->defaultFilters());

        // (120 - 99) / 120 * 100 = 17.5 → rounds to 18
        $this->assertSame(18, $result['items'][0]->savingPercentage);
    }

    // =========================================================================
    // Offer type filter
    // =========================================================================

    public function test_it_only_returns_print_offers_when_filtered_by_print_type(): void
    {
        $tier = $this->makeTier([
            'price'              => 100.00,
            'sale_price'         => 80.00,
            'digital_price'      => 75.00,
            'digital_sale_price' => 60.00,
        ]);

        $this->repositoryReturns([$tier]);

        $filters = new SubscriptionOfferFilters(offerType: OfferType::PRINT);
        $result  = $this->service->search($filters);

        $this->assertCount(1, $result['items']);
        $this->assertSame(OfferType::PRINT, $result['items'][0]->offerType);
    }

    // =========================================================================
    // Pagination metadata
    // =========================================================================

    public function test_it_returns_correct_pagination_metadata(): void
    {
        $this->repository
            ->shouldReceive('findPricingTiersForOffers')
            ->once()
            ->andReturn(['items' => collect([]), 'total' => 47]);

        $filters = new SubscriptionOfferFilters(page: 2, perPage: 15);
        $result  = $this->service->search($filters);

        $this->assertSame(47, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(15, $result['per_page']);
        $this->assertSame(4, $result['last_page']); // ceil(47/15) = 4
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeTier(array $attributes = [], array $vouchers = []): SubscriptionPlanPricing
    {
        $tier = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();

        $defaults = [
            'id'                 => 1,
            'plan_id'            => 55,
            'price'              => null,
            'sale_price'         => null,
            'digital_price'      => null,
            'digital_sale_price' => null,
            'intro_price'        => null,
            'intro_cycles'       => null,
            'label'              => null,
            'currency'           => 'GBP',
        ];

        $data = array_merge($defaults, $attributes);

        foreach ($data as $key => $value) {
            $tier->{$key} = $value;
        }

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id        = $data['plan_id'];
        $plan->name      = 'Premium Annual';
        $plan->currency  = 'GBP';
        $plan->promotion = collect($vouchers);

        $tier->plan = $plan;

        return $tier;
    }

    private function makeVoucher(string $code, float $discountAmount): Voucher
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->code  = $code;
        $voucher->value = $discountAmount;
        $voucher->type  = 'fixed';

        $voucher->shouldReceive('calculateSubscriptionDiscount')
            ->andReturnUsing(fn(float $price) => min($discountAmount, $price));

        return $voucher;
    }

    private function repositoryReturns(array $tiers): void
    {
        $this->repository
            ->shouldReceive('findPricingTiersForOffers')
            ->once()
            ->andReturn([
                'items' => collect($tiers),
                'total' => count($tiers),
            ]);
    }

    private function defaultFilters(): SubscriptionOfferFilters
    {
        return new SubscriptionOfferFilters();
    }
}