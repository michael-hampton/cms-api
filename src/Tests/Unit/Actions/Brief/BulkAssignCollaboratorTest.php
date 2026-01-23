<?php

namespace App\Tests\Unit\Actions\Brief;

use App\Actions\Brief\BulkAssignCollaborator;
use App\Actions\Brief\LogBriefActivity;
use App\Models\BriefCollaborator;
use App\Repositories\Cms\Briefs\BriefCollaboratorRepository;
use Mockery;
use PHPUnit\Framework\TestCase;

class BulkAssignCollaboratorTest extends TestCase
{
    private BriefCollaboratorRepository $collaboratorRepository;
    private LogBriefActivity $activityService;
    private BulkAssignCollaborator $service;

    public function test_bulk_assign_creates_new_collaborators(): void
    {
        $briefIds = [1, 2, 3];

        foreach ($briefIds as $briefId) {
            $this->collaboratorRepository
                ->shouldReceive('findByBriefAndUser')
                ->once()
                ->with($briefId, 10)
                ->andReturn(null);

            $this->collaboratorRepository
                ->shouldReceive('create')
                ->once()
                ->with(Mockery::on(function ($data) use ($briefId) {
                    return $data['brief_id'] === $briefId &&
                        $data['user_id'] === 10 &&
                        $data['role'] === 'editor';
                }));

            $this->activityService
                ->shouldReceive('handle')
                ->once()
                ->with($briefId, 10, 'collaborator_added', 'Assigned as editor');
        }

        $count = $this->service->handle($briefIds, 10, 'editor');

        $this->assertEquals(3, $count);
    }

    public function test_bulk_assign_updates_existing_collaborators(): void
    {
        $existing = Mockery::mock(BriefCollaborator::class)->makePartial();
        $existing->id = 99;

        $this->collaboratorRepository
            ->shouldReceive('findByBriefAndUser')
            ->once()
            ->with(1, 10)
            ->andReturn($existing);

        $this->collaboratorRepository
            ->shouldReceive('update')
            ->once()
            ->with(99, ['role' => 'reviewer']);

        $this->activityService
            ->shouldReceive('handle')
            ->once();

        $count = $this->service->handle([1], 10, 'reviewer');

        $this->assertEquals(1, $count);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->collaboratorRepository = Mockery::mock(BriefCollaboratorRepository::class);
        $this->activityService = Mockery::mock(LogBriefActivity::class);

        $this->service = new BulkAssignCollaborator(
            $this->collaboratorRepository,
            $this->activityService
        );
    }
}