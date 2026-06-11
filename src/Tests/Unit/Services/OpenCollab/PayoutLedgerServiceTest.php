<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\EarningsLedger;
use App\Models\PayoutLedgerEntry;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutLedgerEntryRepository;
use App\Services\OpenCollab\AccrualTransitionService;
use App\Services\OpenCollab\PayoutLedgerService;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class PayoutLedgerServiceTest extends TestCase
{
    private PayoutLedgerService $service;
    private MockInterface $ledgerRepository;
    private MockInterface $payoutLedgerEntryRepository;
    private MockInterface $accrualTransitionService;

    public function test_attach_settled_entries_to_payout_attaches_enough_entries(): void
    {
        $first = $this->makeLedger(['id' => 100, 'amount' => 5000]);
        $second = $this->makeLedger(['id' => 101, 'amount' => 3000]);

        $this->ledgerRepository
            ->shouldReceive('settledAvailableForPayout')
            ->with(7, null)
            ->once()
            ->andReturn(collect([$first, $second]));

        $this->payoutLedgerEntryRepository
            ->shouldReceive('attach')
            ->once()
            ->with(10, 100, 5000)
            ->andReturn($this->makePivot());

        $this->payoutLedgerEntryRepository
            ->shouldReceive('attach')
            ->once()
            ->with(10, 101, 3000)
            ->andReturn($this->makePivot());

        $attached = $this->service->attachSettledEntriesToPayout(
            payoutId: 10,
            userId: 7,
            amountToAttach: 8000,
        );

        $this->assertSame(8000, $attached);
    }

    public function test_attach_settled_entries_rejects_partial_row_attachment(): void
    {
        $entry = $this->makeLedger(['id' => 100, 'amount' => 10000]);

        $this->ledgerRepository
            ->shouldReceive('settledAvailableForPayout')
            ->with(7, null)
            ->once()
            ->andReturn(collect([$entry]));

        $this->payoutLedgerEntryRepository
            ->shouldNotReceive('attach');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Partial payout ledger attachment is not supported yet.');

        $this->service->attachSettledEntriesToPayout(
            payoutId: 10,
            userId: 7,
            amountToAttach: 5000,
        );
    }

    public function test_attach_settled_entries_throws_when_not_enough_entries(): void
    {
        $entry = $this->makeLedger(['id' => 100, 'amount' => 3000]);

        $this->ledgerRepository
            ->shouldReceive('settledAvailableForPayout')
            ->with(7, null)
            ->once()
            ->andReturn(collect([$entry]));

        $this->payoutLedgerEntryRepository
            ->shouldReceive('attach')
            ->once()
            ->with(10, 100, 3000)
            ->andReturn($this->makePivot());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to attach enough settled ledger entries to cover payout amount.');

        $this->service->attachSettledEntriesToPayout(
            payoutId: 10,
            userId: 7,
            amountToAttach: 8000,
        );
    }

    public function test_mark_payout_ledger_entries_withdrawn_transitions_each_attached_entry(): void
    {
        $first = $this->makePivot([
            'payout_id' => 10,
            'earnings_ledger_id' => 100,
        ]);

        $second = $this->makePivot([
            'payout_id' => 10,
            'earnings_ledger_id' => 101,
        ]);

        $this->payoutLedgerEntryRepository
            ->shouldReceive('forPayout')
            ->with(10)
            ->once()
            ->andReturn(collect([$first, $second]));

        $this->accrualTransitionService
            ->shouldReceive('withdraw')
            ->once()
            ->with(100, 10);

        $this->accrualTransitionService
            ->shouldReceive('withdraw')
            ->once()
            ->with(101, 10);

        $this->service->markPayoutLedgerEntriesWithdrawn(10);

        $this->assertTrue(true);
    }

    private function makeLedger(array $attributes = []): EarningsLedger
    {
        $attributes = array_merge([
            'id' => 1,
            'amount' => 5000,
        ], $attributes);

        /** @var EarningsLedger&MockInterface $ledger */
        $ledger = Mockery::mock(EarningsLedger::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $ledger->{$key} = $value;
        }

        $ledger->exists = true;

        return $ledger;
    }

    private function makePivot(array $attributes = []): PayoutLedgerEntry
    {
        $attributes = array_merge([
            'id' => 1,
            'payout_id' => 10,
            'earnings_ledger_id' => 100,
            'amount' => 5000,
        ], $attributes);

        /** @var PayoutLedgerEntry&MockInterface $pivot */
        $pivot = Mockery::mock(PayoutLedgerEntry::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $pivot->{$key} = $value;
        }

        $pivot->exists = true;

        return $pivot;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerRepository = Mockery::mock(EarningsLedgerRepository::class);
        $this->payoutLedgerEntryRepository = Mockery::mock(PayoutLedgerEntryRepository::class);
        $this->accrualTransitionService = Mockery::mock(AccrualTransitionService::class);

        $this->service = new PayoutLedgerService(
            $this->ledgerRepository,
            $this->payoutLedgerEntryRepository,
            $this->accrualTransitionService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}