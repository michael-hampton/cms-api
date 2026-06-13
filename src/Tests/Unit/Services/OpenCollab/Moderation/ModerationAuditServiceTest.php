<?php

namespace App\Tests\Unit\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\ModerationActionType;
use App\Models\ModerationAction;
use App\Repositories\OpenCollab\ModerationActionRepository;
use App\Services\OpenCollab\Moderation\ModerationAuditService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ModerationAuditServiceTest extends TestCase
{
    private ModerationActionRepository $actionRepository;
    private ModerationAuditService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actionRepository = Mockery::mock(ModerationActionRepository::class);
        $this->service = new ModerationAuditService($this->actionRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_record_writes_full_attribute_set(): void
    {
        $action = Mockery::mock(ModerationAction::class)->makePartial();

        $this->actionRepository->shouldReceive('create')
            ->once()
            ->with([
                'site_id' => 1,
                'queue_entry_id' => 10,
                'page_id' => 5,
                'page_version_id' => null,
                'actor_user_id' => 99,
                'action' => ModerationActionType::Approved->value,
                'reason_code' => null,
                'notes' => null,
                'metadata' => null,
            ])
            ->andReturn($action);

        $result = $this->service->record(
            siteId: 1,
            pageId: 5,
            actorUserId: 99,
            action: ModerationActionType::Approved,
            queueEntryId: 10,
        );

        $this->assertSame($action, $result);
    }

    public function test_record_with_reason_notes_and_metadata(): void
    {
        $action = Mockery::mock(ModerationAction::class)->makePartial();

        $this->actionRepository->shouldReceive('create')
            ->once()
            ->with([
                'site_id' => 1,
                'queue_entry_id' => null,
                'page_id' => 5,
                'page_version_id' => null,
                'actor_user_id' => 99,
                'action' => ModerationActionType::Rejected->value,
                'reason_code' => 'quality',
                'notes' => 'Needs sourcing.',
                'metadata' => ['foo' => 'bar'],
            ])
            ->andReturn($action);

        $result = $this->service->record(
            siteId: 1,
            pageId: 5,
            actorUserId: 99,
            action: ModerationActionType::Rejected,
            reasonCode: 'quality',
            notes: 'Needs sourcing.',
            metadata: ['foo' => 'bar'],
        );

        $this->assertSame($action, $result);
    }

    public function test_record_with_empty_metadata_array_passes_null(): void
    {
        $action = Mockery::mock(ModerationAction::class)->makePartial();

        $this->actionRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn(array $attrs) => $attrs['metadata'] === null)
            ->andReturn($action);

        $this->service->record(
            siteId: 1,
            pageId: 5,
            actorUserId: 99,
            action: ModerationActionType::Claimed,
            metadata: [],
        );

        $this->assertTrue(true);
    }
}