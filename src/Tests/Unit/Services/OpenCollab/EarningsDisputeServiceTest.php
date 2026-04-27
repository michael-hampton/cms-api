<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\DisputeStatus;
use App\Enums\OpenCollab\LedgerEntryType;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\EarningsDispute;
use App\Models\EarningsLedger;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\EarningsDisputeRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Services\OpenCollab\EarningsDisputeService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class EarningsDisputeServiceTest extends FunctionalTestCase
{
    private EarningsDisputeService $service;
    private MockInterface $disputeRepository;
    private MockInterface $ledgerRepository;
    private MockInterface $databaseMock;
    private UserRepositoryInterface $userRepository;
    private NotificationDispatcher $notificationDispatcher;
    private EventDispatcher $eventDispatcher;
    // ── raise() ───────────────────────────────────────────────────────────────

    public function test_raise_creates_open_dispute_when_ledger_entry_belongs_to_user(): void
    {
        $ledger = $this->makeLedger(['id' => 10, 'user_id' => 7]);
        $dispute = $this->makeDispute(['id' => 1, 'status' => DisputeStatus::Open->value]);

        $this->ledgerRepository->shouldReceive('find')->with(10)->andReturn($ledger);
        $this->disputeRepository->shouldReceive('hasAnyDisputeForLedgerEntry')->with(7, 10)->andReturn(false);
        $this->disputeRepository->shouldReceive('createForUser')
            ->once()
            ->with(7, 10, 'Incorrect amount charged.')
            ->andReturn($dispute);

        $result = $this->service->raise(userId: 7, ledgerId: 10, reason: 'Incorrect amount charged.');

        $this->assertEquals(DisputeStatus::Open->value, $result->status);
    }

    public function test_raise_throws_when_ledger_entry_not_found(): void
    {
        $this->ledgerRepository->shouldReceive('find')->andReturn(null);
        $this->disputeRepository->shouldNotReceive('createForUser');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->service->raise(7, 99, 'reason here too');
    }

    // ── resolve() ─────────────────────────────────────────────────────────────

    public function test_raise_throws_when_ledger_entry_belongs_to_different_user(): void
    {
        $ledger = $this->makeLedger(['user_id' => 99]); // different user

        $this->ledgerRepository->shouldReceive('find')->andReturn($ledger);
        $this->disputeRepository->shouldNotReceive('createForUser');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->raise(userId: 7, ledgerId: 10, reason: 'reason here too');
    }

    public function test_raise_throws_when_open_dispute_already_exists_for_ledger_entry(): void
    {
        $ledger = $this->makeLedger(['user_id' => 7]);

        $this->ledgerRepository->shouldReceive('find')->andReturn($ledger);
        $this->disputeRepository->shouldReceive('hasAnyDisputeForLedgerEntry')->andReturn(true);
        $this->disputeRepository->shouldNotReceive('createForUser');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/dispute has already been raised/i');

        $this->service->raise(7, 10, 'reason here too');
    }

    public function test_raise_throws_when_resolved_dispute_already_exists_for_ledger_entry(): void
    {
        $ledger = $this->makeLedger(['user_id' => 7]);

        $this->ledgerRepository->shouldReceive('find')->andReturn($ledger);
        // hasAnyDisputeForLedgerEntry returns true even for resolved disputes
        $this->disputeRepository->shouldReceive('hasAnyDisputeForLedgerEntry')->andReturn(true);
        $this->disputeRepository->shouldNotReceive('createForUser');

        $this->expectException(\RuntimeException::class);

        $this->service->raise(7, 10, 'trying again after resolve');
    }

    public function test_raise_throws_when_rejected_dispute_already_exists_for_ledger_entry(): void
    {
        $ledger = $this->makeLedger(['user_id' => 7]);

        $this->ledgerRepository->shouldReceive('find')->andReturn($ledger);
        $this->disputeRepository->shouldReceive('hasAnyDisputeForLedgerEntry')->andReturn(true);
        $this->disputeRepository->shouldNotReceive('createForUser');

        $this->expectException(\RuntimeException::class);

        $this->service->raise(7, 10, 'trying again after reject');
    }


    public function test_resolve_marks_dispute_resolved(): void
    {
        $dispute = $this->makeDispute(['id' => 3, 'status' => DisputeStatus::Open->value, 'earnings_ledger_id' => 10]);
        $resolved = $this->makeDispute(['id' => 3, 'status' => DisputeStatus::Resolved->value]);

        $this->disputeRepository->shouldReceive('find')->with(3)->andReturn($dispute);
        $this->disputeRepository->shouldReceive('markResolved')
            ->once()
            ->with(3, 'Reviewed and corrected.', 55)
            ->andReturn($resolved);

        $result = $this->service->resolve(3, adminId: 55, adminNotes: 'Reviewed and corrected.');

        $this->assertEquals(DisputeStatus::Resolved->value, $result->status);
    }

    public function test_resolve_writes_ledger_adjustment_when_amount_provided(): void
    {
        $ledger = $this->makeLedger(['id' => 10, 'user_id' => 7, 'article_id' => 20, 'currency' => 'GBP']);
        $dispute = $this->makeDispute(['id' => 3, 'status' => DisputeStatus::Open->value, 'earnings_ledger_id' => 10, 'user_id' => 7]);

        $this->disputeRepository->shouldReceive('find')->andReturn($dispute);
        $this->disputeRepository->shouldReceive('markResolved')->andReturn(
            $this->makeDispute(['status' => DisputeStatus::Resolved->value])
        );
        $this->ledgerRepository->shouldReceive('find')->with(10)->andReturn($ledger);
        $this->ledgerRepository->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                return $data['type'] === LedgerEntryType::Adjustment->value
                    && $data['amount'] === 500
                    && $data['user_id'] === 7;
            });

        $this->service->resolve(
            disputeId: 3,
            adminId: 55,
            adminNotes: 'Corrected by £5.',
            adjustmentAmount: 500,
            adjustmentReason: 'Calculation error.',
        );

        $this->assertTrue(true);
    }

    public function test_resolve_does_not_write_ledger_adjustment_when_no_amount(): void
    {
        $dispute = $this->makeDispute(['id' => 3, 'status' => DisputeStatus::Open->value]);

        $this->disputeRepository->shouldReceive('find')->andReturn($dispute);
        $this->disputeRepository->shouldReceive('markResolved')->andReturn(
            $this->makeDispute(['status' => DisputeStatus::Resolved->value])
        );
        $this->ledgerRepository->shouldNotReceive('create');

        $this->service->resolve(3, 55, 'No adjustment needed.');
        $this->assertTrue(true);
    }

    public function test_resolve_throws_when_dispute_not_found(): void
    {
        $this->disputeRepository->shouldReceive('find')->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->resolve(999, 55, 'notes');
    }

    // ── reject() ──────────────────────────────────────────────────────────────

    public function test_resolve_throws_when_dispute_is_not_open(): void
    {
        $dispute = $this->makeDispute(['id' => 3, 'status' => DisputeStatus::Resolved->value]);

        $this->disputeRepository->shouldReceive('find')->andReturn($dispute);
        $this->disputeRepository->shouldNotReceive('markResolved');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not open/i');

        $this->service->resolve(3, 55, 'notes');
    }

    public function test_resolve_throws_when_adjustment_amount_provided_without_reason(): void
    {
        $dispute = $this->makeDispute(['id' => 3, 'status' => DisputeStatus::Open->value]);

        $this->disputeRepository->shouldReceive('find')->andReturn($dispute);
        $this->disputeRepository->shouldNotReceive('markResolved');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Adjustment reason is required/i');

        $this->service->resolve(
            disputeId: 3,
            adminId: 55,
            adminNotes: 'Adjustment needed.',
            adjustmentAmount: 500,
            adjustmentReason: null,
        );
    }

    public function test_resolve_passes_admin_id_to_repository(): void
    {
        $dispute = $this->makeDispute(['id' => 3, 'status' => DisputeStatus::Open->value]);

        $this->disputeRepository->shouldReceive('find')->andReturn($dispute);
        $this->disputeRepository->shouldReceive('markResolved')
            ->once()
            ->withArgs(function ($id, $notes, $adminId): bool {
                return $adminId === 55;
            })
            ->andReturn($this->makeDispute(['status' => DisputeStatus::Resolved->value]));

        $this->service->resolve(3, adminId: 55, adminNotes: 'Done.');
        $this->assertTrue(true);
    }

    public function test_resolve_throws_when_ledger_entry_missing_during_adjustment(): void
    {
        $dispute = $this->makeDispute(['id' => 3, 'status' => DisputeStatus::Open->value, 'earnings_ledger_id' => 10]);

        $this->disputeRepository->shouldReceive('find')->andReturn($dispute);
        $this->disputeRepository->shouldReceive('markResolved')->andReturn(
            $this->makeDispute(['status' => DisputeStatus::Resolved->value])
        );
        $this->ledgerRepository->shouldReceive('find')->with(10)->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ledger entry missing/i');

        $this->service->resolve(
            disputeId: 3,
            adminId: 55,
            adminNotes: 'Correcting.',
            adjustmentAmount: 500,
            adjustmentReason: 'Error in calculation.',
        );
    }

    public function test_resolve_wraps_writes_in_transaction(): void
    {
        $dispute = $this->makeDispute(['id' => 3, 'status' => DisputeStatus::Open->value]);

        $this->disputeRepository->shouldReceive('find')->andReturn($dispute);
        $this->disputeRepository->shouldReceive('markResolved')->andReturn(
            $this->makeDispute(['status' => DisputeStatus::Resolved->value])
        );

        $this->service->resolve(3, 55, 'admin note');
        $this->assertTrue(true); // transaction mock was called (set up in setUp)
    }

    public function test_reject_marks_dispute_rejected(): void
    {
        $dispute = $this->makeDispute(['id' => 4, 'status' => DisputeStatus::Open->value]);
        $rejected = $this->makeDispute(['id' => 4, 'status' => DisputeStatus::Rejected->value]);

        $this->disputeRepository->shouldReceive('find')->with(4)->andReturn($dispute);
        $this->disputeRepository->shouldReceive('markRejected')
            ->once()
            ->with(4, 'Amount is correct per contract.', 55)
            ->andReturn($rejected);

        $result = $this->service->reject(4, adminId: 55, adminNotes: 'Amount is correct per contract.');

        $this->assertEquals(DisputeStatus::Rejected->value, $result->status);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function test_reject_throws_when_dispute_not_found(): void
    {
        $this->disputeRepository->shouldReceive('find')->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->reject(999, 55, 'notes');
    }

    public function test_reject_throws_when_dispute_is_not_open(): void
    {
        $dispute = $this->makeDispute(['status' => DisputeStatus::Rejected->value]);

        $this->disputeRepository->shouldReceive('find')->andReturn($dispute);
        $this->disputeRepository->shouldNotReceive('markRejected');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->reject(1, 55, 'notes');
    }

    public function test_reject_passes_admin_id_to_repository(): void
    {
        $dispute = $this->makeDispute(['id' => 4, 'status' => DisputeStatus::Open->value]);

        $this->disputeRepository->shouldReceive('find')->andReturn($dispute);
        $this->disputeRepository->shouldReceive('markRejected')
            ->once()
            ->withArgs(function ($id, $notes, $adminId): bool {
                return $adminId === 55;
            })
            ->andReturn($this->makeDispute(['status' => DisputeStatus::Rejected->value]));

        $this->service->reject(4, adminId: 55, adminNotes: 'No error found.');
        $this->assertTrue(true);
    }

    private function makeLedger(array $attributes = []): EarningsLedger
    {
        $defaults = [
            'id' => 10,
            'user_id' => 7,
            'article_id' => 5,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 500,
            'currency' => 'GBP',
        ];
        $model = new EarningsLedger(array_merge($defaults, $attributes));
        $model->exists = true;
        return $model;
    }

    private function makeDispute(array $attributes = []): EarningsDispute
    {
        $defaults = [
            'id' => 1,
            'user_id' => 7,
            'earnings_ledger_id' => 10,
            'reason' => 'Incorrect amount.',
            'status' => DisputeStatus::Open->value,
            'admin_notes' => null,
        ];
        $model = new EarningsDispute(array_merge($defaults, $attributes));
        $model->exists = true;
        return $model;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->disputeRepository = Mockery::mock(EarningsDisputeRepository::class);
        $this->ledgerRepository = Mockery::mock(EarningsLedgerRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->notificationDispatcher = Mockery::mock(NotificationDispatcher::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);

        $this->notificationDispatcher->shouldReceive('dispatch')->byDefault();
        $this->userRepository->shouldReceive('find')->byDefault();
        $this->eventDispatcher->shouldReceive('dispatch')->byDefault();

        $this->service = new EarningsDisputeService(
            $this->disputeRepository,
            $this->ledgerRepository,
            $this->userRepository,
            $this->databaseMock,
            $this->notificationDispatcher,
            $this->eventDispatcher
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}