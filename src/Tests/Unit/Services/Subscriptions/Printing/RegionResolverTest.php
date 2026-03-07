<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Models\Subscription;
use App\Models\Territory;
use App\Repositories\Cms\TerritoryRepository;
use App\Services\Subscriptions\Printing\PostcodeRegionResolver;
use App\Services\Subscriptions\Printing\RegionResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class RegionResolverTest extends FunctionalTestCase
{
    private TerritoryRepository|MockInterface $territoryRepository;
    private PostcodeRegionResolver|MockInterface $postcodeRegionResolver;
    private RegionResolver $resolver;

    // =========================================================================
    // Priority 1 — Subscription territory override
    // =========================================================================

    public function test_returns_override_territory_when_flag_set_and_territory_id_present(): void
    {
        $territory = $this->makeTerritory(5, 'Wales');

        $subscription = $this->makeSubscription(territoryId: 5, overrideFlag: true);

        $this->territoryRepository
            ->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn($territory);

        $this->postcodeRegionResolver->shouldNotReceive('resolve');

        $result = $this->resolver->resolve($subscription, 'SW1A 1AA');

        $this->assertSame($territory, $result);
    }

    private function makeTerritory(int $id, string $name): Territory
    {
        $territory = Mockery::mock(Territory::class)->makePartial();
        $territory->id = $id;
        $territory->name = $name;
        return $territory;
    }

    private function makeSubscription(?int $territoryId, bool $overrideFlag): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->territory_id = $territoryId;
        $subscription->territory_override_flag = $overrideFlag;
        return $subscription;
    }

    // =========================================================================
    // Priority 2 — Postcode-derived territory
    // =========================================================================

    public function test_does_not_use_override_when_flag_is_false(): void
    {
        $territory = $this->makeTerritory(7, 'Scotland');

        $subscription = $this->makeSubscription(territoryId: 5, overrideFlag: false);

        $this->territoryRepository->shouldNotReceive('find');

        $this->postcodeRegionResolver
            ->shouldReceive('resolve')
            ->once()
            ->with('SW1A 1AA')
            ->andReturn($territory);

        $result = $this->resolver->resolve($subscription, 'SW1A 1AA');

        $this->assertSame($territory, $result);
    }

    public function test_does_not_use_override_when_territory_id_is_null(): void
    {
        $territory = $this->makeTerritory(7, 'Scotland');

        $subscription = $this->makeSubscription(territoryId: null, overrideFlag: true);

        $this->territoryRepository->shouldNotReceive('find');

        $this->postcodeRegionResolver
            ->shouldReceive('resolve')
            ->once()
            ->with('CF10 3NQ')
            ->andReturn($territory);

        $result = $this->resolver->resolve($subscription, 'CF10 3NQ');

        $this->assertSame($territory, $result);
    }

    public function test_derives_territory_from_postcode_when_no_override(): void
    {
        $territory = $this->makeTerritory(3, 'Wales');

        $subscription = $this->makeSubscription(territoryId: null, overrideFlag: false);

        $this->postcodeRegionResolver
            ->shouldReceive('resolve')
            ->once()
            ->with('CF10 3NQ')
            ->andReturn($territory);

        $result = $this->resolver->resolve($subscription, 'CF10 3NQ');

        $this->assertSame($territory, $result);
    }

    // =========================================================================
    // Priority 3 — No territory resolved
    // =========================================================================

    public function test_skips_postcode_resolver_when_postcode_is_null(): void
    {
        $subscription = $this->makeSubscription(territoryId: null, overrideFlag: false);

        $this->postcodeRegionResolver->shouldNotReceive('resolve');

        $result = $this->resolver->resolve($subscription, null);

        $this->assertNull($result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_skips_postcode_resolver_when_postcode_is_empty_string(): void
    {
        $subscription = $this->makeSubscription(territoryId: null, overrideFlag: false);

        $this->postcodeRegionResolver->shouldNotReceive('resolve');

        $result = $this->resolver->resolve($subscription, '');

        $this->assertNull($result);
    }

    public function test_returns_null_when_no_override_and_no_postcode_mapping(): void
    {
        $subscription = $this->makeSubscription(territoryId: null, overrideFlag: false);

        $this->postcodeRegionResolver
            ->shouldReceive('resolve')
            ->once()
            ->andReturn(null);

        $result = $this->resolver->resolve($subscription, 'ZZ99 9ZZ');

        $this->assertNull($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->territoryRepository = Mockery::mock(TerritoryRepository::class);
        $this->postcodeRegionResolver = Mockery::mock(PostcodeRegionResolver::class);
        $this->resolver = new RegionResolver($this->territoryRepository, $this->postcodeRegionResolver);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}