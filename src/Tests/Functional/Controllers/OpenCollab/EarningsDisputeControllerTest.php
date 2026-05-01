<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Enums\OpenCollab\DisputeStatus;
use App\Enums\OpenCollab\LedgerEntryType;
use App\Framework\Container;
use App\Models\EarningsDispute;
use App\Models\EarningsLedger;
use App\Models\User;
use App\Services\OpenCollab\EarningsDisputeService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

/**
 * EarningsDisputeService is mocked at the container level for the write paths
 * so no real DB side-effects from the service are triggered.
 *
 * The read paths (index, adminIndex) hit the real repository so we can assert
 * correct scoping without additional mocking.
 *
 * Tests cover:
 *   - Contributor: raise (store), list own disputes (index)
 *   - Admin: list open disputes (adminIndex), resolve, reject
 *   - Validation, auth, and error mapping for all endpoints
 */
class EarningsDisputeControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;
    private User $admin;

    // ── POST /disputes (store) ────────────────────────────────────────────────
    public function test_contributor_can_raise_dispute_against_their_ledger_entry(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage(['contributor_id' => $this->contributor->id]);
        $ledger = EarningsLedger::create([
            'user_id' => $this->contributor->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 5000,
            'currency' => 'GBP',
            'reference_id' => 'sale-dispute-1',
        ]);

        $response = $this->postForSite('/api/open-collab/disputes', [
            'earnings_ledger_id' => $ledger->id,
            'reason' => 'The amount credited does not match the sale price shown on my article.',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertDatabaseHas('oc_earnings_disputes', [
            'user_id' => $this->contributor->id,
            'earnings_ledger_id' => $ledger->id,
            'status' => 'open',
        ]);
    }

    public function test_contributor_cannot_raise_duplicate_dispute_for_same_ledger_entry(): void
    {
        $this->actingAs($this->contributor);

        $page = $this->createPage(['contributor_id' => $this->contributor->id]);
        $ledger = EarningsLedger::create([
            'user_id' => $this->contributor->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 3000,
            'currency' => 'GBP',
            'reference_id' => 'sale-dispute-2',
        ]);

        EarningsDispute::create([
            'user_id' => $this->contributor->id,
            'earnings_ledger_id' => $ledger->id,
            'reason' => 'Already disputed this entry.',
            'status' => 'open',
        ]);

        $response = $this->postForSite('/api/open-collab/disputes', [
            'earnings_ledger_id' => $ledger->id,
            'reason' => 'Trying to dispute it again, which should fail.',
        ]);

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function test_store_returns_422_when_ledger_id_is_missing(): void
    {
        $this->actingAs($this->contributor);

        $response = $this->postForSite('/api/open-collab/disputes', [
            'reason' => 'A reason long enough to pass validation.',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_returns_422_when_reason_is_too_short(): void
    {
        $this->actingAs($this->contributor);

        $response = $this->postForSite('/api/open-collab/disputes', [
            'earnings_ledger_id' => 10,
            'reason' => 'Too short',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_returns_409_when_dispute_already_raised_for_ledger_entry(): void
    {
        $this->actingAs($this->contributor);

        $this->bindDisputeService(fn($mock) => $mock->shouldReceive('raise')
            ->andThrow(new \RuntimeException('A dispute has already been raised for ledger entry [10].'))
        );

        $response = $this->postForSite('/api/open-collab/disputes', [
            'earnings_ledger_id' => 10,
            'reason' => 'This is a valid reason for the dispute.',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertStringContainsString('already been raised', $data['error']);
    }

    public function test_store_returns_422_when_ledger_entry_does_not_belong_to_user(): void
    {
        $this->actingAs($this->contributor);

        $this->bindDisputeService(fn($mock) => $mock->shouldReceive('raise')
            ->andThrow(new \InvalidArgumentException('Ledger entry [10] not found or does not belong to user.'))
        );

        $response = $this->postForSite('/api/open-collab/disputes', [
            'earnings_ledger_id' => 10,
            'reason' => 'This is a valid reason for the dispute.',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_store_returns_401_for_unauthenticated_request(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();
        $earningsLedger = EarningsLedger::create([
            'user_id' => $user->id,
            'article_id' => $page->id,
            'type' => 'test',
            'amount' => 10,
            'currency' => 'GBP',
            'reference_id' => 'test',
        ]);

        $this->unauthenticate();

        $response = $this->postForSiteUnauthenticated('/api/open-collab/disputes', [
            'earnings_ledger_id' => $earningsLedger->id,
            'reason' => 'A valid reason that is long enough.',
        ]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    // ── GET /disputes (contributor index) ─────────────────────────────────────

    public function test_contributor_can_list_their_own_disputes(): void
    {
        $this->actingAs($this->contributor);

        $response = $this->getForSite('/api/open-collab/disputes');

        // Returns 200 — empty list is fine, we just care about the shape.
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_contributor_cannot_see_other_contributors_disputes(): void
    {
        $other = $this->createUser([
            'email' => 'other-dispute-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
        $this->actingAs($this->contributor);

        $page = $this->createPage(['contributor_id' => $other->id]);
        $ledger = EarningsLedger::create([
            'user_id' => $other->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 8000,
            'currency' => 'GBP',
            'reference_id' => 'sale-other-1',
        ]);

        EarningsDispute::create([
            'user_id' => $other->id,
            'earnings_ledger_id' => $ledger->id,
            'reason' => 'Other contributor dispute, should not be visible.',
            'status' => 'open',
        ]);

        $response = $this->getForSite('/api/open-collab/disputes');
        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(0, $items);
    }

    public function test_admin_can_list_all_open_disputes_for_site(): void
    {
        $page = $this->createPage(['contributor_id' => $this->contributor->id]);
        $ledger = EarningsLedger::create([
            'user_id' => $this->contributor->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 4000,
            'currency' => 'GBP',
            'reference_id' => 'sale-admin-list-1',
        ]);

        EarningsDispute::create([
            'user_id' => $this->contributor->id,
            'earnings_ledger_id' => $ledger->id,
            'reason' => 'Amount is wrong and needs investigation now.',
            'status' => 'open',
        ]);

        $response = $this->getForSite('/api/open-collab/admin/disputes');
        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data['data'], fn($k) => is_int($k), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $items);
        $this->assertEquals('open', $items[0]['status']);
    }

    public function test_admin_can_resolve_open_dispute(): void
    {
        $page = $this->createPage(['contributor_id' => $this->contributor->id]);
        $ledger = EarningsLedger::create([
            'user_id' => $this->contributor->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 6000,
            'currency' => 'GBP',
            'reference_id' => 'sale-resolve-1',
        ]);

        $dispute = EarningsDispute::create([
            'user_id' => $this->contributor->id,
            'earnings_ledger_id' => $ledger->id,
            'reason' => 'Sale amount does not match the article price listed.',
            'status' => 'open',
        ]);

        $response = $this->postForSite("/api/open-collab/admin/disputes/{$dispute->id}/resolve", [
            'admin_notes' => 'Reviewed and confirmed. Amount adjusted accordingly by finance.',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('oc_earnings_disputes', [
            'id' => $dispute->id,
            'status' => 'resolved',
        ]);
    }

    // ── GET /admin/disputes (adminIndex) ──────────────────────────────────────

    public function test_admin_can_list_open_disputes_for_site(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getForSite('/api/open-collab/admin/disputes');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
    }

    // ── POST /admin/disputes/{id}/resolve ────────────────────────────────────

    public function test_admin_can_resolve_a_dispute(): void
    {
        $this->actingAs($this->admin);

        $this->bindDisputeService(fn($mock) => $mock->shouldReceive('resolve')
            ->once()
            ->withArgs(fn($disputeId, $adminId, $adminNotes) => $disputeId === 7
                && $adminId === $this->admin->id
                && $adminNotes === 'We reviewed and confirmed the error.'
            )
            ->andReturn($this->makeDisputeModel(['status' => DisputeStatus::Resolved->value]))
        );

        $response = $this->postForSite('/api/open-collab/admin/disputes/7/resolve', [
            'admin_notes' => 'We reviewed and confirmed the error.',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(DisputeStatus::Resolved->value, $data['data']['dispute']['status']);
        $this->assertStringContainsString('resolved', strtolower($data['data']['message']));
    }

    public function test_admin_can_resolve_dispute_with_adjustment(): void
    {
        $this->actingAs($this->admin);

        $this->bindDisputeService(fn($mock) => $mock->shouldReceive('resolve')
            ->once()
            ->withArgs(fn($disputeId, $adminId, $adminNotes, $adjustmentAmount, $adjustmentReason) => $adjustmentAmount === 500
                && $adjustmentReason === 'Calculation error on our side.'
            )
            ->andReturn($this->makeDisputeModel(['status' => DisputeStatus::Resolved->value]))
        );

        $response = $this->postForSite('/api/open-collab/admin/disputes/7/resolve', [
            'admin_notes' => 'Confirmed error, adjusting by £5.',
            'adjustment_amount' => 500,
            'adjustment_reason' => 'Calculation error on our side.',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_resolve_returns_422_when_admin_notes_are_missing(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postForSite('/api/open-collab/admin/disputes/7/resolve', []);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('Admin notes', $data['error']);
    }

    public function test_resolve_returns_422_when_dispute_is_not_open(): void
    {
        $this->actingAs($this->admin);

        $this->bindDisputeService(fn($mock) => $mock->shouldReceive('resolve')
            ->andThrow(new \InvalidArgumentException('Dispute [7] is not open (status: resolved).'))
        );

        $response = $this->postForSite('/api/open-collab/admin/disputes/7/resolve', [
            'admin_notes' => 'Trying to resolve an already-resolved dispute.',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_resolve_returns_422_when_adjustment_amount_given_without_reason(): void
    {
        $this->actingAs($this->admin);

        $this->bindDisputeService(fn($mock) => $mock->shouldReceive('resolve')
            ->andThrow(new \InvalidArgumentException('Adjustment reason is required when an adjustment amount is provided.'))
        );

        $response = $this->postForSite('/api/open-collab/admin/disputes/7/resolve', [
            'admin_notes' => 'Some notes here.',
            'adjustment_amount' => 500,
            // adjustment_reason intentionally omitted
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('Adjustment reason', $data['error']);
    }

    //── POST /admin/disputes/{id}/reject ─────────────────────────────────────

    public function test_admin_can_reject_a_dispute(): void
    {
        $this->actingAs($this->admin);

        $this->bindDisputeService(fn($mock) => $mock->shouldReceive('reject')
            ->once()
            ->withArgs(fn($disputeId, $adminId, $adminNotes) => $disputeId === 7
                && $adminId === $this->admin->id
                && $adminNotes === 'The amount charged is correct per the contract.'
            )
            ->andReturn($this->makeDisputeModel(['status' => DisputeStatus::Rejected->value]))
        );

        $response = $this->postForSite('/api/open-collab/admin/disputes/7/reject', [
            'admin_notes' => 'The amount charged is correct per the contract.',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(DisputeStatus::Rejected->value, $data['data']['dispute']['status']);
        $this->assertStringContainsString('rejected', strtolower($data['data']['message']));
    }

    public function test_reject_returns_422_when_admin_notes_are_missing(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postForSite('/api/open-collab/admin/disputes/7/reject', []);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('Admin notes', $data['error']);
    }

    public function test_reject_returns_422_when_dispute_is_not_open(): void
    {
        $this->actingAs($this->admin);

        $this->bindDisputeService(fn($mock) => $mock->shouldReceive('reject')
            ->andThrow(new \InvalidArgumentException('Dispute [7] is not open (status: rejected).'))
        );

        $response = $this->postForSite('/api/open-collab/admin/disputes/7/reject', [
            'admin_notes' => 'Trying to reject an already-rejected dispute.',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'email' => 'dispute-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        $this->admin = $this->createUser([
            'email' => 'dispute-admin@example.com',
            'role' => 'admin',
        ]);

        // Default binding — no service calls expected unless overridden per-test.
//        $this->bindDisputeService(fn($mock) =>
//        $mock->shouldReceive('raise')->never()
//            ->shouldReceive('resolve')->never()
//            ->shouldReceive('reject')->never()
//        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bindDisputeService(callable $configure): void
    {
        Container::getInstance()->bind(EarningsDisputeService::class, function () use ($configure) {
            $mock = Mockery::mock(EarningsDisputeService::class);
            $configure($mock);
            return $mock;
        });
    }

    private function makeDisputeModel(array $overrides = []): \App\Models\EarningsDispute
    {
        $dispute = new \App\Models\EarningsDispute(array_merge([
            'id' => 1,
            'user_id' => $this->contributor->id,
            'earnings_ledger_id' => 10,
            'reason' => 'Test dispute reason.',
            'status' => DisputeStatus::Open->value,
            'admin_notes' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
        $dispute->exists = true;
        return $dispute;
    }
}