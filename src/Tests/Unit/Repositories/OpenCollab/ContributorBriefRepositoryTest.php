<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\Brief;
use App\Models\BriefAttachment;
use App\Models\BriefComment;
use App\Models\BriefTask;
use App\Models\Collaborator;
use App\Repositories\OpenCollab\ContributorBriefRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ContributorBriefRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ContributorBriefRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ContributorBriefRepository();
    }

    public function test_assigned_briefs_for_contributor_returns_current_site_non_archived_briefs(): void
    {
        $contributor = $this->createUser(['email' => 'briefs-contributor@example.com']);
        $other = $this->createUser(['email' => 'briefs-other@example.com']);
        $otherSite = $this->createSite();

        $assigned = $this->createBrief(['site_id' => $this->siteId, 'title' => 'Assigned brief', 'status' => 'draft']);
        $otherUsersBrief = $this->createBrief(['site_id' => $this->siteId, 'title' => 'Other user brief', 'status' => 'draft']);
        $archived = $this->createBrief(['site_id' => $this->siteId, 'title' => 'Archived brief', 'status' => 'archived']);
        $wrongSite = $this->createBrief(['site_id' => $otherSite->id, 'title' => 'Wrong site brief', 'status' => 'draft']);

        $this->assign($assigned, $contributor->id, $this->siteId);
        $this->assign($otherUsersBrief, $other->id, $this->siteId);
        $this->assign($archived, $contributor->id, $this->siteId);
        $this->assign($wrongSite, $contributor->id, $otherSite->id);

        $results = $this->repository->assignedBriefsForContributor($contributor->id, $this->siteId);

        $this->assertCount(1, $results);
        $this->assertEquals($assigned->id, $results->first()->id);
    }

    public function test_find_assigned_brief_requires_assignment_and_loads_workspace_relations(): void
    {
        $contributor = $this->createUser(['email' => 'assigned-brief@example.com']);
        $brief = $this->createBrief(['site_id' => $this->siteId, 'status' => 'draft']);
        $this->assign($brief, $contributor->id, $this->siteId);

        $found = $this->repository->findAssignedBrief($brief->id, $contributor->id, $this->siteId);

        $this->assertNotNull($found);
        $this->assertEquals($brief->id, $found->id);
        $this->assertTrue($found->relationLoaded('collaborators'));
        $this->assertTrue($found->relationLoaded('tasks'));
        $this->assertNull($this->repository->findAssignedBrief($brief->id, 999999, $this->siteId));
    }

    public function test_contributor_scoped_task_comment_and_attachment_lookups(): void
    {
        $contributor = $this->createUser(['email' => 'brief-scope@example.com']);
        $other = $this->createUser(['email' => 'brief-scope-other@example.com']);
        $brief = $this->createBrief(['site_id' => $this->siteId, 'status' => 'draft']);

        $assignedTask = BriefTask::create([
            'brief_id' => $brief->id,
            'title' => 'Assigned task',
            'assigned_to' => $contributor->id,
            'created_by' => $other->id,
            'status' => 'pending',
        ]);
        $otherTask = BriefTask::create([
            'brief_id' => $brief->id,
            'title' => 'Other task',
            'assigned_to' => $other->id,
            'created_by' => $other->id,
            'status' => 'pending',
        ]);
        $comment = BriefComment::create([
            'brief_id' => $brief->id,
            'user_id' => $contributor->id,
            'content' => 'Owned comment',
        ]);
        $attachment = BriefAttachment::create([
            'brief_id' => $brief->id,
            'type' => 'document',
            'file_url' => 'uploads/briefs/doc.pdf',
            'file_name' => 'doc.pdf',
            'metadata' => ['uploaded_by' => $contributor->id],
            'sort_order' => 0,
        ]);

        $this->assertEquals($assignedTask->id, $this->repository->findTaskForContributor($brief->id, $assignedTask->id, $contributor->id)->id);
        $this->assertNull($this->repository->findTaskForContributor($brief->id, $otherTask->id, $contributor->id));
        $this->assertEquals($comment->id, $this->repository->findCommentOwnedByContributor($comment->id, $contributor->id)->id);
        $this->assertNull($this->repository->findCommentOwnedByContributor($comment->id, $other->id));
        $this->assertEquals($attachment->id, $this->repository->findAttachmentOwnedByContributor($brief->id, $attachment->id, $contributor->id)->id);
        $this->assertNull($this->repository->findAttachmentOwnedByContributor($brief->id, $attachment->id, $other->id));
    }

    private function assign(Brief $brief, int $userId, int $siteId, string $role = 'pending'): Collaborator
    {
        return Collaborator::create([
            'collaboratable_type' => Brief::class,
            'collaboratable_id' => $brief->id,
            'user_id' => $userId,
            'role' => $role,
            'site_id' => $siteId,
        ]);
    }
}
