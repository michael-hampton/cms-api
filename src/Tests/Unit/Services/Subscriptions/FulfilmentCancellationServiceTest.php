<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Services\Subscriptions\FulfilmentCancellationService;
use Mockery;
use PHPUnit\Framework\TestCase;

class FulfilmentCancellationServiceTest extends TestCase
{
    public function test_cancel_delegates_to_repository_and_returns_count(): void
    {
        $fulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 55;

        $fulfilmentRepository->shouldReceive('cancelPendingForSubscription')
            ->once()
            ->with(55)
            ->andReturn(4);

        $service = new FulfilmentCancellationService($fulfilmentRepository, $logger);

        $result = $service->cancel($subscription);

        $this->assertSame(4, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
