<?php

namespace App\Tests\Unit\Actions\Brief;

use App\Actions\Brief\DuplicateBrief;
use App\Actions\Brief\LogBriefActivity;
use App\Enums\BriefStatus;
use App\Framework\Database\Database;
use App\Models\Brief;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\Briefs\BriefSubtaskRepository;
use App\Repositories\Cms\Briefs\BriefTaskRepository;
use Mockery;
use PHPUnit\Framework\TestCase;

class DuplicateBriefTest extends TestCase
{
    private DuplicateBrief $action;
    private BriefRepository $briefRepository;
    private BriefTaskRepository $subtaskRepository;
    private LogBriefActivity $logBriefActivity;
    private Database $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->briefRepository = Mockery::mock(BriefRepository::class);
        $this->subtaskRepository = Mockery::mock(BriefTaskRepository::class);
        $this->logBriefActivity = Mockery::mock(LogBriefActivity::class);
        $this->database = Mockery::mock(Database::class);

        $this->action = new DuplicateBrief(
            $this->briefRepository,
            $this->subtaskRepository,
            $this->logBriefActivity,
            $this->database
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
        $original->description = 'Some description';
        $original->category_id = null;
        $original->target_word_count = null;
        $original->seo_keywords = null;
        $original->target_audience = null;
        $original->template_id = null;

        $subtask = Mockery::mock(\App\Models\BriefTask::class)->makePartial();
        $subtask->id = 1;
        $subtask->brief_id = 1;
        $subtask->title = 'Do something';
        $subtask->status = 'pending';

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

        $result = $this->action->handle(1, 5, null, true);
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

        $this->subtaskRepository
            ->shouldNotReceive('getForBrief');

        $this->subtaskRepository
            ->shouldNotReceive('create');

        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->action->handle(1, 5, null, false);
        $this->assertTrue(true);
    }

    public function test_uses_title_override_when_provided(): void
    {
        $original = Mockery::mock(Brief::class)->makePartial();
        $original->id = 1;
        $original->site_id = 1;
        $original->title = 'Original';
        $original->owner_id = 5;
        $original->status = 'draft';
        $original->attachments = collect([]);
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
            ->with(Mockery::on(fn($d) => $d['title'] === 'My Custom Title'))
            ->andReturn($newBrief);

        $this->subtaskRepository->shouldReceive('getForBrief')->andReturn(collect([]));
        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->action->handle(1, 5, 'My Custom Title', true);
        $this->assertTrue(true);
    }

    public function test_throws_when_source_brief_not_found(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Brief not found: 99');

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->briefRepository
            ->shouldReceive('getWithRelations')
            ->with(99)
            ->andReturn(null);

        $this->action->handle(99, 5, null, true);
    }
}