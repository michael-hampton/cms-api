<?php

namespace App\Tests\Functional\Controllers\OpenCollab\Admin;

use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Enums\Pages\PageStatus;
use App\Models\ModerationQueueEntry;
use App\Models\OpenCollabPermission;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ModerationQueueControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $moderator;
    private User $contributor;

    public function test_unauthorised_user_cannot_list_queue(): void
    {
        $unauthorised = $this->createUser([
            'email' => 'no-perms@example.com',
            'role' => 'contributor',
        ]);

        $this->actingAs($unauthorised);

        $response = $this->getForSite('/api/open-collab/admin/moderation');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_moderator_can_list_queue_entries_for_their_site(): void
    {
        $this->actingAs($this->moderator);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);

        $entry = $this->createQueueEntry($page, [
            'status' => ModerationQueueStatus::Queued->value,
        ]);

        $response = $this->getForSite('/api/open-collab/admin/moderation');

        $data = json_decode($response->getContent(), true);
        $items = $data['data'] ?? $data;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($items);
        $this->assertEquals($entry->id, $items[0]['id']);
    }

    public function test_status_filter_excludes_non_matching_entries(): void
    {
        $this->actingAs($this->moderator);

        $page1 = $this->createPage(['contributor_id' => $this->contributor->id, 'status' => PageStatus::WAITING_APPROVAL->value]);
        $page2 = $this->createPage(['contributor_id' => $this->contributor->id, 'status' => PageStatus::WAITING_APPROVAL->value]);

        $this->createQueueEntry($page1, ['status' => ModerationQueueStatus::Queued->value]);
        $this->createQueueEntry($page2, ['status' => ModerationQueueStatus::Escalated->value]);

        $response = $this->getForSite('/api/open-collab/admin/moderation?status=escalated');
        $data = json_decode($response->getContent(), true);
        $items = $data['data'] ?? $data;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $items);
        $this->assertEquals('escalated', $items[0]['status']);
    }

    public function test_unassigned_filter_returns_only_unassigned_entries(): void
    {
        $this->actingAs($this->moderator);

        $page1 = $this->createPage(['contributor_id' => $this->contributor->id, 'status' => PageStatus::WAITING_APPROVAL->value]);
        $page2 = $this->createPage(['contributor_id' => $this->contributor->id, 'status' => PageStatus::WAITING_APPROVAL->value]);

        $this->createQueueEntry($page1, ['status' => ModerationQueueStatus::Queued->value, 'assigned_to_user_id' => null]);
        $this->createQueueEntry($page2, ['status' => ModerationQueueStatus::Claimed->value, 'assigned_to_user_id' => $this->moderator->id, 'claimed_at' => date('Y-m-d H:i:s')]);

        $response = $this->getForSite('/api/open-collab/admin/moderation?unassigned=1');
        $data = json_decode($response->getContent(), true);
        $items = $data['data'] ?? $data;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $items);
        $this->assertNull($items[0]['assigned_to_user_id']);
    }

    public function test_show_returns_full_detail_with_governance(): void
    {
        $this->actingAs($this->moderator);

        $page = $this->createPage(['contributor_id' => $this->contributor->id, 'status' => PageStatus::WAITING_APPROVAL->value]);
        $entry = $this->createQueueEntry($page, ['status' => ModerationQueueStatus::Queued->value]);

        $response = $this->getForSite("/api/open-collab/admin/moderation/{$entry->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('governance', $data);
        $this->assertTrue($data['governance']['can_approve']);
        $this->assertEmpty($data['governance']['blockers']);
    }

    public function test_show_returns_404_for_entry_on_other_site(): void
    {
        $this->actingAs($this->moderator);

        $otherSiteId = $this->createSite()->id; // ASSUMED helper in CreatesTestData, or roll your own second-site fixture

        $page = $this->createPage(['contributor_id' => $this->contributor->id, 'status' => PageStatus::WAITING_APPROVAL->value, 'site_id' => $otherSiteId]);
        $entry = ModerationQueueEntry::create([
            'site_id' => $otherSiteId,
            'page_id' => $page->id,
            'status' => ModerationQueueStatus::Queued->value,
            'submitted_at' => date('Y-m-d H:i:s'),
            'risk_score' => 0,
            'priority_score' => 0,
        ]);

        $response = $this->getForSite("/api/open-collab/admin/moderation/{$entry->id}");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_claim_succeeds_for_unassigned_entry(): void
    {
        $this->actingAs($this->moderator);

        $page = $this->createPage(['contributor_id' => $this->contributor->id, 'status' => PageStatus::WAITING_APPROVAL->value]);
        $entry = $this->createQueueEntry($page, ['status' => ModerationQueueStatus::Queued->value]);

        $response = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/claim");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('claimed', $data['status']);
        $this->assertEquals($this->moderator->id, $data['assigned_to_user_id']);

        $this->assertDatabaseHas('oc_moderation_queue_entries', [
            'id' => $entry->id,
            'status' => 'claimed',
            'assigned_to_user_id' => $this->moderator->id,
        ]);
    }

    public function test_two_claim_attempts_only_one_succeeds(): void
    {
        $page = $this->createPage(['contributor_id' => $this->contributor->id, 'status' => PageStatus::WAITING_APPROVAL->value]);
        $entry = $this->createQueueEntry($page, ['status' => ModerationQueueStatus::Queued->value]);

        $secondModerator = $this->createModerator('second-moderator@example.com');

        $this->actingAs($this->moderator);
        $first = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/claim");

        $this->actingAs($secondModerator);
        $second = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/claim");

        $this->assertEquals(200, $first->getStatusCode());
        $this->assertEquals(409, $second->getStatusCode());

        $this->assertDatabaseHas('oc_moderation_queue_entries', [
            'id' => $entry->id,
            'status' => 'claimed',
            'assigned_to_user_id' => $this->moderator->id,
        ]);
    }

    public function test_release_succeeds_for_assigned_moderator(): void
    {
        $this->actingAs($this->moderator);

        $page = $this->createPage(['contributor_id' => $this->contributor->id, 'status' => PageStatus::WAITING_APPROVAL->value]);
        $entry = $this->createQueueEntry($page, [
            'status' => ModerationQueueStatus::Claimed->value,
            'assigned_to_user_id' => $this->moderator->id,
            'claimed_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/release");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('queued', $data['status']);
        $this->assertNull($data['assigned_to_user_id']);
    }

    public function test_release_fails_for_non_assigned_moderator(): void
    {
        $page = $this->createPage(['contributor_id' => $this->contributor->id, 'status' => PageStatus::WAITING_APPROVAL->value]);
        $entry = $this->createQueueEntry($page, [
            'status' => ModerationQueueStatus::Claimed->value,
            'assigned_to_user_id' => $this->moderator->id,
            'claimed_at' => date('Y-m-d H:i:s'),
        ]);

        $secondModerator = $this->createModerator('second-moderator-2@example.com');
        $this->actingAs($secondModerator);

        $response = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/release");

        $this->assertEquals(422, $response->getStatusCode());

        $this->assertDatabaseHas('oc_moderation_queue_entries', [
            'id' => $entry->id,
            'status' => 'claimed',
            'assigned_to_user_id' => $this->moderator->id,
        ]);
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

    private function createModerator(string $email): User
    {
        $user = $this->createUser(['email' => $email, 'role' => 'moderator']);
        // ASSUMED: CreatesTestData or RbacAdminController test helpers expose a
        // way to grant a permission for a site — mirror however
        // ArticleApprovalControllerTest grants pages.approve/pages.reject.
        //$this->ensurePermission('Approve Page', 'pages.review', 'pages');

        $this->grantSitePermission($user, 'pages.review');
        $this->ensurePermission('Excalate', 'pages.escalate', 'pages');
        $this->grantSitePermission($user, 'pages.escalate');
        //$this->grantSitePermission($user, $this->siteId, 'pages.assign_review');
        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSiteExists();

        $this->moderator = $this->createModerator('moderator@example.com');

        $this->contributor = $this->createUser([
            'email' => 'contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
    }
}