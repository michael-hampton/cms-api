<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Enums\Boost\BoostStatus;
use App\Framework\Container;
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
        $nowUnix = $now->getTimestamp();
        $boost = new Boost(['id' => 1, 'status' => BoostStatus::Active->value]);
        $boost->id = 1;

        $repository = Mockery::mock(BoostRepository::class);
        $service = Mockery::mock(BoostService::class);

        $repository->shouldReceive('getExpiredBoosts')
            ->with(Mockery::on(fn(\DateTimeImmutable $dt) => $dt->getTimestamp() === $nowUnix))
            ->andReturn(new Collection([$boost]));

        $service->shouldReceive('expireBoost')
            ->once()
            ->with(1, $now);

        $container = Container::getInstance();
        $container->instance(BoostRepository::class, $repository);
        $container->instance(BoostService::class, $service);

        $job = ExpireBoostsJob::for($nowUnix);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_is_idempotent_on_double_run(): void
    {
        $now = new \DateTimeImmutable('2026-01-10');
        $nowUnix = $now->getTimestamp();

        $repository = Mockery::mock(BoostRepository::class);
        $service = Mockery::mock(BoostService::class);

        // Second run finds nothing to expire
        $repository->shouldReceive('getExpiredBoosts')->andReturn(new Collection([]));
        $service->shouldNotReceive('expireBoost');

        $container = Container::getInstance();
        $container->instance(BoostRepository::class, $repository);
        $container->instance(BoostService::class, $service);

        $job = ExpireBoostsJob::for($nowUnix);
        $job->__wakeup();
        $job->handle();

        $job2 = ExpireBoostsJob::for($nowUnix);
        $job2->__wakeup();
        $job2->handle(); // idempotent

        $this->assertTrue(true);
    }

    public function test_continues_when_single_boost_expiry_fails(): void
    {
        $now = new \DateTimeImmutable('2026-01-10');
        $nowUnix = $now->getTimestamp();
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

        $container = Container::getInstance();
        $container->instance(BoostRepository::class, $repository);
        $container->instance(BoostService::class, $service);

        $job = ExpireBoostsJob::for($nowUnix);
        $job->__wakeup();
        $job->handle(); // Should not throw
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}