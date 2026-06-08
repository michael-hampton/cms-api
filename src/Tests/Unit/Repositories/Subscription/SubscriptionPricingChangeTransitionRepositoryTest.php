<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\SubscriptionPricingChangeStatus;
use App\Enums\Subscriptions\SubscriptionPricingChangeTransitionStatus;
use App\Models\Member;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPricingChange;
use App\Models\SubscriptionPricingChangeTransition;
use App\Repositories\Subscriptions\SubscriptionPricingChangeTransitionRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class SubscriptionPricingChangeTransitionRepositoryTest extends FunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionPricingChangeTransitionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new SubscriptionPricingChangeTransitionRepository();
    }

    public function test_it_creates_a_transition(): void
    {
        [$pricingChange, $oldSubscription, $plan, $member, $site] = $this->makeGraph();

        $transition = $this->repository->create([
            'subscription_pricing_change_id' => $pricingChange->id,
            'old_subscription_id' => $oldSubscription->id,
            'new_subscription_id' => null,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'old_plan_id' => $plan->id,
            'new_plan_id' => $plan->id,
            'old_price' => 9.99,
            'new_price' => 12.99,
            'currency' => 'GBP',
            'old_stripe_subscription_id' => 'sub_old',
            'new_stripe_subscription_id' => null,
            'itd_required' => true,
            'itd_letter_code' => 'ITD_DD_PRICE_RISE',
            'communication_dedupe_key' => 'pricing-change:' . $pricingChange->id . ':subscription:' . $oldSubscription->id . ':itd',
            'status' => SubscriptionPricingChangeTransitionStatus::Pending->value,
            'metadata' => [
                'source' => 'test',
            ],
        ]);

        $this->assertInstanceOf(SubscriptionPricingChangeTransition::class, $transition);
        $this->assertSame((int) $pricingChange->id, (int) $transition->subscription_pricing_change_id);
        $this->assertSame((int) $oldSubscription->id, (int) $transition->old_subscription_id);
        $this->assertNull($transition->new_subscription_id);
        $this->assertSame((int) $member->id, (int) $transition->member_id);
        $this->assertSame((int) $site->id, (int) $transition->site_id);
        $this->assertSame((int) $plan->id, (int) $transition->old_plan_id);
        $this->assertSame((int) $plan->id, (int) $transition->new_plan_id);
        $this->assertSame(9.99, (float) $transition->old_price);
        $this->assertSame(12.99, (float) $transition->new_price);
        $this->assertSame('GBP', $transition->currency);
        $this->assertSame('sub_old', $transition->old_stripe_subscription_id);
        $this->assertNull($transition->new_stripe_subscription_id);
        $this->assertTrue((bool) $transition->itd_required);
        $this->assertSame('ITD_DD_PRICE_RISE', $transition->itd_letter_code);
        $this->assertSame(SubscriptionPricingChangeTransitionStatus::Pending->value, $transition->status);
    }

    public function test_it_finds_a_transition_for_old_subscription(): void
    {
        [$pricingChange, $oldSubscription] = $this->makeGraph();

        $created = $this->makeTransition($pricingChange, $oldSubscription);

        $found = $this->repository->findForOldSubscription(
            (int) $pricingChange->id,
            (int) $oldSubscription->id
        );

        $this->assertNotNull($found);
        $this->assertSame((int) $created->id, (int) $found->id);
    }

    public function test_it_returns_null_when_transition_for_old_subscription_does_not_exist(): void
    {
        $found = $this->repository->findForOldSubscription(999999, 888888);

        $this->assertNull($found);
    }

    public function test_it_marks_old_subscription_cancelled(): void
    {
        [$pricingChange, $oldSubscription] = $this->makeGraph();
        $transition = $this->makeTransition($pricingChange, $oldSubscription);

        $this->repository->markOldSubscriptionCancelled((int) $transition->id);

        $transition = SubscriptionPricingChangeTransition::find($transition->id);

        $this->assertSame(
            SubscriptionPricingChangeTransitionStatus::OldSubscriptionCancelled->value,
            $transition->status
        );
    }

    public function test_it_marks_new_subscription_created(): void
    {
        [$pricingChange, $oldSubscription, $plan, $member, $site] = $this->makeGraph();
        $transition = $this->makeTransition($pricingChange, $oldSubscription);
        $newSubscription = $this->makeSubscription($member, $site, $plan, 12.99, 'sub_new');

        $this->repository->markNewSubscriptionCreated(
            transitionId: (int) $transition->id,
            newSubscriptionId: (int) $newSubscription->id,
            newStripeSubscriptionId: 'sub_new'
        );

        $transition = SubscriptionPricingChangeTransition::find($transition->id);

        $this->assertSame((int) $newSubscription->id, (int) $transition->new_subscription_id);
        $this->assertSame('sub_new', $transition->new_stripe_subscription_id);
        $this->assertSame(
            SubscriptionPricingChangeTransitionStatus::NewSubscriptionCreated->value,
            $transition->status
        );
    }

    public function test_it_marks_itd_generated(): void
    {
        [$pricingChange, $oldSubscription] = $this->makeGraph();
        $transition = $this->makeTransition($pricingChange, $oldSubscription);

        $this->repository->markItdGenerated((int) $transition->id);

        $transition = SubscriptionPricingChangeTransition::find($transition->id);

        $this->assertSame(
            SubscriptionPricingChangeTransitionStatus::ItdGenerated->value,
            $transition->status
        );
    }

    public function test_it_marks_completed(): void
    {
        [$pricingChange, $oldSubscription] = $this->makeGraph();
        $transition = $this->makeTransition($pricingChange, $oldSubscription);

        $this->repository->markCompleted((int) $transition->id);

        $transition = SubscriptionPricingChangeTransition::find($transition->id);

        $this->assertSame(
            SubscriptionPricingChangeTransitionStatus::Completed->value,
            $transition->status
        );
    }

    public function test_it_marks_failed_with_reason(): void
    {
        [$pricingChange, $oldSubscription] = $this->makeGraph();
        $transition = $this->makeTransition($pricingChange, $oldSubscription);

        $this->repository->markFailed((int) $transition->id, 'Something went wrong');

        $transition = SubscriptionPricingChangeTransition::find($transition->id);

        $this->assertSame(
            SubscriptionPricingChangeTransitionStatus::Failed->value,
            $transition->status
        );
        $this->assertSame('Something went wrong', $transition->failure_reason);
    }

    /**
     * @return array{0: SubscriptionPricingChange, 1: Subscription, 2: SubscriptionPlan, 3: Member, 4: Site}
     */
    private function makeGraph(): array
    {
        $site = Site::create([
            'name' => 'Test Site ' . uniqid(),
            'domain' => 'example-' . uniqid() . '.test',
            'is_active' => true,
        ]);

        $member = Member::create([
            'email' => 'member-' . uniqid() . '@example.com',
            'first_name' => 'Test',
            'last_name' => 'Member',
            'site_id' => $site->id,
        ]);

        $plan = SubscriptionPlan::create([
            'site_id' => $site->id,
            'name' => 'Test Plan ' . uniqid(),
            'slug' => 'test-plan-' . uniqid(),
            'price' => 9.99,
            'currency' => 'GBP',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $oldSubscription = $this->makeSubscription($member, $site, $plan, 9.99, 'sub_old');

        $pricingChange = SubscriptionPricingChange::create([
            'plan_id' => $plan->id,
            'old_price' => 9.99,
            'new_price' => 12.99,
            'currency' => 'GBP',
            'effective_date' => (new \DateTime('+35 days'))->format('Y-m-d H:i:s'),
            'notice_sent_at' => null,
            'status' => SubscriptionPricingChangeStatus::Notified->value,
            'reason' => 'Test',
            'created_by' => 1,
        ]);

        return [$pricingChange, $oldSubscription, $plan, $member, $site];
    }

    private function makeSubscription(
        Member $member,
        Site $site,
        SubscriptionPlan $plan,
        float $price,
        ?string $stripeSubscriptionId
    ): Subscription {
        return Subscription::create([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => now_datetime()->format('Y-m-d H:i:s'),
            'end_date' => (new \DateTime('+1 month'))->format('Y-m-d H:i:s'),
            'next_billing_date' => (new \DateTime('+1 month'))->format('Y-m-d H:i:s'),
            'price' => $price,
            'currency' => 'GBP',
            'auto_renew' => true,
            'type' => 'paid',
            'delivery_type' => 'digital',
            'payment_subscription_id' => $stripeSubscriptionId,
        ]);
    }

    private function makeTransition(
        SubscriptionPricingChange $pricingChange,
        Subscription $oldSubscription
    ): SubscriptionPricingChangeTransition {
        return SubscriptionPricingChangeTransition::create([
            'subscription_pricing_change_id' => $pricingChange->id,
            'old_subscription_id' => $oldSubscription->id,
            'new_subscription_id' => null,
            'member_id' => $oldSubscription->member_id,
            'site_id' => $oldSubscription->site_id,
            'old_plan_id' => $oldSubscription->plan_id,
            'new_plan_id' => $oldSubscription->plan_id,
            'old_price' => 9.99,
            'new_price' => 12.99,
            'currency' => 'GBP',
            'status' => SubscriptionPricingChangeTransitionStatus::Pending->value,
        ]);
    }
}