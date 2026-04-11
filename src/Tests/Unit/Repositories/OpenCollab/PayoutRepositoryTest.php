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

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PayoutRepository();
    }
}
