<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Models\Payout;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class PayoutRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PayoutRepository $repository;

    public function test_for_contributor_returns_newest_payouts_with_limit(): void
    {
        $user = $this->createUser();

        $older = Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 5000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);
        $this->database->query('UPDATE oc_payouts SET created_at = ? WHERE id = ?', ['2024-01-01 00:00:00', $older->id]);

        $newer = Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 7000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'paypal',
        ]);
        $this->database->query('UPDATE oc_payouts SET created_at = ? WHERE id = ?', ['2024-06-01 00:00:00', $newer->id]);

        $results = $this->repository->forContributor($user->id, 1);

        $this->assertCount(1, $results);
        $this->assertEquals($newer->id, $results->first()->id);
        $this->assertNotEquals($older->id, $results->first()->id);
    }

    public function test_for_site_returns_paginated_payouts_for_current_site_only(): void
    {
        $user = $this->createUser();
        $otherSite = $this->createSite();

        Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 5000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);

        Payout::create([
            'user_id' => $user->id,
            'site_id' => $otherSite->id,
            'amount' => 5000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);

        $results = $this->repository->forSite($this->siteId, 10);

        $this->assertCount(1, $results['data']->all());
        $this->assertEquals($this->siteId, $results['data']->all()[0]->site_id);
    }

    public function test_pending_and_totals_only_include_matching_statuses(): void
    {
        $user = $this->createUser();

        Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 1000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);
        Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 2000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'bank_transfer',
        ]);
        Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 3000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Paid->value,
            'method' => 'bank_transfer',
        ]);
        Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 4000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Rejected->value,
            'method' => 'bank_transfer',
        ]);

        $pending = $this->repository->pendingForSite($this->siteId);

        $this->assertCount(1, $pending);
        $this->assertEquals(3000, $this->repository->totalPaidForContributor($user->id));
        $this->assertEquals(3000, $this->repository->totalInFlightForContributor($user->id));
    }

    public function test_create_with_idempotency_requires_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payout idempotency key is required.');

        $this->repository->createWithIdempotency([
            'user_id' => 7,
            'site_id' => 1,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);
    }

    public function test_create_with_idempotency_persists_key(): void
    {
        $user = $this->createUser();

        $payout = $this->repository->createWithIdempotency([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
            'idempotency_key' => 'payout:test:key',
            'processing_attempts' => 0,
        ]);

        $this->assertSame('payout:test:key', $payout->idempotency_key);
    }

    public function test_find_by_idempotency_key_returns_matching_payout(): void
    {
        $user = $this->createUser();

        Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
            'idempotency_key' => 'payout:abc',
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $found = $this->repository->findByIdempotencyKey('payout:abc');

        $this->assertNotNull($found);
        $this->assertSame($user->id, (int) $found->user_id);
    }

    public function test_exists_for_window_and_batch_returns_true_when_duplicate_exists(): void
    {
        $user = $this->createUser();

        Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'batch_id' => 20,
            'accrual_window_id' => 30,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
            'idempotency_key' => 'payout:window:batch',
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $this->assertTrue(
            $this->repository->existsForWindowAndBatch(
                userId: $user->id,
                siteId: $this->siteId,
                accrualWindowId: 30,
                batchId: 20,
            )
        );

        $this->assertFalse(
            $this->repository->existsForWindowAndBatch(
                userId: $user->id,
                siteId: $this->siteId,
                accrualWindowId: 999,
                batchId: 20,
            )
        );
    }

    public function test_increment_processing_attempts_increments_current_value(): void
    {
        $user = $this->createUser();

        $payout = Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'stripe',
            'idempotency_key' => 'payout:attempts',
            'processing_attempts' => 2,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $this->repository->incrementProcessingAttempts((int) $payout->id);

        $fresh = $this->repository->find((int) $payout->id);

        $this->assertSame(3, (int) $fresh->processing_attempts);
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PayoutRepository();
    }
}
