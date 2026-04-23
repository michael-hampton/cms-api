<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Stripe\StripeSubscriptionDeletedEvent;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionCancelledByStripe;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionCancellationHandler;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SubscriptionCancellationHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionCancellationHandler $handler;
    private $subscriptionRepository;
    private $eventDispatcher;
    private $logger;
    private $database;

    public function testHandleSuccess(): void
    {
        $event = new StripeSubscriptionDeletedEvent(
            stripeSubscriptionId: 'sub_123',
            stripeStatus: 'canceled',
            canceledAt: 1600000000,
            currentPeriodEnd: 1600003600
        );

        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('setAttribute')->andReturnNull();
        $subscription->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $subscription->shouldReceive('getAttribute')->andReturnNull();
        $subscription->shouldReceive('relationLoaded')->andReturn(false);
        $subscription->id = 1;
        $subscription->status = SubscriptionStatus::ACTIVE->value;

        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')
            ->once()
            ->with('sub_123')
            ->andReturn($subscription);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['status'] === SubscriptionStatus::CANCELLED->value
                    && $arg['auto_renew'] === false;
            }));

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(SubscriptionCancelledByStripe::class));

        $this->logger->shouldReceive('info')->once();

        $this->handler->handle($event);
    }

    public function testHandleNotFound(): void
    {
        $event = new StripeSubscriptionDeletedEvent(
            stripeSubscriptionId: 'sub_not_found',
            stripeStatus: 'canceled',
            canceledAt: 1600000000,
            currentPeriodEnd: 1600003600
        );

        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')
            ->once()
            ->with('sub_not_found')
            ->andReturn(null);

        $this->logger->shouldReceive('warning')->once();

        $this->handler->handle($event);
    }

    public function testHandleAlreadyCancelled(): void
    {
        $event = new StripeSubscriptionDeletedEvent(
            stripeSubscriptionId: 'sub_123',
            stripeStatus: 'canceled',
            canceledAt: 1600000000,
            currentPeriodEnd: 1600003600
        );

        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('setAttribute')->andReturnNull();
        $subscription->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $subscription->shouldReceive('getAttribute')->with('status')->andReturn(SubscriptionStatus::CANCELLED->value);
        $subscription->shouldReceive('getAttribute')->andReturnNull();
        $subscription->shouldReceive('relationLoaded')->andReturn(false);
        $subscription->id = 1;
        $subscription->status = SubscriptionStatus::CANCELLED->value;

        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')
            ->once()
            ->with('sub_123')
            ->andReturn($subscription);

        $this->logger->shouldReceive('info')->once()->with(Mockery::pattern('/already cancelled/'), Mockery::any());

        $this->handler->handle($event);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->database = Mockery::mock(Database::class);

        $this->handler = new SubscriptionCancellationHandler(
            $this->subscriptionRepository,
            $this->eventDispatcher,
            $this->logger,
            $this->database
        );
    }
}
