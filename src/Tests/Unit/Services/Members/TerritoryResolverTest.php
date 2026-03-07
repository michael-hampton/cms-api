<?php

namespace App\Tests\Unit\Services\Members;

use App\Models\Territory;
use App\Repositories\Cms\TerritoryRepository;
use App\Services\Members\TerritoryResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class TerritoryResolverTest extends FunctionalTestCase
{
    private TerritoryRepository|MockInterface $repository;
    private TerritoryResolver $resolver;

    public function test_resolves_territory_from_two_character_prefix(): void
    {
        $territory = $this->makeTerritory(1, 'Wales');

        $this->repository
            ->shouldReceive('findByPostcodePrefix')
            ->once()
            ->with('CF')
            ->andReturn($territory);

        $result = $this->resolver->resolve('CF10 3NQ');

        $this->assertSame($territory, $result);
    }

    private function makeTerritory(int $id, string $name): Territory
    {
        $territory = Mockery::mock(Territory::class)->makePartial();
        $territory->id = $id;
        $territory->name = $name;
        return $territory;
    }

    public function test_prefix_is_uppercased_before_lookup(): void
    {
        $territory = $this->makeTerritory(2, 'Scotland');

        $this->repository
            ->shouldReceive('findByPostcodePrefix')
            ->once()
            ->with('EH')
            ->andReturn($territory);

        $result = $this->resolver->resolve('eh1 1aa');

        $this->assertSame($territory, $result);
    }

    public function test_returns_null_when_no_mapping_exists(): void
    {
        $this->repository
            ->shouldReceive('findByPostcodePrefix')
            ->once()
            ->andReturn(null);

        $result = $this->resolver->resolve('XX99 9XX');

        $this->assertNull($result);
    }

    public function test_returns_null_for_empty_postcode(): void
    {
        $this->repository->shouldNotReceive('findByPostcodePrefix');

        $result = $this->resolver->resolve('');

        $this->assertNull($result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_trims_whitespace_before_extracting_prefix(): void
    {
        $territory = $this->makeTerritory(3, 'England');

        $this->repository
            ->shouldReceive('findByPostcodePrefix')
            ->once()
            ->with('SW')
            ->andReturn($territory);

        $result = $this->resolver->resolve('  SW1A 1AA  ');

        $this->assertSame($territory, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(TerritoryRepository::class);
        $this->resolver = new TerritoryResolver($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}