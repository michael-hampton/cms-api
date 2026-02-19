<?php

namespace App\Tests\Unit\Repositories\Adverts\Boost;

use App\Enums\Boost\BoostStatus;
use App\Models\Boost;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BoostRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private BoostRepository $repository;

    public function test_create_persists_boost(): void
    {
        $boost = $this->repository->create($this->makeBoostData());

        $this->assertNotNull($boost->id);
        $this->assertEquals('listing', $boost->context);
        $this->assertEquals(BoostStatus::Pending->value, $boost->status);
    }

    private function makeBoostData(array $overrides = []): array
    {
        return array_merge([
            'merchant_id' => 1,
            'boostable_type' => 'product',
            'boostable_id' => 1,
            'context' => 'listing',
            'status' => BoostStatus::Pending->value,
            'multiplier' => 1.5,
            'price_paid' => 35.00,
            'currency' => 'GBP',
            'starts_at' => '2026-01-01 00:00:00',
            'ends_at' => '2026-01-08 00:00:00',
        ], $overrides);
    }

    public function test_find_returns_boost_by_id(): void
    {
        $created = Boost::create($this->makeBoostData());

        $found = $this->repository->find($created->id);

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_find_returns_null_for_missing_id(): void
    {
        $this->assertNull($this->repository->find(99999));
    }

    public function test_update_persists_changes(): void
    {
        $boost = Boost::create($this->makeBoostData());

        $this->repository->update($boost->id, ['status' => BoostStatus::Active->value]);

        $refreshed = $this->repository->find($boost->id);
        $this->assertEquals(BoostStatus::Active->value, $refreshed->status);
    }

    public function test_find_active_for_target_returns_active_boost(): void
    {
        Boost::create($this->makeBoostData(['status' => BoostStatus::Active->value]));

        $found = $this->repository->findActiveForTarget('product', 1);

        $this->assertNotNull($found);
    }

    public function test_find_active_for_target_returns_null_when_no_active_boost(): void
    {
        Boost::create($this->makeBoostData(['status' => BoostStatus::Expired->value]));

        $this->assertNull($this->repository->findActiveForTarget('product', 1));
    }

    public function test_has_active_boost_returns_true(): void
    {
        Boost::create($this->makeBoostData(['status' => BoostStatus::Active->value]));

        $this->assertTrue($this->repository->hasActiveBoost('product', 1));
    }

    public function test_has_active_boost_returns_false_when_only_expired(): void
    {
        Boost::create($this->makeBoostData(['status' => BoostStatus::Expired->value]));

        $this->assertFalse($this->repository->hasActiveBoost('product', 1));
    }

    public function test_get_expired_boosts_returns_only_active_past_end_date(): void
    {
        Boost::create($this->makeBoostData([
            'status' => BoostStatus::Active->value,
            'ends_at' => '2025-01-01 00:00:00', // past
        ]));
        Boost::create($this->makeBoostData([
            'status' => BoostStatus::Active->value,
            'ends_at' => '2030-01-01 00:00:00', // future
        ]));

        $expired = $this->repository->getExpiredBoosts(now_datetime());

        $this->assertCount(1, $expired);
    }

    public function test_get_active_boosts_for_context_filters_correctly(): void
    {
        Boost::create($this->makeBoostData(['status' => BoostStatus::Active->value, 'context' => 'listing']));
        Boost::create($this->makeBoostData(['status' => BoostStatus::Active->value, 'context' => 'deals']));
        Boost::create($this->makeBoostData(['status' => BoostStatus::Expired->value, 'context' => 'listing']));

        $results = $this->repository->getActiveBoostsForContext('listing');

        $this->assertCount(1, $results);
        $this->assertEquals('listing', $results->first()->context);
    }

    public function test_get_by_status_returns_matching_boosts(): void
    {
        Boost::create($this->makeBoostData(['status' => BoostStatus::Active->value]));
        Boost::create($this->makeBoostData(['status' => BoostStatus::Active->value]));
        Boost::create($this->makeBoostData(['status' => BoostStatus::Paused->value]));

        $active = $this->repository->getByStatus(BoostStatus::Active);

        $this->assertCount(2, $active);
    }

    public function test_create_limit_persists_limit(): void
    {
        $boost = Boost::create($this->makeBoostData());

        $limit = $this->repository->createLimit($boost->id, [
            'boost_id' => $boost->id,
            'max_spend' => 50.00,
        ]);

        $this->assertNotNull($limit->id);
        $this->assertEquals(50.00, $limit->max_spend);
    }

    public function test_find_active_or_recent_for_target_returns_latest(): void
    {
        Boost::create($this->makeBoostData([
            'status' => BoostStatus::Expired->value,
            'ends_at' => '2025-06-01 00:00:00',
        ]));
        $recent = Boost::create($this->makeBoostData([
            'status' => BoostStatus::Expired->value,
            'ends_at' => '2026-01-01 00:00:00',
        ]));

        $found = $this->repository->findActiveOrRecentForTarget('product', 1);

        $this->assertEquals($recent->id, $found->id);
    }

    public function test_get_all_with_filters_returns_paginated_results(): void
    {
        Boost::create($this->makeBoostData(['merchant_id' => 1]));
        Boost::create($this->makeBoostData(['merchant_id' => 1]));
        Boost::create($this->makeBoostData(['merchant_id' => 2]));

        $results = $this->repository->getAllWithFilters(['merchant_id' => 1, 'page' => 1, 'perPage' => 10]);

        $this->assertCount(2, $results['data']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BoostRepository();
    }
}