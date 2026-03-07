<?php

namespace App\Tests\Unit\Services\Vouchers;

use App\DTO\Vouchers\VoucherValidationContext;
use App\Models\Voucher;
use App\Repositories\Vouchers\VoucherRepository;
use App\Services\Vouchers\VoucherEligibilityResolver;
use App\Services\Vouchers\VoucherValidationService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class VoucherValidationServiceTest extends TestCase
{
    private VoucherRepository&MockInterface $repository;
    private VoucherEligibilityResolver&MockInterface $eligibilityResolver;
    private VoucherValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(VoucherRepository::class);
        $this->eligibilityResolver = Mockery::mock(VoucherEligibilityResolver::class);

        $this->service = new VoucherValidationService(
            $this->repository,
            $this->eligibilityResolver
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeValidVoucher(): Voucher&MockInterface
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->allows('isValid')->andReturn(true);
        $voucher->campaign_id = null;
        $voucher->per_user_limit = null;

        return $voucher;
    }

    private function makeContext(array $overrides = []): VoucherValidationContext
    {
        // Use named constructor if available; otherwise build directly
        return new VoucherValidationContext(...array_merge([
            'productId' => null,
            'cartItems' => [],
            'orderValue' => 100.0,
            'userId' => null,
            'hasOfferDiscount' => false,
            'subscriptionPlanId' => null,
            'forCart' => false,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Voucher validity
    // -------------------------------------------------------------------------

    public function testValidateReturnsInvalidWhenVoucherIsNotValid(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->allows('isValid')->andReturn(false);
        $voucher->status = 'expired';

        $context = $this->makeContext();

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertSame('Voucher has expired', $result->message);
    }

    public function testValidateReturnsInvalidMessageForInactiveVoucher(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->allows('isValid')->andReturn(false);
        $voucher->status = 'inactive';

        $result = $this->service->validate($voucher, $this->makeContext());

        $this->assertFalse($result->valid);
        $this->assertSame('Voucher is inactive', $result->message);
    }

    public function testValidateReturnsInvalidWhenUsageLimitReached(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->allows('isValid')->andReturn(false);
        $voucher->status = 'active';
        $voucher->usage_limit = 10;
        $voucher->usage_count = 10;

        $result = $this->service->validate($voucher, $this->makeContext());

        $this->assertFalse($result->valid);
        $this->assertSame('Voucher usage limit reached', $result->message);
    }

    public function testValidateReturnsGenericInvalidMessageWhenNoSpecificReason(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->allows('isValid')->andReturn(false);
        $voucher->status = 'active';
        $voucher->usage_limit = null;

        $result = $this->service->validate($voucher, $this->makeContext());

        $this->assertFalse($result->valid);
        $this->assertSame('Voucher is not valid', $result->message);
    }

    // -------------------------------------------------------------------------
    // Campaign validation
    // -------------------------------------------------------------------------

    public function testValidateReturnsInvalidWhenCampaignIsNull(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->campaign_id = 1;
        $voucher->campaign = null;

        $result = $this->service->validate($voucher, $this->makeContext());

        $this->assertFalse($result->valid);
        $this->assertSame('Campaign is not active', $result->message);
    }

    public function testValidateReturnsInvalidWhenCampaignStatusIsNotActive(): void
    {
        $campaign = Mockery::mock()->makePartial();
        $campaign->status = 'paused';
        $campaign->allows('isActive')->andReturn(true);

        $voucher = $this->makeValidVoucher();
        $voucher->campaign_id = 1;
        $voucher->campaign = $campaign;

        $result = $this->service->validate($voucher, $this->makeContext());

        $this->assertFalse($result->valid);
        $this->assertSame('Campaign is not active', $result->message);
    }

    public function testValidateReturnsInvalidWhenCampaignIsNotActiveByDateRange(): void
    {
        $campaign = Mockery::mock()->makePartial();
        $campaign->status = 'active';
        $campaign->allows('isActive')->andReturn(false);

        $voucher = $this->makeValidVoucher();
        $voucher->campaign_id = 1;
        $voucher->campaign = $campaign;

        $result = $this->service->validate($voucher, $this->makeContext());

        $this->assertFalse($result->valid);
        $this->assertSame('Campaign is not active', $result->message);
    }

    // -------------------------------------------------------------------------
    // Per-user usage limit
    // -------------------------------------------------------------------------

    public function testValidateReturnsInvalidWhenPerUserLimitExceeded(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->per_user_limit = 1;
        $voucher->allows('getUserUsageCount')->with(5)->andReturn(1);

        $context = $this->makeContext(['userId' => 5, 'cartItems' => []]);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('maximum number of times', $result->message);
    }

    public function testValidateDoesNotCheckPerUserLimitWhenUserIdIsNull(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->per_user_limit = 1;
        $voucher->allows('calculateDiscount')->andReturn(10.0);
        $voucher->allows('isNonStackable')->andReturn(false);
        $voucher->allows('requiresOverrideForOfferDiscount')->andReturn(false);
        $voucher->minimum_order_value = null;

        $this->eligibilityResolver
            ->allows('resolveEligibleItems')
            ->andReturn([]);

        $context = $this->makeContext(['userId' => null]);

        // Should NOT throw or return per-user-limit error — falls through to cart
        $result = $this->service->validate($voucher, $context);

        $this->assertNotSame('You have already used this voucher the maximum number of times', $result->message ?? '');
    }

    // -------------------------------------------------------------------------
    // Subscription validation
    // -------------------------------------------------------------------------

    public function testValidateSubscriptionReturnsInvalidWhenVoucherDoesNotApplyToSubscriptions(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->allows('appliesToSubscriptions')->andReturn(false);

        $context = $this->makeContext(['subscriptionPlanId' => 1, 'orderValue' => 50.0]);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertSame('This voucher cannot be used for subscriptions', $result->message);
    }

    public function testValidateSubscriptionReturnsInvalidWhenPlanNotApplicable(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->allows('appliesToSubscriptions')->andReturn(true);
        $voucher->allows('isApplicableToSubscriptionPlan')->with(2)->andReturn(false);

        $context = $this->makeContext(['subscriptionPlanId' => 2, 'orderValue' => 50.0]);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertSame('Voucher not applicable to this subscription plan', $result->message);
    }

    public function testValidateSubscriptionReturnsValidResultWithCorrectDiscount(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->allows('appliesToSubscriptions')->andReturn(true);
        $voucher->allows('isApplicableToSubscriptionPlan')->andReturn(true);
        $voucher->allows('calculateSubscriptionDiscount')->with(50.0)->andReturn(10.0);

        $context = $this->makeContext(['subscriptionPlanId' => 1, 'orderValue' => 50.0]);

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertSame(10.0, $result->discount);
        $this->assertSame(40.0, $result->finalPrice);
    }

    public function testValidateSubscriptionFinalPriceNeverGoesBelowZero(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->allows('appliesToSubscriptions')->andReturn(true);
        $voucher->allows('isApplicableToSubscriptionPlan')->andReturn(true);
        $voucher->allows('calculateSubscriptionDiscount')->with(5.0)->andReturn(100.0);

        $context = $this->makeContext(['subscriptionPlanId' => 1, 'orderValue' => 5.0]);

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertSame(0.0, $result->finalPrice);
    }

    // -------------------------------------------------------------------------
    // Product validation
    // -------------------------------------------------------------------------

    public function testValidateProductReturnsInvalidWhenProductNotApplicable(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->allows('isApplicableToProduct')->with(10)->andReturn(false);

        $context = $this->makeContext(['productId' => 10, 'orderValue' => 50.0]);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertSame('Voucher not applicable to this product', $result->message);
    }

    public function testValidateProductReturnsInvalidWhenBelowMinimumOrderValue(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->allows('isApplicableToProduct')->andReturn(true);
        $voucher->minimum_order_value = 100.0;

        $context = $this->makeContext(['productId' => 10, 'orderValue' => 50.0]);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('£100', $result->message);
    }

    public function testValidateProductReturnsValidWithCalculatedDiscount(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->minimum_order_value = null;
        $voucher->is_stackable = true;
        $voucher->allows('isApplicableToProduct')->andReturn(true);
        $voucher->allows('calculateDiscount')->with(80.0)->andReturn(8.0);

        $context = $this->makeContext(['productId' => 1, 'orderValue' => 80.0]);

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertSame(8.0, $result->discount);
    }

    // -------------------------------------------------------------------------
    // Cart validation
    // -------------------------------------------------------------------------

    public function testValidateCartReturnsInvalidWhenNoEligibleItemsInCart(): void
    {
        $voucher = $this->makeValidVoucher();

        $this->eligibilityResolver
            ->expects('resolveEligibleItems')
            ->andReturn([]);

        $context = $this->makeContext(['forCart' => true, 'cartItems' => [['id' => 1]]]);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertSame('Voucher is not applicable to any items in your cart', $result->message);
    }

    public function testValidateCartReturnsInvalidWhenBelowMinimumOrderValue(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->minimum_order_value = 50.0;

        $this->eligibilityResolver
            ->expects('resolveEligibleItems')
            ->andReturn([['subtotal' => 20.0]]);

        $context = $this->makeContext(['forCart' => true, 'cartItems' => [['id' => 1]]]);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('£50', $result->message);
    }

    public function testValidateCartReturnsValidResultWithCorrectEligibleSubtotalAndDiscount(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->minimum_order_value = null;
        $voucher->allows('calculateDiscount')->with(30.0)->andReturn(3.0);
        $voucher->allows('isNonStackable')->andReturn(false);
        $voucher->allows('requiresOverrideForOfferDiscount')->andReturn(false);

        $this->eligibilityResolver
            ->expects('resolveEligibleItems')
            ->andReturn([['subtotal' => 30.0]]);

        $context = $this->makeContext(['forCart' => true, 'cartItems' => [['id' => 1]]]);

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertSame(3.0, $result->discount);
        $this->assertSame(30.0, $result->eligibleSubtotal);
        $this->assertSame(27.0, $result->finalPrice);
    }

    public function testValidateCartSetsIsStackableFalseWhenVoucherIsNonStackable(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->minimum_order_value = null;
        $voucher->allows('calculateDiscount')->andReturn(5.0);
        $voucher->allows('isNonStackable')->andReturn(true);
        $voucher->allows('requiresOverrideForOfferDiscount')->andReturn(false);

        $this->eligibilityResolver
            ->expects('resolveEligibleItems')
            ->andReturn([['subtotal' => 50.0]]);

        $context = $this->makeContext(['forCart' => true, 'cartItems' => [['id' => 1]]]);

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertFalse($result->isStackable);
    }

    public function testValidateCartSetsRequiresOverrideDecisionWhenApplicable(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->minimum_order_value = null;
        $voucher->allows('calculateDiscount')->andReturn(5.0);
        $voucher->allows('isNonStackable')->andReturn(false);
        $voucher->allows('requiresOverrideForOfferDiscount')->andReturn(true);

        $this->eligibilityResolver
            ->expects('resolveEligibleItems')
            ->andReturn([['subtotal' => 50.0]]);

        $context = $this->makeContext(['forCart' => true, 'cartItems' => [['id' => 1]]]);

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertTrue($result->requiresOverrideDecision);
    }

    public function testValidateCartUsesOrderValueWhenNotForCart(): void
    {
        $voucher = $this->makeValidVoucher();
        $voucher->minimum_order_value = null;
        $voucher->allows('calculateDiscount')->with(200.0)->andReturn(20.0);
        $voucher->allows('isNonStackable')->andReturn(false);
        $voucher->allows('requiresOverrideForOfferDiscount')->andReturn(false);

        $this->eligibilityResolver
            ->expects('resolveEligibleItems')
            ->andReturn([]);

        $context = $this->makeContext(['forCart' => false, 'orderValue' => 200.0]);

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertSame(200.0, $result->eligibleSubtotal);
    }
}