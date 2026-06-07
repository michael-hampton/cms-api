<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\PayoutLedgerEntry;
use App\Repositories\OpenCollab\PayoutLedgerEntryRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class PayoutLedgerEntryRepositoryTest extends FunctionalTestCase
{
    private PayoutLedgerEntryRepository $repository;

    public function test_attach_creates_payout_ledger_entry(): void
    {
        $entry = $this->repository->attach(
            payoutId: 10,
            ledgerEntryId: 500,
            amount: 7500,
        );

        $this->assertSame(10, (int) $entry->payout_id);
        $this->assertSame(500, (int) $entry->earnings_ledger_id);
        $this->assertSame(7500, (int) $entry->amount);
    }

    public function test_for_payout_returns_entries_ordered_by_id(): void
    {
        PayoutLedgerEntry::create([
            'payout_id' => 10,
            'earnings_ledger_id' => 501,
            'amount' => 1000,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        PayoutLedgerEntry::create([
            'payout_id' => 10,
            'earnings_ledger_id' => 502,
            'amount' => 2000,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        PayoutLedgerEntry::create([
            'payout_id' => 11,
            'earnings_ledger_id' => 503,
            'amount' => 3000,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $rows = $this->repository->forPayout(10);

        $this->assertCount(2, $rows);
        $this->assertSame(501, (int) $rows->first()->earnings_ledger_id);
        $this->assertSame(502, (int) $rows->last()->earnings_ledger_id);
    }

    public function test_exists_for_ledger_entry_returns_true_when_attached(): void
    {
        PayoutLedgerEntry::create([
            'payout_id' => 10,
            'earnings_ledger_id' => 501,
            'amount' => 1000,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $this->assertTrue($this->repository->existsForLedgerEntry(501));
        $this->assertFalse($this->repository->existsForLedgerEntry(999));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PayoutLedgerEntryRepository();
    }
}