<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\ContributorRequest;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ContributorRequestControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function setUp(): void
    {
        parent::setUp();

        $this->seedContributorRequestFieldsForTest();
    }

    // ── Public submission ──────────────────────────────────────────────────────

    public function test_anyone_can_submit_a_contributor_request(): void
    {
        $response = $this->postForSiteUnauthenticated('/api/open-collab/contributor-requests', [
            'email' => 'newwriter@example.com',
            'name' => 'New Writer',
            'bio' => 'I write about technology and software engineering for fun and professionally.',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertDatabaseHas('oc_contributor_requests', [
            'email' => 'newwriter@example.com',
            // 'site_id' => $this->siteId,
        ]);
    }

    public function test_store_returns_422_when_email_is_invalid(): void
    {
        $response = $this->postForSiteUnauthenticated('/api/open-collab/contributor-requests', [
            'email' => 'not-an-email',
            'name' => 'Test',
            'bio' => 'This is a valid bio that meets the minimum length requirement.',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_returns_422_when_name_is_too_short(): void
    {
        $response = $this->postForSiteUnauthenticated('/api/open-collab/contributor-requests', [
            'email' => 'valid@example.com',
            'name' => 'X',
            'bio' => 'This is a valid bio that meets the minimum length requirement.',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_returns_422_when_bio_is_too_short(): void
    {
        $response = $this->postForSiteUnauthenticated('/api/open-collab/contributor-requests', [
            'email' => 'valid@example.com',
            'name' => 'Valid Name',
            'bio' => 'Too short.',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_indicates_when_approval_is_required(): void
    {
        // Set site to require approval
        \App\Models\Site::find($this->siteId)?->update(['require_invite_approval' => true]);

        $response = $this->postForSiteUnauthenticated('/api/open-collab/contributor-requests', [
            'email' => 'pendingapproval@example.com',
            'name' => 'Pending Approval',
            'bio' => 'I want to write articles about cooking and food culture for your platform.',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($data['data']['requires_approval']);
    }

    // ── Admin endpoints ────────────────────────────────────────────────────────

    public function test_admin_can_list_pending_contributor_requests(): void
    {
        ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'pending-request@example.com',
            'name' => 'Pending Writer',
            'bio' => 'A well-crafted bio about wanting to write for this publication.',
            'status' => 'pending',
        ]);

        $response = $this->getForSite('/api/open-collab/admin/contributor-requests');
        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data['data'], fn($k) => is_int($k), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $items);
        $this->assertEquals('pending-request@example.com', $items[0]['email']);
    }

    public function test_admin_can_approve_pending_request_and_sends_invitation(): void
    {
        $request = ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'approve-me@example.com',
            'name' => 'Approvable Writer',
            'bio' => 'Bio that makes this person a good candidate for approval.',
            'status' => 'pending',
        ]);

        $response = $this->postForSite("/api/open-collab/admin/contributor-requests/{$request->id}/approve");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('invitation', $data['data']);
        $this->assertEquals('approve-me@example.com', $data['data']['invitation']['email']);
        $this->assertDatabaseHas('oc_invitations', [
            'email' => 'approve-me@example.com',
            'site_id' => $this->siteId,
        ]);
    }

    public function test_approve_returns_422_when_request_is_already_approved(): void
    {
        $request = ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'already-approved@example.com',
            'name' => 'Already Approved',
            'bio' => 'Bio for someone who was already approved previously.',
            'status' => 'approved',
        ]);

        $response = $this->postForSite("/api/open-collab/admin/contributor-requests/{$request->id}/approve");

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_admin_can_reject_pending_request(): void
    {
        $request = ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'reject-me@example.com',
            'name' => 'Rejectable Writer',
            'bio' => 'Bio for a writer who does not meet our current requirements.',
            'status' => 'pending',
        ]);

        $response = $this->postForSite("/api/open-collab/admin/contributor-requests/{$request->id}/reject", [
            'reason' => 'Not currently accepting contributors in this niche.',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('oc_contributor_requests', [
            'id' => $request->id,
            'status' => 'rejected',
        ]);
    }

    public function test_reject_works_without_a_reason(): void
    {
        $request = ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'reject-no-reason@example.com',
            'name' => 'Silent Rejection',
            'bio' => 'Bio for a writer being rejected without an explicit reason given.',
            'status' => 'pending',
        ]);

        $response = $this->postForSite("/api/open-collab/admin/contributor-requests/{$request->id}/reject", []);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_index_returns_only_pending_requests_not_approved_or_rejected(): void
    {
        ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'still-pending@example.com',
            'name' => 'Still Pending',
            'bio' => 'This request is still pending review and should appear in the list.',
            'status' => 'pending',
        ]);
        ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'was-approved@example.com',
            'name' => 'Was Approved',
            'bio' => 'This request was already approved and should not appear.',
            'status' => 'approved',
        ]);
        ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'was-rejected@example.com',
            'name' => 'Was Rejected',
            'bio' => 'This request was rejected previously and should not appear.',
            'status' => 'rejected',
        ]);

        $response = $this->getForSite('/api/open-collab/admin/contributor-requests');
        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data['data'], fn($k) => is_int($k), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $items);
        $this->assertEquals('still-pending@example.com', $items[0]['email']);
    }

    public function test_unauthenticated_user_cannot_list_requests(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/open-collab/admin/contributor-requests');

        $this->assertEquals(401, $response->getStatusCode());
    }

    private function seedContributorRequestFieldsForTest(): void
    {
        foreach ([
                     ['key' => 'name', 'name' => 'Full name', 'type' => 'text', 'render_type' => 'input', 'sort_order' => 10],
                     ['key' => 'email', 'name' => 'Email address', 'type' => 'email', 'render_type' => 'input', 'sort_order' => 20],
                     ['key' => 'bio', 'name' => 'Tell us about yourself', 'type' => 'textarea', 'render_type' => 'textarea', 'sort_order' => 30],
                 ] as $field) {
            \App\Models\CustomFieldDefinition::create([
                'site_id' => $this->siteId,
                'context' => 'contributor_request',
                'description' => '',
                'placeholder' => '',
                'is_required' => true,
                'validation_rules' => json_encode(['required']),
                'options' => json_encode([]),
                'is_active' => true,
                ...$field,
            ]);
        }
    }
}