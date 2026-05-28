<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Enums\Vouchers\VoucherType;
use App\Framework\Database\Database;
use App\Models\Voucher;
use App\Repositories\Vouchers\VoucherRepository;
use App\Services\Billing\Stripe\StripeCouponGateway;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Stripe\Coupon;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\CouponService;
use Stripe\StripeClient;

class StripeCouponGatewayTest extends TestCase
{
    private MockInterface $stripe;
    private MockInterface $couponService;
    private MockInterface $voucherRepository;
    private MockInterface $database;
    private StripeCouponGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripe          = Mockery::mock(StripeClient::class)->makePartial();
        $this->couponService   = Mockery::mock(CouponService::class);
        $this->stripe->coupons = $this->couponService;

        $this->voucherRepository = Mockery::mock(VoucherRepository::class);
        $this->database = Mockery::mock(Database::class);

        $this->gateway = new StripeCouponGateway(
            $this->stripe,
            $this->voucherRepository,
            $this->database,
        );
    }

    public function test_it_throws_when_voucher_is_not_found(): void
    {
        $this->voucherRepository
            ->shouldReceive('find')
            ->with(99)
            ->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Voucher #99 not found.');

        $this->gateway->getOrCreateForVoucher(99, 'gbp');
    }

    public function test_it_returns_existing_stripe_coupon_id_when_still_valid(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Percentage,
            percentOff: 10,
            stripeCouponId: 'existing_coupon_id',
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository
            ->shouldReceive('find')
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->voucherRepository
            ->shouldReceive('lockForUpdate')
            ->once()
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->voucherRepository
            ->shouldReceive('find')
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->couponService
            ->shouldReceive('retrieve')
            ->with('existing_coupon_id')
            ->once()
            ->andReturn($this->makeStripeCoupon('existing_coupon_id'));

        $result = $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');

        $this->assertSame('existing_coupon_id', $result['coupon_id']);
        $this->assertSame($voucher->id, $result['voucher_id']);
        $this->assertSame($voucher->code, $result['voucher_code']);
    }

    public function test_it_recreates_coupon_when_stored_stripe_id_is_stale(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Percentage,
            percentOff: 20,
            stripeCouponId: 'stale_coupon_id',
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository
            ->shouldReceive('find')
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->voucherRepository
            ->shouldReceive('lockForUpdate')
            ->once()
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->voucherRepository
            ->shouldReceive('find')
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->couponService
            ->shouldReceive('retrieve')
            ->with('stale_coupon_id')
            ->once()
            ->andThrow(InvalidRequestException::factory(
                'No such coupon',
                404,
                null,
                ['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such coupon']],
            ));

        $newCoupon = $this->makeStripeCoupon('new_coupon_id');

        $this->couponService
            ->shouldReceive('create')
            ->once()
            ->andReturn($newCoupon);

        $voucher->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn(array $data) => $data['stripe_coupon_id'] === 'new_coupon_id' && array_key_exists('stripe_coupon_synced_at', $data)));

        $result = $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');

        $this->assertSame('new_coupon_id', $result['coupon_id']);
    }

    public function test_it_creates_new_coupon_when_voucher_has_no_stripe_id(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Percentage,
            percentOff: 15,
            stripeCouponId: null,
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository
            ->shouldReceive('find')
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->voucherRepository
            ->shouldReceive('lockForUpdate')
            ->once()
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->voucherRepository
            ->shouldReceive('find')
            ->with($voucher->id)
            ->andReturn($voucher);

        $newCoupon = $this->makeStripeCoupon('fresh_coupon_id');

        $this->couponService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                return $payload['percent_off'] === 15
                    && $payload['duration'] === 'once';
            }))
            ->andReturn($newCoupon);

        $voucher->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn(array $data) => $data['stripe_coupon_id'] === 'fresh_coupon_id' && array_key_exists('stripe_coupon_synced_at', $data)));

        $result = $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');

        $this->assertSame('fresh_coupon_id', $result['coupon_id']);
    }

    public function test_it_builds_fixed_amount_coupon_payload_correctly(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Fixed,
            amountOff: 500,
            stripeCouponId: null,
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository
            ->shouldReceive('find')
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->voucherRepository
            ->shouldReceive('lockForUpdate')
            ->once()
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->voucherRepository
            ->shouldReceive('find')
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->couponService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                return $payload['amount_off'] === 500
                    && $payload['currency'] === 'gbp'
                    && !isset($payload['percent_off']);
            }))
            ->andReturn($this->makeStripeCoupon('fixed_coupon_id'));

        $voucher->shouldReceive('update')->once();

        $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');

        $this->assertTrue(true);

        // assertion satisfied by Mockery::on() constraint above
        $this->assertTrue(true);
    }

    public function test_it_builds_repeating_coupon_when_duration_in_months_is_set(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Percentage,
            percentOff: 10,
            stripeCouponId: null,
            duration: 'repeating',
            durationInMonths: 3,
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository
            ->shouldReceive('find')
            ->andReturn($voucher);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->voucherRepository
            ->shouldReceive('lockForUpdate')
            ->once()
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->couponService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                return $payload['duration'] === 'repeating'
                    && $payload['duration_in_months'] === 3;
            }))
            ->andReturn($this->makeStripeCoupon('repeating_coupon'));

        $voucher->shouldReceive('update')->once();

        $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');

        $this->assertTrue(true);

        $this->assertTrue(true);
    }

    public function test_it_lowercases_currency_before_creating_coupon(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Fixed,
            amountOff: 1000,
            stripeCouponId: null,
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository
            ->shouldReceive('find')
            ->andReturn($voucher);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->voucherRepository
            ->shouldReceive('lockForUpdate')
            ->once()
            ->with($voucher->id)
            ->andReturn($voucher);

        $this->couponService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $p) => $p['currency'] === 'usd'))
            ->andReturn($this->makeStripeCoupon('usd_coupon'));

        $voucher->shouldReceive('update')->once();

        $this->gateway->getOrCreateForVoucher($voucher->id, 'USD');

        $this->assertTrue(true);
    }

    public function test_it_builds_once_coupon_without_duration_in_months(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Fixed,
            amountOff: 1500,
            stripeCouponId: null,
            duration: 'once',
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository->shouldReceive('find')->andReturn($voucher);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        $this->voucherRepository->shouldReceive('lockForUpdate')->once()->with($voucher->id)->andReturn($voucher);

        $this->couponService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $payload) => $payload['duration'] === 'once' && !isset($payload['duration_in_months'])))
            ->andReturn($this->makeStripeCoupon('once_coupon'));

        $voucher->shouldReceive('update')->once();

        $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');

        $this->assertTrue(true);
    }

    public function test_it_builds_forever_coupon_without_duration_in_months(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Percentage,
            percentOff: 25,
            stripeCouponId: null,
            duration: 'forever',
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository->shouldReceive('find')->andReturn($voucher);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        $this->voucherRepository->shouldReceive('lockForUpdate')->once()->with($voucher->id)->andReturn($voucher);

        $this->couponService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $payload) => $payload['duration'] === 'forever' && !isset($payload['duration_in_months']) && $payload['percent_off'] === 25))
            ->andReturn($this->makeStripeCoupon('forever_coupon'));

        $voucher->shouldReceive('update')->once();

        $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');

        $this->assertTrue(true);
    }

    public function test_it_throws_for_repeating_coupon_without_duration_in_months(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Fixed,
            amountOff: 1500,
            stripeCouponId: null,
            duration: 'repeating',
            durationInMonths: null,
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository->shouldReceive('find')->andReturn($voucher);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        $this->voucherRepository->shouldReceive('lockForUpdate')->once()->with($voucher->id)->andReturn($voucher);
        $this->couponService->shouldNotReceive('create');

        $this->expectException(\DomainException::class);

        $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');
    }

    public function test_it_throws_for_non_repeating_coupon_with_duration_in_months(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Percentage,
            percentOff: 10,
            stripeCouponId: null,
            duration: 'forever',
            durationInMonths: 6,
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository->shouldReceive('find')->andReturn($voucher);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        $this->voucherRepository->shouldReceive('lockForUpdate')->once()->with($voucher->id)->andReturn($voucher);
        $this->couponService->shouldNotReceive('create');

        $this->expectException(\DomainException::class);

        $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');
    }

    public function test_it_throws_for_invalid_percentage_coupon(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Percentage,
            percentOff: 150,
            stripeCouponId: null,
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository->shouldReceive('find')->andReturn($voucher);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        $this->voucherRepository->shouldReceive('lockForUpdate')->once()->with($voucher->id)->andReturn($voucher);
        $this->couponService->shouldNotReceive('create');

        $this->expectException(\DomainException::class);

        $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');
    }

    public function test_it_throws_for_zero_fixed_coupon_amount(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Fixed,
            amountOff: 0,
            stripeCouponId: null,
        );

        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository->shouldReceive('find')->andReturn($voucher);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        $this->voucherRepository->shouldReceive('lockForUpdate')->once()->with($voucher->id)->andReturn($voucher);
        $this->couponService->shouldNotReceive('create');

        $this->expectException(\DomainException::class);

        $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');
    }

    public function test_it_rejects_order_only_vouchers_for_subscription_coupons(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Fixed,
            amountOff: 1500,
            stripeCouponId: null,
        );
        $voucher->applies_to_subscriptions = false;

        $this->voucherRepository->shouldReceive('find')->with($voucher->id)->andReturn($voucher);
        $this->database->shouldNotReceive('transaction');
        $this->couponService->shouldNotReceive('create');

        $this->expectException(\DomainException::class);

        $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');
    }

    public function test_it_does_not_recreate_coupon_for_non_missing_stripe_errors(): void
    {
        $voucher = $this->makeVoucher(
            discountType: VoucherType::Percentage,
            percentOff: 10,
            stripeCouponId: 'bad_coupon',
        );
        $voucher->applies_to_subscriptions = true;

        $this->voucherRepository->shouldReceive('find')->with($voucher->id)->andReturn($voucher);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());
        $this->voucherRepository->shouldReceive('lockForUpdate')->once()->with($voucher->id)->andReturn($voucher);

        $this->couponService
            ->shouldReceive('retrieve')
            ->once()
            ->with('bad_coupon')
            ->andThrow(InvalidRequestException::factory(
                'Bad API key',
                401,
                null,
                ['error' => ['type' => 'invalid_request_error', 'code' => 'api_key_expired', 'message' => 'Bad API key']],
            ));

        $this->couponService->shouldNotReceive('create');

        $this->expectException(InvalidRequestException::class);

        $this->gateway->getOrCreateForVoucher($voucher->id, 'gbp');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeVoucher(
        VoucherType $discountType,
        ?int        $amountOff = null,
        ?int        $percentOff = null,
        ?string     $stripeCouponId = null,
        ?string     $duration = 'once',
        ?int        $durationInMonths = null,
        int         $id = 1,
        string      $code = 'TEST10',
        string      $name = 'Test Voucher',
    ): Voucher|MockInterface {
        $voucher = Mockery::mock(Voucher::class)->makePartial();

        $voucher->id = $id;
        $voucher->code = $code;
        $voucher->name = $name;
        $voucher->type = $discountType->value;
        $voucher->value = $discountType === VoucherType::Fixed
            ? ($amountOff ?? 0) / 100
            : (float) ($percentOff ?? 0);
        $voucher->discount_type = $discountType->value;
        $voucher->discount_amount = $amountOff;
        $voucher->discount_percentage = $percentOff;
        $voucher->subscription_discount_duration = $duration;
        $voucher->subscription_duration_months = $durationInMonths;
        $voucher->stripe_coupon_id = $stripeCouponId;

        return $voucher;
    }

    /**
     * Construct a real \Stripe\Coupon without hitting the API.
     *
     * StripeObject blocks direct property assignment ($obj->id = '...') because
     * it routes everything through an internal _values array. constructFrom()
     * is the correct factory — it populates that array and makes the properties
     * readable via __get.
     */
    private function makeStripeCoupon(string $id): Coupon
    {
        return Coupon::constructFrom(['id' => $id]);
    }
}
