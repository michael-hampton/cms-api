<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Enums\Boost\BoostStatus;
use App\Framework\Support\Collection;
use App\Jobs\ExpireBoostsJob;
use App\Models\Boost;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Services\Adverts\Boost\BoostService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class ExpireBoostsJobTest extends FunctionalTestCase
{
    public function test_expires_boosts_past_end_date(): void
    {
        $now = new \DateTimeImmutable('2026-01-10');
        $boost = new Boost(['id' => 1, 'status' => BoostStatus::Active->value]);
        $boost->id = 1;

        $repository = Mockery::mock(BoostRepository::class);
        $service = Mockery::mock(BoostService::class);

        $repository->shouldReceive('getExpiredBoosts')
            ->with($now)
            ->andReturn(new Collection([$boost]));

        $service->shouldReceive('expireBoost')
            ->once()
            ->with(1, $now);

        $job = new ExpireBoostsJob($repository, $service);
        $job->handle($now);

        $this->assertTrue(true);
    }

    public function test_is_idempotent_on_double_run(): void
    {
        $now = new \DateTimeImmutable('2026-01-10');

        $repository = Mockery::mock(BoostRepository::class);
        $service = Mockery::mock(BoostService::class);

        // Second run finds nothing to expire
        $repository->shouldReceive('getExpiredBoosts')->andReturn(new Collection([]));
        $service->shouldNotReceive('expireBoost');

        $job = new ExpireBoostsJob($repository, $service);
        $job->handle($now);
        $job->handle($now); // idempotent

        $this->assertTrue(true);
    }

    public function test_continues_when_single_boost_expiry_fails(): void
    {
        $now = new \DateTimeImmutable('2026-01-10');
        $boost1 = new Boost(['id' => 1]);
        $boost1->id = 1;
        $boost2 = new Boost(['id' => 2]);
        $boost2->id = 2;

        $repository = Mockery::mock(BoostRepository::class);
        $service = Mockery::mock(BoostService::class);

        $repository->shouldReceive('getExpiredBoosts')
            ->andReturn(new Collection([$boost1, $boost2]));

        $service->shouldReceive('expireBoost')
            ->with(1, $now)
            ->andThrow(new \RuntimeException('DB error'));

        $service->shouldReceive('expireBoost')
            ->with(2, $now);

        $job = new ExpireBoostsJob($repository, $service);
        $job->handle($now); // Should not throw
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}