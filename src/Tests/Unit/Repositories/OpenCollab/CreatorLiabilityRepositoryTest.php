<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\CreatorLiabilityStatus;
use App\Models\CreatorLiability;
use App\Repositories\OpenCollab\CreatorLiabilityRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class CreatorLiabilityRepositoryTest extends FunctionalTestCase
{
    private CreatorLiabilityRepository $repository;

    public function test_create_liability_persists_open_liability(): void
    {
        $liability = $this->repository->create([
            'user_id' => 7,
            'site_id' => 1,
            'source_type' => 'earnings_reversal',
            'source_id' => 123,
            'amount' => 5000,
            'remaining_amount' => 5000,
            'currency' => 'GBP',
            'status' => CreatorLiabilityStatus::Open->value,
            'reason' => 'Withdrawn earning reversed.',
            'created_by' => 99,
        ]);

        $this->assertSame(7, $liability->user_id);
        $this->assertSame(1, $liability->site_id);
        $this->assertSame(5000, $liability->amount);
        $this->assertSame(5000, $liability->remaining_amount);
        $this->assertSame(CreatorLiabilityStatus::Open->value, $liability->status);
    }

    public function test_open_amount_for_contributor_sums_open_and_partially_recovered_liabilities(): void
    {
        CreatorLiability::create([
            'user_id' => 7,
            'site_id' => 1,
            'source_type' => 'manual_adjustment',
            'amount' => 5000,
            'remaining_amount' => 5000,
            'currency' => 'GBP',
            'status' => CreatorLiabilityStatus::Open->value,
            'reason' => 'Open liability.',
        ]);

        CreatorLiability::create([
            'user_id' => 7,
            'site_id' => 1,
            'source_type' => 'manual_adjustment',
            'amount' => 3000,
            'remaining_amount' => 1000,
            'currency' => 'GBP',
            'status' => CreatorLiabilityStatus::PartiallyRecovered->value,
            'reason' => 'Partial liability.',
        ]);

        CreatorLiability::create([
            'user_id' => 7,
            'site_id' => 1,
            'source_type' => 'manual_adjustment',
            'amount' => 2000,
            'remaining_amount' => 0,
            'currency' => 'GBP',
            'status' => CreatorLiabilityStatus::Recovered->value,
            'reason' => 'Recovered liability.',
        ]);

        $this->assertSame(6000, $this->repository->openAmountForContributor(7, 1));
    }

    public function test_find_open_for_contributor_excludes_recovered_and_written_off(): void
    {
        CreatorLiability::create([
            'user_id' => 7,
            'site_id' => 1,
            'source_type' => 'manual_adjustment',
            'amount' => 5000,
            'remaining_amount' => 5000,
            'currency' => 'GBP',
            'status' => CreatorLiabilityStatus::Open->value,
            'reason' => 'Open.',
        ]);

        CreatorLiability::create([
            'user_id' => 7,
            'site_id' => 1,
            'source_type' => 'manual_adjustment',
            'amount' => 2000,
            'remaining_amount' => 0,
            'currency' => 'GBP',
            'status' => CreatorLiabilityStatus::WrittenOff->value,
            'reason' => 'Written off.',
        ]);

        $rows = $this->repository->findOpenForContributor(7, 1);

        $this->assertCount(1, $rows);
        $this->assertSame(CreatorLiabilityStatus::Open->value, $rows->first()->status);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new CreatorLiabilityRepository();
    }
}