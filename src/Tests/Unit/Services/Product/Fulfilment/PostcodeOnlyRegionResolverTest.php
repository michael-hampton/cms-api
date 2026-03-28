<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Product\Fulfilment;

use App\Models\Territory;
use App\Services\Product\Fulfilment\PostcodeOnlyRegionResolver;
use App\Services\Subscriptions\Printing\PostcodeRegionResolver;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PostcodeOnlyRegionResolverTest extends TestCase
{
    private PostcodeRegionResolver&MockInterface $postcodeRegionResolver;
    private PostcodeOnlyRegionResolver $resolver;

    public function test_it_delegates_to_postcode_region_resolver(): void
    {
        $territory = Mockery::mock(Territory::class);

        $this->postcodeRegionResolver
            ->shouldReceive('resolve')
            ->once()
            ->with('SW1A 2AA')
            ->andReturn($territory);

        $result = $this->resolver->resolve('SW1A 2AA');

        $this->assertSame($territory, $result);
    }

    public function test_it_returns_null_for_null_postcode(): void
    {
        $this->postcodeRegionResolver->shouldNotReceive('resolve');

        $result = $this->resolver->resolve(null);

        $this->assertNull($result);
    }

    public function test_it_returns_null_for_blank_postcode(): void
    {
        $this->postcodeRegionResolver->shouldNotReceive('resolve');

        $result = $this->resolver->resolve('   ');

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_no_territory_mapping_exists(): void
    {
        $this->postcodeRegionResolver
            ->shouldReceive('resolve')
            ->once()
            ->with('XX99 9XX')
            ->andReturn(null);

        $result = $this->resolver->resolve('XX99 9XX');

        $this->assertNull($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->postcodeRegionResolver = Mockery::mock(PostcodeRegionResolver::class);
        $this->resolver = new PostcodeOnlyRegionResolver($this->postcodeRegionResolver);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}