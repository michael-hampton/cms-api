<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\CommunicationTypeEnum;
use App\Models\SubscriptionCommunication;
use App\Repositories\Subscriptions\SubscriptionCommunicationSuppressionLogRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SubscriptionCommunicationSuppressionLogRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriptionCommunicationSuppressionLogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionCommunicationSuppressionLogRepository();
    }

    public function test_logs_a_suppressed_communication_attempt(): void
    {
        $subscription = $this->createSubscription();
        $communication = SubscriptionCommunication::create([
            'key'        => 'comm_' . uniqid(),
            'name'       => 'Test Communication',
            'type'       => CommunicationTypeEnum::FIRST_ISSUE->value,
            'template'   => '',
            'channels'   => ['email'],
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        $log = $this->repository->log(
            subscriptionId: $subscription->id,
            memberId: $subscription->member_id,
            communicationId: $communication->id,
            channel: null,
            reason: 'member_deceased',
            metadata: ['note' => 'test'],
        );

        $this->assertNotNull($log->id);
        $this->assertSame('member_deceased', $log->reason);

        $logs = $this->repository->forSubscription($subscription->id);

        $this->assertCount(1, $logs);
        $this->assertSame($log->id, $logs->first()->id);
    }

    public function test_forSubscription_returns_only_that_subscriptions_logs(): void
    {
        $subscriptionA = $this->createSubscription();
        $subscriptionB = $this->createSubscription();

        $this->repository->log($subscriptionA->id, null, null, null, 'member_deceased');
        $this->repository->log($subscriptionB->id, null, null, null, 'do_not_mail');

        $logsA = $this->repository->forSubscription($subscriptionA->id);

        $this->assertCount(1, $logsA);
        $this->assertSame('member_deceased', $logsA->first()->reason);
    }
}
