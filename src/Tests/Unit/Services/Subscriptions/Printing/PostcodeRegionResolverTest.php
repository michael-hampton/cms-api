<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Models\Territory;
use App\Repositories\Cms\TerritoryRepository;
use App\Services\Subscriptions\Printing\PostcodeRegionResolver;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class PostcodeRegionResolverTest extends UnitTestCase
{
    private TerritoryRepository|MockInterface $territoryRepository;
    private PostcodeRegionResolver $resolver;

    public function test_resolves_territory_from_two_character_prefix(): void
    {
        $territory = $this->makeTerritory(1, 'South East');

        $this->territoryRepository
            ->shouldReceive('findByPostcodePrefix')
            ->once()
            ->with('SW')
            ->andReturn($territory);

        $result = $this->resolver->resolve('SW1A 1AA');

        $this->assertSame($territory, $result);
    }

    private function makeTerritory(int $id, string $name): Territory
    {
        $territory = Mockery::mock(Territory::class)->makePartial();
        $territory->id = $id;
        $territory->name = $name;
        return $territory;
    }

    public function test_uppercases_prefix_before_lookup(): void
    {
        $territory = $this->makeTerritory(2, 'Scotland');

        $this->territoryRepository
            ->shouldReceive('findByPostcodePrefix')
            ->once()
            ->with('EH')
            ->andReturn($territory);

        $result = $this->resolver->resolve('eh1 1aa');

        $this->assertSame($territory, $result);
    }

    public function test_returns_null_when_no_mapping_exists(): void
    {
        $this->territoryRepository
            ->shouldReceive('findByPostcodePrefix')
            ->once()
            ->andReturn(null);

        $result = $this->resolver->resolve('ZZ99 9ZZ');

        $this->assertNull($result);
    }

    public function test_returns_null_for_empty_postcode(): void
    {
        $this->territoryRepository->shouldNotReceive('findByPostcodePrefix');

        $result = $this->resolver->resolve('');

        $this->assertNull($result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_trims_whitespace_from_postcode_before_extracting_prefix(): void
    {
        $territory = $this->makeTerritory(3, 'Wales');

        $this->territoryRepository
            ->shouldReceive('findByPostcodePrefix')
            ->once()
            ->with('CF')
            ->andReturn($territory);

        $result = $this->resolver->resolve('  CF10 3NQ  ');

        $this->assertSame($territory, $result);
    }

    protected function setUp(): void
    {
        $this->territoryRepository = Mockery::mock(TerritoryRepository::class);
        $this->resolver = new PostcodeRegionResolver($this->territoryRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}