<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\Brief;
use App\Models\BriefActivityLog;
use App\Models\BriefComment;
use App\Models\BriefTask;
use App\Models\Collaborator;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ContributorBriefControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'email' => 'brief-controller-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
    }

    public function test_contributor_can_list_only_their_assigned_briefs(): void
    {
        $assigned = $this->assignedBrief('Assigned camera brief');
        $other = $this->createUser(['email' => 'brief-controller-other@example.com']);
        $otherBrief = $this->createBrief(['site_id' => $this->siteId, 'title' => 'Other contributor brief', 'status' => 'draft']);
        $this->assign($otherBrief, $other->id, 'pending');

        $this->actingAs($this->contributor);

        $response = $this->getForSite('/api/open-collab/briefs');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['data']);
        $this->assertEquals($assigned->id, $data['data'][0]['id']);
    }

    public function test_invalid_inbox_filter_returns_validation_error(): void
    {
        $this->actingAs($this->contributor);

        $response = $this->getForSite('/api/open-collab/briefs?filter=unknown');

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_contributor_can_view_assigned_brief_workspace(): void
    {
        $brief = $this->assignedBrief('Workspace brief');
        BriefTask::create([
            'brief_id' => $brief->id,
            'title' => 'Draft intro',
            'assigned_to' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->contributor);

        $response = $this->getForSite("/api/open-collab/briefs/{$brief->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($brief->id, $data['brief']['id']);
        $this->assertCount(1, $data['tasks']);
    }

    public function test_contributor_cannot_view_unassigned_brief(): void
    {
        $brief = $this->createBrief(['site_id' => $this->siteId, 'title' => 'Private brief', 'status' => 'draft']);

        $this->actingAs($this->contributor);

        $response = $this->getForSite("/api/open-collab/briefs/{$brief->id}");

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_contributor_can_accept_assignment(): void
    {
        $brief = $this->assignedBrief('Acceptable brief');

        $this->actingAs($this->contributor);

        $response = $this->postForSite("/api/open-collab/briefs/{$brief->id}/accept");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('collaborators', [
            'collaboratable_type' => Brief::class,
            'collaboratable_id' => $brief->id,
            'user_id' => $this->contributor->id,
            'role' => 'writer',
        ]);
        $this->assertDatabaseHas('brief_activity_log', [
            'brief_id' => $brief->id,
            'user_id' => $this->contributor->id,
            'action' => 'assignment_accepted',
        ]);
    }

    public function test_reject_requires_reason(): void
    {
        $brief = $this->assignedBrief('Rejectable brief');

        $this->actingAs($this->contributor);

        $response = $this->postForSite("/api/open-collab/briefs/{$brief->id}/reject", []);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_contributor_can_submit_brief_for_review(): void
    {
        $brief = $this->assignedBrief('Submission brief', 'writer');

        $this->actingAs($this->contributor);

        $response = $this->postForSite("/api/open-collab/briefs/{$brief->id}/submit", [
            'notes' => 'Ready for review',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('briefs', [
            'id' => $brief->id,
            'status' => 'in_review',
        ]);
    }

    public function test_contributor_can_request_deadline_change_and_it_is_recorded_for_editor_review(): void
    {
        $brief = $this->assignedBrief('Deadline change brief', 'writer');

        $this->actingAs($this->contributor);

        $response = $this->postForSite("/api/open-collab/briefs/{$brief->id}/request-deadline-change", [
            'requested_deadline' => date('Y-m-d H:i:s', strtotime('+2 weeks')),
            'reason' => 'Need extra time for source interviews.',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('brief_activity_log', [
            'brief_id' => $brief->id,
            'user_id' => $this->contributor->id,
            'action' => 'deadline_change_requested',
        ]);
    }

    public function test_contributor_can_negotiate_assignment_and_it_is_recorded_for_editor_review(): void
    {
        $brief = $this->assignedBrief('Negotiation brief');

        $this->actingAs($this->contributor);

        $response = $this->postForSite("/api/open-collab/briefs/{$brief->id}/negotiate", [
            'message' => 'Can we reduce the scope to one product category?',
            'requested_deadline' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'scope_details' => 'Focus only on mirrorless cameras.',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('collaborators', [
            'collaboratable_type' => Brief::class,
            'collaboratable_id' => $brief->id,
            'user_id' => $this->contributor->id,
            'role' => 'negotiating',
        ]);

        $this->assertDatabaseHas('brief_activity_log', [
            'brief_id' => $brief->id,
            'user_id' => $this->contributor->id,
            'action' => 'negotiation_requested',
        ]);
    }

    public function test_contributor_can_update_own_task_and_create_comment(): void
    {
        $brief = $this->assignedBrief('Task brief', 'writer');
        $task = BriefTask::create([
            'brief_id' => $brief->id,
            'title' => 'Write copy',
            'assigned_to' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->contributor);

        $taskResponse = $this->postForSite("/api/open-collab/briefs/{$brief->id}/tasks/{$task->id}", [
            'status' => 'completed',
        ]);
        $commentResponse = $this->postForSite("/api/open-collab/briefs/{$brief->id}/comments", [
            'content' => 'Uploaded the first draft.',
        ]);

        $this->assertEquals(200, $taskResponse->getStatusCode());
        $this->assertEquals(201, $commentResponse->getStatusCode());
        $this->assertDatabaseHas('brief_tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('brief_comments', [
            'brief_id' => $brief->id,
            'user_id' => $this->contributor->id,
            'content' => 'Uploaded the first draft.',
        ]);
    }

    public function test_contributor_can_update_and_resolve_accessible_comment(): void
    {
        $brief = $this->assignedBrief('Comment brief', 'writer');
        $comment = BriefComment::create([
            'brief_id' => $brief->id,
            'user_id' => $this->contributor->id,
            'content' => 'Needs update',
        ]);

        $this->actingAs($this->contributor);

        $updateResponse = $this->postForSite("/api/open-collab/comments/{$comment->id}", [
            'content' => 'Updated comment',
        ]);
        $resolveResponse = $this->postForSite("/api/open-collab/comments/{$comment->id}/resolve");

        $this->assertEquals(200, $updateResponse->getStatusCode());
        $this->assertEquals(200, $resolveResponse->getStatusCode());
        $this->assertDatabaseHas('brief_comments', [
            'id' => $comment->id,
            'content' => 'Updated comment',
            'is_resolved' => 1,
            'resolved_by' => $this->contributor->id,
        ]);
    }

    private function assignedBrief(string $title, string $role = 'pending'): Brief
    {
        $brief = $this->createBrief([
            'site_id' => $this->siteId,
            'title' => $title,
            'status' => 'draft',
            'owner_id' => $this->contributor->id,
        ]);

        $this->assign($brief, $this->contributor->id, $role);

        return $brief;
    }

    private function assign(Brief $brief, int $userId, string $role): Collaborator
    {
        return Collaborator::create([
            'collaboratable_type' => Brief::class,
            'collaboratable_id' => $brief->id,
            'user_id' => $userId,
            'role' => $role,
            'site_id' => $this->siteId,
        ]);
    }
}
