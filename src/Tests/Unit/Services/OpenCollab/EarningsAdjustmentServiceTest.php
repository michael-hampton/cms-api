<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Enums\OpenCollab\EarningsAdjustmentSource;
use App\Models\EarningsLedger;
use App\Models\Model;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Services\OpenCollab\AccrualTransitionService;
use App\Services\OpenCollab\CreatorLiabilityService;
use App\Services\OpenCollab\EarningsAdjustmentService;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class EarningsAdjustmentServiceTest extends TestCase
{
    private EarningsAdjustmentService $service;
    private MockInterface $ledgerRepository;
    private MockInterface $accrualTransitionService;
    private MockInterface $creatorLiabilityService;

    public function test_reverse_rejects_empty_reason(): void
    {
        $this->ledgerRepository->shouldNotReceive('find');
        $this->accrualTransitionService->shouldNotReceive('reverse');
        $this->creatorLiabilityService->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Adjustment reason is required.');

        $this->service->reverse(
            ledgerEntryId: 10,
            source: EarningsAdjustmentSource::ManualFinanceAdjustment,
            reason: '',
            actorId: 99,
        );
    }

    public function test_reverse_rejects_empty_source_string(): void
    {
        $this->ledgerRepository->shouldNotReceive('find');
        $this->accrualTransitionService->shouldNotReceive('reverse');
        $this->creatorLiabilityService->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Adjustment source is required.');

        $this->service->reverse(
            ledgerEntryId: 10,
            source: '   ',
            reason: 'Bad earning.',
            actorId: 99,
        );
    }

    public function test_reverse_throws_when_ledger_entry_not_found(): void
    {
        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn(null);

        $this->accrualTransitionService->shouldNotReceive('reverse');
        $this->creatorLiabilityService->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Earnings ledger entry [10] not found.');

        $this->service->reverse(
            ledgerEntryId: 10,
            source: EarningsAdjustmentSource::ManualFinanceAdjustment,
            reason: 'Bad earning.',
            actorId: 99,
        );
    }

    public function test_reverse_estimated_entry_marks_original_as_reversed(): void
    {
        $entry = $this->makeLedger([
            'id' => 10,
            'accrual_status' => AccrualStatus::Estimated->value,
        ]);

        $reversed = $this->makeLedger([
            'id' => 10,
            'accrual_status' => AccrualStatus::Reversed->value,
            'reversal_reason' => 'Content removed.',
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn($entry);

        $this->accrualTransitionService
            ->shouldReceive('reverse')
            ->with(10, 'Content removed.', 99)
            ->once()
            ->andReturn($reversed);

        $this->ledgerRepository->shouldNotReceive('recordReversal');
        $this->creatorLiabilityService->shouldNotReceive('create');

        $result = $this->service->reverse(
            ledgerEntryId: 10,
            source: EarningsAdjustmentSource::ContentTakedown,
            reason: 'Content removed.',
            actorId: 99,
        );

        $this->assertSame(AccrualStatus::Reversed->value, $result->accrual_status);
    }

    public function test_reverse_confirmed_entry_marks_original_as_reversed(): void
    {
        $entry = $this->makeLedger([
            'id' => 11,
            'accrual_status' => AccrualStatus::Confirmed->value,
        ]);

        $reversed = $this->makeLedger([
            'id' => 11,
            'accrual_status' => AccrualStatus::Reversed->value,
            'reversal_reason' => 'Dispute upheld.',
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(11)
            ->once()
            ->andReturn($entry);

        $this->accrualTransitionService
            ->shouldReceive('reverse')
            ->with(11, 'Dispute upheld.', 99)
            ->once()
            ->andReturn($reversed);

        $this->ledgerRepository->shouldNotReceive('recordReversal');
        $this->creatorLiabilityService->shouldNotReceive('create');

        $result = $this->service->reverse(
            ledgerEntryId: 11,
            source: EarningsAdjustmentSource::DisputeResolution,
            reason: 'Dispute upheld.',
            actorId: 99,
        );

        $this->assertSame(AccrualStatus::Reversed->value, $result->accrual_status);
    }

    public function test_reverse_settled_entry_marks_original_reversed_without_counter_entry(): void
    {
        $entry = $this->makeLedger([
            'id' => 12,
            'user_id' => 7,
            'article_id' => 100,
            'amount' => 8000,
            'currency' => 'GBP',
            'accrual_status' => AccrualStatus::Settled->value,
        ]);

        $reversed = $this->makeLedger([
            'id' => 12,
            'accrual_status' => AccrualStatus::Reversed->value,
            'reversal_reason' => 'Manual finance adjustment.',
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(12)
            ->once()
            ->andReturn($entry);

        $this->ledgerRepository->shouldNotReceive('recordReversal');

        $this->accrualTransitionService
            ->shouldReceive('reverse')
            ->with(12, 'Manual finance adjustment.', 99)
            ->once()
            ->andReturn($reversed);

        $this->creatorLiabilityService->shouldNotReceive('create');

        $result = $this->service->reverse(
            ledgerEntryId: 12,
            source: EarningsAdjustmentSource::ManualFinanceAdjustment,
            reason: 'Manual finance adjustment.',
            actorId: 99,
        );

        $this->assertSame(AccrualStatus::Reversed->value, $result->accrual_status);
    }

    public function test_reverse_settled_entry_handles_negative_amount_as_absolute_reversal(): void
    {
        $entry = $this->makeLedger([
            'id' => 13,
            'user_id' => 7,
            'article_id' => 100,
            'amount' => -2500,
            'currency' => 'GBP',
            'accrual_status' => AccrualStatus::Settled->value,
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(13)
            ->once()
            ->andReturn($entry);

        $this->ledgerRepository->shouldNotReceive('recordReversal');

        $this->accrualTransitionService
            ->shouldReceive('reverse')
            ->with(13, 'Correction.', 99)
            ->once()
            ->andReturn($this->makeLedger([
                'id' => 13,
                'accrual_status' => AccrualStatus::Reversed->value,
            ]));

        $this->creatorLiabilityService->shouldNotReceive('create');

        $result = $this->service->reverse(
            ledgerEntryId: 13,
            source: EarningsAdjustmentSource::ManualFinanceAdjustment,
            reason: 'Correction.',
            actorId: 99,
        );

        $this->assertSame(AccrualStatus::Reversed->value, $result->accrual_status);
    }

    public function test_reverse_withdrawn_entry_creates_creator_liability(): void
    {
        $entry = $this->makeLedger([
            'id' => 14,
            'user_id' => 7,
            'site_id' => 1,
            'article_id' => 100,
            'amount' => 9000,
            'currency' => 'GBP',
            'accrual_status' => AccrualStatus::Withdrawn->value,
        ]);

        $liability = Mockery::mock(Model::class)->makePartial();
        $liability->id = 50;
        $liability->user_id = 7;
        $liability->site_id = 1;
        $liability->amount = 9000;
        $liability->remaining_amount = 9000;

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(14)
            ->once()
            ->andReturn($entry);

        $this->creatorLiabilityService
            ->shouldReceive('create')
            ->once()
            ->with(
                7,
                1,
                EarningsAdjustmentSource::Clawback->value,
                14,
                9000,
                'GBP',
                'Paid earning clawed back.',
                99,
            )
            ->andReturn($liability);

        $this->ledgerRepository->shouldNotReceive('recordReversal');
        $this->accrualTransitionService->shouldNotReceive('reverse');

        $result = $this->service->reverse(
            ledgerEntryId: 14,
            source: EarningsAdjustmentSource::Clawback,
            reason: 'Paid earning clawed back.',
            actorId: 99,
        );

        $this->assertSame(50, (int) $result->id);
        $this->assertSame(9000, (int) $result->remaining_amount);
    }

    public function test_reverse_withdrawn_entry_uses_absolute_amount_for_liability(): void
    {
        $entry = $this->makeLedger([
            'id' => 15,
            'user_id' => 7,
            'site_id' => 1,
            'amount' => -4000,
            'currency' => 'GBP',
            'accrual_status' => AccrualStatus::Withdrawn->value,
        ]);

        $liability = Mockery::mock(Model::class)->makePartial();
        $liability->id = 51;
        $liability->amount = 4000;
        $liability->remaining_amount = 4000;

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(15)
            ->once()
            ->andReturn($entry);

        $this->creatorLiabilityService
            ->shouldReceive('create')
            ->once()
            ->with(
                7,
                1,
                EarningsAdjustmentSource::RevenueReversal->value,
                15,
                4000,
                'GBP',
                'Revenue reversed after withdrawal.',
                99,
            )
            ->andReturn($liability);

        $this->ledgerRepository->shouldNotReceive('recordReversal');
        $this->accrualTransitionService->shouldNotReceive('reverse');

        $result = $this->service->reverse(
            ledgerEntryId: 15,
            source: EarningsAdjustmentSource::RevenueReversal,
            reason: 'Revenue reversed after withdrawal.',
            actorId: 99,
        );

        $this->assertSame(4000, (int) $result->remaining_amount);
    }

    public function test_reverse_withdrawn_entry_rejects_missing_site_id(): void
    {
        $entry = $this->makeLedger([
            'id' => 17,
            'user_id' => 7,
            'site_id' => null,
            'amount' => 9000,
            'currency' => 'GBP',
            'accrual_status' => AccrualStatus::Withdrawn->value,
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(17)
            ->once()
            ->andReturn($entry);

        $this->creatorLiabilityService->shouldNotReceive('create');
        $this->ledgerRepository->shouldNotReceive('recordReversal');
        $this->accrualTransitionService->shouldNotReceive('reverse');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot resolve site for earnings ledger entry [17].');

        $this->service->reverse(
            ledgerEntryId: 17,
            source: EarningsAdjustmentSource::Clawback,
            reason: 'Paid earning clawed back.',
            actorId: 99,
        );
    }

    public function test_reverse_rejects_already_reversed_entry(): void
    {
        $entry = $this->makeLedger([
            'id' => 16,
            'accrual_status' => AccrualStatus::Reversed->value,
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(16)
            ->once()
            ->andReturn($entry);

        $this->ledgerRepository->shouldNotReceive('recordReversal');
        $this->accrualTransitionService->shouldNotReceive('reverse');
        $this->creatorLiabilityService->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Earnings ledger entry [16] is already reversed.');

        $this->service->reverse(
            ledgerEntryId: 16,
            source: EarningsAdjustmentSource::ModerationAction,
            reason: 'Already reversed.',
            actorId: 99,
        );
    }

    private function makeLedger(array $attributes = []): EarningsLedger
    {
        $attributes = array_merge([
            'id' => 1,
            'user_id' => 7,
            'site_id' => 1,
            'article_id' => 100,
            'amount' => 10000,
            'currency' => 'GBP',
            'accrual_status' => AccrualStatus::Estimated->value,
        ], $attributes);

        /** @var EarningsLedger&MockInterface $ledger */
        $ledger = Mockery::mock(EarningsLedger::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $ledger->{$key} = $value;
        }

        $ledger->exists = true;

        return $ledger;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerRepository = Mockery::mock(EarningsLedgerRepository::class);
        $this->accrualTransitionService = Mockery::mock(AccrualTransitionService::class);
        $this->creatorLiabilityService = Mockery::mock(CreatorLiabilityService::class);

        $this->service = new EarningsAdjustmentService(
            $this->ledgerRepository,
            $this->accrualTransitionService,
            $this->creatorLiabilityService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}