<?php

namespace App\Tests\Unit\Actions\Stripe;

use App\Actions\Stripe\HandleSubscriptionCreated;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class HandleSubscriptionCreatedTest extends FunctionalTestCase
{
    use CreatesTestData;
    protected function setUp(): void
    {
        parent::setUp();
    }
    
    private function makeAction(?SubscriptionPlan $plan): HandleSubscriptionCreated
    {
        $repo = Mockery::mock(SubscriptionPlanRepository::class);

        $repo->shouldReceive('find')
            ->andReturn($plan);

        return new HandleSubscriptionCreated($repo);
    }

    private function makePlan(string $name = 'Pro Monthly'): SubscriptionPlan
    {
        $plan = new SubscriptionPlan();
        $plan->id   = 3;
        $plan->name = $name;
        return $plan;
    }

    private function makeEvent(array $subOverrides = []): \Stripe\Event
    {
        $subData = array_merge([
            'id'                   => 'sub_new123',
            'object'               => 'subscription',
            'status'               => 'active',
            'customer'             => 'cus_test123',
            'current_period_start' => strtotime('2024-01-01 00:00:00'),
            'current_period_end'   => strtotime('2024-02-01 00:00:00'),
            'cancel_at_period_end' => false,
            'start_date'           => strtotime('2024-01-01 00:00:00'),
            'created'              => strtotime('2024-01-01 00:00:00'),
            'metadata'             => [
                'member_id' => 1,
                'site_id'   => 1,
                'plan_id'   => 1,
            ],
            'items' => ['data' => []],
        ], $subOverrides);

        return \Stripe\Event::constructFrom([
            'id'          => 'evt_created_' . uniqid(),
            'type'        => 'customer.subscription.created',
            'data'        => ['object' => $subData],
            'api_version' => '2023-10-16',
        ]);
    }

    public function test_it_creates_a_local_subscription_record(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $this->makeAction($this->makePlan('Pro Monthly'))
            ->handle($this->makeEvent(['metadata' => ['site_id' => $plan->site_id, 'plan_id' => $plan->id, 'member_id' => $member->id]]));

        $this->assertDatabaseHas('subscriptions', [
            'payment_subscription_id' => 'sub_new123',
            'stripe_customer_id'     => 'cus_test123',
            'member_id'              => $member->id,
            'site_id'                => $this->siteId,
            'plan_id'                => $plan->id,
            'plan_name'              => 'Pro Monthly',
            'status'                 => 'active',
            'cancel_at_period_end'   => false,
            'type'                   => 'paid',
        ]);
    }

    public function test_it_resolves_plan_name_from_repository(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $this->makeAction($this->makePlan('Digital Annual'))
            ->handle($this->makeEvent(['metadata' => ['site_id' => $plan->site_id, 'plan_id' => $plan->id, 'member_id' => $member->id]]));

        $this->assertDatabaseHas('subscriptions', [
            'payment_subscription_id' => 'sub_new123',
            'plan_name'              => 'Digital Annual',
        ]);
    }

    public function test_it_falls_back_to_stripe_product_name_when_repo_returns_null(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $event = $this->makeEvent([
            'id'    => 'sub_nolocalplan',
            'items' => [
                'data' => [
                    ['price' => ['product' => ['name' => 'Stripe Product Name']]],
                ],
            ],
            'metadata' => ['site_id' => $plan->site_id, 'plan_id' => $plan->id, 'member_id' => $member->id]
        ]);

        $this->makeAction(null)->handle($event);

        $this->assertDatabaseHas('subscriptions', [
            'payment_subscription_id' => 'sub_nolocalplan',
            'plan_name'              => 'Stripe Product Name',
        ]);
    }

    public function test_it_uses_unknown_plan_fallback_when_no_name_resolvable(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $event = $this->makeEvent([
            'id'    => 'sub_fallback',
            'items' => ['data' => []],
            'metadata' => ['site_id' => $plan->site_id, 'plan_id' => $plan->id, 'member_id' => $member->id]
        ]);

        $this->makeAction(null)->handle($event);

        $this->assertDatabaseHas('subscriptions', [
            'payment_subscription_id' => 'sub_fallback',
            'plan_name'              => 'Unknown Plan',
        ]);
    }

    public function test_it_maps_trialing_stripe_status_to_active(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $this->makeAction($this->makePlan())
            ->handle($this->makeEvent(['status' => 'trialing', 'id' => 'sub_trial',
                'metadata' => ['site_id' => $plan->site_id, 'plan_id' => $plan->id, 'member_id' => $member->id]]));

        $this->assertDatabaseHas('subscriptions', [
            'payment_subscription_id' => 'sub_trial',
            'status'                 => 'active',
        ]);
    }

    public function test_it_maps_canceled_stripe_status_correctly(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $this->makeAction($this->makePlan())
            ->handle($this->makeEvent(['status' => 'canceled', 'id' => 'sub_canceled',
                'metadata' => ['site_id' => $plan->site_id, 'plan_id' => $plan->id, 'member_id' => $member->id]]));

        $this->assertDatabaseHas('subscriptions', [
            'payment_subscription_id' => 'sub_canceled',
            'status'                 => 'cancelled',
        ]);
    }

    public function test_it_is_idempotent_on_duplicate_event(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $this->makeAction($this->makePlan())
            ->handle($this->makeEvent(['metadata' => ['site_id' => $plan->site_id, 'plan_id' => $plan->id, 'member_id' => $member->id]]));

        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_it_updates_stale_existing_record_on_re_delivery(): void
    {
        $this->createSubscription([
            'payment_subscription_id' => 'sub_new123',
            'plan_name'              => 'Old Name',
        ]);

        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $this->makeAction($this->makePlan('Pro Monthly'))
            ->handle($this->makeEvent(['status' => 'active',
                'metadata' => ['site_id' => $plan->site_id, 'plan_id' => $plan->id, 'member_id' => $member->id]
            ]));

        $this->assertDatabaseHas('subscriptions', [
            'payment_subscription_id' => 'sub_new123',
            'status'                 => 'active',
            'plan_name'              => 'Pro Monthly',
        ]);

        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_it_stores_period_dates(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $event = $this->makeEvent([
            'id'                   => 'sub_dates',
            'current_period_start' => strtotime('2024-03-01 00:00:00'),
            'current_period_end'   => strtotime('2024-04-01 00:00:00'),
            'metadata' => ['site_id' => $plan->site_id, 'plan_id' => $plan->id, 'member_id' => $member->id]
        ]);

        $this->makeAction($this->makePlan())->handle($event);

        $subscription = Subscription::where('payment_subscription_id', 'sub_dates')->first();

        $this->assertSame('2024-03-01 00:00:00', $subscription->current_period_start->format('Y-m-d H:i:s'));
        $this->assertSame('2024-04-01 00:00:00', $subscription->current_period_end->format('Y-m-d H:i:s'));
    }
}