<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;
use App\Repositories\Subscriptions\SubscriptionCommunicationScheduleRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SubscriptionCommunicationScheduleRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriptionCommunicationScheduleRepository $repository;
    private SubscriptionCommunication $communication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository  = new SubscriptionCommunicationScheduleRepository();
        $this->communication = SubscriptionCommunication::create([
            'key'        => 'test_comm_' . uniqid(),
            'name'       => 'Test Communication',
            'type'       => 'renewal_reminder',
            'template'   => \App\Mail\Subscriptions\RenewalReminderMail::class,
            'channels'   => ['email'],
            'is_active'  => true,
            'sort_order' => 0,
        ]);
    }

    public function test_find_active_for_communication_returns_active_schedules(): void
    {
        SubscriptionCommunicationSchedule::create($this->scheduleAttributes([
            'subscription_communication_id' => $this->communication->id,
            'name'      => 'Active schedule',
            'is_active' => true,
        ]));

        $result = $this->repository->findActiveForCommunication($this->communication->id);

        $this->assertCount(1, $result);
        $this->assertTrue($result->first()->is_active);
    }

    public function test_find_active_for_communication_excludes_inactive_schedules(): void
    {
        SubscriptionCommunicationSchedule::create($this->scheduleAttributes([
            'subscription_communication_id' => $this->communication->id,
            'name'      => 'Inactive schedule',
            'is_active' => false,
        ]));

        $result = $this->repository->findActiveForCommunication($this->communication->id);

        $this->assertCount(0, $result);
    }

    public function test_find_active_for_communication_returns_multiple_active_schedules(): void
    {
        SubscriptionCommunicationSchedule::create($this->scheduleAttributes([
            'subscription_communication_id' => $this->communication->id,
            'name'       => '90 day reminder',
            'offset_days' => -90,
            'is_active'  => true,
            'sort_order' => 1,
        ]));

        SubscriptionCommunicationSchedule::create($this->scheduleAttributes([
            'subscription_communication_id' => $this->communication->id,
            'name'       => '30 day reminder',
            'offset_days' => -30,
            'is_active'  => true,
            'sort_order' => 2,
        ]));

        SubscriptionCommunicationSchedule::create($this->scheduleAttributes([
            'subscription_communication_id' => $this->communication->id,
            'name'       => '7 day reminder',
            'offset_days' => -7,
            'is_active'  => false,
            'sort_order' => 3,
        ]));

        $result = $this->repository->findActiveForCommunication($this->communication->id);

        $this->assertCount(2, $result);
    }

    public function test_find_active_for_communication_ordered_by_sort_order(): void
    {
        SubscriptionCommunicationSchedule::create($this->scheduleAttributes([
            'subscription_communication_id' => $this->communication->id,
            'name'       => 'Second',
            'offset_days' => -7,
            'is_active'  => true,
            'sort_order' => 2,
        ]));

        SubscriptionCommunicationSchedule::create($this->scheduleAttributes([
            'subscription_communication_id' => $this->communication->id,
            'name'       => 'First',
            'offset_days' => -30,
            'is_active'  => true,
            'sort_order' => 1,
        ]));

        $result = $this->repository->findActiveForCommunication($this->communication->id);

        $this->assertEquals('First', $result->first()->name);
    }

    public function test_find_active_for_communication_returns_empty_when_none_exist(): void
    {
        $result = $this->repository->findActiveForCommunication($this->communication->id);

        $this->assertCount(0, $result);
    }

    public function test_find_active_for_communication_only_returns_schedules_for_given_communication(): void
    {
        $otherComm = SubscriptionCommunication::create([
            'key'       => 'other_comm_' . uniqid(),
            'name'      => 'Other Communication',
            'type'      => 'acknowledgement',
            'template'  => \App\Mail\Subscriptions\AcknowledgementMail::class,
            'channels'  => ['email'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        SubscriptionCommunicationSchedule::create($this->scheduleAttributes([
            'subscription_communication_id' => $otherComm->id,
            'name'      => 'Other comm schedule',
            'is_active' => true,
        ]));

        $result = $this->repository->findActiveForCommunication($this->communication->id);

        $this->assertCount(0, $result);
    }

    private function scheduleAttributes(array $overrides = []): array
    {
        return array_merge([
            'subscription_communication_id' => $this->communication->id,
            'name'         => 'Schedule ' . uniqid(),
            'trigger_type' => 'relative',
            'offset_days'  => -30,
            'relative_to'  => 'renewal_date',
            'is_active'    => true,
            'sort_order'   => 0,
        ], $overrides);
    }
}