<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Vouchers\VoucherValidationResult;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlanPricing;
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

    public function test_invalid_voucher_message_is_propagated_without_calling_stripe(): void
    {
        $subscription = $this->subscription();
        $subscription->subscription_plan_pricing_id = 10;
        $subscription->delivery_type = 'print';

        $this->subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $this->voucherService
            ->shouldReceive('validateVoucherForSubscription')
            ->once()
            ->with(
                code: 'EXPIRED',
                planId: 5,
                userId: 20,
                pricingTierId: 10,
                deliveryType: 'print',
            )
            ->andReturn(VoucherValidationResult::invalid('Voucher has expired.'));

        $this->couponGateway->shouldNotReceive('getOrCreateForVoucher');
        $this->stripe->shouldNotReceive('subscriptions');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Voucher has expired.');

        $this->service->applyVoucher(1, 'EXPIRED');
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
