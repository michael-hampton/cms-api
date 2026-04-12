<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\ContributorRequest;
use App\Repositories\OpenCollab\ContributorRequestRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ContributorRequestRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ContributorRequestRepository $repository;

    public function test_has_pending_request_returns_true_when_pending_exists(): void
    {
        ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'bio' => 'Test bio for contributor request.',
            'status' => 'pending',
        ]);

        $this->assertTrue($this->repository->hasPendingRequest('test@example.com', $this->siteId));
    }

    public function test_has_pending_request_returns_false_when_none_exists(): void
    {
        $this->assertFalse($this->repository->hasPendingRequest('nobody@example.com', $this->siteId));
    }

    public function test_has_pending_request_returns_false_for_approved_request(): void
    {
        ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'approved@example.com',
            'name' => 'Approved User',
            'bio' => 'This request was previously approved.',
            'status' => 'approved',
        ]);

        $this->assertFalse($this->repository->hasPendingRequest('approved@example.com', $this->siteId));
    }

    public function test_has_pending_request_returns_false_for_rejected_request(): void
    {
        ContributorRequest::create([
            'site_id' => $this->siteId,
            'email' => 'rejected@example.com',
            'name' => 'Rejected User',
            'bio' => 'This request was previously rejected.',
            'status' => 'rejected',
        ]);

        $this->assertFalse($this->repository->hasPendingRequest('rejected@example.com', $this->siteId));
    }

    public function test_has_pending_request_is_site_scoped(): void
    {
        $otherSite = $this->createSite();
        ContributorRequest::create([
            'site_id' => $otherSite->id,
            'email' => 'scoped@example.com',
            'name' => 'Scoped User',
            'bio' => 'Request on a different site entirely.',
            'status' => 'pending',
        ]);

        $this->assertFalse($this->repository->hasPendingRequest('scoped@example.com', $this->siteId));
    }

    public function test_pending_for_site_returns_only_pending_requests(): void
    {
        ContributorRequest::create(['site_id' => $this->siteId, 'email' => 'p1@example.com', 'name' => 'Pending One', 'bio' => 'Pending request one for site.', 'status' => 'pending']);
        ContributorRequest::create(['site_id' => $this->siteId, 'email' => 'p2@example.com', 'name' => 'Approved Two', 'bio' => 'Approved request two for site.', 'status' => 'approved']);
        ContributorRequest::create(['site_id' => $this->siteId, 'email' => 'p3@example.com', 'name' => 'Pending Three', 'bio' => 'Pending request three for site.', 'status' => 'pending']);

        $results = $this->repository->pendingForSite($this->siteId);

        $this->assertCount(2, $results);
        $results->each(fn($r) => $this->assertEquals('pending', $r->status));
    }

    public function test_pending_for_site_orders_oldest_first(): void
    {
        $older = ContributorRequest::create(['site_id' => $this->siteId, 'email' => 'old@example.com', 'name' => 'Old Request', 'bio' => 'Older pending request for testing.', 'status' => 'pending']);
        $this->database->query('UPDATE oc_contributor_requests SET created_at = ? WHERE id = ?', ['2024-01-01 00:00:00', $older->id]);
        $newer = ContributorRequest::create(['site_id' => $this->siteId, 'email' => 'new@example.com', 'name' => 'New Request', 'bio' => 'Newer pending request for testing.', 'status' => 'pending']);
        $this->database->query('UPDATE oc_contributor_requests SET created_at = ? WHERE id = ?', ['2024-06-01 00:00:00', $newer->id]);

        $results = $this->repository->pendingForSite($this->siteId);

        $this->assertEquals($older->id, $results->first()->id);
    }

    public function test_pending_for_site_excludes_other_sites(): void
    {
        $otherSite = $this->createSite();
        ContributorRequest::create(['site_id' => $otherSite->id, 'email' => 'other@example.com', 'name' => 'Other Site', 'bio' => 'Request on different site for testing.', 'status' => 'pending']);
        ContributorRequest::create(['site_id' => $this->siteId, 'email' => 'mine@example.com', 'name' => 'This Site', 'bio' => 'Request on current site for testing.', 'status' => 'pending']);

        $results = $this->repository->pendingForSite($this->siteId);

        $this->assertCount(1, $results);
        $this->assertEquals($this->siteId, $results->first()->site_id);
    }

    public function test_all_for_site_returns_all_statuses_newest_first(): void
    {
        ContributorRequest::create(['site_id' => $this->siteId, 'email' => 'a@example.com', 'name' => 'A', 'bio' => 'Bio for request A.', 'status' => 'pending']);
        ContributorRequest::create(['site_id' => $this->siteId, 'email' => 'b@example.com', 'name' => 'B', 'bio' => 'Bio for request B.', 'status' => 'approved']);
        ContributorRequest::create(['site_id' => $this->siteId, 'email' => 'c@example.com', 'name' => 'C', 'bio' => 'Bio for request C.', 'status' => 'rejected']);

        $results = $this->repository->allForSite($this->siteId);

        $this->assertCount(3, $results);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ContributorRequestRepository();
    }
}