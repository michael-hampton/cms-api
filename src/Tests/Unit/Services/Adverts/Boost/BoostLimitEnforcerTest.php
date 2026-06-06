<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Models\Boost;
use App\Models\BoostLimit;
use App\Models\BoostStat;
use App\Events\Boost\BoostLimitBreachedEvent;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Adverts\Boost\BoostStatRepository;
use App\Services\Adverts\Boost\BoostLimitEnforcer;
use App\Services\Adverts\Boost\BoostService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use Mockery\MockInterface;

class BoostLimitEnforcerTest extends FunctionalTestCase
{
    private MockInterface $boostRepository;
    private MockInterface $boostStatRepository;
    private MockInterface $boostService;
    private BoostLimitEnforcer $enforcer;
    private CapturingEventDispatcher $events;

    public function test_does_nothing_when_no_limit_set(): void
    {
        $boost = $this->makeBoost();
        $boost->setRelation('limit', null);

        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->boostService->shouldNotReceive('pauseBoost');

        $this->enforcer->enforce(1);
        $this->assertTrue(true);
    }

    private function makeBoost(string $status = 'active'): Boost
    {
        $boost = new Boost(['status' => $status, 'merchant_id' => 1]);
        $boost->id = 1;
        return $boost;
    }

    public function test_does_nothing_when_boost_not_active(): void
    {
        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($this->makeBoost('paused'));
        $this->boostService->shouldNotReceive('pauseBoost');

        $this->enforcer->enforce(1);
        $this->assertTrue(true);
    }

    public function test_pauses_boost_and_emits_event_when_spend_limit_breached(): void
    {
        $boost = $this->makeBoost('active');
        $boost->setRelation('limit', $this->makeLimit(['max_spend' => 20.00]));

        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->boostStatRepository->shouldReceive('findByBoost')->with(1)
            ->andReturn($this->makeStat(['spend_attributed' => 21.00]));

        $this->boostService->shouldReceive('pauseBoost')->once()->with(1);

        $this->enforcer->enforce(1);
        $this->events->assertDispatched(
            BoostLimitBreachedEvent::class,
            fn(BoostLimitBreachedEvent $event): bool => $event->boost === $boost
                && $event->limitType === 'spend'
                && $event->limitValue === 20.00
                && $event->currentValue === 21.00
        );
    }

    private function makeLimit(array $overrides = []): BoostLimit
    {
        return new BoostLimit(array_merge([
            'boost_id' => 1,
            'max_impressions' => null,
            'max_clicks' => null,
            'max_spend' => null,
            'pause_on_breach' => true,
        ], $overrides));
    }

    private function makeStat(array $overrides = []): BoostStat
    {
        return new BoostStat(array_merge([
            'boost_id' => 1,
            'impressions' => 0,
            'clicks' => 0,
            'conversions' => 0,
            'spend_attributed' => 0.0,
        ], $overrides));
    }

    public function test_pauses_when_click_limit_breached(): void
    {
        $boost = $this->makeBoost('active');
        $boost->setRelation('limit', $this->makeLimit(['max_clicks' => 100]));

        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->boostStatRepository->shouldReceive('findByBoost')->with(1)
            ->andReturn($this->makeStat(['clicks' => 101]));

        $this->boostService->shouldReceive('pauseBoost')->once()->with(1);

        $this->enforcer->enforce(1);
        $this->assertTrue(true);
    }

    public function test_pauses_when_impression_limit_breached(): void
    {
        $boost = $this->makeBoost('active');
        $boost->setRelation('limit', $this->makeLimit(['max_impressions' => 1000]));

        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->boostStatRepository->shouldReceive('findByBoost')->with(1)
            ->andReturn($this->makeStat(['impressions' => 1001]));

        $this->boostService->shouldReceive('pauseBoost')->once()->with(1);

        $this->enforcer->enforce(1);
        $this->assertTrue(true);
    }

    public function test_spend_takes_priority_over_clicks_when_both_breached(): void
    {
        $boost = $this->makeBoost('active');
        $boost->setRelation('limit', $this->makeLimit([
            'max_spend' => 20.00,
            'max_clicks' => 50,
        ]));

        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->boostStatRepository->shouldReceive('findByBoost')->with(1)
            ->andReturn($this->makeStat(['spend_attributed' => 25.00, 'clicks' => 60]));

        $this->boostService->shouldReceive('pauseBoost')->once()->with(1);

        $this->enforcer->enforce(1);
        $this->assertTrue(true);
    }

    public function test_does_not_pause_when_under_all_limits(): void
    {
        $boost = $this->makeBoost('active');
        $boost->setRelation('limit', $this->makeLimit([
            'max_spend' => 50.00,
            'max_clicks' => 200,
            'max_impressions' => 2000,
        ]));

        $this->boostRepository->shouldReceive('find')->with(1)->andReturn($boost);
        $this->boostStatRepository->shouldReceive('findByBoost')->with(1)
            ->andReturn($this->makeStat([
                'spend_attributed' => 25.00,
                'clicks' => 100,
                'impressions' => 1000,
            ]));

        $this->boostService->shouldNotReceive('pauseBoost');

        $this->enforcer->enforce(1);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        $this->boostRepository = Mockery::mock(BoostRepository::class);
        $this->boostStatRepository = Mockery::mock(BoostStatRepository::class);
        $this->boostService = Mockery::mock(BoostService::class);
        $this->events = CapturingEventDispatcher::fake();

        $this->enforcer = new BoostLimitEnforcer(
            $this->boostRepository,
            $this->boostStatRepository,
            $this->boostService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
