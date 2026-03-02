<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Exceptions\Subscriptions\PremiumAccessAlreadyGrantedException;
use App\Exceptions\Subscriptions\PremiumAccessNotFoundException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Models\Subscription;
use App\Models\SubscriptionPremiumAccess;
use App\Repositories\Subscriptions\SubscriptionPremiumAccessRepository;
use App\Services\Subscriptions\SubscriptionPremiumAccessService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionPremiumAccessServiceTest extends TestCase
{
    private SubscriptionPremiumAccessRepository $repository;
    private SubscriptionPremiumAccessService $service;

    public function test_grant_creates_access_when_subscription_exists_and_no_active_grant(): void
    {
        $subscription = $this->makeSubscription(1);
        $access = $this->makeAccess(1, 1, 'newsletter', 'insider');

        $this->repository->shouldReceive('findSubscription')
            ->once()
            ->with(1)
            ->andReturn($subscription);

        $this->repository->shouldReceive('findExisting')
            ->once()
            ->with(1, 'newsletter', 'insider')
            ->andReturn(null);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => isset($data['subscription_id'])))
            ->andReturn($access);

        $result = $this->service->grant(1, 'newsletter', 'insider');

        $this->assertSame($access, $result);
    }

    // ── grant ──────────────────────────────────────────────────────────────

    private function makeSubscription(int $id): Subscription
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = $id;

        return $sub;
    }

    private function makeAccess(
        int    $id,
        int    $subscriptionId,
        string $type,
        string $identifier,
        bool   $isActive = true
    ): SubscriptionPremiumAccess
    {
        $access = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();

        $access->id = $id;
        $access->subscription_id = $subscriptionId;
        $access->premium_type = $type;
        $access->premium_identifier = $identifier;
        $access->is_active = $isActive;

        return $access;
    }

    public function test_grant_throws_when_subscription_not_found(): void
    {
        $this->repository->shouldReceive('findSubscription')
            ->once()
            ->with(99)
            ->andReturn(null);

        $this->expectException(SubscriptionNotFoundException::class);

        $this->service->grant(99, 'newsletter', 'insider');
    }

    public function test_grant_throws_when_active_grant_already_exists(): void
    {
        $subscription = $this->makeSubscription(1);
        $existingGrant = $this->makeAccess(5, 1, 'newsletter', 'insider', true);

        $this->repository->shouldReceive('findSubscription')
            ->andReturn($subscription);

        $this->repository->shouldReceive('findExisting')
            ->andReturn($existingGrant);

        $this->expectException(PremiumAccessAlreadyGrantedException::class);

        $this->service->grant(1, 'newsletter', 'insider');
    }

    public function test_grant_creates_access_when_existing_grant_is_inactive(): void
    {
        $subscription = $this->makeSubscription(1);
        $inactiveGrant = $this->makeAccess(5, 1, 'newsletter', 'insider', false);
        $newAccess = $this->makeAccess(6, 1, 'newsletter', 'insider', true);

        $this->repository->shouldReceive('findSubscription')
            ->andReturn($subscription);

        $this->repository->shouldReceive('findExisting')
            ->andReturn($inactiveGrant);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($newAccess);

        $result = $this->service->grant(1, 'newsletter', 'insider');

        $this->assertSame($newAccess, $result);
    }

    // ── revoke ─────────────────────────────────────────────────────────────

    public function test_grant_passes_expires_at_to_repository(): void
    {
        $subscription = $this->makeSubscription(1);
        $access = $this->makeAccess(1, 1, 'content', 'premium-archive');

        $this->repository->shouldReceive('findSubscription')
            ->andReturn($subscription);

        $this->repository->shouldReceive('findExisting')
            ->andReturn(null);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => $data['expires_at'] === '2025-12-31 00:00:00'
            ))
            ->andReturn($access);

        $this->service->grant(1, 'content', 'premium-archive', '2025-12-31 00:00:00');
        $this->assertTrue(true);
    }

    public function test_revoke_deactivates_grant(): void
    {
        $subscription = $this->makeSubscription(1);

        $this->repository->shouldReceive('findSubscription')
            ->andReturn($subscription);

        $this->repository->shouldReceive('deactivate')
            ->once()
            ->with(1, 'newsletter', 'insider')
            ->andReturn(true);

        $result = $this->service->revoke(1, 'newsletter', 'insider');

        $this->assertTrue($result);
    }

    // ── update ─────────────────────────────────────────────────────────────

    public function test_revoke_throws_when_subscription_not_found(): void
    {
        $this->repository->shouldReceive('findSubscription')
            ->andReturn(null);

        $this->expectException(SubscriptionNotFoundException::class);

        $this->service->revoke(99, 'newsletter', 'insider');
    }

    public function test_update_returns_updated_access(): void
    {
        $updated = $this->makeAccess(1, 1, 'newsletter', 'insider');

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, ['is_active' => false])
            ->andReturn($updated);

        $result = $this->service->update(1, [
            'is_active' => false,
            'premium_type' => 'ignored',
        ]);

        $this->assertSame($updated, $result);
    }

    public function test_update_strips_non_allowed_fields(): void
    {
        $updated = $this->makeAccess(1, 1, 'newsletter', 'insider');

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($data) {
                return !isset($data['premium_type'])
                    && !isset($data['subscription_id'])
                    && !isset($data['premium_identifier']);
            }))
            ->andReturn($updated);

        $this->service->update(1, [
            'is_active' => false,
            'premium_type' => 'should-be-stripped',
            'subscription_id' => 99,
            'premium_identifier' => 'should-be-stripped',
        ]);
        $this->assertTrue(true);
    }

    // ── delete ─────────────────────────────────────────────────────────────

    public function test_update_throws_when_access_not_found(): void
    {
        $this->repository->shouldReceive('update')
            ->andReturn(null);

        $this->expectException(PremiumAccessNotFoundException::class);

        $this->service->update(99, ['is_active' => false]);
    }

    public function test_delete_returns_true_on_success(): void
    {
        $this->repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $this->assertTrue($this->service->delete(1));
    }

    // ── getForSubscription ─────────────────────────────────────────────────

    public function test_delete_throws_when_not_found(): void
    {
        $this->repository->shouldReceive('delete')
            ->andReturn(false);

        $this->expectException(PremiumAccessNotFoundException::class);

        $this->service->delete(99);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    public function test_get_for_subscription_delegates_to_repository(): void
    {
        $collection = collect([
            $this->makeAccess(1, 1, 'newsletter', 'insider'),
            $this->makeAccess(2, 1, 'content', 'archive'),
        ]);

        $this->repository->shouldReceive('findBySubscription')
            ->once()
            ->with(1)
            ->andReturn($collection);

        $result = $this->service->getForSubscription(1);

        $this->assertCount(2, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SubscriptionPremiumAccessRepository::class);
        $this->service = new SubscriptionPremiumAccessService($this->repository);
    }
}