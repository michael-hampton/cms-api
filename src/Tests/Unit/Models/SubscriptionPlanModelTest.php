<?php

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\Member;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Models\SubscriptionPlanRegionSet;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class SubscriptionPlanModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateSubscriptionPlan(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'description' => 'Premium features',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'trial_days' => 7,
            'features' => ['Feature 1', 'Feature 2'],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1
        ]);

        $this->assertNotNull($plan->id);
        $this->assertEquals('Premium Plan', $plan->name);
        $this->assertEquals(29.99, $plan->price);
        $this->assertTrue($plan->is_active);
        $this->assertIsArray($plan->features);
    }

    public function testIsActiveMethod(): void
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

        $this->assertTrue($plan->isActive());
        $plan->update(['is_active' => false]);
        $this->assertFalse($plan->isActive());
    }

    public function testHasTrialMethod(): void
    {
        $planWithTrial = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Trial Plan',
            'slug' => 'trial',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'trial_days' => 14
        ]);
        $planWithoutTrial = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'No Trial',
            'slug' => 'no-trial',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'trial_days' => 0
        ]);
        $this->assertTrue($planWithTrial->hasTrial());
        $this->assertFalse($planWithoutTrial->hasTrial());
    }

    public function testGetFormattedPrice(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Test Plan',
            'slug' => 'test',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly'
        ]);
        $this->assertEquals('USD 29.99', $plan->getFormattedPrice());
    }

    public function testGetBillingPeriodLabel(): void
    {
        $monthlyPlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Monthly',
            'slug' => 'monthly',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly'
        ]);
        $lifetimePlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Lifetime',
            'slug' => 'lifetime',
            'price' => 99.00,
            'currency' => 'USD',
            'billing_period' => 'lifetime'
        ]);

        $this->assertEquals('per month', $monthlyPlan->getBillingPeriodLabel());
        $this->assertEquals('one-time', $lifetimePlan->getBillingPeriodLabel());
    }

    public function test_is_recurring_returns_true_for_recurring_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Monthly Plan',
            'slug' => 'monthly',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'plan_type' => 'recurring'
        ]);

        $this->assertTrue($plan->isRecurring());
    }

    public function test_is_recurring_returns_false_for_onetime_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'One Time Plan',
            'slug' => 'onetime',
            'price' => 50.00,
            'currency' => 'USD',
            'billing_period' => 'lifetime',
            'plan_type' => 'onetime'
        ]);

        $this->assertFalse($plan->isRecurring());
    }

    public function test_is_one_time_returns_true_for_onetime_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'One Time Plan',
            'slug' => 'onetime',
            'price' => 50.00,
            'currency' => 'USD',
            'billing_period' => 'lifetime',
            'plan_type' => 'onetime'
        ]);

        $this->assertTrue($plan->isOneTime());
    }

    public function test_is_one_time_returns_false_for_recurring_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Monthly Plan',
            'slug' => 'monthly',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'plan_type' => 'recurring'
        ]);

        $this->assertFalse($plan->isOneTime());
    }

    public function test_has_digital_option_returns_true_when_url_provided(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Digital Plan',
            'slug' => SubscriptionType::DIGITAL->value,
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'digital_download_url' => 'https://example.com/download/magazine.pdf'
        ]);

        $this->assertTrue($plan->hasDigitalOption());
    }

    public function test_has_digital_option_returns_false_when_no_url(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Print Plan',
            'slug' => SubscriptionType::PRINTED->value,
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'digital_download_url' => null
        ]);

        $this->assertFalse($plan->hasDigitalOption());
    }

    public function test_has_digital_option_returns_false_when_empty_url(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Plan',
            'slug' => 'plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'digital_download_url' => ''
        ]);

        $this->assertFalse($plan->hasDigitalOption());
    }

    public function test_has_print_option_returns_true_when_shipping_required(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Print Plan',
            'slug' => SubscriptionType::PRINTED->value,
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'print_shipping_required' => true
        ]);

        $this->assertTrue($plan->hasPrintOption());
    }

    public function test_has_print_option_returns_false_when_shipping_not_required(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Digital Only',
            'slug' => SubscriptionType::DIGITAL->value,
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'print_shipping_required' => false
        ]);

        $this->assertFalse($plan->hasPrintOption());
    }

    public function test_get_delivery_options_returns_both_when_available(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Hybrid Plan',
            'slug' => 'hybrid',
            'price' => 20.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'digital_download_url' => 'https://example.com/download/magazine.pdf',
            'print_shipping_required' => true
        ]);

        $options = $plan->getDeliveryOptions();

        $this->assertCount(2, $options);
        $this->assertContains(SubscriptionType::DIGITAL->value, $options);
        $this->assertContains(SubscriptionType::PRINTED->value, $options);
    }

    public function test_get_delivery_options_returns_digital_only(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Digital Plan',
            'slug' => SubscriptionType::DIGITAL->value,
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'digital_download_url' => 'https://example.com/download/magazine.pdf',
            'print_shipping_required' => false
        ]);

        $options = $plan->getDeliveryOptions();

        $this->assertCount(1, $options);
        $this->assertContains(SubscriptionType::DIGITAL->value, $options);
        $this->assertNotContains(SubscriptionType::PRINTED->value, $options);
    }

    public function test_get_delivery_options_returns_print_only(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Print Plan',
            'slug' => SubscriptionType::PRINTED->value,
            'price' => 15.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'digital_download_url' => null,
            'print_shipping_required' => true
        ]);

        $options = $plan->getDeliveryOptions();

        $this->assertCount(1, $options);
        $this->assertContains(SubscriptionType::PRINTED->value, $options);
        $this->assertNotContains(SubscriptionType::DIGITAL->value, $options);
    }

    public function test_get_delivery_options_returns_empty_when_none_available(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'No Delivery',
            'slug' => 'no-delivery',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'digital_download_url' => null,
            'print_shipping_required' => false
        ]);

        $options = $plan->getDeliveryOptions();

        $this->assertCount(0, $options);
    }

    public function test_scope_one_time_filters_correctly(): void
    {
        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'One Time 1',
            'slug' => 'onetime-1',
            'price' => 50.00,
            'currency' => 'USD',
            'billing_period' => 'lifetime',
            'plan_type' => 'onetime'
        ]);

        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'One Time 2',
            'slug' => 'onetime-2',
            'price' => 60.00,
            'currency' => 'USD',
            'billing_period' => 'lifetime',
            'plan_type' => 'onetime'
        ]);

        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Recurring',
            'slug' => 'recurring',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'plan_type' => 'recurring'
        ]);

        $oneTimePlans = SubscriptionPlan::oneTime()->get();

        $this->assertCount(2, $oneTimePlans);
        foreach ($oneTimePlans as $plan) {
            $this->assertEquals('onetime', $plan->plan_type);
        }
    }

    public function test_scope_recurring_filters_correctly(): void
    {
        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Monthly',
            'slug' => 'monthly',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'plan_type' => 'recurring'
        ]);

        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Yearly',
            'slug' => 'yearly',
            'price' => 100.00,
            'currency' => 'USD',
            'billing_period' => 'yearly',
            'plan_type' => 'recurring'
        ]);

        SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'One Time',
            'slug' => 'onetime',
            'price' => 50.00,
            'currency' => 'USD',
            'billing_period' => 'lifetime',
            'plan_type' => 'onetime'
        ]);

        $recurringPlans = SubscriptionPlan::recurring()->get();

        $this->assertCount(2, $recurringPlans);
        foreach ($recurringPlans as $plan) {
            $this->assertEquals('recurring', $plan->plan_type);
        }
    }

    public function testGetPremiumAccessGrantsReturnsArray(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 39.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'premium_access' => [
                ['type' => 'newsletter', 'identifier' => 'insider'],
                ['type' => 'archive', 'identifier' => 'full']
            ]
        ]);

        $grants = $plan->getPremiumAccessGrants();

        $this->assertCount(2, $grants);
        $this->assertEquals('newsletter', $grants[0]['type']);
        $this->assertEquals('insider', $grants[0]['identifier']);
    }

//    public function testGetPremiumAccessGrantsBackwardCompatibility(): void
//    {
//        $plan = SubscriptionPlan::create([
//            'site_id' => $this->siteId,
//            'name' => 'Insider Plan',
//            'slug' => 'insider',
//            'price' => 29.99,
//            'currency' => 'USD',
//            'billing_period' => 'monthly',
//            'is_active' => true,
//            'includes_insider' => true
//        ]);
//
//        $grants = $plan->getPremiumAccessGrants();
//
//        $this->assertCount(1, $grants);
//        $this->assertEquals('newsletter', $grants[0]['type']);
//        $this->assertEquals('insider', $grants[0]['identifier']);
//    }

    public function testGrantsPremiumAccessReturnsTrueWhenGranted(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 39.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'premium_access' => [
                ['type' => 'newsletter', 'identifier' => 'insider'],
                ['type' => 'newsletter', 'identifier' => 'tech-weekly']
            ]
        ]);

        $this->assertTrue($plan->grantsPremiumAccess('newsletter', 'insider'));
        $this->assertTrue($plan->grantsPremiumAccess('newsletter', 'tech-weekly'));
    }

    public function testGrantsPremiumAccessReturnsFalseWhenNotGranted(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Basic Plan',
            'slug' => 'basic',
            'price' => 19.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true
        ]);

        $this->assertFalse($plan->grantsPremiumAccess('newsletter', 'insider'));
    }

    public function testAddPremiumAccessAddsNewGrant(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 39.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'premium_access' => []
        ]);

        $plan->addPremiumAccess('newsletter', 'insider');

        $plan = $plan->fresh();
        $this->assertTrue($plan->grantsPremiumAccess('newsletter', 'insider'));
    }

    public function testAddPremiumAccessDoesNotDuplicate(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 39.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'premium_access' => [
                ['type' => 'newsletter', 'identifier' => 'insider']
            ]
        ]);

        $plan->addPremiumAccess('newsletter', 'insider');

        $plan = $plan->fresh();
        $grants = $plan->getPremiumAccessGrants();

        $this->assertCount(1, $grants);
    }

    public function testRemovePremiumAccessRemovesGrant(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 39.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'premium_access' => [
                ['type' => 'newsletter', 'identifier' => 'insider'],
                ['type' => 'newsletter', 'identifier' => 'tech-weekly']
            ]
        ]);

        $plan->removePremiumAccess('newsletter', 'insider');

        $plan = $plan->fresh();
        $this->assertFalse($plan->grantsPremiumAccess('newsletter', 'insider'));
        $this->assertTrue($plan->grantsPremiumAccess('newsletter', 'tech-weekly'));
    }

    public function testIncludesInsiderAttributeBackwardCompatibility(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Insider Plan',
            'slug' => 'insider',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'premium_access' => [
                ['type' => 'newsletter', 'identifier' => 'insider']
            ]
        ]);

        $this->assertTrue($plan->includes_insider);
    }

    public function testPremiumAccessCastsToArray(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 39.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'premium_access' => [
                ['type' => 'newsletter', 'identifier' => 'insider']
            ]
        ]);

        $this->assertIsArray($plan->premium_access);
    }

    public function testGetNextIssueReturnsUpcomingIssue(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Magazine Plan',
            'slug' => 'magazine',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'print_shipping_required' => true,
        ]);

        // Create future issue
        $futureIssue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 101,
            'issue_title' => 'Future Issue',
            'on_sale_date' => now_datetime()->modify('+14 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 100,
            'subscription_plan_id' => $plan->id,
        ]);

        $nextIssue = $plan->getNextIssue();

        $this->assertNotNull($nextIssue);
        $this->assertEquals($futureIssue->id, $nextIssue->id);
        $this->assertEquals('Future Issue', $nextIssue->issue_title);
    }

    public function testGetNextIssueReturnsCurrentIssueIfNoUpcoming(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Magazine Plan',
            'slug' => 'magazine',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'print_shipping_required' => true,
        ]);

        // Create current issue (on sale recently)
        $currentIssue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 100,
            'issue_title' => 'Current Issue',
            'on_sale_date' => now_datetime()->modify('-2 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 50,
            'subscription_plan_id' => $plan->id,
        ]);


        $nextIssue = $plan->getNextIssue();

        $this->assertNotNull($nextIssue);
        $this->assertEquals($currentIssue->id, $nextIssue->id);
    }

    public function testGetNextIssueReturnsNullWhenNoIssues(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Magazine Plan',
            'slug' => 'magazine',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'print_shipping_required' => true,
        ]);

        $nextIssue = $plan->getNextIssue();

        $this->assertNull($nextIssue);
    }

    public function testGetCurrentIssueReturnsLatestReleasedIssue(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Magazine Plan',
            'slug' => 'magazine',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'print_shipping_required' => true,
        ]);

        // Create older issue
        $olderIssue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 99,
            'issue_title' => 'Older Issue',
            'on_sale_date' => now_datetime()->modify('-30 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 10,
            'subscription_plan_id' => $plan->id,
        ]);

        // Create current issue
        $currentIssue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 100,
            'issue_title' => 'Current Issue',
            'on_sale_date' => now_datetime()->modify('-2 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 50,
            'subscription_plan_id' => $plan->id,
        ]);


        $current = $plan->getCurrentIssue();

        $this->assertNotNull($current);
        $this->assertEquals($currentIssue->id, $current->id);
        $this->assertEquals('Current Issue', $current->issue_title);
    }

    public function testGetCurrentIssueReturnsNullForFutureOnlyIssues(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Magazine Plan',
            'slug' => 'magazine',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'print_shipping_required' => true,
        ]);

        // Create future issue only
        $futureIssue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 101,
            'title' => 'Future Issue',
            'on_sale_date' => now_datetime()->modify('+30 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 100,
            'subscription_plan_id' => $plan->id,
        ]);


        $current = $plan->getCurrentIssue();

        $this->assertNull($current);
    }

    public function testGetUpcomingIssuesReturnsAllFutureIssues(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Magazine Plan',
            'slug' => 'magazine',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'print_shipping_required' => true,
        ]);

        // Create past issue (should not be included)
        $pastIssue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 99,
            'title' => 'Past Issue',
            'on_sale_date' => now_datetime()->modify('-30 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 10,
            'subscription_plan_id' => $plan->id,
        ]);

        // Create future issues
        $futureIssue1 = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 101,
            'title' => 'Next Month',
            'on_sale_date' => now_datetime()->modify('+30 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 100,
            'subscription_plan_id' => $plan->id,
        ]);

        $futureIssue2 = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 102,
            'title' => 'Two Months Ahead',
            'on_sale_date' => now_datetime()->modify('+60 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 100,
            'subscription_plan_id' => $plan->id,
        ]);

        $upcoming = $plan->getUpcomingIssues();

        $this->assertCount(2, $upcoming);
        $this->assertEquals($futureIssue1->id, $upcoming->first()->id);
        $this->assertEquals($futureIssue2->id, $upcoming->last()->id);
    }

    public function testGetUpcomingIssuesReturnsEmptyCollectionWhenNone(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Magazine Plan',
            'slug' => 'magazine',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'print_shipping_required' => true,
        ]);

        $upcoming = $plan->getUpcomingIssues();

        $this->assertCount(0, $upcoming);
    }

    public function testGetUpcomingIssuesOrdersByDateAscending(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Magazine Plan',
            'slug' => 'magazine',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'print_shipping_required' => true,
        ]);

        // Create issues in random order
        $issue2 = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 102,
            'title' => 'Issue 2',
            'on_sale_date' => now_datetime()->modify('+60 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 100,
            'subscription_plan_id' => $plan->id,
        ]);

        $issue1 = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 101,
            'title' => 'Issue 1',
            'on_sale_date' => now_datetime()->modify('+30 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 100,
            'subscription_plan_id' => $plan->id,
        ]);

        $issue3 = IssueDelivery::create([
            'site_id' => $this->siteId,
            'issue_number' => 103,
            'title' => 'Issue 3',
            'on_sale_date' => now_datetime()->modify('+90 days'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => 100,
            'subscription_plan_id' => $plan->id,
        ]);

        $upcoming = $plan->getUpcomingIssues();

        $this->assertCount(3, $upcoming);
        $this->assertEquals($issue1->id, $upcoming->first()->id);
        $this->assertEquals($issue2->id, $upcoming->get(1)->id);
        $this->assertEquals($issue3->id, $upcoming->last()->id);
    }

    public function testIsPreReleaseReturnsTrueForUnreleasedPlan(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Future Plan',
            'slug' => 'future',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'release_date' => now_datetime()->modify('+30 days'),
            'pre_release_enabled' => true,
        ]);

        $this->assertTrue($plan->isPreRelease());
    }

    public function testIsPreReleaseReturnsFalseForReleasedPlan(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Released Plan',
            'slug' => 'released',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'release_date' => now_datetime()->modify('-30 days'),
            'pre_release_enabled' => true,
        ]);

        $this->assertFalse($plan->isPreRelease());
    }

    public function testIsPreReleaseReturnsFalseWhenPreReleaseDisabled(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Future Plan',
            'slug' => 'future',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'release_date' => now_datetime()->modify('+30 days'),
            'pre_release_enabled' => false,
        ]);

        $this->assertFalse($plan->isPreRelease());
    }

    public function testPlanWithNoRegionSetsIsVisibleToAnyMember(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Open Plan',
            'slug' => 'open-plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
        ]);

        $member = new Member(['territory_id' => 99]);

        $plan->setRelation('regionSets', new \App\Framework\Support\Collection([]));

        $this->assertTrue($plan->isVisibleToMember($member));
    }

    public function testPlanIsVisibleToMemberWithMatchingTerritory(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Regional Plan',
            'slug' => 'regional-plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
        ]);

        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        SubscriptionPlanRegionSet::create([
            'subscription_plan_id' => $plan->id,
            'region_set_id' => $regionSet->id
        ]);

        $member = $this->createMember(['territory_id' => $territory->id]);

        $this->assertTrue($plan->isVisibleToMember($member));
    }

    public function testPlanIsNotVisibleToMemberWithNonMatchingTerritory(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Regional Plan 2',
            'slug' => 'regional-plan-2',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
        ]);

        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        SubscriptionPlanRegionSet::create([
            'subscription_plan_id' => $plan->id,
            'region_set_id' => $regionSet->id
        ]);

        $territory2 = $this->createTerritory();

        $member = $this->createMember(['territory_id' => $territory2->id]);

        $this->assertFalse($plan->isVisibleToMember($member));
    }

    public function testPlanIsVisibleToNullMember(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Any Plan',
            'slug' => 'any-plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
        ]);

        $this->assertTrue($plan->isVisibleToMember(null));
    }

    public function testGetDefaultLowestEffectivePriceReturnsDefaultTierDigitalPrice(): void
    {
        $plan = SubscriptionPlan::create([
            'price' => 0,
            'site_id' => $this->siteId,
            'name' => 'Any Plan',
            'slug' => 'any-plan',
            'digital_download_url' => 'test'
        ]);

        $tier = SubscriptionPlanPricing::create([
            'subscription_plan_id' => $plan->id,
            'price' => 29.99,
            'digital_price' => 19.99,
            'digital_sale_price' => 0,
            'sale_price' => null,
            'is_default' => true,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 0,
            'label' => 'test',
            'period_description' => 'test',
        ]);

        $plan->setRelation('pricingTiers', collect([$tier]));

        $result = $plan->getDefaultEffectivePrice();

        $this->assertEquals(19.99, $result['min']);
        $this->assertEquals($tier->id, $result['tier']->id);
    }

    public function testGetDefaultEffectivePriceFallsBackToPlanPriceWhenNoDefaultTier(): void
    {
        $plan = SubscriptionPlan::create([
            'price' => 29.99,
            'site_id' => $this->siteId,
            'name' => 'No Tier Plan',
            'slug' => 'no-tier-plan',
        ]);

        $result = $plan->getDefaultEffectivePrice();

        $this->assertEquals(29.99, $result['min']);
        $this->assertNull($result['tier']);
    }

    public function testGetDefaultEffectivePriceReturnsLowestAcrossDeliveryOptions(): void
    {
        $plan = SubscriptionPlan::create([
            'price' => 0,
            'site_id' => $this->siteId,
            'name' => 'Mixed Plan',
            'slug' => 'mixed-plan',
            'digital_download_url' => 'test',
            'print_shipping_required' => true,
        ]);

        $tier = SubscriptionPlanPricing::create([
            'subscription_plan_id' => $plan->id,
            'price' => 29.99,
            'digital_price' => 19.99,
            'print_price' => 29.99,
            'is_default' => true,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 0,
            'label' => 'tier',
            'period_description' => 'tier',
        ]);

        $plan->load('pricingTiers');

        $result = $plan->getDefaultEffectivePrice();

        $this->assertEquals(19.99, $result['min']);
    }

    public function testGetDefaultEffectivePriceReturnsOnlyPrintWhenNoDigital(): void
    {
        $plan = SubscriptionPlan::create([
            'price' => 0,
            'site_id' => $this->siteId,
            'name' => 'Print Only',
            'slug' => 'print-only',
            'digital_download_url' => null,
            'print_shipping_required' => true,
        ]);

        $tier = SubscriptionPlanPricing::create([
            'subscription_plan_id' => $plan->id,
            'price' => 29.99,
            'print_price' => 29.99,
            'digital_price' => null,
            'is_default' => true,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 0,
            'label' => 'tier',
            'period_description' => 'tier',
        ]);

        $plan->load('pricingTiers');

        $result = $plan->getDefaultEffectivePrice();

        $this->assertEquals(29.99, $result['min']);
    }

    public function testGetDefaultEffectivePriceReturnsOnlyDigitalWhenNoPrint(): void
    {
        $plan = SubscriptionPlan::create([
            'price' => 0,
            'site_id' => $this->siteId,
            'name' => 'Digital Only',
            'slug' => 'digital-only',
            'digital_download_url' => 'test',
            'print_shipping_required' => false,
        ]);

        $tier = SubscriptionPlanPricing::create([
            'subscription_plan_id' => $plan->id,
            'price' => 0,
            'digital_price' => 19.99,
            'print_price' => null,
            'is_default' => true,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'duration_months' => 12,
            'issue_count' => 0,
            'label' => 'tier',
            'period_description' => 'tier',
        ]);

        $plan->load('pricingTiers');

        $result = $plan->getDefaultEffectivePrice();

        $this->assertEquals(19.99, $result['min']);
    }

    public function testGetDefaultEffectivePriceReturnsPlanPriceWhenNoDeliveryOptions(): void
    {
        $plan = SubscriptionPlan::create([
            'price' => 15.00,
            'site_id' => $this->siteId,
            'name' => 'Basic Plan',
            'slug' => 'basic-plan',
        ]);

        $result = $plan->getDefaultEffectivePrice();

        $this->assertEquals(15.00, $result['min']);
        $this->assertNull($result['tier']);
    }

    // ---------------------------------------------------------------------------
// getBestSale — stock-awareness
// ---------------------------------------------------------------------------
    public function testGetBestSaleReturnsPrintSaleWhenIssueIsInStock(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id'                => $this->siteId,
            'name'                   => 'Print Plan',
            'slug'                   => 'print-in-stock',
            'price'                  => 0,
            'currency'               => 'USD',
            'billing_period'         => 'monthly',
            'print_shipping_required'=> true,
            'digital_download_url'   => null,
        ]);

        SubscriptionPlanPricing::create([
            'plan_id'            => $plan->id,
            'site_id'            => $this->siteId,
            'price'              => 30.00,
            'sale_price'         => 20.00,   // 33% saving
            'digital_price'      => 0,
            'digital_sale_price' => 0,
            'is_default'         => true,
            'is_active'          => true,
            'sort_order'         => 1,
            'duration_months'    => 1,
            'issue_count'        => 1,
            'label'              => 'Monthly',
            'period_description' => '1 month',
        ]);

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 1,
            'issue_title'          => 'Issue 1',
            'on_sale_date'         => now_datetime()->modify('-2 days'),
            'status'               => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity'       => 50,    // in stock
        ]);

        $plan->load('pricingTiers');
        $result = $plan->getBestSale();

        $this->assertNotNull($result, 'Expected a sale when print issue is in stock');
        $this->assertEquals(30.00, $result['original']);
        $this->assertEquals(20.00, $result['sale']);
        $this->assertEquals(33,    $result['savingPct']);
    }

    public function testGetBestSaleIgnoresPrintSaleWhenIssueHasZeroStock(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id'                => $this->siteId,
            'name'                   => 'Print Plan OOS',
            'slug'                   => 'print-oos',
            'price'                  => 0,
            'currency'               => 'USD',
            'billing_period'         => 'monthly',
            'print_shipping_required'=> true,
            'digital_download_url'   => null,
        ]);

        SubscriptionPlanPricing::create([
            'plan_id'            => $plan->id,
            'site_id'            => $this->siteId,
            'price'              => 30.00,
            'sale_price'         => 20.00,
            'digital_price'      => 0,
            'digital_sale_price' => 0,
            'is_default'         => true,
            'is_active'          => true,
            'sort_order'         => 1,
            'duration_months'    => 1,
            'issue_count'        => 1,
            'label'              => 'Monthly',
            'period_description' => '1 month',
        ]);

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 1,
            'issue_title'          => 'Issue 1',
            'on_sale_date'         => now_datetime()->modify('-2 days'),
            'status'               => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity'       => 0,    // out of stock
        ]);

        $plan->load('pricingTiers');
        $result = $plan->getBestSale();

        $this->assertNull($result, 'Expected no sale when print issue is out of stock');
    }

    public function testGetBestSaleIgnoresPrintSaleWhenIssueHasNullStock(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id'                => $this->siteId,
            'name'                   => 'Print Plan Null Stock',
            'slug'                   => 'print-null-stock',
            'price'                  => 0,
            'currency'               => 'USD',
            'billing_period'         => 'monthly',
            'print_shipping_required'=> true,
            'digital_download_url'   => null,
        ]);

        SubscriptionPlanPricing::create([
            'plan_id'            => $plan->id,
            'site_id'            => $this->siteId,
            'price'              => 30.00,
            'sale_price'         => 20.00,
            'digital_price'      => 0,
            'digital_sale_price' => 0,
            'is_default'         => true,
            'is_active'          => true,
            'sort_order'         => 1,
            'duration_months'    => 1,
            'issue_count'        => 1,
            'label'              => 'Monthly',
            'period_description' => '1 month',
        ]);

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 1,
            'issue_title'          => 'Issue 1',
            'on_sale_date'         => now_datetime()->modify('-2 days'),
            'status'               => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity'       => 0,  // null stock treated as out of stock
        ]);

        $plan->load('pricingTiers');
        $result = $plan->getBestSale();

        $this->assertNull($result, 'Expected no sale when issue stock is null');
    }

    public function testGetBestSaleIgnoresPrintSaleWhenNoIssueExists(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id'                => $this->siteId,
            'name'                   => 'Print Plan No Issue',
            'slug'                   => 'print-no-issue',
            'price'                  => 0,
            'currency'               => 'USD',
            'billing_period'         => 'monthly',
            'print_shipping_required'=> true,
            'digital_download_url'   => null,
        ]);

        SubscriptionPlanPricing::create([
            'plan_id'            => $plan->id,
            'site_id'            => $this->siteId,
            'price'              => 30.00,
            'sale_price'         => 20.00,
            'digital_price'      => 0,
            'digital_sale_price' => 0,
            'is_default'         => true,
            'is_active'          => true,
            'sort_order'         => 1,
            'duration_months'    => 1,
            'issue_count'        => 1,
            'label'              => 'Monthly',
            'period_description' => '1 month',
        ]);

        // No IssueDelivery created

        $plan->load('pricingTiers');
        $result = $plan->getBestSale();

        $this->assertNull($result, 'Expected no sale when no issue delivery exists for the plan');
    }

    public function testGetBestSaleReturnsDigitalSaleRegardlessOfPrintStock(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id'                => $this->siteId,
            'name'                   => 'Hybrid Plan OOS Print',
            'slug'                   => 'hybrid-oos-print',
            'price'                  => 0,
            'currency'               => 'USD',
            'billing_period'         => 'monthly',
            'print_shipping_required'=> true,
            'digital_download_url'   => 'https://example.com/dl',
        ]);

        SubscriptionPlanPricing::create([
            'plan_id'            => $plan->id,
            'site_id'            => $this->siteId,
            'price'              => 30.00,
            'sale_price'         => 20.00,    // print 33% — should be ignored (OOS)
            'digital_price'      => 25.00,
            'digital_sale_price' => 18.00,   // digital 28% — should be returned
            'is_default'         => true,
            'is_active'          => true,
            'sort_order'         => 1,
            'duration_months'    => 1,
            'issue_count'        => 1,
            'label'              => 'Monthly',
            'period_description' => '1 month',
        ]);

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 1,
            'issue_title'          => 'Issue 1',
            'on_sale_date'         => now_datetime()->modify('-2 days'),
            'status'               => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity'       => 0,    // print out of stock
        ]);

        $plan->load('pricingTiers');
        $result = $plan->getBestSale();

        $this->assertNotNull($result, 'Expected digital sale even when print is out of stock');
        $this->assertEquals(25.00, $result['original']);
        $this->assertEquals(18.00, $result['sale']);
        $this->assertEquals(28,    $result['savingPct']);
    }

    public function testGetBestSaleReturnsBestSaleAcrossMultipleTiersWithMixedStock(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id'                => $this->siteId,
            'name'                   => 'Multi-Tier Plan',
            'slug'                   => 'multi-tier',
            'price'                  => 0,
            'currency'               => 'USD',
            'billing_period'         => 'monthly',
            'print_shipping_required'=> true,
            'digital_download_url'   => 'https://example.com/dl',
        ]);

        // Tier 1: small digital saving
        SubscriptionPlanPricing::create([
            'plan_id'            => $plan->id,
            'site_id'            => $this->siteId,
            'price'              => 30.00,
            'sale_price'         => 20.00,   // print 33% — OOS, must be ignored
            'digital_price'      => 20.00,
            'digital_sale_price' => 18.00,  // digital 10%
            'is_default'         => false,
            'is_active'          => true,
            'sort_order'         => 1,
            'duration_months'    => 1,
            'issue_count'        => 1,
            'label'              => 'Monthly',
            'period_description' => '1 month',
        ]);

        // Tier 2: larger digital saving
        SubscriptionPlanPricing::create([
            'plan_id'            => $plan->id,
            'site_id'            => $this->siteId,
            'price'              => 100.00,
            'sale_price'         => 60.00,  // print 40% — OOS, must be ignored
            'digital_price'      => 90.00,
            'digital_sale_price' => 45.00, // digital 50% — should win
            'is_default'         => true,
            'is_active'          => true,
            'sort_order'         => 2,
            'duration_months'    => 12,
            'issue_count'        => 12,
            'label'              => 'Annual',
            'period_description' => '12 months',
        ]);

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 1,
            'issue_title'          => 'Issue 1',
            'on_sale_date'         => now_datetime()->modify('-2 days'),
            'status'               => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity'       => 0,  // print out of stock
        ]);

        $plan->load('pricingTiers');
        $result = $plan->getBestSale();

        $this->assertNotNull($result);
        $this->assertEquals(90.00, $result['original'], 'Should use digital price as original');
        $this->assertEquals(45.00, $result['sale'],     'Should use digital sale price');
        $this->assertEquals(50,    $result['savingPct'], 'Should return 50% — the best digital saving');
    }

    public function testIsPrintInStockReturnsTrueWhenNextIssueHasStock(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id'                => $this->siteId,
            'name'                   => 'Print Plan',
            'slug'                   => 'print-stocked',
            'price'                  => 10.00,
            'currency'               => 'USD',
            'billing_period'         => 'monthly',
            'print_shipping_required'=> true,
        ]);

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 1,
            'issue_title'          => 'Issue 1',
            'on_sale_date'         => now_datetime()->modify('-1 days'),
            'status'               => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity'       => 10,
        ]);

        $this->assertTrue($plan->isPrintInStock());
    }

    public function testIsPrintInStockReturnsFalseWhenNextIssueIsOutOfStock(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id'                => $this->siteId,
            'name'                   => 'Print Plan OOS',
            'slug'                   => 'print-empty',
            'price'                  => 10.00,
            'currency'               => 'USD',
            'billing_period'         => 'monthly',
            'print_shipping_required'=> true,
        ]);

        IssueDelivery::create([
            'site_id'              => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number'         => 1,
            'issue_title'          => 'Issue 1',
            'on_sale_date'         => now_datetime()->modify('-1 days'),
            'status'               => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity'       => 0,
        ]);

        $this->assertFalse($plan->isPrintInStock());
    }

    public function testIsPrintInStockReturnsFalseWhenNoIssueExists(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id'                => $this->siteId,
            'name'                   => 'Print Plan No Issues',
            'slug'                   => 'print-no-issues',
            'price'                  => 10.00,
            'currency'               => 'USD',
            'billing_period'         => 'monthly',
            'print_shipping_required'=> true,
        ]);

        $this->assertFalse($plan->isPrintInStock());
    }

}