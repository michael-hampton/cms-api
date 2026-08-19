<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\CreatorLiabilityStatus;
use App\Models\CreatorLiability;
use App\Models\EarningsLedger;
use App\Repositories\OpenCollab\CreatorLiabilityRepository;
use App\Services\OpenCollab\CreatorLiabilityService;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class CreatorLiabilityServiceTest extends TestCase
{
    private CreatorLiabilityService $service;
    private MockInterface $repository;
    private MockInterface $databaseMock;

    public function test_create_creates_open_liability(): void
    {
        $liability = $this->makeLiability([
            'amount' => 5000,
            'remaining_amount' => 5000,
            'status' => CreatorLiabilityStatus::Open->value,
        ]);

        $this->repository
            ->shouldReceive('createOpenLiability')
            ->once()
            ->withArgs(fn (
                int $userId,
                int $siteId,
                string $sourceType,
                ?int $sourceId,
                int $amount,
                string $currency,
                string $reason,
                ?int $createdBy = null,
            ): bool =>
                $userId === 7
                && $siteId === 1
                && $sourceType === 'earnings_reversal'
                && $sourceId === 123
                && $amount === 5000
                && $currency === 'GBP'
                && $reason === 'Withdrawn earning reversed.'
                && $createdBy === 99
            )
            ->andReturn($liability);

        $result = $this->service->create(
            userId: 7,
            siteId: 1,
            sourceType: 'earnings_reversal',
            sourceId: 123,
            amount: 5000,
            currency: 'GBP',
            reason: 'Withdrawn earning reversed.',
            createdBy: 99,
        );

        $this->assertSame(5000, $result->remaining_amount);
        $this->assertSame(CreatorLiabilityStatus::Open->value, $result->status);
    }

    public function test_create_rejects_zero_or_negative_amount(): void
    {
        $this->repository->shouldNotReceive('createOpenLiability');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/amount/i');

        $this->service->create(
            userId: 7,
            siteId: 1,
            sourceType: 'manual_adjustment',
            sourceId: null,
            amount: 0,
            currency: 'GBP',
            reason: 'Invalid.',
            createdBy: 99,
        );
    }

    public function test_recover_partially_updates_remaining_amount_and_status(): void
    {
        $liability = $this->makeLiability([
            'id' => 50,
            'remaining_amount' => 5000,
            'status' => CreatorLiabilityStatus::Open->value,
        ]);

        $updated = $this->makeLiability([
            'id' => 50,
            'remaining_amount' => 2000,
            'status' => CreatorLiabilityStatus::PartiallyRecovered->value,
        ]);

        $this->repository
            ->shouldReceive('findOrFail')
            ->with(50)
            ->twice()
            ->andReturn($liability, $updated);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (int $id, array $data): bool =>
                $id === 50
                && $data['remaining_amount'] === 2000
                && $data['status'] === CreatorLiabilityStatus::PartiallyRecovered->value
                && isset($data['updated_at'])
                && !isset($data['settled_at'])
            );

        $result = $this->service->recover(50, 3000);

        $this->assertSame(2000, $result->remaining_amount);
        $this->assertSame(CreatorLiabilityStatus::PartiallyRecovered->value, $result->status);
    }

    public function test_recover_wraps_read_and_write_in_a_transaction(): void
    {
        // The read of remaining_amount and the write that depends on it
        // must happen inside the same transaction — reading outside then
        // writing later re-opens the exact race this method is meant to
        // close (see CreatorLiabilityService::recover() docblock).
        $liability = $this->makeLiability(['id' => 50, 'remaining_amount' => 5000, 'status' => CreatorLiabilityStatus::Open->value]);
        $updated = $this->makeLiability(['id' => 50, 'remaining_amount' => 2000, 'status' => CreatorLiabilityStatus::PartiallyRecovered->value]);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(fn (callable $cb) => $cb());

        $this->repository->shouldReceive('findOrFail')->with(50)->twice()->andReturn($liability, $updated);
        $this->repository->shouldReceive('update')->once();

        $result = $this->service->recover(50, 3000);

        $this->assertSame(2000, $result->remaining_amount);
    }

    public function test_recover_does_not_write_when_transaction_throws(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('db error'));

        $this->repository->shouldNotReceive('update');

        $this->expectException(\RuntimeException::class);

        $this->service->recover(50, 3000);
    }

    public function test_recover_fully_closes_liability(): void
    {
        $liability = $this->makeLiability([
            'id' => 50,
            'remaining_amount' => 5000,
            'status' => CreatorLiabilityStatus::Open->value,
        ]);

        $updated = $this->makeLiability([
            'id' => 50,
            'remaining_amount' => 0,
            'status' => CreatorLiabilityStatus::Recovered->value,
        ]);

        $this->repository
            ->shouldReceive('findOrFail')
            ->with(50)
            ->twice()
            ->andReturn($liability, $updated);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (int $id, array $data): bool =>
                $id === 50
                && $data['remaining_amount'] === 0
                && $data['status'] === CreatorLiabilityStatus::Recovered->value
                && isset($data['settled_at'])
                && isset($data['updated_at'])
            );

        $result = $this->service->recover(50, 5000);

        $this->assertSame(0, $result->remaining_amount);
        $this->assertSame(CreatorLiabilityStatus::Recovered->value, $result->status);
    }

    public function test_recover_does_not_over_recover(): void
    {
        $liability = $this->makeLiability([
            'id' => 50,
            'remaining_amount' => 3000,
            'status' => CreatorLiabilityStatus::Open->value,
        ]);

        $updated = $this->makeLiability([
            'id' => 50,
            'remaining_amount' => 0,
            'status' => CreatorLiabilityStatus::Recovered->value,
        ]);

        $this->repository
            ->shouldReceive('findOrFail')
            ->with(50)
            ->twice()
            ->andReturn($liability, $updated);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (int $id, array $data): bool =>
                $id === 50
                && $data['remaining_amount'] === 0
                && $data['status'] === CreatorLiabilityStatus::Recovered->value
                && isset($data['settled_at'])
                && isset($data['updated_at'])
            );

        $result = $this->service->recover(50, 9999);

        $this->assertSame(0, $result->remaining_amount);
        $this->assertSame(CreatorLiabilityStatus::Recovered->value, $result->status);
    }

    public function test_write_off_closes_liability_as_written_off(): void
    {
        $liability = $this->makeLiability([
            'id' => 50,
            'remaining_amount' => 3000,
            'status' => CreatorLiabilityStatus::Open->value,
        ]);

        $updated = $this->makeLiability([
            'id' => 50,
            'remaining_amount' => 0,
            'status' => CreatorLiabilityStatus::WrittenOff->value,
            'written_off_by' => 99,
            'write_off_reason' => 'Commercial decision.',
        ]);

        $this->repository
            ->shouldReceive('findOrFail')
            ->with(50)
            ->twice()
            ->andReturn($liability, $updated);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (int $id, array $data): bool =>
                $id === 50
                && $data['remaining_amount'] === 0
                && $data['status'] === CreatorLiabilityStatus::WrittenOff->value
                && $data['written_off_by'] === 99
                && $data['write_off_reason'] === 'Commercial decision.'
                && isset($data['settled_at'])
                && isset($data['updated_at'])
            );

        $result = $this->service->writeOff(50, 99, 'Commercial decision.');

        $this->assertSame(0, $result->remaining_amount);
        $this->assertSame(CreatorLiabilityStatus::WrittenOff->value, $result->status);
        $this->assertSame(99, $result->written_off_by);
        $this->assertSame('Commercial decision.', $result->write_off_reason);
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

        $this->repository = Mockery::mock(CreatorLiabilityRepository::class);
        $this->databaseMock = Mockery::mock(\App\Framework\Database\Database::class);
        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(fn (callable $cb) => $cb())
            ->byDefault();

        $this->service = new CreatorLiabilityService($this->repository, $this->databaseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}