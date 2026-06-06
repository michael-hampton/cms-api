<?php

namespace App\Tests\Unit\Services\Cms\Briefs;

use App\Actions\Brief\BulkAssignCollaborator;
use App\Actions\Brief\ConvertBriefToPage;
use App\Actions\Brief\CreateBriefVersion;
use App\Actions\Brief\DuplicateBrief;
use App\Actions\Brief\LogBriefActivity;
use App\DTO\Briefs\BriefPresetSubtask;
use App\Framework\Database\Database;
use App\Models\Brief;
use App\Models\BriefCollaborator;
use App\Models\BriefComment;
use App\Models\BriefTask;
use App\Models\BriefTemplate;
use App\Models\BriefVersion;
use App\Models\Model;
use App\Models\User;
use App\Repositories\Cms\Briefs\BriefCollaboratorRepository;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\Briefs\BriefScheduleRepository;
use App\Repositories\Cms\Briefs\BriefTaskRepository;
use App\Repositories\Cms\Briefs\BriefTemplateRepository;
use App\Repositories\Cms\Briefs\BriefVersionRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use App\Services\Cms\BriefService;
use Exception;
use Mockery;
use PHPUnit\Framework\TestCase;

class BriefServiceTest extends TestCase
{
    private BriefService $service;
    private BriefRepository $briefRepository;
    private BriefTemplateRepository $templateRepository;
    private BriefCollaboratorRepository $collaboratorRepository;
    private BriefTaskRepository $taskRepository;
    private BriefVersionRepository $versionRepository;
    private CreateBriefVersion $createBriefVersion;
    private LogBriefActivity $logBriefActivity;
    private DuplicateBrief $duplicateBrief;
    private BulkAssignCollaborator $bulkAssignCollaborator;
    private ConvertBriefToPage $convertBriefToPage;
    private Database $database;
    private BriefScheduleRepository $briefScheduleRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->briefRepository = Mockery::mock(BriefRepository::class);
        $this->templateRepository = Mockery::mock(BriefTemplateRepository::class);
        $this->collaboratorRepository = Mockery::mock(BriefCollaboratorRepository::class);
        $this->taskRepository = Mockery::mock(BriefTaskRepository::class);
        $this->versionRepository = Mockery::mock(BriefVersionRepository::class);
        $this->createBriefVersion = Mockery::mock(CreateBriefVersion::class);
        $this->logBriefActivity = Mockery::mock(LogBriefActivity::class);
        $this->duplicateBrief = Mockery::mock(DuplicateBrief::class);
        $this->bulkAssignCollaborator = Mockery::mock(BulkAssignCollaborator::class);
        $this->convertBriefToPage = Mockery::mock(ConvertBriefToPage::class);
        $this->database = Mockery::mock(Database::class);
        $this->briefScheduleRepository = Mockery::mock(BriefScheduleRepository::class);

        $this->service = new BriefService(
            $this->briefRepository,
            $this->templateRepository,
            $this->collaboratorRepository,
            $this->taskRepository,
            $this->versionRepository,
            $this->createBriefVersion,
            $this->logBriefActivity,
            $this->duplicateBrief,
            $this->bulkAssignCollaborator,
            $this->convertBriefToPage,
            $this->database,
            $this->briefScheduleRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_can_search_briefs(): void
    {
        $criteria = Mockery::mock(SearchCriteria::class);
        $expectedResult = Mockery::mock(PaginatedResult::class);

        $this->briefRepository
            ->shouldReceive('search')
            ->once()
            ->with($criteria)
            ->andReturn($expectedResult);

        $result = $this->service->search($criteria);

        $this->assertEquals($expectedResult, $result);
    }

    public function test_it_can_get_complete_brief(): void
    {
        $briefId = 1;
        $brief = Mockery::mock(Brief::class);

        $this->briefRepository
            ->shouldReceive('getCompleteBriefData')
            ->once()
            ->with($briefId)
            ->andReturn($brief);

        $result = $this->service->getCompleteBrief($briefId);

        $this->assertSame($brief, $result);
    }

    public function test_it_can_create_brief_with_initial_version_and_activity_log(): void
    {
        $data = [
            'title' => 'Test Brief',
            'owner_id' => 1,
            'site_id' => 1
        ];
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = 1;

        $this->briefRepository
            ->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($brief);

        $this->createBriefVersion
            ->shouldReceive('handle')
            ->once()
            ->with(1, 1, 'Initial version');

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with(1, 1, 'created', 'Brief created');

        $result = $this->service->createBrief($data);

        $this->assertSame($brief, $result);
    }

    public function test_it_can_update_brief_without_versioning_when_no_significant_changes(): void
    {
        $briefId = 1;
        $data = ['description' => 'Same description'];
        $userId = 1;

        $oldBrief = Mockery::mock(Brief::class)->makePartial();
        $oldBrief->description = 'Same description';
        $oldBrief->title = 'Title';

        $updatedBrief = Mockery::mock(Model::class);

        $this->briefRepository
            ->shouldReceive('find')
            ->once()
            ->with($briefId)
            ->andReturn($oldBrief);

        $this->briefRepository
            ->shouldReceive('update')
            ->once()
            ->with($briefId, $data)
            ->andReturn($updatedBrief);

        $this->createBriefVersion
            ->shouldNotReceive('handle');

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $userId, 'updated', 'Brief updated');

        $result = $this->service->updateBrief($briefId, $data, $userId);

        $this->assertSame($updatedBrief, $result);
    }

    public function test_it_can_update_brief_with_versioning_when_title_changes(): void
    {
        $briefId = 1;
        $data = ['title' => 'New Title'];
        $userId = 1;

        $oldBrief = Mockery::mock(Brief::class)->makePartial();
        $oldBrief->title = 'Old Title';

        $updatedBrief = Mockery::mock(Brief::class)->makePartial();

        $this->briefRepository
            ->shouldReceive('find')
            ->once()
            ->with($briefId)
            ->andReturn($oldBrief);

        $this->briefRepository
            ->shouldReceive('update')
            ->once()
            ->with($briefId, $data)
            ->andReturn($updatedBrief);

        $this->createBriefVersion
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $userId, 'Title updated');

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $userId, 'updated', 'Brief updated');

        $result = $this->service->updateBrief($briefId, $data, $userId);

        $this->assertSame($updatedBrief, $result);
    }

    public function test_it_throws_exception_when_updating_non_existent_brief(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Brief not found: 1');

        $this->briefRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->service->updateBrief(1, ['title' => 'Test'], 1);
    }

    public function test_it_can_delete_brief(): void
    {
        $briefId = 1;

        $this->briefRepository
            ->shouldReceive('delete')
            ->once()
            ->with($briefId)
            ->andReturn(true);

        $result = $this->service->deleteBrief($briefId);

        $this->assertTrue($result);
    }

    public function test_it_can_add_attachment(): void
    {
        $briefId = 1;
        $data = ['file_url' => 'path/to/file.pdf'];
        $attachment = Mockery::mock(Model::class);

        $this->briefRepository
            ->shouldReceive('addAttachment')
            ->once()
            ->with($briefId, $data)
            ->andReturn($attachment);

        $result = $this->service->addAttachment($briefId, $data);

        $this->assertSame($attachment, $result);
    }

    public function test_it_can_resolve_comment(): void
    {
        $briefId = 1;
        $commentId = 1;
        $userId = 1;

        $comment = Mockery::mock(BriefComment::class);

        $this->briefRepository
            ->shouldReceive('updateComment')
            ->once()
            ->with($briefId, $commentId, Mockery::on(function ($data) {
                return $data['is_resolved'] === true
                    && $data['resolved_by'] === 1
                    && isset($data['resolved_at']);
            }))
            ->andReturn($comment);

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $userId, 'comment_resolved', 'Resolved a comment');

        $result = $this->service->resolveComment($briefId, $commentId, $userId);

        $this->assertSame($comment, $result);
    }

    public function test_it_can_convert_brief_to_page(): void
    {
        $briefId = 1;
        $conversionData = ['title' => 'Test Page'];
        $expectedResult = ['success' => true, 'page_id' => 1];

        $this->convertBriefToPage
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $conversionData)
            ->andReturn($expectedResult);

        $result = $this->service->convertToPage($briefId, $conversionData);

        $this->assertEquals($expectedResult, $result);
    }

    public function test_it_can_duplicate_brief(): void
    {
        $briefId = 1;
        $userId = 1;
        $newBrief = Mockery::mock(Brief::class);

        $this->duplicateBrief
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $userId)
            ->andReturn($newBrief);

        $result = $this->service->duplicateBrief($briefId, $userId);

        $this->assertSame($newBrief, $result);
    }

    public function test_it_can_update_status_with_versioning(): void
    {
        $briefId = 1;
        $newStatus = 'in_review';
        $userId = 1;

        $oldBrief = Mockery::mock(Brief::class)->makePartial();
        $oldBrief->id = $briefId;
        $oldBrief->site_id = 1;
        $oldBrief->title = 'Test Brief';
        $oldBrief->owner_id = 5;
        $oldBrief->status = 'draft';

        $updatedBrief = Mockery::mock(Brief::class)->makePartial();
        $updatedBrief->id = $briefId;
        $updatedBrief->site_id = 1;
        $updatedBrief->title = 'Test Brief';
        $updatedBrief->owner_id = 5;
        $updatedBrief->status = $newStatus;

        $this->briefRepository
            ->shouldReceive('find')
            ->once()
            ->with($briefId)
            ->andReturn($oldBrief);

        $this->createBriefVersion
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $userId, "Status changed to {$newStatus}");

        $this->briefRepository
            ->shouldReceive('update')
            ->once()
            ->with($briefId, Mockery::on(function ($data) use ($newStatus, $userId) {
                return $data['status'] === $newStatus
                    && isset($data['last_activity_at'])
                    && $data['last_activity_user_id'] === $userId;
            }))
            ->andReturn($updatedBrief);

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $userId, 'status_changed', "Status changed to {$newStatus}");

        $result = $this->service->updateStatus($briefId, $newStatus, $userId);

        $this->assertSame($updatedBrief, $result);
    }

    public function test_it_can_bulk_update_status(): void
    {
        $briefIds = [1, 2, 3];
        $status = 'archived';

        $this->briefRepository
            ->shouldReceive('bulkUpdateStatus')
            ->once()
            ->with($briefIds, $status)
            ->andReturn(3);

        $result = $this->service->bulkUpdateStatus($briefIds, $status);

        $this->assertEquals(1, $result);
    }

    public function test_it_can_bulk_assign_collaborator(): void
    {
        $briefIds = [1, 2, 3];
        $userId = 5;
        $role = 'writer';

        $this->bulkAssignCollaborator
            ->shouldReceive('handle')
            ->once()
            ->with($briefIds, $userId, $role, 1)
            ->andReturn(3);

        $result = $this->service->bulkAssignCollaborator($briefIds, $userId, $role, 1);

        $this->assertEquals(3, $result);
    }

    public function test_it_can_create_from_template(): void
    {
        $templateId = 1;
        $data = ['owner_id' => 1, 'site_id' => 1];

        $template = Mockery::mock(BriefTemplate::class)->makePartial();
        $template->name = 'Article Template';
        $template->default_fields = ['target_word_count' => 1000];

        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = 1;

        $this->templateRepository
            ->shouldReceive('find')
            ->once()
            ->with($templateId)
            ->andReturn($template);

        $this->briefRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($createData) use ($templateId) {
                return $createData['template_id'] === $templateId
                    && $createData['target_word_count'] === 1000;
            }))
            ->andReturn($brief);

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with(1, 1, 'created_from_template', 'Created from template: Article Template');

        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($brief);

        $result = $this->service->createFromTemplate($templateId, $data);

        $this->assertSame($brief, $result);
    }

    public function test_it_throws_exception_when_template_not_found(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Template not found: 1');

        $this->templateRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->service->createFromTemplate(1, ['owner_id' => 1]);
    }

    public function test_it_can_save_brief_as_template(): void
    {
        $briefId = 1;
        $templateData = [
            'name' => 'My Template',
            'description' => 'Template description',
            'created_by' => 1
        ];

        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->site_id = 1;
        $brief->title = 'Brief Title';
        $brief->description = 'Brief Description';
        $brief->target_word_count = 1000;
        $brief->seo_keywords = 'keyword1, keyword2';

        $template = Mockery::mock(Model::class);

        $this->briefRepository
            ->shouldReceive('getCompleteBriefData')
            ->once()
            ->with($briefId)
            ->andReturn($brief);

        $this->templateRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['name'] === 'My Template'
                    && $data['site_id'] === 1
                    && $data['is_system'] === false
                    && isset($data['default_fields']['title']);
            }))
            ->andReturn($template);

        $result = $this->service->saveAsTemplate($briefId, $templateData);

        $this->assertSame($template, $result);
    }

    public function test_it_can_add_collaborator_with_activity_log(): void
    {
        $briefId = 1;
        $userId = 1;
        $data = ['user_id' => 5, 'role' => 'writer'];

        $user = Mockery::mock(User::class)->makePartial();
        $user->name = 'John Doe';

        $collaborator = Mockery::mock(BriefCollaborator::class)->makePartial();
        $collaborator->user = $user;
        $collaborator->role = 'writer';

        $this->collaboratorRepository
            ->shouldReceive('createForBrief')
            ->once()
            ->with(1, Mockery::on(function ($createData) use ($briefId) {
                return $createData['brief_id'] === $briefId
                    && isset($createData['assigned_at']);
            }))
            ->andReturn($collaborator);

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $userId, 'collaborator_added', 'Added collaborator: John Doe as writer');

        $result = $this->service->addCollaborator($briefId, $data, $userId);

        $this->assertSame($collaborator, $result);
    }

    public function test_it_can_update_collaborator(): void
    {
        $briefId = 1;
        $collaboratorId = 1;
        $data = ['role' => 'editor'];

        $collaborator = Mockery::mock(BriefCollaborator::class)->makePartial();
        $collaborator->collaboratable_id = $briefId;

        $updatedCollaborator = Mockery::mock(BriefCollaborator::class);

        $this->collaboratorRepository
            ->shouldReceive('find')
            ->once()
            ->with($collaboratorId)
            ->andReturn($collaborator);

        $this->collaboratorRepository
            ->shouldReceive('update')
            ->once()
            ->with($collaboratorId, $data)
            ->andReturn($updatedCollaborator);

        $result = $this->service->updateCollaborator($briefId, $collaboratorId, $data);

        $this->assertSame($updatedCollaborator, $result);
    }

    public function test_it_throws_exception_when_updating_collaborator_from_different_brief(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Collaborator not found');

        $collaborator = Mockery::mock(BriefCollaborator::class)->makePartial();
        $collaborator->brief_id = 999;

        $this->collaboratorRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($collaborator);

        $this->service->updateCollaborator(1, 1, ['role' => 'editor']);
    }

    public function test_it_can_create_task_with_activity_log(): void
    {
        $briefId = 1;
        $data = ['title' => 'Review content', 'created_by' => 1];

        $task = Mockery::mock(BriefTask::class)->makePartial();
        $task->title = 'Review content';

        $this->taskRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($createData) use ($briefId) {
                return $createData['brief_id'] === $briefId
                    && $createData['title'] === 'Review content';
            }))
            ->andReturn($task);

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, 1, 'task_created', 'Created task: Review content');

        $result = $this->service->createTask($briefId, $data);

        $this->assertSame($task, $result);
    }

    public function test_it_can_update_task_and_mark_as_completed(): void
    {
        $taskId = 1;
        $data = ['status' => 'completed'];

        $task = Mockery::mock(BriefTask::class);

        $this->taskRepository
            ->shouldReceive('update')
            ->once()
            ->with($taskId, Mockery::on(function ($updateData) {
                return $updateData['status'] === 'completed'
                    && isset($updateData['completed_at']);
            }))
            ->andReturn($task);

        $result = $this->service->updateTask($taskId, $data);

        $this->assertSame($task, $result);
    }

    public function test_it_can_restore_version_with_new_version_backup(): void
    {
        $briefId = 1;
        $versionId = 1;
        $userId = 1;

        $version = Mockery::mock(BriefVersion::class)->makePartial();
        $version->brief_id = $briefId;
        $version->version_number = 5;
        $version->title = 'Old Title';
        $version->description = 'Old Description';
        $version->data = ['target_word_count' => 1000];

        $this->versionRepository
            ->shouldReceive('find')
            ->once()
            ->with($versionId)
            ->andReturn($version);

        $this->createBriefVersion
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $userId, 'Before restore');

        $this->briefRepository
            ->shouldReceive('update')
            ->once()
            ->with($briefId, Mockery::on(function ($data) {
                return $data['title'] === 'Old Title'
                    && $data['description'] === 'Old Description'
                    && $data['target_word_count'] === 1000;
            }));

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with($briefId, $userId, 'version_restored', 'Restored version 5');

        $result = $this->service->restoreVersion($briefId, $versionId, $userId);
        $this->assertTrue($result);
    }

    public function test_it_throws_exception_when_restoring_version_from_different_brief(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Version not found');

        $version = Mockery::mock(Brief::class)->makePartial();
        $version->brief_id = 999;

        $this->versionRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($version);

        $this->service->restoreVersion(1, 1, 1);
    }

//    public function test_it_can_set_deadline_creating_new(): void
//    {
//        $briefId = 1;
//        $data = [
//            'due_date' => '2024-12-31',
//            'reminder_days' => [7, 3, 1],
//            'notify_collaborators' => true,
//            'user_id' => 1
//        ];
//
//        // Mock static method - note this requires special setup
//        $deadline = Mockery::mock(Model::class);
//
//        $this->logBriefActivity
//            ->shouldReceive('handle')
//            ->once()
//            ->with($briefId, 1, 'deadline_set', 'Deadline set to 2024-12-31');
//
//        // This test would need actual implementation of BriefDeadline::create mocking
//        // which depends on your testing framework setup
//    }
//
//    public function test_it_can_add_workflow_change_and_update_brief_status(): void
//    {
//        $briefId = 1;
//        $data = [
//            'status' => 'in_review',
//            'changed_by' => 1,
//            'notes' => 'Moving to review'
//        ];
//
//        $workflow = Mockery::mock(Model::class);
//
//        $this->briefRepository
//            ->shouldReceive('update')
//            ->once()
//            ->with($briefId, Mockery::on(function ($updateData) {
//                return $updateData['status'] === 'in_review'
//                    && isset($updateData['last_activity_at'])
//                    && $updateData['last_activity_user_id'] === 1;
//            }));
//
//        $this->logBriefActivity
//            ->shouldReceive('handle')
//            ->once()
//            ->with($briefId, 1, 'workflow_changed', 'Status changed to in_review');
//
//        // Note: BriefWorkflowHistory::create would need mocking setup
//    }

    public function test_create_preset_sets_is_system_false(): void
    {
        $this->templateRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => $data['is_system'] === false))
            ->andReturn(Mockery::mock(Model::class));

        $this->service->createPreset(['name' => 'My Preset'], 1);
        $this->assertTrue(true);
    }

    public function test_create_preset_sets_site_id(): void
    {
        $siteId = 42;

        $this->templateRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => $data['site_id'] === $siteId))
            ->andReturn(Mockery::mock(Model::class));

        $this->service->createPreset(['name' => 'My Preset'], $siteId);
        $this->assertTrue(true);
    }

    public function test_create_preset_passes_all_provided_data(): void
    {
        $input = [
            'name' => 'Preset A',
            'description' => 'A test preset',
            'default_subtasks' => [['title' => 'Draft']],
        ];

        $this->templateRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($input) {
                return $data['name'] === $input['name']
                    && $data['description'] === $input['description']
                    && $data['default_subtasks'] === $input['default_subtasks'];
            }))
            ->andReturn(Mockery::mock(Model::class));

        $this->service->createPreset($input, 1);
        $this->assertTrue(true);
    }

    public function test_update_preset_throws_when_not_found(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->templateRepository
            ->shouldReceive('find')
            ->once()
            ->with(99)
            ->andReturn(null);

        $this->service->updatePreset(99, ['name' => 'New Name']);
        $this->assertTrue(true);
    }

    public function test_update_preset_calls_repository_update(): void
    {
        $preset = Mockery::mock(BriefTemplate::class);
        $updated = Mockery::mock(Model::class);

        $this->templateRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($preset);

        $this->templateRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['name' => 'New Name'])
            ->andReturn($updated);

        $result = $this->service->updatePreset(1, ['name' => 'New Name']);

        $this->assertSame($updated, $result);
    }

    public function test_create_from_template_creates_tasks_for_subtasks(): void
    {
        $template = $this->makeTemplate([
            ['title' => 'Write draft'],
            ['title' => 'Review copy'],
        ]);

        $brief = $this->makeBrief();
        $fullBrief = $this->makeBrief();

        $this->templateRepository->shouldReceive('find')->andReturn($template);
        $this->briefRepository->shouldReceive('create')->andReturn($brief);
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($fullBrief);

        $this->taskRepository
            ->shouldReceive('create')
            ->twice();   // one call per subtask

        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->service->createFromTemplate(1, ['owner_id' => 5, 'site_id' => 1]);
        $this->assertTrue(true);
    }

    public function test_create_from_template_with_no_subtasks_creates_no_tasks(): void
    {
        $template = $this->makeTemplate([]);  // no subtasks

        $brief = $this->makeBrief();
        $fullBrief = $this->makeBrief();

        $this->templateRepository->shouldReceive('find')->andReturn($template);
        $this->briefRepository->shouldReceive('create')->andReturn($brief);
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($fullBrief);

        $this->taskRepository->shouldNotReceive('create');

        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->service->createFromTemplate(1, ['owner_id' => 5, 'site_id' => 1]);
        $this->assertTrue(true);
    }

    public function test_task_count_matches_subtask_count(): void
    {
        $subtasks = [
            ['title' => 'Task A'],
            ['title' => 'Task B'],
            ['title' => 'Task C'],
        ];

        $template = $this->makeTemplate($subtasks);
        $brief = $this->makeBrief();
        $fullBrief = $this->makeBrief();

        $this->templateRepository->shouldReceive('find')->andReturn($template);
        $this->briefRepository->shouldReceive('create')->andReturn($brief);
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($fullBrief);

        $callCount = 0;
        $this->taskRepository
            ->shouldReceive('create')
            ->times(3)
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                return Mockery::mock(Model::class);
            });

        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->service->createFromTemplate(1, ['owner_id' => 5, 'site_id' => 1]);

        $this->assertSame(3, $callCount);
    }

    public function test_subtask_owner_maps_to_task_assignee(): void
    {
        $template = $this->makeTemplate([
            ['title' => 'Draft', 'defaultOwnerId' => '99'],
        ]);

        $brief = $this->makeBrief();
        $fullBrief = $this->makeBrief();

        $this->templateRepository->shouldReceive('find')->andReturn($template);
        $this->briefRepository->shouldReceive('create')->andReturn($brief);
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($fullBrief);

        $this->taskRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => $data['assigned_to'] === 99));

        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->service->createFromTemplate(1, ['owner_id' => 5, 'site_id' => 1]);
        $this->assertTrue(true);
    }

    public function test_subtask_null_owner_maps_to_null_assignee(): void
    {
        $template = $this->makeTemplate([
            ['title' => 'No owner subtask'],  // no defaultOwnerId
        ]);

        $brief = $this->makeBrief();
        $fullBrief = $this->makeBrief();

        $this->templateRepository->shouldReceive('find')->andReturn($template);
        $this->briefRepository->shouldReceive('create')->andReturn($brief);
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($fullBrief);

        $this->taskRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => $data['assigned_to'] === null));

        $this->logBriefActivity->shouldReceive('handle')->once();

        $this->service->createFromTemplate(1, ['owner_id' => 5, 'site_id' => 1]);
        $this->assertTrue(true);
    }

    public function test_create_from_template_logs_preset_activity(): void
    {
        $template = $this->makeTemplate(
            [['title' => 'Write']],      // has subtasks → preset path
            [7]
        );

        $brief = $this->makeBrief();
        $fullBrief = $this->makeBrief();

        $this->templateRepository->shouldReceive('find')->andReturn($template);
        $this->briefRepository->shouldReceive('create')->andReturn($brief);
        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($fullBrief);
        $this->taskRepository->shouldReceive('create');

        $this->logBriefActivity
            ->shouldReceive('handle')
            ->once()
            ->with($brief->id, 5, 'created_from_preset', 'Created from preset: My Preset');

        $this->service->createFromTemplate(1, ['owner_id' => 5, 'site_id' => 1]);
        $this->assertTrue(true);
    }

    public function test_create_from_template_uses_template_owner_when_caller_omits_it(): void
    {
        $template = $this->makeTemplate([], [55]);  // default owner 55, no subtasks

        $brief = $this->makeBrief();
        $fullBrief = $this->makeBrief();

        $this->templateRepository->shouldReceive('find')->andReturn($template);

        $this->briefRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => $data['owner_id'] === 55))
            ->andReturn($brief);

        $this->briefRepository->shouldReceive('getWithRelations')->andReturn($fullBrief);

        $this->logBriefActivity->shouldReceive('handle');

        // Caller does NOT supply owner_id.
        $this->service->createFromTemplate(1, ['site_id' => 1]);
        $this->assertTrue(true);
    }

    private function makeBrief(int $id = 10): Brief
    {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id = $id;

        return $brief;
    }

    private function makeTemplate(array $subtasks = [], array $ownerIds = []): BriefTemplate
    {
        $template = Mockery::mock(BriefTemplate::class)->makePartial();
        $template->id = 1;
        $template->name = 'My Preset';
        $template->default_fields = null;
        $template->default_owner_ids = $ownerIds ?: null;
        $template->default_category_tag_id = null;
        $template->default_subtasks = $subtasks ?: null;

        // Wire getDefaultSubtasksTyped() to return real value objects.
        $typed = array_map(
            fn($s) => BriefPresetSubtask::fromArray($s),
            $subtasks
        );
        $template->shouldReceive('getDefaultSubtasksTyped')->andReturn($typed);

        return $template;
    }
}