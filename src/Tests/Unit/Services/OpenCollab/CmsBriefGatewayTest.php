<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Actions\Brief\LogBriefActivity;
use App\Models\Brief;
use App\Models\BriefComment;
use App\Models\BriefTask;
use App\Models\Collaborator;
use App\Repositories\Cms\Briefs\BriefCollaboratorRepository;
use App\Repositories\OpenCollab\ContributorBriefRepository;
use App\Services\Cms\BriefService;
use App\Services\OpenCollab\CmsBriefGateway;
use App\Services\OpenCollab\OpenCollabBriefNotificationService;
use Mockery;
use PHPUnit\Framework\TestCase;

class CmsBriefGatewayTest extends TestCase
{
    private BriefService $briefService;
    private BriefCollaboratorRepository $collaborators;
    private ContributorBriefRepository $briefs;
    private LogBriefActivity $activity;
    private OpenCollabBriefNotificationService $notifications;
    private CmsBriefGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->briefService = Mockery::mock(BriefService::class);
        $this->collaborators = Mockery::mock(BriefCollaboratorRepository::class);
        $this->briefs = Mockery::mock(ContributorBriefRepository::class);
        $this->activity = Mockery::mock(LogBriefActivity::class);
        $this->notifications = Mockery::mock(OpenCollabBriefNotificationService::class);
        $this->gateway = new CmsBriefGateway(
            $this->briefService,
            $this->collaborators,
            $this->briefs,
            $this->activity,
            $this->notifications,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_accept_assignment_updates_collaborator_logs_activity_and_notifies(): void
    {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = 10;
        $brief->title = 'Camera guide';

        $assignment = Mockery::mock(Collaborator::class)->makePartial();
        $assignment->id = 20;
        $assignment->role = 'pending';


        $this->collaborators->shouldReceive('update')
            ->once()
            ->with(20, Mockery::on(fn(array $data) => $data['role'] === 'writer' && isset($data['assigned_at'])));
        $this->activity->shouldReceive('handle')
            ->once()
            ->with(10, 5, 'assignment_accepted', 'Assignment accepted');
        $this->notifications->shouldReceive('notifyContributor')
            ->once()
            ->with(5, $brief, 'brief_assignment_accepted', 'Assignment accepted', 'You accepted Camera guide.');

        $this->gateway->acceptAssignment($brief, $assignment, 5);

        $this->addToAssertionCount(1);
    }

    public function test_update_task_uses_repository_scope_before_cms_update(): void
    {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = 10;
        $brief->title = 'Camera guide';

        $task = Mockery::mock(BriefTask::class)->makePartial();
        $task->id = 30;
        $task->brief_id = 10;
        $task->title = 'Draft copy';
        $task->assigned_to = 5;

        $updated = Mockery::mock(BriefTask::class)->makePartial();
        $updated->id = 30;
        $updated->status = 'completed';

        $this->briefs->shouldReceive('findTaskForContributor')
            ->once()
            ->with(10, 30, 5)
            ->andReturn($task);

        $this->briefService->shouldReceive('updateTask')
            ->once()
            ->with(30, ['status' => 'completed'])
            ->andReturn($updated);

        $this->activity->shouldReceive('handle')
            ->once()
            ->with(10, 5, 'task_updated', 'Task updated: Draft copy');

        $this->assertSame($updated, $this->gateway->updateTask($brief, 30, 5, 'completed'));
    }

    public function test_update_task_throws_when_repository_denies_scope(): void
    {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = 10;

        $this->briefs->shouldReceive('findTaskForContributor')
            ->once()
            ->with(10, 30, 5)
            ->andReturn(null);

        $this->briefService->shouldNotReceive('updateTask');
        $this->activity->shouldNotReceive('handle');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task not found');

        $this->gateway->updateTask($brief, 30, 5, 'completed');
    }

    public function test_update_comment_requires_owned_comment_from_repository(): void
    {
        $comment = Mockery::mock(BriefComment::class)->makePartial();
        $comment->id = 40;
        $comment->brief_id = 10;
        $comment->user_id = 5;
        $comment->content = 'Old';

        $updated = Mockery::mock(BriefComment::class)->makePartial();
        $updated->id = 40;
        $updated->brief_id = 10;
        $updated->content = 'New';

        $this->briefs->shouldReceive('findCommentOwnedByContributor')
            ->once()
            ->with(40, 5)
            ->andReturn($comment);

        $this->briefService->shouldReceive('updateComment')
            ->once()
            ->with(10, 40, ['content' => 'New'])
            ->andReturn($updated);

        $this->activity->shouldNotReceive('handle');

        $this->assertSame($updated, $this->gateway->updateComment(40, 5, 'New'));
    }

    public function test_submit_moves_brief_to_review_and_notifies(): void
    {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = 10;
        $brief->title = 'Camera guide';

        $this->briefService->shouldReceive('updateStatus')
            ->once()
            ->with(10, 'in_review', 5);

        $this->activity->shouldReceive('handle')
            ->once()
            ->with(10, 5, 'submission_notes_added', 'Submission notes: Ready for review');

        $this->notifications->shouldReceive('notifyContributor')
            ->once()
            ->with(
                5,
                $brief,
                'brief_submitted_for_approval',
                'Brief submitted',
                'Camera guide was submitted for review.'
            );

        $this->gateway->submit($brief, 5, 'Ready for review');

        $this->addToAssertionCount(1);
    }
}
