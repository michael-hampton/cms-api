<?php

namespace App\Tests\Unit\Services\Billing;

use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Models\Address;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\StripeSubscriptionOrchestrator;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Subscriptions\SubscriptionBillingService;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class StripeSubscriptionOrchestratorTest extends TestCase
{
    private MockInterface $customerGateway;
    private MockInterface $billingService;
    private MockInterface $pricingRepository;
    private StripeSubscriptionOrchestrator $orchestrator;
    private SubscriptionRepository $subscriptionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerGateway   = m::mock(StripeCustomerGateway::class);
        $this->billingService    = m::mock(SubscriptionBillingService::class);
        $this->pricingRepository = m::mock(SubscriptionPlanPricingRepository::class);

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->subscriptionRepository->shouldReceive('memberHadTrialOnPlan')->andReturn(false)->byDefault();

        $this->orchestrator = new StripeSubscriptionOrchestrator(
            $this->customerGateway,
            $this->billingService,
            $this->pricingRepository,
            $this->subscriptionRepository
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ── Standard flow ─────────────────────────────────────────────────────────

    public function test_create_runs_full_workflow_and_returns_result(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();
        $result = $this->makeResult();

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);

        $member->shouldReceive('resolveBillingAddress')->andReturn(null);

        $this->customerGateway
            ->shouldReceive('getOrCreate')
            ->once()
            ->with($member, null)
            ->andReturn('cus_test');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->once()
            ->with($subscription, $plan, $pricingTier, 'cus_test', 0)
            ->andReturn($result);

        $subscription->shouldReceive('update')->once();

        $returned = $this->orchestrator->create($subscription, $plan, $member, []);

        $this->assertSame($result, $returned);
    }

    public function test_create_passes_address_to_customer_gateway(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();
        $address = m::mock(Address::class)->makePartial();

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);
        $member->shouldReceive('resolveBillingAddress')->andReturn($address);

        $this->customerGateway
            ->shouldReceive('getOrCreate')
            ->once()
            ->with($member, $address)
            ->andReturn('cus_test');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->once();

        $this->orchestrator->create($subscription, $plan, $member, []);

        $this->assertTrue(true);
    }

    // ── Pricing tier resolution ───────────────────────────────────────────────

    public function test_uses_explicit_pricing_tier_id_from_data_when_provided(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $this->pricingRepository
            ->shouldReceive('find')
            ->once()
            ->with(99)
            ->andReturn($pricingTier);

        // getDefaultPricing must NOT be called when an explicit tier is resolved
        $plan->shouldNotReceive('getDefaultPricing');

        $member->shouldReceive('resolveBillingAddress')->andReturn(null);

        $this->customerGateway
            ->shouldReceive('getOrCreate')
            ->andReturn('cus_test');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->once()
            ->with($subscription, $plan, $pricingTier, 'cus_test', 0)
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->once();

        $this->orchestrator->create(
            $subscription, $plan, $member,
            ['pricing_tier_id' => 99]
        );

        $this->assertTrue(true);
    }

    public function test_falls_back_to_plan_default_when_explicit_tier_not_found(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $this->pricingRepository
            ->shouldReceive('find')
            ->with(99)
            ->andReturn(null); // not found

        $plan->shouldReceive('getDefaultPricing')->once()->andReturn($pricingTier);

        $member->shouldReceive('resolveBillingAddress')->andReturn(null);

        $this->customerGateway
            ->shouldReceive('getOrCreate')
            ->andReturn('cus_test');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->once();

        $this->orchestrator->create(
            $subscription, $plan, $member,
            ['pricing_tier_id' => 99]
        );

        $this->assertTrue(true);
    }

    public function test_falls_back_to_plan_default_when_explicit_tier_belongs_to_different_plan(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $wrongTier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $wrongTier->id      = 99;
        $wrongTier->plan_id = 999; // different plan
        $wrongTier->is_active = true;

        $this->pricingRepository
            ->shouldReceive('find')
            ->with(99)
            ->andReturn($wrongTier);

        $plan->shouldReceive('getDefaultPricing')->once()->andReturn($pricingTier);

        $member->shouldReceive('resolveBillingAddress')->andReturn(null);

        $this->customerGateway->shouldReceive('getOrCreate')->andReturn('cus_test');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->once();

        $this->orchestrator->create(
            $subscription, $plan, $member,
            ['pricing_tier_id' => 99]
        );

        $this->assertTrue(true);
    }

    public function test_falls_back_to_plan_default_when_explicit_tier_is_inactive(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $inactiveTier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $inactiveTier->id        = 99;
        $inactiveTier->plan_id   = 1;
        $inactiveTier->is_active = false;

        $this->pricingRepository
            ->shouldReceive('find')
            ->with(99)
            ->andReturn($inactiveTier);

        $plan->shouldReceive('getDefaultPricing')->once()->andReturn($pricingTier);

        $member->shouldReceive('resolveBillingAddress')->andReturn(null);

        $this->customerGateway->shouldReceive('getOrCreate')->andReturn('cus_test');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->once();

        $this->orchestrator->create(
            $subscription, $plan, $member,
            ['pricing_tier_id' => 99]
        );

        $this->assertTrue(true);
    }

    public function test_throws_when_no_active_pricing_tier_exists(): void
    {
        [$subscription, $plan, $member] = $this->makeModels();

        $plan->shouldReceive('getDefaultPricing')->andReturn(null);
        $member->shouldReceive('resolveBillingAddress')->andReturn(null);
        $this->customerGateway->shouldReceive('getOrCreate')->andReturn('cus_test');

        $this->billingService->shouldNotReceive('createSubscription');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Plan #1 has no active pricing tier");

        $this->orchestrator->create($subscription, $plan, $member, []);

        $this->assertTrue(true);
    }

    // ── Payment method attachment ─────────────────────────────────────────────

    public function test_attaches_payment_method_when_provided_in_data(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);
        $member->shouldReceive('resolveBillingAddress')->andReturn(null);

        $this->customerGateway
            ->shouldReceive('getOrCreate')
            ->andReturn('cus_test');

        $this->customerGateway
            ->shouldReceive('attachPaymentMethod')
            ->once()
            ->with('cus_test', 'pm_test_123');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->once();

        $this->orchestrator->create(
            $subscription, $plan, $member,
            ['payment_method_id' => 'pm_test_123']
        );

        $this->assertTrue(true);
    }

    public function test_skips_payment_method_attachment_when_not_in_data(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);
        $member->shouldReceive('resolveBillingAddress')->andReturn(null);

        $this->customerGateway
            ->shouldReceive('getOrCreate')
            ->andReturn('cus_test');

        $this->customerGateway->shouldNotReceive('attachPaymentMethod');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->once();

        $this->orchestrator->create($subscription, $plan, $member, []);

        $this->assertTrue(true);
    }

    public function test_skips_payment_method_attachment_when_empty_string(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);
        $member->shouldReceive('resolveBillingAddress')->andReturn(null);

        $this->customerGateway->shouldReceive('getOrCreate')->andReturn('cus_test');
        $this->customerGateway->shouldNotReceive('attachPaymentMethod');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->once();

        $this->orchestrator->create(
            $subscription, $plan, $member,
            ['payment_method_id' => '']
        );

        $this->assertTrue(true);
    }

    // ── Result persistence ────────────────────────────────────────────────────

    public function test_persists_all_stripe_ids_to_subscription(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $result = new StripeSubscriptionResultDto(
            stripeSubscriptionId:      'sub_abc',
            stripeScheduleId:          'sched_xyz',
            status:                    'active',
            stripeCustomerId:          'cus_test',
            currentPeriodStart:        1700000000,
            currentPeriodEnd:          1702592000,
            latestInvoiceId:           'in_test',
            paymentIntentId:           'pi_test',
            paymentIntentClientSecret: null,
            requiresAction:            false,
            stripeSubscriptionItemId:   'si_abc',
        );

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);
        $member->shouldReceive('resolveBillingAddress')->andReturn(null);
        $this->customerGateway->shouldReceive('getOrCreate')->andReturn('cus_test');
        $this->billingService->shouldReceive('createSubscription')->andReturn($result);

        $subscription
            ->shouldReceive('update')
            ->once()
            ->with(m::on(function (array $data) {
                return $data['payment_subscription_id'] === 'sub_abc'
                    && $data['stripe_subscription_item_id'] === 'si_abc'
                    && $data['stripe_schedule_id']       === 'sched_xyz'
                    && $data['stripe_customer_id']       === 'cus_test'
                    && $data['status']                   === 'active'
                    && $data['current_period_start']     !== null
                    && $data['current_period_end']       !== null;
            }));

        $this->orchestrator->create($subscription, $plan, $member, []);

        $this->assertTrue(true);
    }

    public function test_persists_null_schedule_id_for_standard_subscription(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $result = $this->makeResult(scheduleId: null);

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);
        $member->shouldReceive('resolveBillingAddress')->andReturn(null);
        $this->customerGateway->shouldReceive('getOrCreate')->andReturn('cus_test');
        $this->billingService->shouldReceive('createSubscription')->andReturn($result);

        $subscription
            ->shouldReceive('update')
            ->once()
            ->with(m::on(fn (array $d) => $d['stripe_schedule_id'] === null));

        $this->orchestrator->create($subscription, $plan, $member, []);

        $this->assertTrue(true);
    }

    public function test_persists_null_period_dates_when_result_has_no_timestamps(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $result = new StripeSubscriptionResultDto(
            stripeSubscriptionId:      'sub_abc',
            stripeScheduleId:          null,
            status:                    'trialing',
            stripeCustomerId:          'cus_test',
            currentPeriodStart:        null,
            currentPeriodEnd:          null,
            latestInvoiceId:           null,
            paymentIntentId:           null,
            paymentIntentClientSecret: null,
            requiresAction:            false,
        );

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);
        $member->shouldReceive('resolveBillingAddress')->andReturn(null);
        $this->customerGateway->shouldReceive('getOrCreate')->andReturn('cus_test');
        $this->billingService->shouldReceive('createSubscription')->andReturn($result);

        $subscription
            ->shouldReceive('update')
            ->once()
            ->with(m::on(fn (array $d) =>
                $d['current_period_start'] === null
                && $d['current_period_end'] === null
            ));

        $this->orchestrator->create($subscription, $plan, $member, []);

        $this->assertTrue(true);
    }

    // ── Exception propagation ─────────────────────────────────────────────────

    public function test_propagates_exception_from_billing_service(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);
        $member->shouldReceive('resolveBillingAddress')->andReturn(null);
        $this->customerGateway->shouldReceive('getOrCreate')->andReturn('cus_test');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->andThrow(new \RuntimeException('Stripe API error'));

        $subscription->shouldNotReceive('update');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe API error');

        $this->orchestrator->create($subscription, $plan, $member, []);

        $this->assertTrue(true);
    }

    public function test_propagates_exception_from_customer_gateway(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);
        $member->shouldReceive('resolveBillingAddress')->andReturn(null);

        $this->customerGateway
            ->shouldReceive('getOrCreate')
            ->andThrow(new \RuntimeException('Customer creation failed'));

        $this->billingService->shouldNotReceive('createSubscription');
        $subscription->shouldNotReceive('update');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Customer creation failed');

        $this->orchestrator->create($subscription, $plan, $member, []);

        $this->assertTrue(true);
    }

    public function test_trial_is_passed_through_when_member_has_no_prior_trial(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();
        $plan->trial_days = 7;

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);

        $this->customerGateway->shouldReceive('getOrCreate')->andReturn('cus_test');

        $this->subscriptionRepository
            ->expects('memberHadTrialOnPlan')
            ->with($member->id, $plan->id)
            ->andReturn(false);

        $this->billingService
            ->expects('createSubscription')
            ->withArgs(fn($s, $p, $pt, $cid, $trialDays) => $trialDays === 7)
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->andReturn(true);

        $this->orchestrator->create($subscription, $plan, $member);

        $this->assertTrue(true);
    }

    public function test_trial_is_stripped_when_member_previously_had_trial(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);

        $this->customerGateway
            ->shouldReceive('getOrCreate')
            ->andReturn('cus_test');

        $this->billingService
            ->shouldReceive('createSubscription')
            ->withArgs(fn($s, $p, $pt, $cid, $trialDays) => $trialDays === 0)
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->andReturn(true);

        $this->orchestrator->create($subscription, $plan, $member);

        $this->assertTrue(true);
    }

    public function test_trial_check_is_skipped_when_plan_has_no_trial(): void
    {
        [$subscription, $plan, $member, $pricingTier] = $this->makeModels();

        $plan->shouldReceive('getDefaultPricing')->andReturn($pricingTier);

        $this->customerGateway
            ->shouldReceive('getOrCreate')
            ->andReturn('cus_test');

        $this->subscriptionRepository
            ->expects('memberHadTrialOnPlan')
            ->never();

        $this->billingService
            ->shouldReceive('createSubscription')
            ->withArgs(fn($s, $p, $pt, $cid, $trialDays) => $trialDays === 0)
            ->andReturn($this->makeResult());

        $subscription->shouldReceive('update')->andReturn(true);

        $this->orchestrator->create($subscription, $plan, $member);

        $this->assertTrue(true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeModels(): array
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id        = 1;
        $subscription->plan_id   = 1;
        $subscription->member_id = 1;
        $subscription->site_id   = 1;

        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->trial_days = 0;

        $member = m::mock(Member::class)->makePartial();
        $member->id   = 1;
        $member->shouldReceive('resolveBillingAddress')->andReturn(null)->byDefault();

        $pricingTier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricingTier->id        = 1;
        $pricingTier->plan_id   = 1;
        $pricingTier->is_active = true;

        return [$subscription, $plan, $member, $pricingTier];
    }

    private function makeResult(
        string  $status     = 'active',
        ?string $scheduleId = null,
    ): StripeSubscriptionResultDto {
        return new StripeSubscriptionResultDto(
            stripeSubscriptionId:      'sub_test',
            stripeScheduleId:          $scheduleId,
            status:                    $status,
            stripeCustomerId:          'cus_test',
            currentPeriodStart:        time(),
            currentPeriodEnd:          time() + 2592000,
            latestInvoiceId:           'in_test',
            paymentIntentId:           'pi_test',
            paymentIntentClientSecret: null,
            requiresAction:            false,
        );
    }
}
