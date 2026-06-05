<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Member;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionPlanRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;
    private SubscriptionPlanRepository $repository;

    public function testGetActivePlans(): void
    {
        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Active Plan',
            'slug' => 'active',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true
        ]);

        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Inactive Plan',
            'slug' => 'inactive',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => false
        ]);

        $plans = $this->repository->getActivePlans($this->siteId);

        $this->assertEquals(1, $plans->count());
        $this->assertEquals('Active Plan', $plans->first()->name);
    }

    public function testGetActivePlansOrdersByPriceThenSortOrder(): void
    {
        SubscriptionPlan::create($this->planAttributes(['slug' => 'cheap', 'name' => 'Cheap', 'price' => 5.00, 'sort_order' => 2, 'is_active' => true]));
        SubscriptionPlan::create($this->planAttributes(['slug' => 'mid', 'name' => 'Mid', 'price' => 10.00, 'sort_order' => 1, 'is_active' => true]));

        $plans = $this->repository->getActivePlans($this->siteId);

        // sort_order is the primary sort; price is secondary.
        $this->assertEquals('Mid', $plans->first()->name);
    }

    public function testGetActivePlansScopedToSite(): void
    {
        $otherSite = Site::create(['name' => uniqid(), 'slug' => uniqid()]);

        SubscriptionPlan::create($this->planAttributes(['slug' => 'mine', 'name' => 'Mine', 'is_active' => true]));
        SubscriptionPlan::create($this->planAttributes(['slug' => 'theirs', 'name' => 'Theirs', 'site_id' => $otherSite->id, 'is_active' => true]));

        $plans = $this->repository->getActivePlans($this->siteId);

        $this->assertEquals(1, $plans->count());
        $this->assertEquals('Mine', $plans->first()->name);
    }

    public function testFindBySlug(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium',
            'slug' => 'premium-plan',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly'
        ]);

        $found = $this->repository->findBySlug('premium-plan', $this->siteId);

        $this->assertNotNull($found);
        $this->assertEquals($plan->id, $found->id);
        $this->assertEquals('Premium', $found->name);
    }

    public function testFindBySlugReturnsNullForUnknownSlug(): void
    {
        $result = $this->repository->findBySlug('does-not-exist', $this->siteId);

        $this->assertNull($result);
    }

    public function testFindBySlugIsScoped_ToSite(): void
    {
        $otherSite = Site::create(['name' => uniqid(), 'slug' => uniqid()]);
        SubscriptionPlan::create($this->planAttributes(['slug' => 'shared-slug', 'site_id' => $otherSite->id]));

        $result = $this->repository->findBySlug('shared-slug', $this->siteId);

        $this->assertNull($result);
    }

    public function testToggleActive(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Test Plan',
            'slug' => 'test',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true
        ]);

        $this->assertTrue($plan->is_active);

        $this->repository->toggleActive($plan->id);
        $plan = $this->repository->find($plan->id);

        $this->assertFalse($plan->is_active);
    }

    public function testToggleActiveReturnsFalseForNonExistentPlan(): void
    {
        $result = $this->repository->toggleActive(999999);

        $this->assertFalse($result);
    }

    public function testToggleFeatured(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Test Plan',
            'slug' => 'test',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_featured' => false
        ]);

        $this->assertFalse($plan->is_featured);

        $this->repository->toggleFeatured($plan->id);
        $plan = $this->repository->find($plan->id);

        $this->assertTrue($plan->is_featured);
    }

    public function testToggleFeaturedReturnsFalseForNonExistentPlan(): void
    {
        $result = $this->repository->toggleFeatured(999999);

        $this->assertFalse($result);
    }

    public function testGetSubscriberCountsForPlansReturnsEmptyArrayForEmptyInput(): void
    {
        $counts = $this->repository->getSubscriberCountsForPlans([]);

        $this->assertSame([], $counts);
    }

    public function testUpdateSortOrderPersistsNewOrdering(): void
    {
        $plan1 = SubscriptionPlan::create($this->planAttributes(['slug' => 'p1', 'sort_order' => 1]));
        $plan2 = SubscriptionPlan::create($this->planAttributes(['slug' => 'p2', 'sort_order' => 2]));

        $this->repository->updateSortOrder([$plan1->id => 10, $plan2->id => 5]);

        $this->assertEquals(10, $this->repository->find($plan1->id)->sort_order);
        $this->assertEquals(5, $this->repository->find($plan2->id)->sort_order);
    }

    public function testUpdateSortOrderReturnsTrueOnSuccess(): void
    {
        $plan = SubscriptionPlan::create($this->planAttributes(['slug' => 'sort-test', 'sort_order' => 1]));

        $result = $this->repository->updateSortOrder([$plan->id => 99]);

        $this->assertTrue($result);
    }

    public function testGetUpgradePlansForReturnsUpgradePlans(): void
    {
        $base = SubscriptionPlan::create($this->planAttributes(['slug' => 'base']));
        $upgrade = SubscriptionPlan::create($this->planAttributes([
            'slug' => 'upgrade',
            'upgrade_from_plan_id' => $base->id,
            'is_upgrade_option' => true,
            'is_active' => true,
        ]));

        $results = $this->repository->getUpgradePlansFor($base->id);

        $this->assertCount(1, $results);
        $this->assertEquals($upgrade->id, $results->first()->id);
    }

    public function testFindWithPricingTiersReturnsPlan(): void
    {
        $plan = SubscriptionPlan::create($this->planAttributes(['slug' => 'with-tiers']));

        $result = $this->repository->findWithPricingTiers($plan->id);

        $this->assertNotNull($result);
        $this->assertEquals($plan->id, $result->id);
        $this->assertTrue($result->relationLoaded('pricingTiers'));
    }

    public function testFindWithPricingTiersReturnsNullForUnknownPlan(): void
    {
        $result = $this->repository->findWithPricingTiers(999999);

        $this->assertNull($result);
    }

    public function test_find_available_publication_targets_returns_active_plans_for_site(): void
    {
        $currentPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Current Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $targetPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Target Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $results = $this->repository->findAvailablePublicationTargets(
            siteId: $this->siteId,
            excludePlanId: $currentPlan->id,
            deliveryType: 'print',
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($targetPlan->id, $ids);
    }

    public function test_find_available_publication_targets_excludes_current_plan(): void
    {
        $currentPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Current Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Other Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $results = $this->repository->findAvailablePublicationTargets(
            siteId: $this->siteId,
            excludePlanId: $currentPlan->id,
            deliveryType: 'print',
        );

        $ids = $results->pluck('id')->all();

        $this->assertNotContains($currentPlan->id, $ids);
    }

    public function test_find_available_publication_targets_excludes_inactive_plans(): void
    {
        $currentPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Current Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $inactivePlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Inactive Plan',
            'delivery_type' => 'print',
            'is_active'     => false,
        ]);

        $activePlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Active Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $results = $this->repository->findAvailablePublicationTargets(
            siteId: $this->siteId,
            excludePlanId: $currentPlan->id,
            deliveryType: 'print',
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($activePlan->id, $ids);
        $this->assertNotContains($inactivePlan->id, $ids);
    }

    public function test_find_available_publication_targets_excludes_plans_from_other_sites(): void
    {
        $otherSite = $this->createSite();

        $currentPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Current Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $sameSitePlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Same Site Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $otherSitePlan = $this->createSubscriptionPlan([
            'site_id'       => $otherSite->id,
            'name'          => 'Other Site Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $results = $this->repository->findAvailablePublicationTargets(
            siteId: $this->siteId,
            excludePlanId: $currentPlan->id,
            deliveryType: 'print',
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($sameSitePlan->id, $ids);
        $this->assertNotContains($otherSitePlan->id, $ids);
    }

    public function test_find_available_publication_targets_filters_by_delivery_type(): void
    {
        $currentPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Current Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $printPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Another Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $digitalPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Digital Plan',
            'delivery_type' => 'digital',
            'is_active'     => true,
        ]);

        $results = $this->repository->findAvailablePublicationTargets(
            siteId: $this->siteId,
            excludePlanId: $currentPlan->id,
            deliveryType: 'print',
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($printPlan->id, $ids);
        $this->assertNotContains($digitalPlan->id, $ids);
    }

    public function test_find_available_publication_targets_does_not_filter_by_delivery_type_when_null(): void
    {
        $currentPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Current Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $printPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $digitalPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Digital Plan',
            'delivery_type' => 'digital',
            'is_active'     => true,
        ]);

        $results = $this->repository->findAvailablePublicationTargets(
            siteId: $this->siteId,
            excludePlanId: $currentPlan->id,
            deliveryType: null,
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($printPlan->id, $ids);
        $this->assertContains($digitalPlan->id, $ids);
    }

    public function test_find_available_publication_targets_does_not_filter_by_delivery_type_when_empty_string(): void
    {
        $currentPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Current Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $printPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $digitalPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Digital Plan',
            'delivery_type' => 'digital',
            'is_active'     => true,
        ]);

        $results = $this->repository->findAvailablePublicationTargets(
            siteId: $this->siteId,
            excludePlanId: $currentPlan->id,
            deliveryType: '',
        );

        $ids = $results->pluck('id')->all();

        $this->assertContains($printPlan->id, $ids);
        $this->assertContains($digitalPlan->id, $ids);
    }

    public function test_find_available_publication_targets_orders_by_name_then_id(): void
    {
        $currentPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Current Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $planB = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Bravo',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $planA1 = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Alpha',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $planA2 = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Alpha',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $results = $this->repository->findAvailablePublicationTargets(
            siteId: $this->siteId,
            excludePlanId: $currentPlan->id,
            deliveryType: 'print',
        );

        $ids = $results->pluck('id')->all();

        $this->assertEquals([
            $planA1->id,
            $planA2->id,
            $planB->id,
        ], $ids);
    }

    public function test_find_available_publication_targets_returns_empty_collection_when_no_matches(): void
    {
        $currentPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Current Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Digital Only',
            'delivery_type' => 'digital',
            'is_active'     => true,
        ]);

        $results = $this->repository->findAvailablePublicationTargets(
            siteId: $this->siteId,
            excludePlanId: $currentPlan->id,
            deliveryType: 'print',
        );

        $this->assertCount(0, $results);
    }

    public function testGetUpgradePlansForReturnsEmptyCollectionWhenNoneExist(): void
    {
        $plan = SubscriptionPlan::create($this->planAttributes(['slug' => 'lonely']));
        $results = $this->repository->getUpgradePlansFor($plan->id);

        $this->assertCount(0, $results);
    }

    private function planAttributes(array $overrides = []): array
    {
        return array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Plan ' . uniqid(),
            'slug' => 'test-' . uniqid(),
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionPlanRepository();
    }

    private function createMember()
    {
        return Member::create(['first_name' => uniqid(), 'last_name' => uniqid(), 'site_id' => $this->siteId, 'email' => uniqid()]);
    }
}