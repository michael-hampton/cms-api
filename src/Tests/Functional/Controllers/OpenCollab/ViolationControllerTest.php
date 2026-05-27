<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Enums\OpenCollab\ViolationAction;
use App\Models\ContributorViolation;
use App\Models\Site;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ViolationControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;

    public function test_index_returns_403_for_user_without_violation_view_permission(): void
    {
        $this->enableSiteRbac();

        $restrictedUser = $this->createUser([
            'email' => 'violations-restricted@example.com',
            'role' => 'user',
        ]);
        $this->actingAs($restrictedUser);

        $response = $this->getForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/violations");

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_index_lists_violations_for_contributor_on_current_site(): void
    {
        $siteViolation = ContributorViolation::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'type' => 'spam',
            'severity' => 'low',
            'reason' => 'Repeated low-quality spam content.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $this->authenticatedUser->id,
        ]);

        $otherSite = Site::create(['name' => 'Other Site', 'slug' => 'other-site-violations', 'is_default' => false]);
        ContributorViolation::create([
            'user_id' => $this->contributor->id,
            'site_id' => $otherSite->id,
            'type' => 'policy',
            'severity' => 'medium',
            'reason' => 'Different site violation reason.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $this->authenticatedUser->id,
        ]);

        $response = $this->getForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/violations");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['data']);
        $this->assertEquals($siteViolation->id, $data['data'][0]['id']);
    }

    public function test_store_records_violation_and_deactivates_user_when_threshold_triggers_ban(): void
    {
        $response = $this->postForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/violations", [
            'type' => 'plagiarism',
            'severity' => 'high',
            'reason' => 'Plagiarised article copied from another publication.',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertDatabaseHas('oc_contributor_violations', [
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'action_taken' => ViolationAction::Ban->value,
        ]);
        $this->assertDatabaseHas('users', ['id' => $this->contributor->id, 'is_active' => 0]);
    }

    public function test_store_returns_422_for_reason_that_is_too_short(): void
    {
        $response = $this->postForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/violations", [
            'type' => 'spam',
            'severity' => 'low',
            'reason' => 'short',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_returns_422_for_invalid_payload(): void
    {
        $response = $this->postForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/violations", [
            'type' => 'spam',
            'severity' => 'low',
            'reason' => 'short',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_returns_validation_errors_for_invalid_enum_values(): void
    {
        $response = $this->postForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/violations", [
            'type' => 'fake',
            'severity' => 'severe',
            'reason' => 'This reason is long enough to pass length validation.',
            'action_taken' => 'delete',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Validation failed', $data['error']);
        $this->assertArrayHasKey('type', $data['errors']);
        $this->assertArrayHasKey('severity', $data['errors']);
        $this->assertArrayHasKey('action_taken', $data['errors']);
    }

    public function test_store_returns_403_for_user_without_violation_record_permission(): void
    {
        $this->enableSiteRbac();

        $restrictedUser = $this->createUser([
            'email' => 'violations-record-restricted@example.com',
            'role' => 'user',
        ]);
        $this->actingAs($restrictedUser);
        $this->grantSitePermission($restrictedUser, 'violation.view');

        $response = $this->postForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/violations", [
            'type' => 'plagiarism',
            'severity' => 'high',
            'reason' => 'This payload is valid but the actor should still be forbidden.',
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_resolve_marks_violation_resolved_and_reactivates_user(): void
    {
        $violation = ContributorViolation::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'type' => 'policy',
            'severity' => 'medium',
            'reason' => 'Serious policy breach requiring suspension.',
            'action_taken' => ViolationAction::Suspension->value,
            'created_by' => $this->authenticatedUser->id,
        ]);

        $this->contributor->update(['is_active' => false]);

        $response = $this->postForSite("/api/open-collab/admin/violations/{$violation->id}/resolve", [
            'notes' => 'Issue investigated and cleared.',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('oc_contributor_violations', [
            'id' => $violation->id,
            'resolved_by' => $this->authenticatedUser->id,
            'resolution_notes' => 'Issue investigated and cleared.',
        ]);
        $this->assertDatabaseHas('users', ['id' => $this->contributor->id, 'is_active' => 1]);
    }

    public function test_resolve_with_no_notes_still_succeeds(): void
    {
        $violation = ContributorViolation::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'type' => 'spam',
            'severity' => 'low',
            'reason' => 'Low severity spam violation that can be resolved silently.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $this->authenticatedUser->id,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/violations/{$violation->id}/resolve", []);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('oc_contributor_violations', [
            'id' => $violation->id,
            'resolved_by' => $this->authenticatedUser->id,
        ]);
    }

    public function test_admin_can_list_all_violations_for_site(): void
    {
        ContributorViolation::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'type' => 'spam',
            'severity' => 'low',
            'reason' => 'Repeated low-quality spam submissions on the platform.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $this->authenticatedUser->id,
        ]);

        $response = $this->getForSite('/api/open-collab/admin/violations');
        $data = json_decode($response->getContent(), true);

        $items = array_values(array_filter($data['data'], fn($k) => is_int($k), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $items);
        $this->assertEquals($this->contributor->id, $items[0]['user_id']);
    }

    public function test_resolve_returns_422_for_already_resolved_violation(): void
    {
        $violation = ContributorViolation::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'type' => 'quality',
            'severity' => 'low',
            'reason' => 'Quality violation that is already resolved in the system.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $this->authenticatedUser->id,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_by' => $this->authenticatedUser->id,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/violations/{$violation->id}/resolve", [
            'notes' => 'Attempting to resolve an already resolved violation.',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_resolve_returns_403_for_user_without_violation_resolve_permission(): void
    {
        $this->enableSiteRbac();

        $restrictedUser = $this->createUser([
            'email' => 'violations-resolve-restricted@example.com',
            'role' => 'user',
        ]);
        $this->actingAs($restrictedUser);
        $this->grantSitePermission($restrictedUser, 'violation.view');
        $this->grantSitePermission($restrictedUser, 'violation.record');

        $violation = ContributorViolation::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'type' => 'policy',
            'severity' => 'medium',
            'reason' => 'Resolvable violation that should still be protected.',
            'action_taken' => ViolationAction::Suspension->value,
            'created_by' => $restrictedUser->id,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/violations/{$violation->id}/resolve", [
            'notes' => 'Attempted without resolve permission.',
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'email' => 'violations@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
            'is_active' => true,
        ]);
    }

    public function test_site_wide_list_does_not_include_violations_from_other_sites(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other-viol-site', 'is_default' => false]);

        ContributorViolation::create([
            'user_id' => $this->contributor->id,
            'site_id' => $otherSite->id,
            'type' => 'policy',
            'severity' => 'medium',
            'reason' => 'Policy violation on a different site entirely.',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => $this->authenticatedUser->id,
        ]);

        $response = $this->getForSite('/api/open-collab/admin/violations');
        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data['data'], fn($k) => is_int($k), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(0, $items);
    }

}
