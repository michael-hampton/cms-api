<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\CommunicationTypeEnum;
use App\Models\Segment;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SubscriptionCommunicationRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriptionCommunicationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionCommunicationRepository();
    }

    public function test_find_active_for_segment_returns_global_communications(): void
    {
        SubscriptionCommunication::create($this->commAttributes([
            'key'        => 'global_renewal',
            'segment_id' => null,
            'is_active'  => true,
        ]));

        $result = $this->repository->findActiveForSegment(null);

        $this->assertCount(1, $result);
        $this->assertNull($result->first()->segment_id);
    }

    public function test_find_active_for_segment_returns_segment_specific_communications(): void
    {
        $segment = $this->createSegment();

        SubscriptionCommunication::create($this->commAttributes([
            'key'        => 'segment_comm',
            'segment_id' => $segment->id,
            'is_active'  => true,
        ]));

        $result = $this->repository->findActiveForSegment($segment->id);

        $this->assertCount(1, $result);
        $this->assertEquals($segment->id, $result->first()->segment_id);
    }

    public function test_find_active_for_segment_returns_both_global_and_segment_communications(): void
    {
        $segment = $this->createSegment();

        SubscriptionCommunication::create($this->commAttributes([
            'key'        => 'global_comm',
            'segment_id' => null,
            'is_active'  => true,
        ]));

        SubscriptionCommunication::create($this->commAttributes([
            'key'        => 'segment_comm',
            'segment_id' => $segment->id,
            'is_active'  => true,
        ]));

        $result = $this->repository->findActiveForSegment($segment->id);

        $this->assertCount(2, $result);
    }

    public function test_find_active_for_segment_excludes_inactive_communications(): void
    {
        SubscriptionCommunication::create($this->commAttributes([
            'key'       => 'inactive_comm',
            'is_active' => false,
        ]));

        $result = $this->repository->findActiveForSegment(null);

        $this->assertCount(0, $result);
    }

    public function test_find_active_for_segment_excludes_other_segments_communications(): void
    {
        $segment      = $this->createSegment();
        $otherSegment = $this->createSegment();

        SubscriptionCommunication::create($this->commAttributes([
            'key'        => 'other_segment_comm',
            'segment_id' => $otherSegment->id,
            'is_active'  => true,
        ]));

        $result = $this->repository->findActiveForSegment($segment->id);

        $this->assertCount(0, $result);
    }

    public function test_find_active_for_segment_ordered_by_sort_order(): void
    {
        SubscriptionCommunication::create($this->commAttributes([
            'key'        => 'second_comm',
            'segment_id' => null,
            'is_active'  => true,
            'sort_order' => 2,
        ]));

        SubscriptionCommunication::create($this->commAttributes([
            'key'        => 'first_comm',
            'segment_id' => null,
            'is_active'  => true,
            'sort_order' => 1,
        ]));

        $result = $this->repository->findActiveForSegment(null);

        $this->assertEquals('first_comm', $result->first()->key);
    }

    public function test_find_active_by_type_returns_matching_communications(): void
    {
        SubscriptionCommunication::create($this->commAttributes([
            'key'       => 'renewal_comm',
            'type'      => CommunicationTypeEnum::RENEWAL_REMINDER->value,
            'is_active' => true,
        ]));

        SubscriptionCommunication::create($this->commAttributes([
            'key'       => 'ack_comm',
            'type'      => CommunicationTypeEnum::ACKNOWLEDGEMENT->value,
            'is_active' => true,
        ]));

        $result = $this->repository->findActiveByType(CommunicationTypeEnum::RENEWAL_REMINDER);
        $this->assertCount(1, $result);
        $this->assertEquals(CommunicationTypeEnum::RENEWAL_REMINDER->value, $result->first()->type);
    }

    public function test_find_active_by_type_excludes_inactive(): void
    {
        SubscriptionCommunication::create($this->commAttributes([
            'key'       => 'inactive_renewal',
            'type'      => CommunicationTypeEnum::RENEWAL_REMINDER->value,
            'is_active' => false,
        ]));

        $result = $this->repository->findActiveByType(CommunicationTypeEnum::RENEWAL_REMINDER);

        $this->assertCount(0, $result);
    }

    public function test_find_with_schedules_returns_communication_with_loaded_schedules(): void
    {
        $comm = SubscriptionCommunication::create($this->commAttributes([
            'key'       => 'with_schedules',
            'is_active' => true,
        ]));

        SubscriptionCommunicationSchedule::create([
            'name'          => '30 day reminder',
            'trigger_type'  => 'relative',
            'offset_days'   => -30,
            'relative_to'   => 'renewal_date',
            'is_active'     => true,
            'sort_order'    => 1,
            'subscription_communication_id' => $comm->id,
        ]);

        $result = $this->repository->findWithSchedules($comm->id);

        $this->assertNotNull($result);
        $this->assertEquals($comm->id, $result->id);
        $this->assertCount(1, $result->schedules);
    }

    public function test_find_with_schedules_only_loads_active_schedules(): void
    {
        $comm = SubscriptionCommunication::create($this->commAttributes([
            'key' => 'schedules_filter',
        ]));

        SubscriptionCommunicationSchedule::create([
            'name'         => 'Active schedule',
            'trigger_type' => 'relative',
            'offset_days'  => -30,
            'relative_to'  => 'renewal_date',
            'is_active'    => true,
            'sort_order'   => 1,
            'subscription_communication_id' => $comm->id,
        ]);

        SubscriptionCommunicationSchedule::create([
            'name'         => 'Inactive schedule',
            'trigger_type' => 'relative',
            'offset_days'  => -7,
            'relative_to'  => 'renewal_date',
            'is_active'    => false,
            'sort_order'   => 2,
            'subscription_communication_id' => $comm->id,
        ]);

        $result = $this->repository->findWithSchedules($comm->id);

        $this->assertCount(1, $result->schedules);
        $this->assertTrue($result->schedules->first()->is_active);
    }

    public function test_find_with_schedules_returns_null_for_unknown_id(): void
    {
        $result = $this->repository->findWithSchedules(99999);

        $this->assertNull($result);
    }

    public function test_find_with_schedules_orders_schedules_by_sort_order(): void
    {
        $comm = SubscriptionCommunication::create($this->commAttributes([
            'key' => 'schedule_order',
        ]));

        SubscriptionCommunicationSchedule::create([
            'name' => 'Second', 'trigger_type' => 'relative',
            'offset_days' => -7, 'relative_to' => 'renewal_date',
            'is_active' => true, 'sort_order' => 2,
            'subscription_communication_id' => $comm->id,
        ]);

        SubscriptionCommunicationSchedule::create([
            'name' => 'First', 'trigger_type' => 'relative',
            'offset_days' => -30, 'relative_to' => 'renewal_date',
            'is_active' => true, 'sort_order' => 1,
            'subscription_communication_id' => $comm->id,
        ]);

        $result = $this->repository->findWithSchedules($comm->id);

        $this->assertEquals('First', $result->schedules->first()->name);
    }

    private function commAttributes(array $overrides = []): array
    {
        return array_merge([
            'key'        => 'comm_' . uniqid(),
            'name'       => 'Test Communication ' . uniqid(),
            'type'       => CommunicationTypeEnum::RENEWAL_REMINDER->value,
            'template'   => \App\Mail\Subscriptions\RenewalReminderMail::class,
            'channels'   => ['email'],
            'is_active'  => true,
            'sort_order' => 0,
        ], $overrides);
    }

    private function createSegment(): Segment
    {
        return Segment::create([
            'key'          => 'seg_' . uniqid(),
            'name'         => 'Test Segment ' . uniqid(),
            'subject_type' => 'subscription',
            'is_active'    => true,
        ]);
    }
}