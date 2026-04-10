<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\LedgerEntryType;
use App\Models\EarningsLedger;
use App\Models\Page;
use App\Models\User;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class EarningsLedgerRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private EarningsLedgerRepository $repository;
    private User $user;
    private Page $page;

    public function test_record_sale_creates_positive_ledger_entry(): void
    {
        $entry = $this->repository->recordSale($this->user->id, $this->page->id, 500, 'gbp', 'pi_abc');

        $this->assertInstanceOf(EarningsLedger::class, $entry);
        $this->assertEquals(LedgerEntryType::Sale->value, $entry->type);
        $this->assertEquals(500, $entry->amount);
        $this->assertEquals('pi_abc', $entry->reference_id);
    }

    public function test_record_refund_creates_negative_ledger_entry(): void
    {
        $entry = $this->repository->recordRefund($this->user->id, $this->page->id, 500, 'gbp', 'pi_abc');

        $this->assertEquals(LedgerEntryType::Refund->value, $entry->type);
        $this->assertEquals(-500, $entry->amount);
    }

    public function test_record_refund_stores_negative_even_if_positive_amount_passed(): void
    {
        // abs() is applied internally, so passing a negative should also work
        $entry = $this->repository->recordRefund($this->user->id, $this->page->id, -500, 'gbp', 'pi_abc');

        $this->assertEquals(-500, $entry->amount);
    }

    public function test_balance_for_contributor_sums_all_entries(): void
    {
        EarningsLedger::create(['user_id' => $this->user->id, 'article_id' => $this->page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 600, 'currency' => 'gbp', 'reference_id' => 'a']);
        EarningsLedger::create(['user_id' => $this->user->id, 'article_id' => $this->page->id, 'type' => LedgerEntryType::Refund->value, 'amount' => -100, 'currency' => 'gbp', 'reference_id' => 'b']);

        $balance = $this->repository->balanceForContributor($this->user->id);

        $this->assertEquals(500, $balance);
    }

    public function test_balance_returns_zero_when_no_entries(): void
    {
        $this->assertEquals(0, $this->repository->balanceForContributor(999));
    }

    public function test_balance_does_not_include_other_users(): void
    {
        EarningsLedger::create(['user_id' => $this->user->id, 'article_id' => $this->page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 1000, 'currency' => 'gbp', 'reference_id' => 'x']);

        $this->assertEquals(0, $this->repository->balanceForContributor(2));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EarningsLedgerRepository();
        $this->user = $this->createUser();
        $this->page = $this->createPage();
    }
}