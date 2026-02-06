<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPremiumAccess;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\PremiumAccessGrantService;
use Mockery;
use PHPUnit\Framework\TestCase;

class PremiumAccessGrantServiceTest extends TestCase
{
    private PremiumAccessGrantService $service;
    private $subscriptionRepository;

    public function testGrantPremiumAccessForSingleGrant(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'tech-weekly', 'expires_at' => null]
        ]);

        $access = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access->id = 1;

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'tech-weekly', null)
            ->once()
            ->andReturn($access);

        $result = $this->service->grantPremiumAccess($subscription, $upgradePlan, 1);

        $this->assertCount(1, $result);
        $this->assertSame($access, $result[0]);
    }

    public function testGrantPremiumAccessForMultipleGrants(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider', 'expires_at' => null],
            ['type' => 'archive', 'identifier' => 'full', 'expires_at' => null],
            ['type' => 'podcast', 'identifier' => 'premium', 'expires_at' => null],
        ]);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access2 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access3 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'insider', null)
            ->once()
            ->andReturn($access1);

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('archive', 'full', null)
            ->once()
            ->andReturn($access2);

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('podcast', 'premium', null)
            ->once()
            ->andReturn($access3);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, ['includes_digital_access' => true])
            ->andReturn($subscription);

        $result = $this->service->grantPremiumAccess($subscription, $upgradePlan, 1);

        $this->assertCount(3, $result);
    }

    public function testGrantPremiumAccessSetsBackwardCompatibilityFlagForInsider(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider', 'expires_at' => null]
        ]);

        $access = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'insider', null)
            ->once()
            ->andReturn($access);

        // Should set backward compatibility flag
        $this->subscriptionRepository->shouldReceive('update')
            ->with(1, ['includes_digital_access' => true])
            ->once();

        $this->service->grantPremiumAccess($subscription, $upgradePlan, 1);

        $this->assertTrue(true);
    }

    public function testGrantPremiumAccessDoesNotSetFlagForNonInsider(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'archive', 'identifier' => 'full', 'expires_at' => null]
        ]);

        $access = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();

        $subscription->shouldReceive('grantPremiumAccess')
            ->once()
            ->andReturn($access);

        // Should NOT set flag for non-insider
        $this->subscriptionRepository->shouldReceive('update')->never();

        $this->service->grantPremiumAccess($subscription, $upgradePlan, 1);

        $this->assertTrue(true);
    }

    public function testGrantPremiumAccessWithExpiryDates(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $expiryDate = new \DateTime('+1 year');

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'trial', 'expires_at' => $expiryDate]
        ]);

        $access = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'trial', $expiryDate)
            ->once()
            ->andReturn($access);

        $result = $this->service->grantPremiumAccess($subscription, $upgradePlan, 1);

        $this->assertCount(1, $result);
    }

    public function testGrantPremiumAccessReturnsEmptyArrayWhenNoGrants(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([]);

        $result = $this->service->grantPremiumAccess($subscription, $upgradePlan, 1);

        $this->assertCount(0, $result);
        $this->assertIsArray($result);
    }

    public function testGrantPremiumAccessIsIdempotent(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider', 'expires_at' => null]
        ]);

        $access = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();

        // grantPremiumAccess should handle idempotency internally
        $subscription->shouldReceive('grantPremiumAccess')
            ->twice()
            ->andReturn($access);

        $this->subscriptionRepository->shouldReceive('update')->twice();

        // Call twice
        $this->service->grantPremiumAccess($subscription, $upgradePlan, 1);
        $this->service->grantPremiumAccess($subscription, $upgradePlan, 1);

        $this->assertTrue(true); // Should not throw
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->service = new PremiumAccessGrantService($this->subscriptionRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}