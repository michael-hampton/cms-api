<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Enums\Boost\BoostEventType;
use App\Exceptions\Boost\BoostNotFoundException;
use App\Models\Boost;
use App\Repositories\Adverts\Boost\BoostEventRepository;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Services\Adverts\Boost\BoostEventService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class BoostEventServiceTest extends FunctionalTestCase
{
    private MockInterface $boostRepository;
    private MockInterface $boostEventRepository;
    private BoostEventService $service;

    public function test_records_impression_for_active_boost(): void
    {
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($this->makeBoost('active'));
        $this->boostEventRepository->shouldReceive('hasEvent')
            ->with(1, BoostEventType::Impression, 'abc123')
            ->andReturn(false);
        $this->boostEventRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($d) => $d['type'] === BoostEventType::Impression->value));

        $this->service->recordImpression(1, 'abc123');
        $this->assertTrue(true);
    }

    private function makeBoost(string $status = 'active'): Boost
    {
        $boost = new Boost(['status' => $status]);
        $boost->id = 1;
        return $boost;
    }

    public function test_deduplicates_impressions_for_same_session(): void
    {
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($this->makeBoost('active'));
        $this->boostEventRepository->shouldReceive('hasEvent')
            ->with(1, BoostEventType::Impression, 'abc123')
            ->andReturn(true);
        $this->boostEventRepository->shouldNotReceive('create');

        $this->service->recordImpression(1, 'abc123');
        $this->assertTrue(true);
    }

    public function test_records_click_for_active_boost(): void
    {
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($this->makeBoost('active'));
        $this->boostEventRepository->shouldReceive('hasEvent')
            ->with(1, BoostEventType::Click, 'abc123')
            ->andReturn(false);
        $this->boostEventRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($d) => $d['type'] === BoostEventType::Click->value));

        $this->service->recordClick(1, 'abc123');
        $this->assertTrue(true);
    }

    public function test_deduplicates_clicks_for_same_session(): void
    {
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($this->makeBoost('active'));
        $this->boostEventRepository->shouldReceive('hasEvent')
            ->with(1, BoostEventType::Click, 'abc123')
            ->andReturn(true);
        $this->boostEventRepository->shouldNotReceive('create');

        $this->service->recordClick(1, 'abc123');
        $this->assertTrue(true);
    }

    public function test_records_conversion_without_deduplication(): void
    {
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($this->makeBoost('active'));
        $this->boostEventRepository->shouldReceive('create')
            ->twice()
            ->with(Mockery::on(fn($d) => $d['type'] === BoostEventType::Conversion->value));

        $this->service->recordConversion(1, 'abc123');
        $this->service->recordConversion(1, 'abc123'); // same session, second purchase
        $this->assertTrue(true);
    }

    public function test_throws_when_boost_not_found(): void
    {
        $this->expectException(BoostNotFoundException::class);
        $this->boostRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->service->recordImpression(99, 'abc123');
    }

    public function test_throws_when_boost_is_not_active(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($this->makeBoost('paused'));

        $this->service->recordImpression(1, 'abc123');
    }

    protected function setUp(): void
    {
        $this->boostRepository = Mockery::mock(BoostRepository::class);
        $this->boostEventRepository = Mockery::mock(BoostEventRepository::class);

        $this->service = new BoostEventService(
            $this->boostRepository,
            $this->boostEventRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}