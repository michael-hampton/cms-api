<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\CreatorLiabilityStatus;
use App\Models\CreatorLiability;
use App\Models\EarningsLedger;
use App\Repositories\OpenCollab\CreatorLiabilityRepository;
use App\Services\OpenCollab\CreatorLiabilityService;
use App\Services\OpenCollab\SetOffService;
use App\Framework\Database\Database;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class SetOffServiceTest extends TestCase
{
    private SetOffService $service;
    private MockInterface $liabilityRepository;
    private MockInterface $liabilityService;
    private MockInterface $database;

    public function test_apply_set_off_fully_recovers_liability_when_balance_covers_it(): void
    {
        $liability = $this->makeLiability([
            'id' => 1,
            'remaining_amount' => 3000,
        ]);

        $this->liabilityRepository
            ->shouldReceive('findOpenForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(collect([$liability]));

        $this->liabilityService
            ->shouldReceive('recover')
            ->with(1, 3000)
            ->once();

        $result = $this->service->apply(7, 1, 10000);

        $this->assertSame(3000, $result->deductedAmount);
        $this->assertSame(7000, $result->netAmount);
        $this->assertCount(1, $result->deductions);
        $this->assertSame(1, $result->deductions[0]['liability_id']);
        $this->assertSame(3000, $result->deductions[0]['amount']);
    }

    public function test_apply_set_off_partially_recovers_when_liability_exceeds_balance(): void
    {
        $liability = $this->makeLiability([
            'id' => 1,
            'remaining_amount' => 15000,
        ]);

        $this->liabilityRepository
            ->shouldReceive('findOpenForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(collect([$liability]));

        $this->liabilityService
            ->shouldReceive('recover')
            ->with(1, 10000)
            ->once();

        $result = $this->service->apply(7, 1, 10000);

        $this->assertSame(10000, $result->deductedAmount);
        $this->assertSame(0, $result->netAmount);
        $this->assertCount(1, $result->deductions);
        $this->assertSame(10000, $result->deductions[0]['amount']);
    }

    public function test_apply_set_off_handles_multiple_liabilities_in_order(): void
    {
        $first = $this->makeLiability([
            'id' => 1,
            'remaining_amount' => 3000,
        ]);

        $second = $this->makeLiability([
            'id' => 2,
            'remaining_amount' => 4000,
        ]);

        $this->liabilityRepository
            ->shouldReceive('findOpenForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(collect([$first, $second]));

        $this->liabilityService
            ->shouldReceive('recover')
            ->with(1, 3000)
            ->once();

        $this->liabilityService
            ->shouldReceive('recover')
            ->with(2, 2000)
            ->once();

        $result = $this->service->apply(7, 1, 5000);

        $this->assertSame(5000, $result->deductedAmount);
        $this->assertSame(0, $result->netAmount);
        $this->assertCount(2, $result->deductions);
        $this->assertSame(3000, $result->deductions[0]['amount']);
        $this->assertSame(2000, $result->deductions[1]['amount']);
    }

    public function test_apply_set_off_returns_original_amount_when_no_liabilities_exist(): void
    {
        $this->liabilityRepository
            ->shouldReceive('findOpenForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(collect([]));

        $this->liabilityService
            ->shouldNotReceive('recover');

        $result = $this->service->apply(7, 1, 10000);

        $this->assertSame(0, $result->deductedAmount);
        $this->assertSame(10000, $result->netAmount);
        $this->assertSame([], $result->deductions);
    }

    private function makeLiability(array $attributes = []): CreatorLiability
    {
        $defaults = [
            'id' => 1,
            'user_id' => 7,
            'site_id' => 1,
            'source_type' => 'manual_adjustment',
            'source_id' => null,
            'amount' => 5000,
            'remaining_amount' => 5000,
            'currency' => 'GBP',
            'status' => CreatorLiabilityStatus::Open->value,
            'reason' => 'Test liability.',
            'created_by' => 99,
        ];

        /** @var EarningsLedger&\Mockery\MockInterface $liability */
        $liability = Mockery::mock(CreatorLiability::class)
            ->makePartial();

        foreach (array_merge($defaults, $attributes) as $key => $value) {
            $liability->{$key} = $value;
        }

        $liability->exists = true;


        return $liability;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->liabilityRepository = Mockery::mock(CreatorLiabilityRepository::class);
        $this->liabilityService = Mockery::mock(CreatorLiabilityService::class);
        $this->database = Mockery::mock(Database::class);

        $this->database
            ->shouldReceive('transaction')
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->service = new SetOffService(
            $this->liabilityRepository,
            $this->liabilityService,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}