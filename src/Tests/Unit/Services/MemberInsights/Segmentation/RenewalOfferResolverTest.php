<?php

namespace App\Tests\Unit\Services\MemberInsights\Segmentation;

use App\Models\Segment;
use App\Models\Subscription;
use App\Models\SubscriptionPlanPricing;
use App\Models\SubscriptionSegment;
use App\Models\Voucher;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Repositories\MemberInsights\SubscriptionSegmentRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Repositories\Vouchers\VoucherRepository;
use App\Services\MemberInsights\Segmentation\RenewalOfferFilter;
use App\Services\MemberInsights\Segmentation\RenewalOfferResolver;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class RenewalOfferResolverTest extends TestCase
{
    private SubscriptionRepository|MockInterface $subscriptionRepository;

    private SubscriptionSegmentRepository|MockInterface $subscriptionSegmentRepository;

    private VoucherRepository|MockInterface $voucherRepository;

    private SubscriptionPlanRepository|MockInterface $subscriptionPlanRepository;

    private RenewalOfferResolver $resolver;

    // =========================================================================
    // Segment returned
    // =========================================================================

    public function test_it_returns_segment_and_voucher_promotion(): void
    {
        $subscription = $this->makeSubscription(id: 1, planId: 22);
        $segment = $this->makeSegment(10, 'price_rise_dd', 'Price Rise DD');
        $assignment = $this->makeAssignment($segment);
        $voucher = $this->makeVoucher(5, 'RET20', '20% Retention');

        $this->subscriptionRepository
            ->allows('find')
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionSegmentRepository
            ->allows('findActive')
            ->with(1)
            ->andReturn($assignment);

        $this->voucherRepository
            ->allows('findBestForSubscriptionSegment')
            ->with(
                Mockery::on(fn (Segment $givenSegment) => $givenSegment->id === 10),
                22,
                Mockery::type(RenewalOfferFilter::class)
            )
            ->andReturn($voucher);

        $this->subscriptionPlanRepository
            ->allows('findDiscountedPricingForPlan')
            ->with(22, Mockery::type(RenewalOfferFilter::class))
            ->andReturn(collect([]));

        $result = $this->resolver->resolve(1, new RenewalOfferFilter());

        $this->assertSame(10, $result['segment']['id']);
        $this->assertSame('price_rise_dd', $result['segment']['key']);
        $this->assertSame('Price Rise DD', $result['segment']['name']);

        $this->assertSame(5, $result['promotion']['id']);
        $this->assertSame('RET20', $result['promotion']['code']);
        $this->assertSame('20% Retention', $result['promotion']['name']);
    }

    public function test_it_returns_null_segment_and_null_promotion_when_no_active_assignment(): void
    {
        $subscription = $this->makeSubscription(id: 1, planId: 22);

        $this->subscriptionRepository
            ->allows('find')
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionSegmentRepository
            ->allows('findActive')
            ->with(1)
            ->andReturnNull();

        $this->voucherRepository
            ->shouldNotReceive('findBestForSubscriptionSegment');

        $this->subscriptionPlanRepository
            ->allows('findDiscountedPricingForPlan')
            ->with(22, Mockery::type(RenewalOfferFilter::class))
            ->andReturn(collect());

        $result = $this->resolver->resolve(1, new RenewalOfferFilter());

        $this->assertNull($result['segment']);
        $this->assertNull($result['promotion']);
        $this->assertSame([], $result['offers']);
    }

    // =========================================================================
    // Filters applied
    // =========================================================================

    public function test_it_forwards_region_filter_to_plan_pricing_repository(): void
    {
        $subscription = $this->makeSubscription(id: 1, planId: 22);
        $filter = new RenewalOfferFilter(region: 'UK');

        $this->subscriptionRepository
            ->allows('find')
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionSegmentRepository
            ->allows('findActive')
            ->with(1)
            ->andReturnNull();

        $this->subscriptionPlanRepository
            ->expects('findDiscountedPricingForPlan')
            ->once()
            ->with(
                22,
                Mockery::on(fn (RenewalOfferFilter $givenFilter) => $givenFilter->region === 'UK')
            )
            ->andReturn(collect());

        $this->resolver->resolve(1, $filter);

        $this->addToAssertionCount(1);
    }

    public function test_it_forwards_payment_type_filter_to_plan_pricing_repository(): void
    {
        $subscription = $this->makeSubscription(id: 1, planId: 22);
        $filter = new RenewalOfferFilter(paymentType: 'direct_debit');

        $this->subscriptionRepository
            ->allows('find')
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionSegmentRepository
            ->allows('findActive')
            ->with(1)
            ->andReturnNull();

        $this->subscriptionPlanRepository
            ->expects('findDiscountedPricingForPlan')
            ->once()
            ->with(
                22,
                Mockery::on(fn (RenewalOfferFilter $givenFilter) => $givenFilter->paymentType === 'direct_debit')
            )
            ->andReturn(collect());

        $this->resolver->resolve(1, $filter);

        $this->addToAssertionCount(1);
    }

    public function test_it_forwards_edition_filter_to_plan_pricing_repository(): void
    {
        $subscription = $this->makeSubscription(id: 1, planId: 22);
        $filter = new RenewalOfferFilter(edition: 'print');

        $this->subscriptionRepository
            ->allows('find')
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionSegmentRepository
            ->allows('findActive')
            ->with(1)
            ->andReturnNull();

        $this->subscriptionPlanRepository
            ->expects('findDiscountedPricingForPlan')
            ->once()
            ->with(
                22,
                Mockery::on(fn (RenewalOfferFilter $givenFilter) => $givenFilter->edition === 'print')
            )
            ->andReturn(collect());

        $this->resolver->resolve(1, $filter);

        $this->addToAssertionCount(1);
    }

    public function test_it_forwards_active_date_filter_to_plan_pricing_repository(): void
    {
        $subscription = $this->makeSubscription(id: 1, planId: 22);
        $filter = new RenewalOfferFilter(activeDate: '2026-06-01');

        $this->subscriptionRepository
            ->allows('find')
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionSegmentRepository
            ->allows('findActive')
            ->with(1)
            ->andReturnNull();

        $this->subscriptionPlanRepository
            ->expects('findDiscountedPricingForPlan')
            ->once()
            ->with(
                22,
                Mockery::on(fn (RenewalOfferFilter $givenFilter) => $givenFilter->activeDate === '2026-06-01')
            )
            ->andReturn(collect());

        $this->resolver->resolve(1, $filter);

        $this->addToAssertionCount(1);
    }

    public function test_it_returns_available_standard_and_digital_pricing_offers(): void
    {
        $subscription = $this->makeSubscription(id: 1, planId: 22);
        $pricing = $this->makePricingOffer(id: 3, planId: 22);

        $this->subscriptionRepository
            ->allows('find')
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionSegmentRepository
            ->allows('findActive')
            ->with(1)
            ->andReturnNull();

        $this->subscriptionPlanRepository
            ->allows('findDiscountedPricingForPlan')
            ->with(22, Mockery::type(RenewalOfferFilter::class))
            ->andReturn(collect([$pricing]));

        $result = $this->resolver->resolve(1, new RenewalOfferFilter());

        $this->assertCount(1, $result['offers']);

        $this->assertSame(3, $result['offers'][0]['id']);
        $this->assertSame(22, $result['offers'][0]['plan_id']);

        $this->assertSame(100.00, $result['offers'][0]['standard']['price']);
        $this->assertSame(80.00, $result['offers'][0]['standard']['sale_price']);
        $this->assertTrue($result['offers'][0]['standard']['has_offer']);
        $this->assertSame(20.0, $result['offers'][0]['standard']['discount_amount']);
        $this->assertSame(20.0, $result['offers'][0]['standard']['discount_percentage']);

        $this->assertSame(50.00, $result['offers'][0]['digital']['price']);
        $this->assertSame(40.00, $result['offers'][0]['digital']['sale_price']);
        $this->assertTrue($result['offers'][0]['digital']['has_offer']);
        $this->assertSame(10.0, $result['offers'][0]['digital']['discount_amount']);
        $this->assertSame(20.0, $result['offers'][0]['digital']['discount_percentage']);
    }

    public function test_it_returns_zero_discount_when_standard_price_has_no_offer(): void
    {
        $subscription = $this->makeSubscription(id: 1, planId: 22);

        $pricing = $this->makePricingOffer(id: 3, planId: 22);
        $pricing->sale_price = null;

        $this->subscriptionRepository
            ->allows('find')
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionSegmentRepository
            ->allows('findActive')
            ->with(1)
            ->andReturnNull();

        $this->subscriptionPlanRepository
            ->allows('findDiscountedPricingForPlan')
            ->with(22, Mockery::type(RenewalOfferFilter::class))
            ->andReturn(collect([$pricing]));

        $result = $this->resolver->resolve(1, new RenewalOfferFilter());

        $this->assertFalse($result['offers'][0]['standard']['has_offer']);
        $this->assertSame(0, $result['offers'][0]['standard']['discount_amount']);
        $this->assertSame(0, $result['offers'][0]['standard']['discount_percentage']);

        $this->assertTrue($result['offers'][0]['digital']['has_offer']);
    }

    public function test_it_returns_zero_discount_when_digital_price_has_no_offer(): void
    {
        $subscription = $this->makeSubscription(id: 1, planId: 22);

        $pricing = $this->makePricingOffer(id: 3, planId: 22);
        $pricing->digital_sale_price = null;

        $this->subscriptionRepository
            ->allows('find')
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionSegmentRepository
            ->allows('findActive')
            ->with(1)
            ->andReturnNull();

        $this->subscriptionPlanRepository
            ->allows('findDiscountedPricingForPlan')
            ->with(22, Mockery::type(RenewalOfferFilter::class))
            ->andReturn(collect([$pricing]));

        $result = $this->resolver->resolve(1, new RenewalOfferFilter());

        $this->assertTrue($result['offers'][0]['standard']['has_offer']);

        $this->assertFalse($result['offers'][0]['digital']['has_offer']);
        $this->assertSame(0, $result['offers'][0]['digital']['discount_amount']);
        $this->assertSame(0, $result['offers'][0]['digital']['discount_percentage']);
    }

    public function test_it_throws_when_subscription_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Subscription #99 not found/');

        $this->subscriptionRepository
            ->allows('find')
            ->with(99)
            ->andReturnNull();

        $this->subscriptionSegmentRepository
            ->shouldNotReceive('findActive');

        $this->voucherRepository
            ->shouldNotReceive('findBestForSubscriptionSegment');

        $this->subscriptionPlanRepository
            ->shouldNotReceive('findDiscountedPricingForPlan');

        $this->resolver->resolve(99, new RenewalOfferFilter());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSubscription(int $id, int $planId): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $subscription->id = $id;
        $subscription->plan_id = $planId;

        return $subscription;
    }

    private function makeSegment(int $id, string $key, string $name): Segment
    {
        $segment       = Mockery::mock(Segment::class)->makePartial();
        $segment->id   = $id;
        $segment->key  = $key;
        $segment->name = $name;

        return $segment;
    }

    private function makeAssignment(Segment $segment): SubscriptionSegment
    {
        $assignment          = Mockery::mock(SubscriptionSegment::class)->makePartial();
        $assignment->segment = $segment;

        return $assignment;
    }

    private function makePromotion(int $id, string $name): object
    {
        $promo       = new \stdClass();
        $promo->id   = $id;
        $promo->name = $name;
        $promo->code = 'PROMO' . $id;

        return $promo;
    }

    private function makeOffer(int $id, string $name): object
    {
        $offer              = new \stdClass();
        $offer->id          = $id;
        $offer->name        = $name;
        $offer->description = '';
        $offer->discount    = 10;

        return $offer;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->subscriptionSegmentRepository = Mockery::mock(SubscriptionSegmentRepository::class);
        $this->voucherRepository = Mockery::mock(VoucherRepository::class);
        $this->subscriptionPlanRepository = Mockery::mock(SubscriptionPlanRepository::class);

        $this->resolver = new RenewalOfferResolver(
            $this->subscriptionRepository,
            $this->subscriptionSegmentRepository,
            $this->voucherRepository,
            $this->subscriptionPlanRepository,
        );
    }

    private function makeVoucher(int $id, string $code, string $name): Voucher
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();

        $voucher->id = $id;
        $voucher->code = $code;
        $voucher->name = $name;
        $voucher->description = 'Voucher description';
        $voucher->type = 'percentage';
        $voucher->value = 20;
        $voucher->discount_type = 'percentage';
        $voucher->discount_amount = null;
        $voucher->discount_percentage = 20;
        $voucher->subscription_discount_duration = 'once';
        $voucher->subscription_duration_months = null;
        $voucher->duration_in_months = null;

        return $voucher;
    }

    private function makePricingOffer(int $id, int $planId): SubscriptionPlanPricing
    {
        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();

        $pricing->id = $id;
        $pricing->plan_id = $planId;

        $pricing->edition = 'print';
        $pricing->region = 'UK';
        $pricing->payment_type = 'direct_debit';

        $pricing->price = 100.00;
        $pricing->sale_price = 80.00;

        $pricing->digital_price = 50.00;
        $pricing->digital_sale_price = 40.00;

        return $pricing;
    }


    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}