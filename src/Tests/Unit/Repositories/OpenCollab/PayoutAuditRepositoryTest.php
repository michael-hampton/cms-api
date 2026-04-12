<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\PayoutAuditAction;
use App\Enums\OpenCollab\PayoutStatus;
use App\Models\Payout;
use App\Models\PayoutAudit;
use App\Repositories\OpenCollab\PayoutAuditRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class PayoutAuditRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PayoutAuditRepository $repository;

    // ── log() ─────────────────────────────────────────────────────────────────

    public function test_log_creates_audit_record_with_correct_fields(): void
    {
        $user = $this->createUser();
        $payout = $this->createPayout($user->id);
        $admin = $this->createUser();

        $audit = $this->repository->log(
            payoutId: $payout->id,
            action: PayoutAuditAction::Approved,
            performedBy: $admin->id,
        );

        $this->assertInstanceOf(PayoutAudit::class, $audit);
        $this->assertEquals($payout->id, $audit->payout_id);
        $this->assertEquals(PayoutAuditAction::Approved->value, $audit->action);
        $this->assertEquals($admin->id, $audit->performed_by);
        $this->assertNull($audit->reason);
        $this->assertNotNull($audit->created_at);
    }

    private function createPayout(int $userId): Payout
    {
        $payout = Payout::create([
            'user_id' => $userId,
            'site_id' => $this->siteId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);
        $payout->exists = true;
        return $payout;
    }

    public function test_log_stores_optional_reason(): void
    {
        $user = $this->createUser();
        $payout = $this->createPayout($user->id);
        $admin = $this->createUser();

        $audit = $this->repository->log(
            payoutId: $payout->id,
            action: PayoutAuditAction::Declined,
            performedBy: $admin->id,
            reason: 'Missing bank details.',
        );

        $this->assertEquals('Missing bank details.', $audit->reason);
    }

    // ── forPayout() ──────────────────────────────────────────────────────────

    public function test_log_accepts_all_action_values(): void
    {
        $user = $this->createUser();
        $admin = $this->createUser();

        foreach (PayoutAuditAction::cases() as $action) {
            $payout = $this->createPayout($user->id);
            $audit = $this->repository->log(
                payoutId: $payout->id,
                action: $action,
                performedBy: $admin->id,
            );
            $this->assertEquals($action->value, $audit->action);
        }
    }

    public function test_for_payout_returns_only_audits_for_that_payout(): void
    {
        $user = $this->createUser();
        $admin = $this->createUser();
        $payout1 = $this->createPayout($user->id);
        $payout2 = $this->createPayout($user->id);

        $this->repository->log($payout1->id, PayoutAuditAction::Approved, $admin->id);
        $this->repository->log($payout2->id, PayoutAuditAction::Declined, $admin->id);

        $results = $this->repository->forPayout($payout1->id);

        $this->assertCount(1, $results);
        $this->assertEquals($payout1->id, $results->first()->payout_id);
    }

    public function test_for_payout_returns_audits_in_chronological_order(): void
    {
        $user = $this->createUser();
        $admin = $this->createUser();
        $payout = $this->createPayout($user->id);

        $first = PayoutAudit::create([
            'payout_id' => $payout->id,
            'action' => PayoutAuditAction::Approved->value,
            'performed_by' => $admin->id,
            'created_at' => '2024-01-01 09:00:00',
        ]);
        $second = PayoutAudit::create([
            'payout_id' => $payout->id,
            'action' => PayoutAuditAction::Paid->value,
            'performed_by' => $admin->id,
            'created_at' => '2024-01-02 10:00:00',
        ]);

        $results = $this->repository->forPayout($payout->id);

        $this->assertEquals($first->id, $results->first()->id);
        $this->assertEquals($second->id, $results->last()->id);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function test_for_payout_returns_empty_collection_when_no_audits(): void
    {
        $user = $this->createUser();
        $payout = $this->createPayout($user->id);

        $results = $this->repository->forPayout($payout->id);

        $this->assertCount(0, $results);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PayoutAuditRepository();
    }
}