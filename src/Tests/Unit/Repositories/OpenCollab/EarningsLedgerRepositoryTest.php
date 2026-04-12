<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\LedgerEntryType;
use App\Models\EarningsLedger;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class EarningsLedgerRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private EarningsLedgerRepository $repository;

    // ── recordSale() ──────────────────────────────────────────────────────────

    public function test_record_sale_creates_positive_ledger_entry(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();

        $entry = $this->repository->recordSale($user->id, $page->id, 500, 'gbp', 'pi_abc');

        $this->assertInstanceOf(EarningsLedger::class, $entry);
        $this->assertEquals(LedgerEntryType::Sale->value, $entry->type);
        $this->assertEquals(500, $entry->amount);
        $this->assertEquals('pi_abc', $entry->reference_id);
    }

    // ── recordRefund() ────────────────────────────────────────────────────────

    public function test_record_refund_creates_negative_ledger_entry(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();

        $entry = $this->repository->recordRefund($user->id, $page->id, 500, 'gbp', 'pi_abc');

        $this->assertEquals(LedgerEntryType::Refund->value, $entry->type);
        $this->assertEquals(-500, $entry->amount);
    }

    public function test_record_refund_stores_negative_even_if_positive_amount_passed(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();

        $entry = $this->repository->recordRefund($user->id, $page->id, -500, 'gbp', 'pi_abc');

        $this->assertEquals(-500, $entry->amount);
    }

    // ── balanceForContributor() ───────────────────────────────────────────────

    public function test_balance_for_contributor_sums_all_entries(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();

        EarningsLedger::create(['user_id' => $user->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 600, 'currency' => 'gbp', 'reference_id' => 'a']);
        EarningsLedger::create(['user_id' => $user->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Refund->value, 'amount' => -100, 'currency' => 'gbp', 'reference_id' => 'b']);

        $balance = $this->repository->balanceForContributor($user->id);

        $this->assertEquals(500, $balance);
    }

    public function test_balance_returns_zero_when_no_entries(): void
    {
        $this->assertEquals(0, $this->repository->balanceForContributor(9999));
    }

    public function test_balance_does_not_include_other_users(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $page = $this->createPage();

        EarningsLedger::create(['user_id' => $user1->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 1000, 'currency' => 'gbp', 'reference_id' => 'x']);

        $this->assertEquals(0, $this->repository->balanceForContributor($user2->id));
    }

    // ── eligibleForPayout() ───────────────────────────────────────────────────

    public function test_eligible_for_payout_returns_entries_before_cutoff(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();
        $cutoff = new \DateTime('2024-06-01');

        $old = EarningsLedger::create([
            'user_id' => $user->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 500,
            'currency' => 'gbp',
            'reference_id' => 'old',
            'earned_at' => '2024-01-01 00:00:00',
        ]);

        $new = EarningsLedger::create([
            'user_id' => $user->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 800,
            'currency' => 'gbp',
            'reference_id' => 'new',
            'earned_at' => '2024-12-01 00:00:00', // after cutoff
        ]);

        $results = $this->repository->eligibleForPayout($user->id, $cutoff);

        $this->assertCount(1, $results);
        $this->assertEquals($old->id, $results->first()->id);
    }

    public function test_eligible_for_payout_excludes_already_paid_entries(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();
        $cutoff = new \DateTime('2025-01-01');

        EarningsLedger::create([
            'user_id' => $user->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 500,
            'currency' => 'gbp',
            'reference_id' => 'paid',
            'paid_at' => '2024-03-01 00:00:00', // already paid
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $results = $this->repository->eligibleForPayout($user->id, $cutoff);

        $this->assertCount(0, $results);
    }

    public function test_eligible_for_payout_returns_entries_ordered_oldest_first(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();
        $cutoff = new \DateTime('2025-01-01');

        $older = EarningsLedger::create([
            'user_id' => $user->id, 'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value, 'amount' => 300,
            'currency' => 'gbp', 'reference_id' => 'r1', 'earned_at' => '2024-01-01',
        ]);
        $newer = EarningsLedger::create([
            'user_id' => $user->id, 'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value, 'amount' => 400,
            'currency' => 'gbp', 'reference_id' => 'r2', 'earned_at' => '2024-06-01',
        ]);

        $results = $this->repository->eligibleForPayout($user->id, $cutoff);

        $this->assertEquals($older->id, $results->first()->id);
        $this->assertEquals($newer->id, $results->last()->id);
    }

    // ── eligibleBalanceForContributor() ───────────────────────────────────────

    public function test_eligible_balance_sums_eligible_entries_only(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();
        $cutoff = new \DateTime('2025-01-01');

        EarningsLedger::create(['user_id' => $user->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 600, 'currency' => 'gbp', 'reference_id' => 'a', 'earned_at' => '2024-01-01']);
        EarningsLedger::create(['user_id' => $user->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 400, 'currency' => 'gbp', 'reference_id' => 'b', 'earned_at' => '2024-06-01']);
        // Already paid — must not count
        EarningsLedger::create(['user_id' => $user->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 999, 'currency' => 'gbp', 'reference_id' => 'c', 'earned_at' => '2024-01-01', 'paid_at' => '2024-02-01']);

        $balance = $this->repository->eligibleBalanceForContributor($user->id, $cutoff);

        $this->assertEquals(1000, $balance);
    }

    public function test_eligible_balance_returns_zero_when_no_eligible_entries(): void
    {
        $user = $this->createUser();
        $cutoff = new \DateTime('2020-01-01'); // all entries are after this

        $this->assertEquals(0, $this->repository->eligibleBalanceForContributor($user->id, $cutoff));
    }

    // ── eligibleGroupedBySiteAndUser() ────────────────────────────────────────

    public function test_eligible_grouped_returns_entries_grouped_by_user(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $page1 = $this->createPage(['site_id' => $this->siteId, 'contributor_id' => $user1->id]);
        $page2 = $this->createPage(['site_id' => $this->siteId, 'contributor_id' => $user2->id]);
        $cutoff = new \DateTime('2025-01-01');

        EarningsLedger::create(['user_id' => $user1->id, 'article_id' => $page1->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 500, 'currency' => 'GBP', 'reference_id' => 'u1', 'earned_at' => '2024-01-01']);
        EarningsLedger::create(['user_id' => $user2->id, 'article_id' => $page2->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 800, 'currency' => 'GBP', 'reference_id' => 'u2', 'earned_at' => '2024-01-01']);

        $grouped = $this->repository->eligibleGroupedBySiteAndUser($this->siteId, $cutoff);

        $this->assertArrayHasKey($user1->id, $grouped);
        $this->assertArrayHasKey($user2->id, $grouped);
        $this->assertEquals(500, $grouped[$user1->id][0]['amount']);
        $this->assertEquals(800, $grouped[$user2->id][0]['amount']);
    }

    public function test_eligible_grouped_excludes_paid_entries(): void
    {
        $user = $this->createUser();
        $page = $this->createPage(['site_id' => $this->siteId]);
        $cutoff = new \DateTime('2025-01-01');

        EarningsLedger::create([
            'user_id' => $user->id, 'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value, 'amount' => 500,
            'currency' => 'GBP', 'reference_id' => 'paid', 'paid_at' => '2024-02-01',
            'created_at' => '2024-01-01',
        ]);

        $grouped = $this->repository->eligibleGroupedBySiteAndUser($this->siteId, $cutoff);

        $this->assertArrayNotHasKey($user->id, $grouped);
    }

    public function test_eligible_grouped_excludes_entries_after_cutoff(): void
    {
        $user = $this->createUser();
        $page = $this->createPage(['site_id' => $this->siteId]);
        $cutoff = new \DateTime('2024-01-01');

        EarningsLedger::create([
            'user_id' => $user->id, 'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value, 'amount' => 500,
            'currency' => 'GBP', 'reference_id' => 'future',
            'created_at' => '2024-06-01', // after cutoff
        ]);

        $grouped = $this->repository->eligibleGroupedBySiteAndUser($this->siteId, $cutoff);

        $this->assertArrayNotHasKey($user->id, $grouped);
    }

    public function test_eligible_grouped_is_scoped_to_site(): void
    {
        $user = $this->createUser();
        $otherSite = $this->createSite();
        $page = $this->createPage(['site_id' => $otherSite->id]);
        $cutoff = new \DateTime('2025-01-01');

        EarningsLedger::create([
            'user_id' => $user->id, 'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value, 'amount' => 500,
            'currency' => 'GBP', 'reference_id' => 'other-site',
            'created_at' => '2024-01-01',
        ]);

        $grouped = $this->repository->eligibleGroupedBySiteAndUser($this->siteId, $cutoff);

        $this->assertArrayNotHasKey($user->id, $grouped);
    }

    public function test_eligible_grouped_returns_empty_array_when_no_eligible_entries(): void
    {
        $cutoff = new \DateTime('2020-01-01');

        $grouped = $this->repository->eligibleGroupedBySiteAndUser($this->siteId, $cutoff);

        $this->assertEmpty($grouped);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EarningsLedgerRepository();
    }
}