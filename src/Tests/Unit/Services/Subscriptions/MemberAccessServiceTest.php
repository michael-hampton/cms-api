<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\MemberAccessService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class MemberAccessServiceTest extends TestCase
{
    private SubscriptionRepository&MockInterface $subscriptionRepository;
    private Logger&MockInterface $logger;
    private MemberAccessService $service;

    public function test_it_calls_grant_premium_access_for_each_plan_grant(): void
    {
        $accessUntil = new \DateTimeImmutable('2025-07-01 00:00:00');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 5;
        $plan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider'],
            ['type' => 'newsletter', 'identifier' => 'premium'],
        ]);
        $plan->shouldReceive('grantsPremiumAccess')->andReturn(false);

        $subscription = $this->makeSubscription($plan);
        $subscription->shouldReceive('grantPremiumAccess')
            ->twice()
            ->with(Mockery::type('string'), Mockery::type('string'), Mockery::type(\DateTime::class));

        $this->service->refreshSubscriptionAccess($subscription, $accessUntil);
        $this->assertTrue(true);
    }

    private function makeSubscription(MockInterface $plan): Subscription&MockInterface
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 10;
        $subscription->plan = $plan;

        return $subscription;
    }

    // ── refreshSubscriptionAccess ──────────────────────────────────────────

    public function test_it_passes_access_until_as_expiry_to_each_grant(): void
    {
        $accessUntil = new \DateTimeImmutable('2025-08-15 00:00:00');
        $expectedExpiry = \DateTime::createFromImmutable($accessUntil);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 5;
        $plan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider'],
        ]);
        $plan->shouldReceive('grantsPremiumAccess')->andReturn(false);

        $subscription = $this->makeSubscription($plan);
        $subscription->shouldReceive('grantPremiumAccess')
            ->once()
            ->with('newsletter', 'insider', Mockery::on(fn($d) => $d instanceof \DateTime &&
                $d->format('Y-m-d') === $expectedExpiry->format('Y-m-d')
            ));

        $this->service->refreshSubscriptionAccess($subscription, $accessUntil);
        $this->assertTrue(true);
    }

    public function test_it_sets_includes_digital_access_when_plan_grants_insider(): void
    {
        $accessUntil = new \DateTimeImmutable('2025-07-01 00:00:00');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 5;
        $plan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider'],
        ]);
        $plan->shouldReceive('grantsPremiumAccess')
            ->with('newsletter', 'insider')
            ->andReturn(true);

        $subscription = $this->makeSubscription($plan);
        $subscription->shouldReceive('grantPremiumAccess');
        $subscription->shouldReceive('update')
            ->once()
            ->with(['includes_digital_access' => true]);

        $this->service->refreshSubscriptionAccess($subscription, $accessUntil);
        $this->assertTrue(true);
    }

    public function test_it_skips_access_refresh_when_subscription_has_no_plan(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan = null;

        $subscription->shouldNotReceive('grantPremiumAccess');

        $this->logger->shouldReceive('warning')->once();

        $this->service->refreshSubscriptionAccess(
            $subscription,
            new \DateTimeImmutable('2025-07-01'),
        );
        $this->assertTrue(true);
    }

    public function test_it_skips_without_error_when_plan_has_no_grants(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 5;
        $plan->shouldReceive('getPremiumAccessGrants')->andReturn([]);

        $subscription = $this->makeSubscription($plan);
        $subscription->shouldNotReceive('grantPremiumAccess');

        // Must complete without error
        $this->service->refreshSubscriptionAccess(
            $subscription,
            new \DateTimeImmutable('2025-07-01'),
        );
        $this->assertTrue(true);
    }

    public function test_it_delegates_revocation_to_the_repository(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 42;

        $this->subscriptionRepository
            ->shouldReceive('revokeAllPremiumAccess')
            ->once()
            ->with(42);

        $this->service->revokeSubscriptionAccess($subscription);
        $this->assertTrue(true);
    }

    // ── revokeSubscriptionAccess ───────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new MemberAccessService(
            subscriptionRepository: $this->subscriptionRepository,
            logger: $this->logger,
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}