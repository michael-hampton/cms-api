<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionActivationService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionActivationServiceTest extends TestCase
{
    private SubscriptionRepository&MockInterface $repository;
    private Logger&MockInterface $logger;
    private SubscriptionActivationService $service;
    private \DateTimeImmutable $now;

    public function test_it_activates_all_scheduled_subscriptions_that_are_due(): void
    {
        $sub1 = $this->makeSubscription(id: 1);
        $sub2 = $this->makeSubscription(id: 2);

        $this->repository
            ->shouldReceive('getScheduledDue')
            ->once()
            ->with($this->now)
            ->andReturn(new Collection([$sub1, $sub2]));

        $this->repository
            ->shouldReceive('markAsActive')
            ->twice();

        $result = $this->service->activateScheduled($this->now);

        $this->assertSame(2, $result['activated']);
        $this->assertSame(0, $result['failed']);
    }

    /**
     * Build a minimal Subscription mock with just enough surface area for the
     * service to interact with. Using a partial mock on the real class (rather
     * than stdClass) keeps the test honest about the model's interface.
     */
    private function makeSubscription(int $id): Subscription&MockInterface
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = $id;

        return $sub;
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_it_returns_zero_counts_when_no_subscriptions_are_due(): void
    {
        $this->repository
            ->shouldReceive('getScheduledDue')
            ->andReturn(new Collection());

        $result = $this->service->activateScheduled($this->now);

        $this->assertSame(0, $result['activated']);
        $this->assertSame(0, $result['failed']);
    }

    public function test_it_continues_processing_remaining_subscriptions_when_one_fails(): void
    {
        $failing = $this->makeSubscription(id: 10);
        $passing = $this->makeSubscription(id: 11);

        $this->repository
            ->shouldReceive('getScheduledDue')
            ->andReturn(new Collection([$failing, $passing]));

        $this->repository
            ->shouldReceive('markAsActive')
            ->with($failing, $this->now)
            ->once()
            ->andThrow(new \RuntimeException('DB connection lost'));

        $this->repository
            ->shouldReceive('markAsActive')
            ->with($passing, $this->now)
            ->once();

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with('Failed to activate scheduled subscription', Mockery::on(
                fn(array $ctx) => $ctx['subscription_id'] === 10
            ));

        $result = $this->service->activateScheduled($this->now);

        $this->assertSame(1, $result['activated']);
        $this->assertSame(1, $result['failed']);
    }

    // -------------------------------------------------------------------------
    // Failure isolation
    // -------------------------------------------------------------------------

    public function test_it_logs_the_subscription_id_and_error_message_on_failure(): void
    {
        $sub = $this->makeSubscription(id: 7);

        $this->repository
            ->shouldReceive('getScheduledDue')
            ->andReturn(new Collection([$sub]));

        $this->repository
            ->shouldReceive('markAsActive')
            ->andThrow(new \RuntimeException('Something went wrong'));

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with(
                'Failed to activate scheduled subscription',
                Mockery::on(function (array $context): bool {
                    return $context['subscription_id'] === 7
                        && $context['error'] === 'Something went wrong';
                })
            );

        $this->service->activateScheduled($this->now);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SubscriptionRepository::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->now = new \DateTimeImmutable('2025-06-01 12:00:00');

        $this->service = new SubscriptionActivationService(
            $this->repository,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}