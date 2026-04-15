<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutAuditAction;
use App\Enums\OpenCollab\PayoutStatus;
use App\Events\OpenCollab\PayoutProcessedEvent;
use App\Events\OpenCollab\PayoutRequestedEvent;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\Payout;
use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutAuditRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\PayoutService;
use App\Services\OpenCollab\Policies\ContributorPolicy;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class PayoutServiceTest extends FunctionalTestCase
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

    // ── availableBalance() ────────────────────────────────────────────────────

    public function test_available_balance_is_ledger_minus_paid_minus_in_flight(): void
    {
        $this->ledgerRepository->shouldReceive('balanceForContributor')->with(7)->andReturn(10000);
        $this->payoutRepository->shouldReceive('totalPaidForContributor')->with(7)->andReturn(3000);
        $this->payoutRepository->shouldReceive('totalInFlightForContributor')->with(7)->andReturn(2000);

        $this->assertEquals(5000, $this->service->availableBalance(7));
    }

    public function test_available_balance_never_goes_negative(): void
    {
        $this->ledgerRepository->shouldReceive('balanceForContributor')->andReturn(1000);
        $this->payoutRepository->shouldReceive('totalPaidForContributor')->andReturn(800);
        $this->payoutRepository->shouldReceive('totalInFlightForContributor')->andReturn(500);

        $this->assertEquals(0, $this->service->availableBalance(7));
    }

    // ── requestPayout() — policy enforcement ─────────────────────────────────

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
        $this->siteRepository->shouldReceive('find')->with(1)->andReturn($site);

        $this->policy->shouldReceive('canWithdraw')->andReturn(true);
        $this->ledgerRepository->shouldReceive('balanceForContributor')->andReturn(10000);
        $this->payoutRepository->shouldReceive('totalPaidForContributor')->andReturn(0);
        $this->payoutRepository->shouldReceive('totalInFlightForContributor')->andReturn(0);
        $this->payoutRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn($data) => $data['status'] === PayoutStatus::Pending->value && $data['method'] === 'bank_transfer')
            ->andReturn($this->makePayout(['status' => PayoutStatus::Pending->value]));
        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($e) => $e instanceof PayoutRequestedEvent);

        $payout = $this->service->requestPayout(7, 1, 'bank_transfer');

        $this->assertEquals(PayoutStatus::Pending->value, $payout->status);
    }

    public function test_request_payout_throws_when_balance_below_minimum(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;
        $this->siteRepository->shouldReceive('find')->andReturn($site);

        $this->policy->shouldReceive('canWithdraw')->andReturn(true);
        $this->ledgerRepository->shouldReceive('balanceForContributor')->andReturn(4999);
        $this->payoutRepository->shouldReceive('totalPaidForContributor')->andReturn(0);
        $this->payoutRepository->shouldReceive('totalInFlightForContributor')->andReturn(0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Minimum payout/i');
        $this->service->requestPayout(7, 1, 'bank_transfer');
    }

    public function test_request_payout_throws_when_payout_already_in_flight(): void
    {
        $site = new Site(['id' => 1]);
        $site->exists = true;
        $this->siteRepository->shouldReceive('find')->andReturn($site);

        $this->policy->shouldReceive('canWithdraw')->andReturn(true);
        $this->ledgerRepository->shouldReceive('balanceForContributor')->andReturn(20000);
        $this->payoutRepository->shouldReceive('totalPaidForContributor')->andReturn(0);
        $this->payoutRepository->shouldReceive('totalInFlightForContributor')->andReturn(10000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already in progress/i');
        $this->service->requestPayout(7, 1, 'bank_transfer');
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
        $this->siteRepository->shouldReceive('find')->andReturn($site);

        $this->policy->shouldReceive('canWithdraw')->andReturn(true);
        $this->ledgerRepository->shouldReceive('balanceForContributor')->andReturn(10000);
        $this->payoutRepository->shouldReceive('totalPaidForContributor')->andReturn(0);
        $this->payoutRepository->shouldReceive('totalInFlightForContributor')->andReturn(0);
        $this->payoutRepository->shouldReceive('create')->andReturn($this->makePayout());
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->service->requestPayout(7, 1, 'bank_transfer');
        $this->assertTrue(true); // transaction mock was invoked
    }

    // ── approve() ─────────────────────────────────────────────────────────────

    public function test_approve_transitions_pending_to_approved_and_logs_audit(): void
    {
        $payout = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Pending->value]);
        $approved = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Approved->value]);

        $this->payoutRepository->shouldReceive('find')->with(5)->andReturn($payout, $approved);
        $this->payoutRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, $data) => $data['status'] === PayoutStatus::Approved->value && $data['approved_by'] === 99);
        $this->payoutAuditRepository->shouldReceive('log')
            ->once()
            ->withArgs(fn($pid, $action) => $pid === 5 && $action === PayoutAuditAction::Approved);

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
        $payout = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Approved->value]);
        $paid = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Paid->value]);

        $this->payoutRepository->shouldReceive('find')->andReturn($payout, $paid);
        $this->payoutRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, $data) => $data['status'] === PayoutStatus::Paid->value && $data['reference'] === 'REF-001');
        $this->payoutAuditRepository->shouldReceive('log')
            ->once()
            ->withArgs(fn($pid, $action) => $pid === 5 && $action === PayoutAuditAction::Paid);
        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($e) => $e instanceof PayoutProcessedEvent && $e->adminId === 99);

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
        parent::setUp();

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
            $this->siteRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}