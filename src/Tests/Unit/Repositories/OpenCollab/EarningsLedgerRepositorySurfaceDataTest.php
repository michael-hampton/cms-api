<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Enums\OpenCollab\LedgerEntryType;
use App\Models\EarningsLedger;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class EarningsLedgerRepositorySurfaceDataTest extends RepositoryTestCase
{
    use CreatesTestData;

    private EarningsLedgerRepository $repository;

    public function test_total_earnings_for_contributor_uses_ledger_and_site_scope(): void
    {
        $user = $this->createUser();
        $page = $this->createPage(['site_id' => $this->siteId, 'title' => 'Site Article']);
        $otherSite = $this->createSite(['slug' => 'other-site-' . uniqid(), 'is_default' => false]);
        $otherPage = $this->createPage(['site_id' => $otherSite->id, 'title' => 'Other Site Article']);

        $this->ledgerEntry($user->id, $page->id, 6299, AccrualStatus::Settled, 'sale-a');
        $this->ledgerEntry($user->id, $page->id, -1000, AccrualStatus::Settled, 'refund-a', LedgerEntryType::Refund);
        $this->ledgerEntry($user->id, $page->id, 5000, AccrualStatus::Reversed, 'reversed-a');
        $this->ledgerEntry($user->id, $otherPage->id, 9999, AccrualStatus::Settled, 'other-site');

        $this->assertSame(5299, $this->repository->totalEarningsForContributor($user->id, $this->siteId));
        $this->assertSame(15298, $this->repository->totalEarningsForContributor($user->id));
    }

    public function test_earnings_breakdown_returns_page_title_total_and_percent_from_ledger(): void
    {
        $user = $this->createUser();
        $pageA = $this->createPage(['site_id' => $this->siteId, 'title' => 'First Article']);
        $pageB = $this->createPage(['site_id' => $this->siteId, 'title' => 'Second Article']);

        $this->ledgerEntry($user->id, $pageA->id, 6000, AccrualStatus::Settled, 'sale-a');
        $this->ledgerEntry($user->id, $pageB->id, 3000, AccrualStatus::Confirmed, 'sale-b');
        $this->ledgerEntry($user->id, $pageB->id, 1000, AccrualStatus::Reversed, 'reversed-b');

        $breakdown = $this->repository->earningsBreakdownForContributor($user->id, $this->siteId);

        $this->assertCount(2, $breakdown);
        $this->assertSame($pageA->id, $breakdown[0]['page_id']);
        $this->assertSame('First Article', $breakdown[0]['title']);
        $this->assertSame(6000, $breakdown[0]['total']);
        $this->assertSame(100.0, $breakdown[0]['percent']);

        $this->assertSame($pageB->id, $breakdown[1]['page_id']);
        $this->assertSame('Second Article', $breakdown[1]['title']);
        $this->assertSame(3000, $breakdown[1]['total']);
        $this->assertSame(50.0, $breakdown[1]['percent']);
    }

    public function test_transaction_history_returns_joined_page_title_and_formatted_date(): void
    {
        $user = $this->createUser();
        $page = $this->createPage(['site_id' => $this->siteId, 'title' => 'Joined Title']);

        $this->ledgerEntry(
            userId: $user->id,
            articleId: $page->id,
            amount: 6299,
            status: AccrualStatus::Settled,
            reference: 'pi_test_123',
            type: LedgerEntryType::Sale,
            earnedAt: '2026-06-26 10:19:46'
        );

        $transactions = $this->repository->transactionHistoryForContributor($user->id, $this->siteId);

        $this->assertCount(1, $transactions);
        $this->assertSame('Joined Title', $transactions[0]['page_title']);
        $this->assertSame(6299, $transactions[0]['amount']);
        $this->assertSame('GBP', $transactions[0]['currency']);
        $this->assertSame('succeeded', $transactions[0]['status']);
        $this->assertSame(AccrualStatus::Settled->value, $transactions[0]['accrual_status']);
        $this->assertSame('sale', $transactions[0]['type']);
        $this->assertSame('pi_test_123', $transactions[0]['reference_id']);
        $this->assertSame('2026-06-26 10:19:46', $transactions[0]['created_at']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EarningsLedgerRepository();
    }

    private function ledgerEntry(
        int $userId,
        int $articleId,
        int $amount,
        AccrualStatus $status,
        string $reference,
        LedgerEntryType $type = LedgerEntryType::Sale,
        string $earnedAt = '2026-06-26 10:19:46'
    ): EarningsLedger {
        return EarningsLedger::create([
            'user_id' => $userId,
            'article_id' => $articleId,
            'type' => $type->value,
            'amount' => $amount,
            'currency' => 'gbp',
            'reference_id' => $reference,
            'accrual_status' => $status->value,
            'earned_at' => $earnedAt,
            'created_at' => $earnedAt,
            'updated_at' => $earnedAt,
        ]);
    }
}
