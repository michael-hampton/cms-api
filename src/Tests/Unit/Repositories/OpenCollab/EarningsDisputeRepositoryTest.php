<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\DisputeStatus;
use App\Enums\OpenCollab\LedgerEntryType;
use App\Models\EarningsDispute;
use App\Models\EarningsLedger;
use App\Models\Model;
use App\Repositories\OpenCollab\EarningsDisputeRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class EarningsDisputeRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private EarningsDisputeRepository $repository;

    // ── createForUser() ───────────────────────────────────────────────────────

    public function test_create_for_user_persists_open_dispute(): void
    {
        $user = $this->createUser();
        $ledger = $this->createLedgerEntry($user->id);

        $dispute = $this->repository->createForUser($user->id, $ledger->id, 'Amount seems wrong.');

        $this->assertInstanceOf(EarningsDispute::class, $dispute);
        $this->assertEquals($user->id, $dispute->user_id);
        $this->assertEquals($ledger->id, $dispute->earnings_ledger_id);
        $this->assertEquals('Amount seems wrong.', $dispute->reason);
        $this->assertEquals(DisputeStatus::Open->value, $dispute->status);
        $this->assertNotNull($dispute->created_at);
    }

    // ── markResolved() ────────────────────────────────────────────────────────

    private function createLedgerEntry(int $userId): Model
    {
        $page = $this->createPage(['contributor_id' => $userId]);

        return EarningsLedger::create([
            'user_id' => $userId,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 500,
            'currency' => 'GBP',
            'reference_id' => 'test-' . uniqid(),
        ]);
    }

    public function test_mark_resolved_sets_status_and_admin_notes(): void
    {
        $user = $this->createUser();
        $ledger = $this->createLedgerEntry($user->id);
        $dispute = $this->repository->createForUser($user->id, $ledger->id, 'reason');

        $resolved = $this->repository->markResolved($dispute->id, 'Reviewed and corrected.');

        $this->assertEquals(DisputeStatus::Resolved->value, $resolved->status);
        $this->assertEquals('Reviewed and corrected.', $resolved->admin_notes);
    }

    // ── markRejected() ────────────────────────────────────────────────────────

    public function test_mark_resolved_returns_updated_model(): void
    {
        $user = $this->createUser();
        $ledger = $this->createLedgerEntry($user->id);
        $dispute = $this->repository->createForUser($user->id, $ledger->id, 'reason');

        $result = $this->repository->markResolved($dispute->id, 'notes');

        $this->assertInstanceOf(EarningsDispute::class, $result);
        $this->assertEquals($dispute->id, $result->id);
    }

    // ── forContributor() ──────────────────────────────────────────────────────

    public function test_mark_rejected_sets_status_and_admin_notes(): void
    {
        $user = $this->createUser();
        $ledger = $this->createLedgerEntry($user->id);
        $dispute = $this->repository->createForUser($user->id, $ledger->id, 'reason');

        $rejected = $this->repository->markRejected($dispute->id, 'Amount is correct per terms.');

        $this->assertEquals(DisputeStatus::Rejected->value, $rejected->status);
        $this->assertEquals('Amount is correct per terms.', $rejected->admin_notes);
    }

    public function test_for_contributor_returns_disputes_for_that_user_only(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $ledger = $this->createLedgerEntry($user1->id);

        $this->repository->createForUser($user1->id, $ledger->id, 'User 1 dispute');

        $ledger2 = $this->createLedgerEntry($user2->id);
        $this->repository->createForUser($user2->id, $ledger2->id, 'User 2 dispute');

        $results = $this->repository->forContributor($user1->id);

        $this->assertCount(1, $results);
        $this->assertEquals($user1->id, $results->first()->user_id);
    }

    // ── hasOpenDisputeForLedgerEntry() ────────────────────────────────────────

    public function test_for_contributor_orders_newest_first(): void
    {
        $user = $this->createUser();
        $ledger = $this->createLedgerEntry($user->id);

        $older = EarningsDispute::create([
            'user_id' => $user->id,
            'earnings_ledger_id' => $ledger->id,
            'reason' => 'Old dispute',
            'status' => DisputeStatus::Open->value,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);
        $newer = EarningsDispute::create([
            'user_id' => $user->id,
            'earnings_ledger_id' => $ledger->id,
            'reason' => 'New dispute',
            'status' => DisputeStatus::Open->value,
            'created_at' => '2024-06-01 00:00:00',
            'updated_at' => '2024-06-01 00:00:00',
        ]);

        $results = $this->repository->forContributor($user->id);

        $this->assertEquals($newer->id, $results->first()->id);
    }

    public function test_has_open_dispute_returns_true_when_open_dispute_exists(): void
    {
        $user = $this->createUser();
        $ledger = $this->createLedgerEntry($user->id);

        $this->repository->createForUser($user->id, $ledger->id, 'reason');

        $this->assertTrue($this->repository->hasOpenDisputeForLedgerEntry($user->id, $ledger->id));
    }

    public function test_has_open_dispute_returns_false_when_no_dispute_exists(): void
    {
        $user = $this->createUser();
        $ledger = $this->createLedgerEntry($user->id);

        $this->assertFalse($this->repository->hasOpenDisputeForLedgerEntry($user->id, $ledger->id));
    }

    public function test_has_open_dispute_returns_false_when_dispute_is_resolved(): void
    {
        $user = $this->createUser();
        $ledger = $this->createLedgerEntry($user->id);

        $dispute = $this->repository->createForUser($user->id, $ledger->id, 'reason');
        $this->repository->markResolved($dispute->id, 'Fixed.');

        $this->assertFalse($this->repository->hasOpenDisputeForLedgerEntry($user->id, $ledger->id));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function test_has_open_dispute_returns_false_for_different_user(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $ledger = $this->createLedgerEntry($user1->id);

        $this->repository->createForUser($user1->id, $ledger->id, 'reason');

        $this->assertFalse($this->repository->hasOpenDisputeForLedgerEntry($user2->id, $ledger->id));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EarningsDisputeRepository();
    }
}