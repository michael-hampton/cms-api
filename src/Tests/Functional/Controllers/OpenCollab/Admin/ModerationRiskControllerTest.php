<?php

namespace App\Tests\Functional\Controllers\OpenCollab\Admin;

use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskSource;
use App\Enums\OpenCollab\RiskStatus;
use App\Enums\OpenCollab\RiskType;
use App\Enums\Pages\PageStatus;
use App\Models\ContentRiskMarker;
use App\Models\ModerationQueueEntry;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ModerationRiskControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $moderator;
    private User $contributor;

    public function test_moderator_can_add_risk_marker(): void
    {
        $this->actingAs($this->moderator);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $entry = $this->createQueueEntry($page);

        $response = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/risks", [
            'risk_type' => RiskType::Copyright->value,
            'severity' => RiskSeverity::High->value,
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(RiskType::Copyright->value, $data['risk_type']);
        $this->assertEquals(RiskStatus::Open->value, $data['status']);

        $this->assertDatabaseHas('oc_content_risk_markers', [
            'site_id' => $this->siteId,
            'page_id' => $page->id,
            'risk_type' => RiskType::Copyright->value,
            'severity' => RiskSeverity::High->value,
            'status' => RiskStatus::Open->value,
        ]);
    }

    public function test_adding_high_risk_marker_increases_queue_priority(): void
    {
        $this->actingAs($this->moderator);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $entry = $this->createQueueEntry($page);

        $response = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/risks", [
            'risk_type' => RiskType::Copyright->value,
            'severity' => RiskSeverity::High->value,
        ]);

        $this->assertEquals(201, $response->getStatusCode());

        $entry->refresh();

        $this->assertEquals(RiskSeverity::High->score(), $entry->risk_score);
        $this->assertGreaterThanOrEqual(RiskSeverity::High->score(), $entry->priority_score);
    }

    public function test_unauthorised_user_cannot_add_risk_marker(): void
    {
        $unauthorised = $this->createUser([
            'email' => 'no-perms-risk@example.com',
            'role' => 'contributor',
        ]);
        $this->actingAs($unauthorised);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $entry = $this->createQueueEntry($page);

        $response = $this->postForSite("/api/open-collab/admin/moderation/{$entry->id}/risks", [
            'risk_type' => RiskType::Copyright->value,
            'severity' => RiskSeverity::High->value,
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_moderator_can_resolve_risk_marker_with_notes(): void
    {
        $this->actingAs($this->moderator);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $marker = $this->createRiskMarker($page, [
            'severity' => RiskSeverity::High->value,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/risks/{$marker->id}/resolve", [
            'notes' => 'Confirmed cleared after legal review.',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(RiskStatus::Cleared->value, $data['status']);
        $this->assertEquals('Confirmed cleared after legal review.', $data['resolution_notes']);

        $this->assertDatabaseHas('oc_content_risk_markers', [
            'id' => $marker->id,
            'status' => RiskStatus::Cleared->value,
            'resolution_notes' => 'Confirmed cleared after legal review.',
            'resolved_by_user_id' => $this->moderator->id,
        ]);
    }

    public function test_resolving_high_risk_marker_without_notes_fails(): void
    {
        $this->actingAs($this->moderator);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $marker = $this->createRiskMarker($page, [
            'severity' => RiskSeverity::High->value,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/risks/{$marker->id}/resolve", []);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertDatabaseHas('oc_content_risk_markers', [
            'id' => $marker->id,
            'status' => RiskStatus::Open->value,
        ]);
    }

    public function test_unauthorised_user_cannot_resolve_risk_marker(): void
    {
        $unauthorised = $this->createUser([
            'email' => 'no-perms-resolve@example.com',
            'role' => 'contributor',
        ]);
        $this->actingAs($unauthorised);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $marker = $this->createRiskMarker($page, [
            'severity' => RiskSeverity::Low->value,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/risks/{$marker->id}/resolve", []);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_moderator_can_dismiss_risk_marker(): void
    {
        $this->actingAs($this->moderator);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);
        $marker = $this->createRiskMarker($page, [
            'severity' => RiskSeverity::Low->value,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/risks/{$marker->id}/dismiss", [
            'notes' => 'False positive.',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(RiskStatus::Dismissed->value, $data['status']);

        $this->assertDatabaseHas('oc_content_risk_markers', [
            'id' => $marker->id,
            'status' => RiskStatus::Dismissed->value,
            'resolution_notes' => 'False positive.',
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

    private function createRiskMarker($page, array $attributes = []): ContentRiskMarker
    {
        return ContentRiskMarker::create(array_merge([
            'site_id' => $page->site_id,
            'page_id' => $page->id,
            'risk_type' => RiskType::Copyright->value,
            'source' => RiskSource::Moderator->value,
            'severity' => RiskSeverity::Low->value,
            'status' => RiskStatus::Open->value,
        ], $attributes));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSiteExists();
        $this->ensurePermission('Review', 'pages.review', 'test');
        $this->ensurePermission('Resolve Risk', 'pages.resolve_risk', 'test');

        $this->moderator = $this->createUser([
            'email' => 'risk-moderator@example.com',
            'role' => 'moderator',
        ]);
        $this->grantSitePermission($this->moderator, 'pages.review');
        $this->grantSitePermission($this->moderator, 'pages.resolve_risk');

        $this->contributor = $this->createUser([
            'email' => 'risk-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
    }
}
