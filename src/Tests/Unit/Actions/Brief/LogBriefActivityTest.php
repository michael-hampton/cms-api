<?php

namespace App\Tests\Unit\Actions\Brief;

use App\Actions\Brief\LogBriefActivity;
use App\Models\Brief;
use App\Repositories\Cms\Briefs\BriefActivityLogRepository;
use App\Repositories\Cms\Briefs\BriefRepository;
use Mockery;
use PHPUnit\Framework\TestCase;

class LogBriefActivityTest extends TestCase
{
    private BriefActivityLogRepository $activityLogRepository;
    private BriefRepository $briefRepository;
    private LogBriefActivity $service;

    public function test_log_creates_activity_and_updates_brief(): void
    {
        $briefId = 1;
        $userId = 10;
        $action = 'created';
        $description = 'Brief created';
        $metadata = ['foo' => 'bar'];

        $brief = Mockery::mock(Brief::class);

        $this->activityLogRepository
            ->shouldReceive('create')
            ->once()
            ->with([
                'brief_id' => $briefId,
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata
            ]);

        $this->briefRepository
            ->shouldReceive('updateLastActivity')
            ->once()
            ->with($briefId, $userId)
            ->andReturn($brief);

        $result = $this->service->handle($briefId, $userId, $action, $description, $metadata);

        $this->assertInstanceOf(Brief::class, $result);
    }

    public function test_log_with_empty_metadata(): void
    {
        $brief = Mockery::mock(Brief::class);

        $this->activityLogRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['metadata'] === [];
            }));

        $this->briefRepository
            ->shouldReceive('updateLastActivity')
            ->once()
            ->andReturn($brief);

        $result = $this->service->handle(1, 10, 'updated', 'Brief updated');

        $this->assertInstanceOf(Brief::class, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->activityLogRepository = Mockery::mock(BriefActivityLogRepository::class);
        $this->briefRepository = Mockery::mock(BriefRepository::class);
        $this->service = new LogBriefActivity(
            $this->activityLogRepository,
            $this->briefRepository
        );
    }
}