<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\DTO\Stripe\CreateStripeSubscriptionScheduleDto;
use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Factories\Stripe\StripeSchedulePhaseFactory;
use App\Services\Billing\Stripe\StripeSubscriptionScheduleGateway;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Service\SubscriptionScheduleService;
use Stripe\Service\SubscriptionService;
use Stripe\Subscription;
use Stripe\StripeClient;
use Stripe\SubscriptionSchedule;

class StripeSubscriptionScheduleGatewayTest extends TestCase
{
    private StripeClient                       $stripeClient;
    private SubscriptionScheduleService        $schedules;
    private SubscriptionService                $subscriptions;
    private StripeSchedulePhaseFactory         $phaseFactory;
    private StripeSubscriptionScheduleGateway  $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schedules     = m::mock(SubscriptionScheduleService::class);
        $this->subscriptions = m::mock(SubscriptionService::class);
        $this->phaseFactory  = m::mock(StripeSchedulePhaseFactory::class);

        $this->stripeClient = m::mock(StripeClient::class)->makePartial();
        $this->stripeClient->subscriptionSchedules = $this->schedules;
        $this->stripeClient->subscriptions         = $this->subscriptions;

        $this->gateway = new StripeSubscriptionScheduleGateway(
            $this->stripeClient,
            $this->phaseFactory,
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_create_calls_schedule_api_with_built_phases(): void
    {
        $dto    = $this->makeDto();
        $phases = [['items' => [['price' => 'price_intro']]], ['items' => [['price' => 'price_rec']]]];

        $this->phaseFactory
            ->shouldReceive('buildPhases')
            ->once()
            ->with($dto)
            ->andReturn($phases);

        $this->schedules
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function (array $params) use ($phases) {
                return $params['customer']     === 'cus_test'
                    && $params['start_date']   === 'now'
                    && $params['end_behavior'] === 'release'
                    && $params['phases']       === $phases;
            }))
            ->andReturn($this->makeSchedule('sub_test', 'sched_test'));

        $this->subscriptions
            ->shouldReceive('retrieve')
            ->once()
            ->with('sub_test', m::any())
            ->andReturn($this->makeStripeSubscription('active'));

        $result = $this->gateway->create($dto);

        $this->assertInstanceOf(StripeSubscriptionResultDto::class, $result);
        $this->assertSame('sub_test',   $result->stripeSubscriptionId);
        $this->assertSame('sched_test', $result->stripeScheduleId);
    }

    public function test_create_offsets_start_date_for_trial_intro(): void
    {
        $dto = $this->makeDto(trialDays: 14);

        $this->phaseFactory->shouldReceive('buildPhases')->andReturn([]);

        $this->schedules
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function (array $params) {
                // start_date should be a timestamp ~14 days in the future, not 'now'
                return is_int($params['start_date'])
                    && $params['start_date'] > time() + (13 * 86400)
                    && $params['start_date'] < time() + (15 * 86400);
            }))
            ->andReturn($this->makeSchedule('sub_test', 'sched_test'));

        $this->subscriptions
            ->shouldReceive('retrieve')
            ->andReturn($this->makeStripeSubscription('active'));

        $result = $this->gateway->create($dto);

        $this->assertSame('sched_test', $result->stripeScheduleId);
    }

    public function test_create_wraps_stripe_api_exception(): void
    {
        $dto = $this->makeDto();

        $this->phaseFactory->shouldReceive('buildPhases')->andReturn([]);

        $this->schedules
            ->shouldReceive('create')
            ->andThrow(new \Stripe\Exception\InvalidRequestException('Invalid', null));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe subscription schedule creation failed');

        $this->gateway->create($dto);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeDto(?int $trialDays = null): CreateStripeSubscriptionScheduleDto
    {
        return new CreateStripeSubscriptionScheduleDto(
            stripeCustomerId:  'cus_test',
            introPriceId:      'price_intro',
            recurringPriceId:  'price_rec',
            introCycles:       1,
            subscriptionId:    1,
            planId:            1,
            memberId:          1,
            siteId:            1,
            trialDays:         $trialDays,
        );
    }

    private function makeSchedule(
        string $subscriptionId,
        string $scheduleId
    ): SubscriptionSchedule {
        return SubscriptionSchedule::constructFrom([
            'id' => $scheduleId,
            'subscription' => $subscriptionId,
        ]);
    }

    private function makeStripeSubscription(string $status): Subscription
    {
        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_test',
            'status' => 'succeeded',
            'client_secret' => null,
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