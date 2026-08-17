<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Vouchers\VoucherValidationResult;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlanPricing;
use App\Models\Voucher;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeCouponGateway;
use App\Services\Billing\Stripe\StripeSubscriptionPlanUpdater;
use App\Services\Subscriptions\SubscriptionRetentionIncentiveService;
use App\Services\Vouchers\VoucherService;
use InvalidArgumentException;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Stripe\StripeClient;

final class SubscriptionRetentionIncentiveServiceTest extends TestCase
{
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionPlanPricingRepository $pricingRepository;
    private VoucherService $voucherService;
    private StripeCouponGateway $couponGateway;
    private StripeSubscriptionPlanUpdater $planUpdater;
    private Database $database;
    private StripeClient $stripe;
    private SubscriptionRetentionIncentiveService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->pricingRepository = m::mock(SubscriptionPlanPricingRepository::class);
        $this->voucherService = m::mock(VoucherService::class);
        $this->couponGateway = m::mock(StripeCouponGateway::class);
        $this->planUpdater = m::mock(StripeSubscriptionPlanUpdater::class);
        $this->database = m::mock(Database::class);
        $this->stripe = m::mock(StripeClient::class);

        $this->service = new SubscriptionRetentionIncentiveService(
            $this->subscriptionRepository,
            $this->pricingRepository,
            $this->voucherService,
            $this->couponGateway,
            $this->planUpdater,
            $this->database,
            $this->stripe,
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_offer_is_rejected_when_subscription_does_not_exist(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(99)
            ->andReturnNull();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription not found.');

        $this->service->applyOffer(99, 10, 'print');
    }

    public function test_offer_is_rejected_when_subscription_is_not_live(): void
    {
        $subscription = $this->subscription(status: 'cancelled');

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($subscription);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Retention incentives can only be applied to a live subscription.');

        $this->service->applyOffer(1, 10, 'print');
    }

    public function test_offer_is_rejected_when_pricing_belongs_to_another_plan(): void
    {
        $subscription = $this->subscription(planId: 5);
        $pricing = $this->pricing(planId: 6);

        $this->subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);
        $this->pricingRepository->shouldReceive('find')->once()->with(10)->andReturn($pricing);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Selected offer does not belong to this subscription plan.');

        $this->service->applyOffer(1, 10, 'print');
    }

    public function test_intro_offer_is_rejected_because_it_requires_a_schedule(): void
    {
        $subscription = $this->subscription();
        $pricing = $this->pricing();

        $this->subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);
        $this->pricingRepository->shouldReceive('find')->once()->with(10)->andReturn($pricing);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only standard print or digital offers can be used for retention.');

        $this->service->applyOffer(1, 10, 'intro');
    }

    public function test_print_offer_updates_stripe_and_restores_subscription_renewal(): void
    {
        $subscription = $this->subscription();
        $pricing = $this->pricing();
        $updated = $this->subscription();
        $updated->price = 12.50;

        $pricing->shouldReceive('getEffectivePrintPrice')->once()->andReturn(12.50);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription, $updated);

        $this->pricingRepository->shouldReceive('find')->once()->with(10)->andReturn($pricing);

        $this->planUpdater
            ->shouldReceive('updateSubscriptionItemPrice')
            ->once()
            ->with('si_123', 'price_123', ['proration_behavior' => 'none'])
            ->andReturn(['success' => true]);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static fn (callable $callback) => $callback());

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, m::on(static function (array $data): bool {
                return $data['subscription_plan_pricing_id'] === 10
                    && $data['offer_type'] === 'print'
                    && $data['price'] === 12.50
                    && $data['price_paid_cents'] === 1250
                    && $data['stripe_price_id'] === 'price_123'
                    && $data['stripe_subscription_item_id'] === 'si_123'
                    && $data['cancelled_at'] === null
                    && $data['cancel_at_period_end'] === false
                    && $data['auto_renew'] === true;
            }));

        $result = $this->service->applyOffer(1, 10, 'print');

        self::assertSame($updated, $result);
    }

    public function test_invalid_voucher_message_is_propagated_before_stripe_is_called(): void
    {
        $subscription = $this->subscription();
        $subscription->subscription_plan_pricing_id = 10;
        $subscription->delivery_type = 'print';

        $this->subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $this->voucherService
            ->shouldReceive('validateVoucherForSubscription')
            ->once()
            ->with('EXPIRED', 5, 20, 10, 'print')
            ->andReturn(VoucherValidationResult::invalid('Voucher has expired.'));

        $this->couponGateway->shouldNotReceive('getOrCreateForVoucher');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Voucher has expired.');

        $this->service->applyVoucher(1, 'EXPIRED');
    }

    public function test_voucher_applies_stripe_coupon_and_restores_subscription_renewal(): void
    {
        $subscription = $this->subscription();
        $subscription->subscription_plan_pricing_id = 10;
        $subscription->delivery_type = 'print';
        $updated = $this->subscription();

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = 55;
        $voucher->code = 'SAVE10';
        $validation = VoucherValidationResult::valid($voucher, 5.00);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription, $updated);

        $this->voucherService
            ->shouldReceive('validateVoucherForSubscription')
            ->once()
            ->with('SAVE10', 5, 20, 10, 'print')
            ->andReturn($validation);

        $this->couponGateway
            ->shouldReceive('getOrCreateForVoucher')
            ->once()
            ->with(55, 'gbp')
            ->andReturn(['coupon_id' => 'coup_1']);

        $subscriptions = new class {
            public array $calls = [];
            public function update(string $id, array $data): void
            {
                $this->calls[] = [$id, $data];
            }
        };
        $this->stripe->subscriptions = $subscriptions;

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static fn (callable $callback) => $callback());

        $this->voucherService
            ->shouldReceive('applyVoucher')
            ->once()
            ->with(55, 20, 5.0)
            ->andReturn(true);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, [
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'auto_renew' => true,
            ]);

        $result = $this->service->applyVoucher(1, 'SAVE10');

        self::assertSame($updated, $result);
        self::assertSame('sub_123', $subscriptions->calls[0][0]);
        self::assertSame('coup_1', $subscriptions->calls[0][1]['discounts'][0]['coupon']);
    }

    public function test_voucher_is_not_consumed_when_transaction_fails(): void
    {
        // Regression test: applyVoucher() previously called
        // voucherService->applyVoucher() (marking the voucher used) and
        // subscriptionRepository->update() (lifting the cancellation) as
        // two unwrapped writes. A failure between them consumed the
        // voucher without ever restoring the subscription. Both writes
        // must now happen inside a single Database::transaction() call.
        $subscription = $this->subscription();
        $subscription->subscription_plan_pricing_id = 10;
        $subscription->delivery_type = 'print';

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = 55;
        $voucher->code = 'SAVE10';
        $validation = VoucherValidationResult::valid($voucher, 5.00);

        $this->subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $this->voucherService
            ->shouldReceive('validateVoucherForSubscription')
            ->once()
            ->andReturn($validation);

        $this->couponGateway
            ->shouldReceive('getOrCreateForVoucher')
            ->once()
            ->andReturn(['coupon_id' => 'coup_1']);

        $subscriptions = new class {
            public function update(string $id, array $data): void
            {
            }
        };
        $this->stripe->subscriptions = $subscriptions;

        $this->voucherService->shouldNotReceive('applyVoucher');
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('could not open transaction'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not open transaction');

        $this->service->applyVoucher(1, 'SAVE10');
    }

    private function subscription(string $status = 'active', int $planId = 5): Subscription
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->member_id = 20;
        $subscription->plan_id = $planId;
        $subscription->status = $status;
        $subscription->currency = 'GBP';
        $subscription->stripe_subscription_id = 'sub_123';
        $subscription->stripe_subscription_item_id = 'si_123';

        return $subscription;
    }

    private function pricing(int $planId = 5): SubscriptionPlanPricing
    {
        $pricing = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->id = 10;
        $pricing->plan_id = $planId;
        $pricing->is_active = true;
        $pricing->stripe_price_id = 'price_123';

        return $pricing;
    }
}
