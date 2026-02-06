<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPremiumAccess;
use App\Services\Subscriptions\UpgradeBenefitsService;
use Mockery;
use PHPUnit\Framework\TestCase;

class UpgradeBenefitsServiceTest extends TestCase
{
    private UpgradeBenefitsService $service;

    public function testGetUpgradeBenefitsForNewPremiumAccess(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan = null;

        $currentAccess = collect([]);
        $subscription->shouldReceive('premiumAccess')->andReturn($currentAccess);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->features = [];
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

        $result = $this->service->getUpgradeBenefits($subscription, $upgradePlan);

        $this->assertCount(1, $result);
        $this->assertEquals('🔓', $result[0]['icon']);
        $this->assertEquals('Unlock Insider Newsletter', $result[0]['title']);
    }

    public function testGetUpgradeBenefitsExcludesExistingAccess(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan = null;

        $existingAccess1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $existingAccess1->premium_type = 'newsletter';
        $existingAccess1->premium_identifier = 'insider';

        $currentAccess = collect([$existingAccess1]);
        $subscription->shouldReceive('premiumAccess')->andReturn($currentAccess);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->features = [];
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider'], // Already have
            ['type' => 'archive', 'identifier' => 'full']        // New
        ]);

        $result = $this->service->getUpgradeBenefits($subscription, $upgradePlan);

        // Should only have archive access benefit
        $this->assertCount(1, $result);
        $this->assertStringContainsString('Archive', $result[0]['title']);
    }

    public function testGetUpgradeBenefitsIncludesNewFeatures(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $currentPlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $currentPlan->features = ['Feature A', 'Feature B'];
        $subscription->plan = $currentPlan;

        $currentAccess = collect([]);
        $subscription->shouldReceive('premiumAccess')->andReturn($currentAccess);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->features = ['Feature A', 'Feature B', 'Feature C', 'Feature D', 'Feature E'];
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([]);

        $result = $this->service->getUpgradeBenefits($subscription, $upgradePlan);

        // Should have up to 3 new features
        $this->assertLessThanOrEqual(3, count($result));

        foreach ($result as $benefit) {
            $this->assertEquals('✨', $benefit['icon']);
            $this->assertEquals('New Feature', $benefit['title']);
        }
    }

    public function testGetUpgradeBenefitsLimitsFeaturesToThree(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $currentPlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $currentPlan->features = [];
        $subscription->plan = $currentPlan;

        $currentAccess = collect([]);
        $subscription->shouldReceive('premiumAccess')->andReturn($currentAccess);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->features = ['F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7'];
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([]);

        $result = $this->service->getUpgradeBenefits($subscription, $upgradePlan);

        // Should max out at 3 features
        $this->assertCount(3, $result);
    }

    public function testGetUpgradeBenefitsWithCustomBenefitMap(): void
    {
        $customBenefitMap = [
            'newsletter:custom' => [
                'icon' => '🎯',
                'title' => 'Custom Newsletter',
                'description' => 'Custom description'
            ]
        ];

        $service = new UpgradeBenefitsService($customBenefitMap);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan = null;

        $currentAccess = collect([]);
        $subscription->shouldReceive('premiumAccess')->andReturn($currentAccess);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->features = [];
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'custom']
        ]);

        $result = $service->getUpgradeBenefits($subscription, $upgradePlan);

        $this->assertCount(1, $result);
        $this->assertEquals('🎯', $result[0]['icon']);
        $this->assertEquals('Custom Newsletter', $result[0]['title']);
        $this->assertEquals('Custom description', $result[0]['description']);
    }

    public function testGetUpgradeBenefitsUsesDefaultForUnknownAccess(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan = null;

        $currentAccess = collect([]);
        $subscription->shouldReceive('premiumAccess')->andReturn($currentAccess);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->features = [];
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'unknown', 'identifier' => 'mystery']
        ]);

        $result = $this->service->getUpgradeBenefits($subscription, $upgradePlan);

        $this->assertCount(1, $result);
        $this->assertEquals('⭐', $result[0]['icon']);
        $this->assertEquals('Mystery', $result[0]['title']);
        $this->assertStringContainsString('Premium unknown access', $result[0]['description']);
    }

    public function testGetUpgradeBenefitsReturnsEmptyArrayWhenNoChanges(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $currentPlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $currentPlan->features = ['Feature A'];
        $subscription->plan = $currentPlan;

        $existingAccess = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $existingAccess->premium_type = 'newsletter';
        $existingAccess->premium_identifier = 'insider';

        $currentAccess = collect([$existingAccess]);
        $subscription->shouldReceive('premiumAccess')->andReturn($currentAccess);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->features = ['Feature A']; // Same features
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider'] // Same access
        ]);

        $result = $this->service->getUpgradeBenefits($subscription, $upgradePlan);

        $this->assertCount(0, $result);
    }

    public function testGetUpgradeBenefitsCombinesPremiumAccessAndFeatures(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $currentPlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $currentPlan->features = ['Old Feature'];
        $subscription->plan = $currentPlan;

        $currentAccess = collect([]);
        $subscription->shouldReceive('premiumAccess')->andReturn($currentAccess);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->features = ['Old Feature', 'New Feature 1', 'New Feature 2'];
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

        $result = $this->service->getUpgradeBenefits($subscription, $upgradePlan);

        // Should have 1 premium access + 2 features (limited to 3 total)
        $this->assertGreaterThan(0, count($result));
        $this->assertLessThanOrEqual(4, count($result)); // 1 access + 2 features
    }

    public function testGetUpgradeBenefitsHandlesNullCurrentPlan(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan = null;

        $currentAccess = collect([]);
        $subscription->shouldReceive('premiumAccess')->andReturn($currentAccess);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->features = ['Feature 1', 'Feature 2'];
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([]);

        $result = $this->service->getUpgradeBenefits($subscription, $upgradePlan);

        // Should treat as all new features (up to 3)
        $this->assertCount(2, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UpgradeBenefitsService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}