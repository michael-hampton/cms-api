<?php

namespace App\Tests\Unit\Actions\Brief;

use App\Actions\Brief\DuplicateBrief;
use App\Actions\Brief\LogBriefActivity;
use App\DTO\Briefs\DuplicateBriefOptions;
use App\Enums\BriefStatus;
use App\Framework\Database\Database;
use App\Models\Brief;
use App\Models\BriefCollaborator;
use App\Models\BriefComment;
use App\Models\BriefDeadline;
use App\Models\BriefRelationship;
use App\Models\BriefTask;
use App\Repositories\Cms\Briefs\BriefCollaboratorRepository;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\Briefs\BriefTaskRepository;
use Mockery;
use PHPUnit\Framework\TestCase;

class DuplicateBriefTest extends TestCase
{
    private DuplicateBrief $action;
    private BriefRepository $briefRepository;
    private BriefTaskRepository $subtaskRepository;
    private BriefCollaboratorRepository $collaboratorRepository;
    private LogBriefActivity $logBriefActivity;
    private Database $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->briefRepository = Mockery::mock(BriefRepository::class);
        $this->subtaskRepository = Mockery::mock(BriefTaskRepository::class);
        $this->collaboratorRepository = Mockery::mock(BriefCollaboratorRepository::class);
        $this->logBriefActivity = Mockery::mock(LogBriefActivity::class);
        $this->database = Mockery::mock(Database::class);

        $this->action = new DuplicateBrief(
            $this->briefRepository,
            $this->subtaskRepository,
            $this->collaboratorRepository,
            $this->logBriefActivity,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_clones_brief_with_attachments_and_subtasks(): void
    {
        $original = Mockery::mock(Brief::class)->makePartial();
        $original->id = 1;
        $original->site_id = 1;
        $original->title = 'Original';
        $original->owner_id = 5;
        $original->status = 'ready';
        $original->attachments = collect([]);
        $original->collaborators = collect([]);
        $original->comments = collect([]);
        $original->relationships = collect([]);
        $original->deadlines = collect([]);
        $original->description = 'Some description';
        $original->category_id = null;
        $original->target_word_count = null;
        $original->seo_keywords = null;
        $original->target_audience = null;
        $original->template_id = null;

        $subtask = Mockery::mock(BriefTask::class)->makePartial();
        $subtask->id = 1;
        $subtask->brief_id = 1;
        $subtask->title = 'Do something';
        $subtask->description = null;
        $subtask->status = 'pending';
        $subtask->assigned_to = null;
        $subtask->due_date = null;
        $subtask->sort_order = 0;

        $newBrief = Mockery::mock(Brief::class)->makePartial();
        $newBrief->id = 2;

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->briefRepository
            ->shouldReceive('getWithRelations')
            ->with(1)
            ->andReturn($original);

        $this->briefRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($d) => $d['title'] === 'Original (Copy)'
                && $d['status'] === BriefStatus::DRAFT->value
                && !isset($d['id'])
                && !isset($d['converted_page_id'])
            ))
            ->andReturn($newBrief);

        $this->subtaskRepository
            ->shouldReceive('getForBrief')
            ->once()
            ->with(1)
            ->andReturn(collect([$subtask]));

        $this->subtaskRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($d) => $d['brief_id'] === 2
                && $d['title'] === 'Do something'
                && !isset($d['id'])
            ));

        $this->briefRepository
            ->shouldReceive('getWithRelations')
            ->with(2)
            ->andReturn($newBrief);

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once();

        $result = $this->action->handle(1, 5, null, null);

        $this->assertSame($newBrief, $result);
    }

    public function test_skips_subtasks_when_include_subtasks_is_false(): void
    {
        $original = Mockery::mock(Brief::class)->makePartial();
        $original->id = 1;
        $original->site_id = 1;
        $original->title = 'Original';
        $original->owner_id = 5;
        $original->status = 'draft';
        $original->attachments = collect([]);
        $original->collaborators = collect([]);
        $original->comments = collect([]);
        $original->relationships = collect([]);
        $original->deadlines = collect([]);
        $original->description = null;
        $original->category_id = null;
        $original->target_word_count = null;
        $original->seo_keywords = null;
        $original->target_audience = null;
        $original->template_id = null;

        $newBrief = Mockery::mock(Brief::class)->makePartial();
        $newBrief->id = 2;

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->briefRepository
            ->shouldReceive('getWithRelations')
            ->andReturn($original, $newBrief);

        $this->briefRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newBrief);

        $this->subtaskRepository->shouldNotReceive('getForBrief');
        $this->subtaskRepository->shouldNotReceive('create');

        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->action->handle(1, 5, null, new DuplicateBriefOptions(includeSubtasks: false));

        $this->assertTrue(true);
    }

    public function test_clones_core_brief_fields_and_resets_status_to_draft(): void
    {
        $original = $this->makeOriginalBrief();
        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->expectGetWithRelations(1, $original, 2, $newBrief);

        $this->briefRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['title'] === 'Original (Copy)'
                    && $data['status'] === BriefStatus::DRAFT->value
                    && $data['owner_id'] === 5
                    && !isset($data['id'])
                    && !isset($data['converted_page_id']);
            }))
            ->andReturn($newBrief);

        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $result = $this->action->handle(1, 5);

        $this->assertSame($newBrief, $result);
    }

    public function test_uses_title_override_when_provided(): void
    {
        $original = $this->makeOriginalBrief();
        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->expectGetWithRelations(1, $original, 2, $newBrief);

        $this->briefRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($d) => $d['title'] === 'My Custom Title'))
            ->andReturn($newBrief);

        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->action->handle(1, 5, 'My Custom Title');

        $this->assertTrue(true);
    }

    public function test_throws_when_source_brief_not_found(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Brief not found: 99');

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->briefRepository
            ->shouldReceive('getWithRelations')
            ->with(99)
            ->andReturn(null);

        $this->action->handle(99, 5);
    }

    public function test_wraps_all_writes_in_a_transaction(): void
    {
        $original = $this->makeOriginalBrief();
        $newBrief = $this->makeNewBrief();

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($original, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->action->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_logs_cloned_activity_on_new_brief(): void
    {
        $original = $this->makeOriginalBrief();
        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($original, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with(2, 5, 'cloned', 'Cloned from brief #1');

        $this->action->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_clones_attachments(): void
    {
        $original = $this->makeOriginalBrief();

        $attachment = Mockery::mock()->makePartial();
        $attachment->type = 'image';
        $attachment->file_url = 'https://example.com/img.jpg';
        $attachment->file_name = 'img.jpg';
        $attachment->image_id = 42;
        $attachment->product_id = null;
        $attachment->url = null;
        $attachment->metadata = [];
        $attachment->sort_order = 0;

        $original->attachments = collect([$attachment]);

        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->expectGetWithRelations(1, $original, 2, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->briefRepository
            ->shouldReceive('addAttachment')
            ->once()
            ->with(2, Mockery::on(fn($d) => $d['type'] === 'image'
                && $d['image_id'] === 42
                && !isset($d['id'])
            ));

        $this->action->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_clones_subtasks_by_default(): void
    {
        $original = $this->makeOriginalBrief();
        $newBrief = $this->makeNewBrief();

        $subtask = Mockery::mock(BriefTask::class)->makePartial();
        $subtask->title = 'Do something';
        $subtask->description = null;
        $subtask->status = 'pending';
        $subtask->assigned_to = null;
        $subtask->due_date = null;
        $subtask->sort_order = 0;

        $this->expectTransaction();
        $this->expectGetWithRelations(1, $original, 2, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->subtaskRepository
            ->shouldReceive('getForBrief')
            ->with(1)
            ->andReturn(collect([$subtask]));

        $this->subtaskRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($d) => $d['brief_id'] === 2
                && $d['title'] === 'Do something'
                && !isset($d['id'])
            ));

        $this->action->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_skips_subtasks_when_option_disabled(): void
    {
        $original = $this->makeOriginalBrief();
        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($original, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->subtaskRepository->shouldNotReceive('getForBrief');
        $this->subtaskRepository->shouldNotReceive('create');

        $options = new DuplicateBriefOptions(includeSubtasks: false);
        $this->action->handle(1, 5, null, $options);

        $this->assertTrue(true);
    }

    public function test_clones_collaborators_with_reset_assigned_meta(): void
    {
        $original = $this->makeOriginalBrief();

        $collaborator = Mockery::mock(BriefCollaborator::class)->makePartial();
        $collaborator->user_id = 99;
        $collaborator->role = 'reviewer';

        $original->collaborators = collect([$collaborator]);

        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->expectGetWithRelations(1, $original, 2, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->collaboratorRepository
            ->shouldReceive('createForBrief')
            ->once()
            ->with(2, Mockery::on(fn($d) => $d['user_id'] === 99
                && $d['role'] === 'reviewer'
                && $d['assigned_by'] === 5       // acting user, not original
                && isset($d['assigned_at'])       // reset to now
            ));

        $this->action->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_skips_collaborators_when_option_disabled(): void
    {
        $original = $this->makeOriginalBrief();
        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($original, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->collaboratorRepository->shouldNotReceive('createForBrief');

        $options = new DuplicateBriefOptions(includeCollaborators: false);
        $this->action->handle(1, 5, null, $options);

        $this->assertTrue(true);
    }

    public function test_clones_top_level_comments_as_unresolved(): void
    {
        $original = $this->makeOriginalBrief();

        $comment = Mockery::mock(BriefComment::class)->makePartial();
        $comment->id = 10;
        $comment->user_id = 7;
        $comment->content = 'Great point';
        $comment->parent_comment_id = null;
        $comment->highlighted_text = null;
        $comment->highlighted_range = null;
        $comment->mentions = [];
        $comment->is_resolved = true;   // resolved on original — must reset
        $comment->resolved_by = 3;
        $comment->resolved_at = '2024-01-01 00:00:00';

        $original->comments = collect([$comment]);

        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->expectGetWithRelations(1, $original, 2, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $newComment = Mockery::mock(BriefComment::class)->makePartial();
        $newComment->id = 20;

        $this->briefRepository
            ->shouldReceive('addComment')
            ->once()
            ->with(2, Mockery::on(fn($d) => $d['user_id'] === 7
                && $d['content'] === 'Great point'
                && $d['is_resolved'] === false
                && $d['resolved_by'] === null
                && $d['resolved_at'] === null
                && $d['parent_comment_id'] === null
            ))
            ->andReturn($newComment);

        $this->action->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_clones_replies_reparented_to_new_top_level_comment(): void
    {
        $original = $this->makeOriginalBrief();

        $topComment = Mockery::mock(BriefComment::class)->makePartial();
        $topComment->id = 10;
        $topComment->user_id = 7;
        $topComment->content = 'Parent';
        $topComment->parent_comment_id = null;
        $topComment->highlighted_text = null;
        $topComment->highlighted_range = null;
        $topComment->mentions = [];
        $topComment->is_resolved = false;
        $topComment->resolved_by = null;
        $topComment->resolved_at = null;

        $reply = Mockery::mock(BriefComment::class)->makePartial();
        $reply->id = 11;
        $reply->user_id = 8;
        $reply->content = 'Reply';
        $reply->parent_comment_id = 10;   // points to original top comment
        $reply->highlighted_text = null;
        $reply->highlighted_range = null;
        $reply->mentions = [];
        $reply->is_resolved = false;
        $reply->resolved_by = null;
        $reply->resolved_at = null;

        $original->comments = collect([$topComment, $reply]);

        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->expectGetWithRelations(1, $original, 2, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $newTopComment = Mockery::mock(BriefComment::class)->makePartial();
        $newTopComment->id = 20;

        // Top-level first
        $this->briefRepository
            ->shouldReceive('addComment')
            ->once()
            ->with(2, Mockery::on(fn($d) => $d['parent_comment_id'] === null && $d['content'] === 'Parent'))
            ->andReturn($newTopComment);

        // Reply re-parented to new ID 20
        $this->briefRepository
            ->shouldReceive('addComment')
            ->once()
            ->with(2, Mockery::on(fn($d) => $d['parent_comment_id'] === 20
                && $d['content'] === 'Reply'
                && $d['is_resolved'] === false
            ));

        $this->action->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_skips_orphaned_replies_when_parent_not_in_loaded_set(): void
    {
        $original = $this->makeOriginalBrief();

        // A reply whose parent_comment_id was not in the loaded comments collection
        $orphanReply = Mockery::mock(BriefComment::class)->makePartial();
        $orphanReply->id = 11;
        $orphanReply->user_id = 8;
        $orphanReply->content = 'Orphan reply';
        $orphanReply->parent_comment_id = 999; // parent not present
        $orphanReply->highlighted_text = null;
        $orphanReply->highlighted_range = null;
        $orphanReply->mentions = [];
        $orphanReply->is_resolved = false;
        $orphanReply->resolved_by = null;
        $orphanReply->resolved_at = null;

        $original->comments = collect([$orphanReply]);

        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($original, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        // addComment must not be called for the orphan
        $this->briefRepository->shouldNotReceive('addComment');

        $this->action->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_skips_comments_when_option_disabled(): void
    {
        $original = $this->makeOriginalBrief();
        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($original, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->briefRepository->shouldNotReceive('addComment');

        $options = new DuplicateBriefOptions(includeComments: false);
        $this->action->handle(1, 5, null, $options);

        $this->assertTrue(true);
    }

    public function test_clones_relationships(): void
    {
        $original = $this->makeOriginalBrief();

        $relationship = Mockery::mock(BriefRelationship::class)->makePartial();
        $relationship->related_brief_id = 55;
        $relationship->related_page_id = null;
        $relationship->relationship_type = 'related';
        $relationship->sort_order = 1;

        $original->relationships = collect([$relationship]);

        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->expectGetWithRelations(1, $original, 2, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->briefRepository
            ->shouldReceive('addRelationship')
            ->once()
            ->with(2, Mockery::on(fn($d) => $d['related_brief_id'] === 55
                && $d['relationship_type'] === 'related'
            ));

        $this->action->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_skips_relationships_when_option_disabled(): void
    {
        $original = $this->makeOriginalBrief();
        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($original, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->briefRepository->shouldNotReceive('addRelationship');

        $options = new DuplicateBriefOptions(includeRelationships: false);
        $this->action->handle(1, 5, null, $options);

        $this->assertTrue(true);
    }

    public function test_clones_deadlines_verbatim_with_actor_as_creator(): void
    {
        $original = $this->makeOriginalBrief();

        $deadline = Mockery::mock(BriefDeadline::class)->makePartial();
        $deadline->due_date = '2025-06-01 00:00:00';
        $deadline->reminder_days = [3, 7];
        $deadline->notify_collaborators = true;
        $deadline->created_by = 3; // original creator — must be replaced

        $original->deadlines = collect([$deadline]);

        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->expectGetWithRelations(1, $original, 2, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->briefRepository
            ->shouldReceive('addDeadline')
            ->once()
            ->with(2, Mockery::on(fn($d) => $d['due_date'] === '2025-06-01 00:00:00'
                && $d['reminder_days'] === [3, 7]
                && $d['notify_collaborators'] === true
                && $d['created_by'] === 5        // acting user, not original creator
            ));

        $this->action->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_skips_deadlines_when_option_disabled(): void
    {
        $original = $this->makeOriginalBrief();
        $newBrief = $this->makeNewBrief();

        $this->expectTransaction();
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($original, $newBrief);
        $this->briefRepository->shouldReceive('create')->andReturn($newBrief);
        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->briefRepository->shouldNotReceive('addDeadline');

        $options = new DuplicateBriefOptions(includeDeadlines: false);
        $this->action->handle(1, 5, null, $options);

        $this->assertTrue(true);
    }

    public function test_options_all_enables_every_flag(): void
    {
        $options = DuplicateBriefOptions::all();

        $this->assertTrue($options->includeSubtasks);
        $this->assertTrue($options->includeCollaborators);
        $this->assertTrue($options->includeComments);
        $this->assertTrue($options->includeRelationships);
        $this->assertTrue($options->includeDeadlines);
    }

    public function test_options_core_only_disables_every_flag(): void
    {
        $options = DuplicateBriefOptions::coreOnly();

        $this->assertFalse($options->includeSubtasks);
        $this->assertFalse($options->includeCollaborators);
        $this->assertFalse($options->includeComments);
        $this->assertFalse($options->includeRelationships);
        $this->assertFalse($options->includeDeadlines);
    }

    private function makeOriginalBrief(): Brief
    {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = 1;
        $brief->site_id = 10;
        $brief->title = 'Original';
        $brief->owner_id = 5;
        $brief->status = 'ready';
        $brief->description = 'Some description';
        $brief->category_id = null;
        $brief->target_word_count = null;
        $brief->seo_keywords = null;
        $brief->target_audience = null;
        $brief->template_id = null;
        $brief->attachments = collect([]);
        $brief->collaborators = collect([]);
        $brief->comments = collect([]);
        $brief->relationships = collect([]);
        $brief->deadlines = collect([]);
        return $brief;
    }

    private function makeNewBrief(int $id = 2): Brief
    {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = $id;
        return $brief;
    }

    private function expectTransaction(): void
    {
        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());
    }

    private function expectGetWithRelations(int $sourceId, Brief $original, int $newId, Brief $newBrief): void
    {
        $this->briefRepository
            ->shouldReceive('getWithRelations')
            ->with($sourceId)
            ->andReturn($original);

        $this->briefRepository
            ->shouldReceive('getWithRelations')
            ->with($newId)
            ->andReturn($newBrief);
    }
}