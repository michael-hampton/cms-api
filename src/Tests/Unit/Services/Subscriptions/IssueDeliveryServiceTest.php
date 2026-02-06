<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Database\Database;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Services\Subscriptions\IssueDeliveryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class IssueDeliveryServiceTest extends FunctionalTestCase
{
    private $scheduleRepository;
    private $service;
    private $databaseMock;

    public function testActivateSchedule(): void
    {
        $scheduleId = 1;
        $schedule = Mockery::mock(IssueDelivery::class);

        $this->scheduleRepository->shouldReceive('update')
            ->with($scheduleId, ['status' => IssueScheduleStatus::ACTIVE->value])
            ->once()
            ->andReturn($schedule);

        $result = $this->service->activateSchedule($scheduleId);

        $this->assertSame($schedule, $result);
    }

    public function testCancelSchedule(): void
    {
        $scheduleId = 1;
        $schedule = Mockery::mock(IssueDelivery::class);

        $this->scheduleRepository->shouldReceive('update')
            ->with($scheduleId, ['status' => IssueScheduleStatus::CANCELLED->value])
            ->once()
            ->andReturn($schedule);

        $result = $this->service->cancelSchedule($scheduleId);

        $this->assertSame($schedule, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduleRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->service = new IssueDeliveryService($this->scheduleRepository, $this->databaseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}