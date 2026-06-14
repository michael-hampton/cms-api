<?php

namespace App\Tests\Functional\Controllers\OpenCollab\Admin;

use App\Enums\OpenCollab\EscalationCategory;
use App\Enums\OpenCollab\EscalationStatus;
use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\Pages\PageStatus;
use App\Models\ModerationEscalation;
use App\Models\ModerationQueueEntry;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ModerationEscalationControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $moderator;
    private User $legal;
    private User $contributor;

    public function test_moderator_can_escalate_queue_entry(): void
    {
        $this->actingAs($this->moderator);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $entry = $this->createQueueEntry($page);

        $response = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/escalate", [
            'category' => EscalationCategory::Copyright->value,
            'severity' => RiskSeverity::High->value,
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('copyright', $data['category']);
        $this->assertEquals('legal', $data['assigned_team']);
        $this->assertNotNull($data['due_at']);

        $this->assertDatabaseHas('oc_moderation_queue_entries', [
            'id' => $entry->id,
            'status' => ModerationQueueStatus::Escalated->value,
        ]);
    }

    public function test_unauthorised_user_cannot_escalate(): void
    {
        $unauthorised = $this->createUser([
            'email' => 'no-perms-escalate@example.com',
            'role' => 'contributor',
        ]);
        $this->actingAs($unauthorised);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $entry = $this->createQueueEntry($page);

        $response = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/escalate", [
            'category' => EscalationCategory::Copyright->value,
            'severity' => RiskSeverity::High->value,
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_escalation_blocks_approval_until_resolved(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $entry = $this->createQueueEntry($page);

        $this->actingAs($this->moderator);

        $escalateResponse = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/escalate", [
            'category' => EscalationCategory::Copyright->value,
            'severity' => RiskSeverity::High->value,
        ]);

        $this->assertEquals(201, $escalateResponse->getStatusCode());
        $escalation = json_decode($escalateResponse->getContent(), true);

        $approveResponse = $this->postForSite("/api/open-collab/admin/articles/{$page->id}/approve");
        $approveData = json_decode($approveResponse->getContent(), true);

        $this->assertEquals(422, $approveResponse->getStatusCode());
        $this->assertNotEmpty($approveData['governance_failures']);
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);

        $this->actingAs($this->legal);

        $resolveResponse = $this->postForSite("/api/open-collab/admin/escalations/{$escalation['id']}/resolve", [
            'resolution' => 'cleared',
            'notes' => 'No infringement found.',
        ]);

        $this->assertEquals(200, $resolveResponse->getStatusCode());

        $this->actingAs($this->moderator);
        $approveResponse = $this->postForSite("/api/open-collab/admin/articles/{$page->id}/approve");

        $this->assertEquals(200, $approveResponse->getStatusCode());
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'status' => PageStatus::PUBLISHED->value,
        ]);
    }

    public function test_escalation_can_be_acknowledged_and_assigned(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $entry = $this->createQueueEntry($page);
        $escalation = $this->createEscalation($entry);

        $this->actingAs($this->legal);

        $ackResponse = $this->postForSite("/api/open-collab/admin/escalations/{$escalation->id}/acknowledge");
        $ackData = json_decode($ackResponse->getContent(), true);

        $this->assertEquals(200, $ackResponse->getStatusCode());
        $this->assertEquals(EscalationStatus::Acknowledged->value, $ackData['status']);

        $assignResponse = $this->postForSite("/api/open-collab/admin/escalations/{$escalation->id}/assign", [
            'user_id' => $this->legal->id,
        ]);
        $assignData = json_decode($assignResponse->getContent(), true);

        $this->assertEquals(200, $assignResponse->getStatusCode());
        $this->assertEquals($this->legal->id, $assignData['assigned_user_id']);
    }

    public function test_index_lists_escalations_for_site(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $entry = $this->createQueueEntry($page);
        $escalation = $this->createEscalation($entry);

        $this->actingAs($this->moderator);

        $response = $this->getForSite('/api/open-collab/admin/escalations');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($data);
        $this->assertEquals($escalation->id, $data[0]['id']);
    }

    public function test_wrong_site_escalation_cannot_be_resolved(): void
    {
        $otherSiteId = $this->createSite()->id;

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
            'site_id' => $otherSiteId,
        ]);
        $entry = ModerationQueueEntry::create([
            'site_id' => $otherSiteId,
            'page_id' => $page->id,
            'status' => ModerationQueueStatus::Queued->value,
            'submitted_at' => date('Y-m-d H:i:s'),
            'risk_score' => 0,
            'priority_score' => 0,
        ]);
        $escalation = $this->createEscalation($entry);

        $this->actingAs($this->legal);

        $response = $this->postForSite("/api/open-collab/admin/escalations/{$escalation->id}/resolve", [
            'resolution' => 'cleared',
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    private function createQueueEntry($page, array $attributes = []): ModerationQueueEntry
    {
        return ModerationQueueEntry::create(array_merge([
            'site_id' => $page->site_id,
            'page_id' => $page->id,
            'status' => ModerationQueueStatus::Queued->value,
            'submitted_at' => date('Y-m-d H:i:s'),
            'risk_score' => 0,
            'priority_score' => 0,
        ], $attributes));
    }

    private function createEscalation(ModerationQueueEntry $entry, array $attributes = []): ModerationEscalation
    {
        return ModerationEscalation::create(array_merge([
            'site_id' => $entry->site_id,
            'queue_entry_id' => $entry->id,
            'page_id' => $entry->page_id,
            'category' => EscalationCategory::Copyright->value,
            'severity' => RiskSeverity::High->value,
            'assigned_team' => 'legal',
            'status' => EscalationStatus::Open->value,
            'due_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'created_by_user_id' => $this->moderator->id,
            'created_at' => date('Y-m-d H:i:s'),
        ], $attributes));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSiteExists();

        $this->ensurePermission('Resolve Risk', 'pages.resolve_risk', 'test');
        $this->ensurePermission('View High Risk', 'pages.view_high_risk', 'test');
        $this->ensurePermission('Escalate', 'pages.escalate', 'test');
        $this->ensurePermission('Assign Review', 'pages.assign_review', 'test');

        $this->moderator = $this->createUser([
            'email' => 'esc-moderator@example.com',
            'role' => 'moderator',
        ]);
        $this->grantSitePermission($this->moderator, 'pages.review');
        $this->grantSitePermission($this->moderator, 'pages.approve');
        $this->grantSitePermission($this->moderator, 'pages.escalate');

        $this->legal = $this->createUser([
            'email' => 'legal@example.com',
            'role' => 'moderator',
        ]);
        $this->grantSitePermission($this->legal, 'pages.resolve_risk');
        $this->grantSitePermission($this->legal, 'pages.view_high_risk');
        $this->grantSitePermission($this->legal, 'pages.escalate');
        $this->grantSitePermission($this->legal, 'pages.assign_review');

        $this->contributor = $this->createUser([
            'email' => 'esc-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
    }
}
