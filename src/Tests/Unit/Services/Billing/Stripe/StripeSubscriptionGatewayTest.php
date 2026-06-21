<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\DTO\Stripe\CreateStripeSubscriptionDto;
use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Services\Billing\Stripe\StripeCouponGateway;
use App\Services\Billing\Stripe\StripeSubscriptionGateway;
use DateTimeImmutable;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Service\SubscriptionService;
use Stripe\Subscription;
use Stripe\StripeClient;

class StripeSubscriptionGatewayTest extends TestCase
{
    private StripeClient $stripeClient;
    private SubscriptionService $subscriptions;
    private StripeCouponGateway $couponGateway;
    private StripeSubscriptionGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptions = m::mock(SubscriptionService::class);
        $this->couponGateway = m::mock(StripeCouponGateway::class);

        $this->stripeClient = m::mock(StripeClient::class)->makePartial();
        $this->stripeClient->subscriptions = $this->subscriptions;

        $this->gateway = new StripeSubscriptionGateway($this->stripeClient, $this->couponGateway);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_create_calls_stripe_with_correct_payload(): void
    {
        $dto = $this->makeDto();

        $this->couponGateway->shouldNotReceive('getOrCreateForVoucher');

        $this->subscriptions
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function (array $params) {
                return $params['customer'] === 'cus_test'
                    && $params['items'][0]['price'] === 'price_test'
                    && $params['metadata']['plan_id'] === 1
                    && !isset($params['trial_period_days']);
            }))
            ->andReturn($this->makeStripeSubscription('active'));

        $result = $this->gateway->create($dto);

        $this->assertInstanceOf(StripeSubscriptionResultDto::class, $result);
        $this->assertSame('sub_test', $result->stripeSubscriptionId);
        $this->assertSame('active', $result->status);
        $this->assertNull($result->stripeScheduleId);
    }

    public function test_create_with_trial_includes_trial_period_days(): void
    {
        $dto = $this->makeDto(trialDays: 14);

        $this->couponGateway->shouldNotReceive('getOrCreateForVoucher');

        $this->subscriptions
            ->shouldReceive('create')
            ->once()
            ->with(m::on(fn (array $p) => $p['trial_period_days'] === 14))
            ->andReturn($this->makeStripeSubscription('trialing'));

        $result = $this->gateway->createWithTrial($dto);

        $this->assertSame('trialing', $result->status);
    }

    public function test_create_with_trial_throws_when_trial_days_null(): void
    {
        $dto = $this->makeDto(trialDays: null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('createWithTrial requires a trialDays value');

        $this->gateway->createWithTrial($dto);
    }

    public function test_create_with_trial_throws_when_trial_days_zero(): void
    {
        $dto = $this->makeDto(trialDays: 0);

        $this->expectException(\InvalidArgumentException::class);

        $this->gateway->createWithTrial($dto);
    }

    public function test_create_maps_requires_action_from_payment_intent(): void
    {
        $dto = $this->makeDto();

        $this->couponGateway->shouldNotReceive('getOrCreateForVoucher');

        $this->subscriptions
            ->shouldReceive('create')
            ->once()
            ->andReturn($this->makeStripeSubscription('active', requiresAction: true));

        $result = $this->gateway->create($dto);

        $this->assertTrue($result->requiresAction);
        $this->assertNotNull($result->paymentIntentClientSecret);
    }

    public function test_create_wraps_stripe_api_exception(): void
    {
        $dto = $this->makeDto();

        $this->couponGateway->shouldNotReceive('getOrCreateForVoucher');

        $this->subscriptions
            ->shouldReceive('create')
            ->andThrow(new \Stripe\Exception\CardException('card_declined', null));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe subscription creation failed');

        $this->gateway->create($dto);
    }

    public function test_create_applies_stripe_coupon_when_voucher_present(): void
    {
        $dto = $this->makeDto(voucherId: 55);

        $this->couponGateway
            ->shouldReceive('getOrCreateForVoucher')
            ->once()
            ->with(55, 'gbp')
            ->andReturn([
                'coupon_id' => 'coupon_test',
                'voucher_id' => 55,
                'voucher_code' => 'SAVE10',
            ]);
        $this->subscriptions
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function (array $params) {
                return $params['discounts'] === [['coupon' => 'coupon_test']]
                    && $params['metadata']['voucher_id'] === 55
                    && $params['metadata']['voucher_code'] === 'SAVE10';
            }))
            ->andReturn($this->makeStripeSubscription('active'));

        $result = $this->gateway->create($dto);

        $this->assertSame('sub_test', $result->stripeSubscriptionId);
    }

    public function test_pause_collection_voids_invoices_while_paused(): void
    {
        $this->subscriptions
            ->shouldReceive('update')
            ->once()
            ->with('sub_test', [
                'pause_collection' => [
                    'behavior' => 'void',
                ],
            ]);

        $this->gateway->pauseCollection('sub_test');

        $this->assertTrue(true);
    }

    public function test_resume_collection_clears_pause_and_returns_stripe_period_end(): void
    {
        $periodEnd = strtotime('2026-07-21 00:00:00');
        $stripeSubscription = Subscription::constructFrom([
            'id' => 'sub_test',
            'current_period_end' => $periodEnd,
        ]);

        $this->subscriptions
            ->shouldReceive('update')
            ->once()
            ->with('sub_test', [
                'pause_collection' => '',
            ])
            ->andReturn($stripeSubscription);

        $result = $this->gateway->resumeCollection('sub_test');

        $this->assertSame(
            '2026-07-21 00:00:00',
            $result?->format('Y-m-d H:i:s'),
        );
    }

    public function test_move_end_date_updates_cancel_at_and_metadata(): void
    {
        $newEndDate = new DateTimeImmutable('2026-07-28 00:00:00');

        $this->subscriptions
            ->shouldReceive('update')
            ->once()
            ->with('sub_test', m::on(function (array $params) use ($newEndDate): bool {
                return $params['cancel_at'] === $newEndDate->getTimestamp()
                    && $params['metadata']['replacement_extension_applied'] === '1'
                    && $params['metadata']['replacement_extension_end_date'] === '2026-07-28 00:00:00';
            }));

        $this->gateway->moveEndDate('sub_test', $newEndDate);

        $this->assertTrue(true);
    }

    private function makeDto(?int $trialDays = null, ?int $voucherId = null): CreateStripeSubscriptionDto
    {
        return new CreateStripeSubscriptionDto(
            stripeCustomerId: 'cus_test',
            stripePriceId: 'price_test',
            subscriptionId: 1,
            planId: 1,
            memberId: 1,
            siteId: 1,
            trialDays: $trialDays,
            currency: 'gbp',
            voucherId: $voucherId,
        );
    }

    private function makeStripeSubscription(
        string $status,
        bool $requiresAction = false,
    ): Subscription {
        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_test',
            'status' => $requiresAction ? 'requires_action' : 'succeeded',
            'client_secret' => $requiresAction ? 'secret_test' : null,
        ]);

        $invoice = Invoice::constructFrom([
            'id' => 'in_test',
            'payment_intent' => $paymentIntent,
        ]);

        return Subscription::constructFrom([
            'id' => 'sub_test',
            'status' => $status,
            'customer' => 'cus_test',
            'current_period_start' => time(),
            'current_period_end' => time() + 2592000,
            'latest_invoice' => $invoice,
        ]);
    }
}
