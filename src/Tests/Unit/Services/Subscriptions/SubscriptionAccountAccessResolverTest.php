<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionAccountAccessResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionAccountAccessResolverTest extends TestCase
{
    private $subscriptionRepository;
    private SubscriptionAccountAccessResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->resolver = new SubscriptionAccountAccessResolver($this->subscriptionRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_delegates_to_repository_with_site_scope(): void
    {
        $subscription = Mockery::mock(Subscription::class);

        $this->subscriptionRepository
            ->shouldReceive('findForMemberAccess')
            ->once()
            ->with(1, 42, 5)
            ->andReturn($subscription);

        $result = $this->resolver->resolve(1, 42, 5);

        $this->assertSame($subscription, $result);
    }

    public function test_resolve_passes_null_site_id_through_unscoped(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('findForMemberAccess')
            ->once()
            ->with(1, 42, null)
            ->andReturn(null);

        $result = $this->resolver->resolve(1, 42, null);

        $this->assertNull($result);
    }
}
