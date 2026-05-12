<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\FulfilmentReplacement;
use App\Models\Subscription;
use App\Repositories\Subscriptions\FulfilmentReplacementRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentReplacementService;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

class FulfilmentReplacementServiceTest extends TestCase
{
    private FulfilmentReplacementRepository $replacementRepository;
    private SubscriptionRepository $subscriptionRepository;
    private FulfilmentReplacementService $service;

    public function test_throws_exception_when_reason_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reason is required for issue replacement.');

        $this->service->requestReplacement(
            subscriptionId: 1,
            issueId: 100,
            reason: '   ',
            agentId: 5,
            siteId: 1
        );
    }

    public function test_throws_exception_when_subscription_not_found(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription #1 not found.');

        $this->service->requestReplacement(1, 100, 'Missing issue', 5, 1);
    }

    public function test_throws_exception_when_site_mismatch(): void
    {
        $subscription = $this->makeSubscription(siteId: 99);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription does not belong to this site.');

        $this->service->requestReplacement(1, 100, 'Missing issue', 5, 1);
    }

    private function makeSubscription(
        int    $siteId = 1,
        string $status = 'active',
        string $deliveryType = 'print'
    ): object
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 10;
        $subscription->status = $status;
        $subscription->delivery_type = $deliveryType;
        $subscription->site_id = $siteId;

        return $subscription;
    }

    public function test_throws_exception_when_subscription_not_active(): void
    {
        $subscription = $this->makeSubscription(status: 'paused');

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only active subscriptions can have issues replaced.');

        $this->service->requestReplacement(1, 100, 'Missing issue', 5, 1);
    }

    public function test_throws_exception_when_not_print_subscription(): void
    {
        $subscription = $this->makeSubscription(deliveryType: 'digital');

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Issue replacement is only available for print subscriptions.');

        $this->service->requestReplacement(1, 100, 'Missing issue', 5, 1);
    }

    public function test_throws_exception_when_issue_does_not_belong_to_subscription(): void
    {
        $subscription = $this->makeSubscription();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->replacementRepository
            ->shouldReceive('issueExistsForSubscription')
            ->once()
            ->with(100, 1)
            ->andReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Issue #100 does not belong to subscription #1.');

        $this->service->requestReplacement(1, 100, 'Missing issue', 5, 1);
    }

    public function test_successfully_creates_replacement_and_returns_object(): void
    {
        $subscription = $this->makeSubscription();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->replacementRepository
            ->shouldReceive('issueExistsForSubscription')
            ->once()
            ->with(100, 1)
            ->andReturn(true);


        $replacement = Mockery::mock(FulfilmentReplacement::class)->makePartial();
        $replacement->id = 999;
        $replacement->status = 'pending';
        $replacement->subscription_id = 1;
        $replacement->issue_id = 100;

        $this->replacementRepository
            ->shouldReceive('createReplacement')
            ->once()
            ->with(
                1,
                100,
                'Missing issue',
                5
            )
            ->andReturn($replacement);

        // We cannot assert event() or Logger::info() due to static usage,
        // but we ensure execution completes successfully.

        $result = $this->service->requestReplacement(
            subscriptionId: 1,
            issueId: 100,
            reason: 'Missing issue',
            agentId: 5,
            siteId: 1
        );

        $this->assertSame($replacement, $result);
        $this->assertEquals(999, $result->id);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->replacementRepository = Mockery::mock(FulfilmentReplacementRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);

        $this->service = new FulfilmentReplacementService(
            $this->replacementRepository,
            $this->subscriptionRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}