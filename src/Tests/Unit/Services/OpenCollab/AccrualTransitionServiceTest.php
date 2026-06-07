<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Events\OpenCollab\AccrualStatusChangedEvent;
use App\Exceptions\OpenCollab\InvalidAccrualTransitionException;
use App\Framework\Events\EventDispatcher;
use App\Models\EarningsLedger;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Services\OpenCollab\AccrualTransitionService;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class AccrualTransitionServiceTest extends TestCase
{
    private AccrualTransitionService $service;
    private MockInterface $ledgerRepository;
    private MockInterface $eventDispatcher;

    public function test_confirm_transitions_estimated_to_confirmed(): void
    {
        $entry = $this->makeLedger([
            'id' => 10,
            'accrual_status' => AccrualStatus::Estimated->value,
        ]);

        $confirmed = $this->makeLedger([
            'id' => 10,
            'accrual_status' => AccrualStatus::Confirmed->value,
            'confirmed_by' => 99,
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn($entry);

        $this->ledgerRepository
            ->shouldReceive('updateAccrualStatus')
            ->once()
            ->withArgs(function (int $id, AccrualStatus $status, array $metadata): bool {
                return $id === 10
                    && $status === AccrualStatus::Confirmed
                    && $metadata['confirmed_by'] === 99
                    && isset($metadata['confirmed_at']);
            })
            ->andReturn($confirmed);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($event) => $event instanceof AccrualStatusChangedEvent);

        $result = $this->service->confirm(10, 99);

        $this->assertSame(AccrualStatus::Confirmed->value, $result->accrual_status);
    }

    public function test_confirm_allows_confirmed_to_confirmed_as_idempotent_transition(): void
    {
        $entry = $this->makeLedger([
            'id' => 10,
            'accrual_status' => AccrualStatus::Confirmed->value,
            'confirmed_by' => 55,
            'confirmed_at' => '2026-06-01 10:00:00',
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn($entry);

        $this->ledgerRepository
            ->shouldNotReceive('updateAccrualStatus');

        $this->eventDispatcher
            ->shouldNotReceive('dispatch');

        $result = $this->service->confirm(10, 99);

        $this->assertSame(AccrualStatus::Confirmed->value, $result->accrual_status);
        $this->assertSame(55, $result->confirmed_by);
    }

    public function test_settle_transitions_confirmed_to_settled(): void
    {
        $entry = $this->makeLedger([
            'id' => 11,
            'accrual_status' => AccrualStatus::Confirmed->value,
        ]);

        $settled = $this->makeLedger([
            'id' => 11,
            'accrual_status' => AccrualStatus::Settled->value,
            'settled_by' => 99,
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(11)
            ->once()
            ->andReturn($entry);

        $this->ledgerRepository
            ->shouldReceive('updateAccrualStatus')
            ->once()
            ->withArgs(function (int $id, AccrualStatus $status, array $metadata): bool {
                return $id === 11
                    && $status === AccrualStatus::Settled
                    && $metadata['settled_by'] === 99
                    && isset($metadata['settled_at']);
            })
            ->andReturn($settled);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($event) => $event instanceof AccrualStatusChangedEvent);

        $result = $this->service->settle(11, 99);

        $this->assertSame(AccrualStatus::Settled->value, $result->accrual_status);
    }

    public function test_withdraw_transitions_settled_to_withdrawn(): void
    {
        $entry = $this->makeLedger([
            'id' => 12,
            'accrual_status' => AccrualStatus::Settled->value,
        ]);

        $withdrawn = $this->makeLedger([
            'id' => 12,
            'accrual_status' => AccrualStatus::Withdrawn->value,
            'payout_id' => 500,
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(12)
            ->once()
            ->andReturn($entry);

        $this->ledgerRepository
            ->shouldReceive('updateAccrualStatus')
            ->once()
            ->withArgs(function (int $id, AccrualStatus $status, array $metadata): bool {
                return $id === 12
                    && $status === AccrualStatus::Withdrawn
                    && $metadata['payout_id'] === 500
                    && isset($metadata['withdrawn_at']);
            })
            ->andReturn($withdrawn);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($event) => $event instanceof AccrualStatusChangedEvent);

        $result = $this->service->withdraw(12, 500);

        $this->assertSame(AccrualStatus::Withdrawn->value, $result->accrual_status);
        $this->assertSame(500, $result->payout_id);
    }

    public function test_reverse_transitions_estimated_to_reversed(): void
    {
        $entry = $this->makeLedger([
            'id' => 13,
            'accrual_status' => AccrualStatus::Estimated->value,
        ]);

        $reversed = $this->makeLedger([
            'id' => 13,
            'accrual_status' => AccrualStatus::Reversed->value,
            'reversal_reason' => 'Content removed.',
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(13)
            ->once()
            ->andReturn($entry);

        $this->ledgerRepository
            ->shouldReceive('updateAccrualStatus')
            ->once()
            ->withArgs(function (int $id, AccrualStatus $status, array $metadata): bool {
                return $id === 13
                    && $status === AccrualStatus::Reversed
                    && $metadata['reversal_reason'] === 'Content removed.'
                    && $metadata['reversed_by'] === 99
                    && isset($metadata['reversed_at']);
            })
            ->andReturn($reversed);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($event) => $event instanceof AccrualStatusChangedEvent);

        $result = $this->service->reverse(13, 'Content removed.', 99);

        $this->assertSame(AccrualStatus::Reversed->value, $result->accrual_status);
    }

    public function test_reverse_rejects_withdrawn_entry(): void
    {
        $entry = $this->makeLedger([
            'id' => 14,
            'accrual_status' => AccrualStatus::Withdrawn->value,
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(14)
            ->once()
            ->andReturn($entry);

        $this->ledgerRepository
            ->shouldNotReceive('updateAccrualStatus');

        $this->eventDispatcher
            ->shouldNotReceive('dispatch');

        $this->expectException(InvalidAccrualTransitionException::class);

        $this->service->reverse(14, 'Too late.', 99);
    }

    public function test_invalid_transition_throws_clear_exception(): void
    {
        $entry = $this->makeLedger([
            'id' => 15,
            'accrual_status' => AccrualStatus::Estimated->value,
        ]);

        $this->ledgerRepository
            ->shouldReceive('find')
            ->with(15)
            ->once()
            ->andReturn($entry);

        $this->ledgerRepository
            ->shouldNotReceive('updateAccrualStatus');

        $this->eventDispatcher
            ->shouldNotReceive('dispatch');

        $this->expectException(InvalidAccrualTransitionException::class);

        $this->service->settle(15, 99);
    }

    private function makeLedger(array $attributes = []): EarningsLedger
    {
        $defaults = [
            'id' => 1,
            'user_id' => 7,
            'article_id' => 100,
            'amount' => 10000,
            'currency' => 'GBP',
            'accrual_status' => AccrualStatus::Estimated->value,
        ];

        /** @var EarningsLedger&\Mockery\MockInterface $ledger */
        $ledger = Mockery::mock(EarningsLedger::class)
            ->makePartial();

        foreach (array_merge($defaults, $attributes) as $key => $value) {
            $ledger->{$key} = $value;
        }

        $ledger->exists = true;

        return $ledger;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerRepository = Mockery::mock(EarningsLedgerRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);

        $this->service = new AccrualTransitionService(
            $this->ledgerRepository,
            $this->eventDispatcher,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}