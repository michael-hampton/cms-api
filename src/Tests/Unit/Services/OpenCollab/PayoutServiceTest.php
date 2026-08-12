<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutAuditAction;
use App\Enums\OpenCollab\PayoutStatus;
use App\Events\OpenCollab\PayoutRequestedEvent;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\PendingDispatch;
use App\Models\Payout;
use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutAuditRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\Notifications\PayoutPaidNotification;
use App\Services\OpenCollab\PayoutService;
use App\Services\OpenCollab\Policies\ContributorPolicy;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\OpenCollab\CreatorBalanceService;
use App\Events\OpenCollab\PayoutProcessedEvent;
use App\DTO\OpenCollab\SetOffResult;
use App\Repositories\OpenCollab\PayoutLiabilityRecoveryRepository;
use App\Services\OpenCollab\PayoutLedgerService;
use App\Services\OpenCollab\SetOffService;

class PayoutServiceTest extends UnitTestCase
{
    private PayoutService $service;
    private MockInterface $payoutRepository;
    private MockInterface $ledgerRepository;
    private MockInterface $paymentRepository;
    private MockInterface $payoutAuditRepository;
    private MockInterface $eventDispatcher;
    private MockInterface $databaseMock;
    private MockInterface $userRepository;
    private MockInterface $notificationDispatcher;
    private MockInterface $policy;
    private MockInterface $siteRepository;
    private MockInterface $creatorBalanceService;
    private MockInterface $setOffService;
    private MockInterface $payoutLedgerService;
    private MockInterface $payoutLiabilityRecoveryRepository;

    public function test_available_balance_delegates_to_creator_balance_service(): void
    {
        $this->creatorBalanceService
            ->shouldReceive('availableToWithdraw')
            ->with(7, 1)
            ->once()
            ->andReturn(7500);

        $this->assertSame(7500, $this->service->availableBalance(7, 1));
    }

    public function test_request_payout_blocks_when_set_off_reduces_net_below_minimum(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;

        $this->siteRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canWithdraw')
            ->with(7, $site)
            ->once()
            ->andReturn(true);

        $this->creatorBalanceService
            ->shouldReceive('settledBalance')
            ->with(7, 1)
            ->once()
            ->andReturn(10000);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(0);

        $this->payoutRepository
            ->shouldReceive('findByIdempotencyKey')
            ->once()
            ->withArgs(fn (string $key): bool => str_starts_with($key, 'payout:user:7:site:1:manual:'))
            ->andReturn(null);

        $this->setOffService
            ->shouldReceive('apply')
            ->with(7, 1, 10000)
            ->once()
            ->andReturn(new SetOffResult(
                grossAmount: 10000,
                deductedAmount: 6000,
                netAmount: 4000,
                deductions: [],
            ));

        $this->payoutRepository->shouldNotReceive('createWithIdempotency');
        $this->payoutLedgerService->shouldNotReceive('attachSettledEntriesToPayout');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Minimum payout/i');

        $this->service->requestPayout(7, 1, 'bank_transfer');
    }

    public function test_request_payout_applies_set_off_and_creates_net_payout(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;

        $this->siteRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canWithdraw')
            ->with(7, $site)
            ->once()
            ->andReturn(true);

        $this->creatorBalanceService
            ->shouldReceive('settledBalance')
            ->with(7, 1)
            ->once()
            ->andReturn(10000);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(0);

        $this->setOffService
            ->shouldReceive('apply')
            ->with(7, 1, 10000)
            ->once()
            ->andReturn(new SetOffResult(
                grossAmount: 10000,
                deductedAmount: 3000,
                netAmount: 7000,
                deductions: [
                    [
                        'liability_id' => 50,
                        'amount' => 3000,
                        'source_type' => 'earnings_reversal',
                        'source_id' => 123,
                        'reason' => 'Withdrawn earning reversed.',
                    ],
                ],
            ));

        $this->payoutRepository
            ->shouldReceive('findByIdempotencyKey')
            ->once()
            ->withArgs(fn (string $key): bool => str_starts_with($key, 'payout:user:7:site:1:manual:'))
            ->andReturn(null);

        $this->payoutRepository
            ->shouldReceive('createWithIdempotency')
            ->once()
            ->withArgs(fn (array $data): bool =>
                $data['user_id'] === 7
                && $data['site_id'] === 1
                && $data['amount'] === 7000
                && $data['currency'] === 'GBP'
                && $data['status'] === PayoutStatus::Pending->value
                && $data['method'] === 'bank_transfer'
                && isset($data['idempotency_key'])
                && $data['processing_attempts'] === 0
            )
            ->andReturn($this->makePayout([
                'id' => 99,
                'user_id' => 7,
                'site_id' => 1,
                'amount' => 7000,
                'status' => PayoutStatus::Pending->value,
                'method' => 'bank_transfer',
            ]));

        $this->payoutLiabilityRecoveryRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                99,
                50,
                3000,
                'earnings_reversal',
                123,
                'Withdrawn earning reversed.',
            );

        $this->payoutLedgerService
            ->shouldReceive('attachSettledEntriesToPayout')
            ->once()
            ->with(99, 7, 10000, 1)
            ->andReturn(10000);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($event): bool => $event instanceof PayoutRequestedEvent);

        $payout = $this->service->requestPayout(7, 1, 'bank_transfer');

        $this->assertSame(7000, (int) $payout->amount);
    }

    public function test_request_payout_throws_when_state_aware_balance_below_minimum(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;

        $this->siteRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canWithdraw')
            ->with(7, $site)
            ->once()
            ->andReturn(true);

        $this->creatorBalanceService
            ->shouldReceive('settledBalance')
            ->with(7, 1)
            ->once()
            ->andReturn(4999);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(0);

        $this->setOffService->shouldNotReceive('apply');

        $this->payoutRepository->shouldNotReceive('findByIdempotencyKey');
        $this->payoutRepository->shouldNotReceive('createWithIdempotency');
        $this->payoutRepository->shouldNotReceive('create');
        $this->payoutLedgerService->shouldNotReceive('attachSettledEntriesToPayout');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Minimum payout/i');

        $this->service->requestPayout(7, 1, 'bank_transfer');
    }

    public function test_request_payout_throws_onboarding_incomplete_when_policy_blocks(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;
        $this->siteRepository->shouldReceive('find')->with(1)->andReturn($site);

        $this->policy->shouldReceive('canWithdraw')->with(7, $site)->once()->andReturn(false);
        $this->payoutRepository->shouldNotReceive('create');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(OnboardingIncompleteException::class);
        $this->service->requestPayout(7, 1, 'bank_transfer');
    }

    public function test_request_payout_creates_pending_payout_when_policy_allows(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;

        $this->siteRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canWithdraw')
            ->with(7, $site)
            ->once()
            ->andReturn(true);

        $this->creatorBalanceService
            ->shouldReceive('settledBalance')
            ->with(7, 1)
            ->once()
            ->andReturn(10000);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(0);

        $this->setOffService
            ->shouldReceive('apply')
            ->with(7, 1, 10000)
            ->once()
            ->andReturn(new SetOffResult(
                grossAmount: 10000,
                deductedAmount: 0,
                netAmount: 10000,
                deductions: [],
            ));

        $this->payoutRepository
            ->shouldReceive('findByIdempotencyKey')
            ->once()
            ->withArgs(fn (string $key): bool => str_starts_with($key, 'payout:user:7:site:1:manual:'))
            ->andReturn(null);

        $this->payoutRepository
            ->shouldReceive('createWithIdempotency')
            ->once()
            ->withArgs(fn (array $data): bool =>
                $data['user_id'] === 7
                && $data['site_id'] === 1
                && $data['amount'] === 10000
                && $data['currency'] === 'GBP'
                && $data['status'] === PayoutStatus::Pending->value
                && $data['method'] === 'bank_transfer'
                && isset($data['idempotency_key'])
                && $data['processing_attempts'] === 0
            )
            ->andReturn($this->makePayout([
                'id' => 99,
                'user_id' => 7,
                'site_id' => 1,
                'amount' => 10000,
                'status' => PayoutStatus::Pending->value,
                'method' => 'bank_transfer',
            ]));

        $this->payoutLiabilityRecoveryRepository
            ->shouldNotReceive('record');

        $this->payoutLedgerService
            ->shouldReceive('attachSettledEntriesToPayout')
            ->once()
            ->with(99, 7, 10000, 1)
            ->andReturn(10000);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($event): bool => $event instanceof PayoutRequestedEvent);

        $payout = $this->service->requestPayout(7, 1, 'bank_transfer');

        $this->assertEquals(PayoutStatus::Pending->value, $payout->status);
        $this->assertEquals(10000, (int) $payout->amount);
    }

    public function test_request_payout_throws_when_balance_below_minimum(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;

        $this->siteRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canWithdraw')
            ->with(7, $site)
            ->once()
            ->andReturn(true);

        $this->creatorBalanceService
            ->shouldReceive('settledBalance')
            ->with(7, 1)
            ->once()
            ->andReturn(4000);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(0);

        $this->setOffService->shouldNotReceive('apply');

        $this->payoutRepository->shouldNotReceive('findByIdempotencyKey');
        $this->payoutRepository->shouldNotReceive('createWithIdempotency');
        $this->payoutRepository->shouldNotReceive('create');
        $this->payoutLedgerService->shouldNotReceive('attachSettledEntriesToPayout');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Minimum payout/i');

        $this->service->requestPayout(7, 1, 'bank_transfer');
    }

    public function test_request_payout_throws_when_payout_already_in_flight(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;

        $this->siteRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canWithdraw')
            ->with(7, $site)
            ->once()
            ->andReturn(true);

        $this->creatorBalanceService
            ->shouldReceive('settledBalance')
            ->with(7, 1)
            ->once()
            ->andReturn(20000);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(10000);

        $this->setOffService->shouldNotReceive('apply');
        $this->payoutRepository->shouldNotReceive('createWithIdempotency');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already in progress/i');

        $this->service->requestPayout(7, 1, 'bank_transfer');
    }

    public function test_request_payout_returns_existing_payout_when_idempotency_key_already_exists(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;

        $existing = $this->makePayout([
            'id' => 50,
            'user_id' => 7,
            'site_id' => 1,
            'amount' => 7000,
            'status' => PayoutStatus::Pending->value,
        ]);

        $this->siteRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canWithdraw')
            ->with(7, $site)
            ->once()
            ->andReturn(true);

        $this->creatorBalanceService
            ->shouldReceive('settledBalance')
            ->with(7, 1)
            ->once()
            ->andReturn(7000);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(0);

        $this->setOffService->shouldNotReceive('apply');

        $this->payoutRepository
            ->shouldReceive('findByIdempotencyKey')
            ->once()
            ->andReturn($existing);

        $this->payoutRepository->shouldNotReceive('createWithIdempotency');
        $this->payoutLedgerService->shouldNotReceive('attachSettledEntriesToPayout');

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($event): bool => $event instanceof PayoutRequestedEvent);

        $result = $this->service->requestPayout(7, 1, 'bank_transfer');

        $this->assertSame(50, (int) $result->id);
    }

    public function test_request_payout_throws_for_invalid_method(): void
    {
        $this->policy->shouldNotReceive('canWithdraw');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid payout method/i');
        $this->service->requestPayout(7, 1, 'magic_money');
    }

    public function test_request_payout_wraps_in_transaction(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;

        $transactionCalled = false;

        $this->databaseMock = Mockery::mock(Database::class);
        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) use (&$transactionCalled) {
                $transactionCalled = true;
                return $callback();
            });

        $this->service = new PayoutService(
            $this->payoutRepository,
            $this->payoutAuditRepository,
            $this->ledgerRepository,
            $this->paymentRepository,
            $this->userRepository,
            $this->eventDispatcher,
            $this->databaseMock,
            $this->notificationDispatcher,
            $this->policy,
            $this->siteRepository,
            $this->creatorBalanceService,
            $this->setOffService,
            $this->payoutLedgerService,
            $this->payoutLiabilityRecoveryRepository,
        );

        $this->siteRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canWithdraw')
            ->with(7, $site)
            ->once()
            ->andReturn(true);

        $this->creatorBalanceService
            ->shouldReceive('settledBalance')
            ->with(7, 1)
            ->once()
            ->andReturn(10000);

        $this->payoutRepository
            ->shouldReceive('totalInFlightForContributor')
            ->with(7, 1)
            ->once()
            ->andReturn(0);

        $this->setOffService
            ->shouldReceive('apply')
            ->with(7, 1, 10000)
            ->once()
            ->andReturn(new SetOffResult(
                grossAmount: 10000,
                deductedAmount: 0,
                netAmount: 10000,
                deductions: [],
            ));

        $this->payoutRepository
            ->shouldReceive('findByIdempotencyKey')
            ->once()
            ->andReturn(null);

        $this->payoutRepository
            ->shouldReceive('createWithIdempotency')
            ->once()
            ->andReturn($this->makePayout([
                'id' => 99,
                'status' => PayoutStatus::Pending->value,
                'amount' => 10000,
            ]));

        $this->payoutLedgerService
            ->shouldReceive('attachSettledEntriesToPayout')
            ->once()
            ->with(99, 7, 10000, 1)
            ->andReturn(10000);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($event): bool => $event instanceof PayoutRequestedEvent);

        $this->service->requestPayout(7, 1, 'bank_transfer');

        $this->assertTrue($transactionCalled);
    }

    // ── approve() ─────────────────────────────────────────────────────────────

    public function test_approve_transitions_pending_to_approved_and_logs_audit(): void
    {
        $payout = $this->makePayout([
            'id' => 5,
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);

        $approved = $this->makePayout([
            'id' => 5,
            'status' => PayoutStatus::Approved->value,
            'method' => 'bank_transfer',
        ]);

        $this->payoutRepository
            ->shouldReceive('find')
            ->with(5)
            ->andReturn($payout, $approved);

        $this->payoutRepository
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, $data) =>
                $id === 5
                && $data['status'] === PayoutStatus::Approved->value
                && $data['approved_by'] === 99
            );

        $this->payoutAuditRepository
            ->shouldReceive('log')
            ->once()
            ->withArgs(fn($pid, $action) =>
                $pid === 5
                && $action === PayoutAuditAction::Approved
            );

        $result = $this->service->approve(5, adminId: 99);

        $this->assertEquals(PayoutStatus::Approved->value, $result->status);
    }

    public function test_approve_throws_when_payout_not_pending(): void
    {
        $payout = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Paid->value]);

        $this->payoutRepository->shouldReceive('find')->andReturn($payout);
        $this->payoutRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->approve(5, 99);
    }

    public function test_approve_throws_when_payout_not_found(): void
    {
        $this->payoutRepository->shouldReceive('find')->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->approve(999, 99);
    }

    // ── markPaid() ────────────────────────────────────────────────────────────

    public function test_mark_paid_transitions_approved_to_paid_logs_audit_and_dispatches_event(): void
    {
        $payout = $this->makePayout([
            'id' => 5,
            'user_id' => 7,
            'status' => PayoutStatus::Approved->value,
            'method' => 'paypal',
        ]);

        $paid = $this->makePayout([
            'id' => 5,
            'user_id' => 7,
            'status' => PayoutStatus::Paid->value,
            'method' => 'paypal',
        ]);

        $contributor = Mockery::mock(\App\Models\User::class)->makePartial();
        $contributor->id = 7;
        $contributor->exists = true;

        $this->payoutRepository
            ->shouldReceive('find')
            ->with(5)
            ->andReturn($payout, $paid);

        $this->payoutRepository
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn ($id, array $data): bool =>
                $id === 5
                && $data['status'] === PayoutStatus::Paid->value
                && $data['paid_by'] === 99
                && $data['reference'] === 'REF-001'
                && $data['notes'] === null
                && isset($data['processed_at'])
            );

        $this->payoutAuditRepository
            ->shouldReceive('log')
            ->once()
            ->withArgs(fn (
                int $payoutId,
                PayoutAuditAction $action,
                int $performedBy,
                ?string $reason = null,
            ): bool =>
                $payoutId === 5
                && $action === PayoutAuditAction::Paid
                && $performedBy === 99
                && $reason === null
            );

        $this->payoutLedgerService
            ->shouldReceive('markPayoutLedgerEntriesWithdrawn')
            ->once()
            ->with(5);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($event): bool =>
                $event instanceof PayoutProcessedEvent
                && $event->adminId === 99
                && $event->userId === 7
            );

        $this->userRepository
            ->shouldReceive('find')
            ->once()
            ->with(7)
            ->andReturn($contributor);

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(\App\Services\OpenCollab\Notifications\PayoutPaidNotification::class));

        $result = $this->service->markPaid(5, 99, 'REF-001');

        $this->assertEquals(PayoutStatus::Paid->value, $result->status);
    }

    public function test_mark_paid_throws_when_payout_not_approved(): void
    {
        $payout = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Pending->value]);

        $this->payoutRepository->shouldReceive('find')->andReturn($payout);
        $this->payoutRepository->shouldNotReceive('update');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->markPaid(5, 99);
    }

    public function test_mark_paid_rejects_stripe_payouts(): void
    {
        $payout = $this->makePayout([
            'id' => 6,
            'status' => PayoutStatus::Approved->value,
            'method' => 'stripe',
        ]);

        $this->payoutRepository->shouldReceive('find')->andReturn($payout);
        $this->payoutRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be finalised by Stripe webhooks/i');
        $this->service->markPaid(6, 99);
    }

    public function test_retry_stripe_failed_payout_transitions_to_approved(): void
    {
        $failed = $this->makePayout([
            'id' => 15,
            'status' => PayoutStatus::Failed->value,
            'method' => 'stripe',
        ]);
        $approved = $this->makePayout([
            'id' => 15,
            'status' => PayoutStatus::Approved->value,
            'method' => 'stripe',
        ]);

        $this->payoutRepository->shouldReceive('find')->with(15)->andReturn($failed, $approved);
        $this->payoutRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, $data) => $id === 15
                && $data['status'] === PayoutStatus::Approved->value
                && $data['provider_status'] === 'retry_queued'
            );
        $this->payoutAuditRepository->shouldReceive('log')
            ->once()
            ->withArgs(fn($pid, $action) => $pid === 15 && $action === PayoutAuditAction::Approved);

        $result = $this->service->retryStripeFailedPayout(15, 99);
        $this->assertEquals(PayoutStatus::Approved->value, $result->status);
    }

    // ── reject() ──────────────────────────────────────────────────────────────

    public function test_reject_transitions_pending_to_rejected_with_audit_and_reason(): void
    {
        $payout = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Pending->value]);
        $rejected = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Rejected->value]);

        $this->payoutRepository->shouldReceive('find')->andReturn($payout, $rejected);
        $this->payoutRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, $data) => $data['status'] === PayoutStatus::Rejected->value
                && $data['rejection_reason'] === 'Missing bank details.');
        $this->payoutAuditRepository->shouldReceive('log')
            ->once()
            ->withArgs(fn($pid, $action, $adminId, $reason): bool => $pid === 5
                && $action === PayoutAuditAction::Declined
                && $adminId === 99
                && $reason === 'Missing bank details.'
            );

        $result = $this->service->reject(5, 99, 'Missing bank details.');

        $this->assertEquals(PayoutStatus::Rejected->value, $result->status);
    }

    public function test_reject_throws_when_payout_not_pending(): void
    {
        $payout = $this->makePayout(['status' => PayoutStatus::Approved->value]);

        $this->payoutRepository->shouldReceive('find')->andReturn($payout);
        $this->payoutRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->reject(1, 99, 'reason');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makePayout(array $attributes = []): Payout
    {
        $defaults = [
            'id' => 1,
            'user_id' => 7,
            'site_id' => 1,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ];
        $payout = new Payout(array_merge($defaults, $attributes));
        $payout->exists = true;
        return $payout;
    }

    protected function setUp(): void
    {

        $this->payoutRepository = Mockery::mock(PayoutRepository::class);
        $this->ledgerRepository = Mockery::mock(EarningsLedgerRepository::class);
        $this->paymentRepository = Mockery::mock(ArticlePaymentRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->payoutAuditRepository = Mockery::mock(PayoutAuditRepository::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->notificationDispatcher = Mockery::mock(NotificationDispatcher::class);
        $this->policy = Mockery::mock(ContributorPolicy::class);
        $this->siteRepository = Mockery::mock(SiteRepository::class);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->userRepository->shouldReceive('find')->byDefault();
        $this->notificationDispatcher->shouldReceive('dispatch')->byDefault();
        $this->siteRepository = Mockery::mock(SiteRepository::class);
        $this->creatorBalanceService = Mockery::mock(CreatorBalanceService::class);
        $this->setOffService = Mockery::mock(SetOffService::class);
        $this->payoutLedgerService = Mockery::mock(PayoutLedgerService::class);
        $this->payoutLiabilityRecoveryRepository = Mockery::mock(PayoutLiabilityRecoveryRepository::class);

        $this->ledgerRepository
            ->shouldReceive('settledAvailableForPayout')
            ->byDefault()
            ->andReturn(collect([(object)['id' => 1], (object)['id' => 2]]));

        $this->service = new PayoutService(
            $this->payoutRepository,
            $this->payoutAuditRepository,
            $this->ledgerRepository,
            $this->paymentRepository,
            $this->userRepository,
            $this->eventDispatcher,
            $this->databaseMock,
            $this->notificationDispatcher,
            $this->policy,
            $this->siteRepository,
            $this->creatorBalanceService,
            $this->setOffService,
            $this->payoutLedgerService,
            $this->payoutLiabilityRecoveryRepository,
        );

        $pendingDispatch = Mockery::mock(PendingDispatch::class);

        $pendingDispatch
            ->shouldReceive('onQueue')
            ->byDefault()
            ->andReturnSelf();

        $pendingDispatch
            ->shouldReceive('dispatchNow')
            ->byDefault()
            ->andReturnNull();

        $dispatcher = Mockery::mock(Dispatcher::class);

        $dispatcher
            ->shouldReceive('dispatch')
            ->byDefault()
            ->andReturn($pendingDispatch);

        Container::getInstance()->bind(Dispatcher::class, fn () => $dispatcher);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
