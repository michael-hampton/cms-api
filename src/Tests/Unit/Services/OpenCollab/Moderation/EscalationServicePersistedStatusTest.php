<?php

namespace App\Tests\Unit\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\EscalationStatus;
use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Collection;
use App\Models\ModerationEscalation;
use App\Models\ModerationQueueEntry;
use App\Repositories\OpenCollab\ModerationEscalationRepository;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use App\Services\OpenCollab\Moderation\EscalationRoutingService;
use App\Services\OpenCollab\Moderation\EscalationService;
use App\Services\OpenCollab\Moderation\EscalationSlaService;
use App\Services\OpenCollab\Moderation\ModerationAuditService;
use Mockery;
use PHPUnit\Framework\TestCase;

class EscalationServicePersistedStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_returns_persisted_escalated_queue_entry_to_in_review(): void
    {
        $escalationRepository = Mockery::mock(ModerationEscalationRepository::class);
        $queueRepository = Mockery::mock(ModerationQueueRepository::class);
        $database = Mockery::mock(Database::class);

        $service = new EscalationService(
            $escalationRepository,
            $queueRepository,
            Mockery::mock(EscalationRoutingService::class),
            Mockery::mock(EscalationSlaService::class),
            Mockery::mock(ModerationAuditService::class),
            Mockery::mock(EventDispatcher::class),
            $database,
        );

        $escalation = Mockery::mock(ModerationEscalation::class)->makePartial();
        $escalation->id = 50;
        $escalation->site_id = 1;
        $escalation->page_id = 5;
        $escalation->queue_entry_id = 10;
        $escalation->status = EscalationStatus::Open->value;

        $resolved = Mockery::mock(ModerationEscalation::class)->makePartial();
        $resolved->id = 50;
        $resolved->site_id = 1;
        $resolved->page_id = 5;
        $resolved->queue_entry_id = 10;
        $resolved->status = EscalationStatus::Resolved->value;

        $queueEntry = Mockery::mock(ModerationQueueEntry::class)->makePartial();
        $queueEntry->id = 10;
        $queueEntry->status = ModerationQueueStatus::Escalated->value;

        $escalationRepository->shouldReceive('find')
            ->once()
            ->with(50)
            ->andReturn($escalation);

        $database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $callback) => $callback());

        $escalationRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn(int $id, array $attributes) =>
                $id === 50
                && $attributes['status'] === EscalationStatus::Resolved->value
                && $attributes['resolution'] === 'cleared'
            )
            ->andReturn($resolved);

        $escalationRepository->shouldReceive('openForPage')
            ->once()
            ->with(1, 5)
            ->andReturn(Collection::make([]));

        $queueRepository->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($queueEntry);

        $queueRepository->shouldReceive('update')
            ->once()
            ->with(10, ['status' => ModerationQueueStatus::InReview->value]);

        $result = $service->resolve(50, 99, 1, 'cleared', 'No issue found.');

        $this->assertSame($resolved, $result);
    }
}
