<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\PayoutLiabilityRecovery;
use App\Repositories\OpenCollab\PayoutLiabilityRecoveryRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class PayoutLiabilityRecoveryRepositoryTest extends FunctionalTestCase
{
    private PayoutLiabilityRecoveryRepository $repository;

    public function test_record_creates_recovery_row(): void
    {
        $row = $this->repository->record(
            payoutId: 10,
            creatorLiabilityId: 20,
            amount: 3000,
            sourceType: 'earnings_reversal',
            sourceId: 123,
            reason: 'Withdrawn earning reversed.',
        );

        $this->assertSame(10, (int) $row->payout_id);
        $this->assertSame(20, (int) $row->creator_liability_id);
        $this->assertSame(3000, (int) $row->amount);
        $this->assertSame('earnings_reversal', $row->source_type);
        $this->assertSame(123, (int) $row->source_id);
        $this->assertSame('Withdrawn earning reversed.', $row->reason);
    }

    public function test_for_payout_returns_rows_for_specific_payout(): void
    {
        PayoutLiabilityRecovery::create([
            'payout_id' => 10,
            'creator_liability_id' => 20,
            'amount' => 3000,
            'source_type' => 'manual_adjustment',
            'reason' => 'First',
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        PayoutLiabilityRecovery::create([
            'payout_id' => 11,
            'creator_liability_id' => 21,
            'amount' => 4000,
            'source_type' => 'manual_adjustment',
            'reason' => 'Second',
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $rows = $this->repository->forPayout(10);

        $this->assertCount(1, $rows);
        $this->assertSame(20, (int) $rows->first()->creator_liability_id);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PayoutLiabilityRecoveryRepository();
    }
}