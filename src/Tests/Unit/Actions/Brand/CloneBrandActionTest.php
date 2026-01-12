<?php

namespace App\Tests\Unit\Actions\Brand;

use App\Actions\Brand\CloneBrand;
use App\Framework\Database\Database;
use App\Models\Brand;
use App\Repositories\Cms\BrandRepository;
use App\Services\Cms\ImageUploadService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class CloneBrandActionTest extends FunctionalTestCase
{
    use HasSiteHistory;

    private $brandRepository;
    private $imageUploadService;
    private $databaseMock;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brandRepository = Mockery::mock(BrandRepository::class);
        $this->imageUploadService = Mockery::mock(ImageUploadService::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new CloneBrand(
            $this->brandRepository,
            $this->imageUploadService,
            $this->databaseMock
        );
    }

    public function testDuplicateBrandSuccessfully(): void
    {
        $originalBrand = new Brand([
            'id' => 1,
            'name' => 'Nike',
            'description' => 'Sports brand',
            'website' => 'https://nike.com',
            'logo' => 'logos/nike.png',
            'status' => 'active',
            'slug' => 'nike',
            'seo_title' => 'Nike SEO Title',
            'seo_description' => 'Nike SEO Description',
            'no_index' => false,
            'canonical_url' => 'https://example.com/nike'
        ]);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->brandRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalBrand);

        $this->brandRepository
            ->shouldReceive('findBySlug')
            ->with('nike-copy')
            ->once()
            ->andReturn(null);

        $this->imageUploadService
            ->shouldReceive('duplicate')
            ->with('logos/nike.png')
            ->once()
            ->andReturn('logos/nike-copy.png');

        $newBrand = new Brand([
            'id' => 2,
            'name' => 'Nike (Copy)',
            'slug' => 'nike-copy',
            'status' => 'inactive',
            'seo_title' => 'Nike SEO Title',
            'seo_description' => 'Nike SEO Description',
            'no_index' => false,
            'canonical_url' => null
        ]);

        $this->brandRepository
            ->shouldReceive('create')
            ->with([
                'name' => 'Nike (Copy)',
                'description' => 'Sports brand',
                'website' => 'https://nike.com',
                'status' => 'inactive',
                'seo_title' => 'Nike SEO Title',
                'seo_description' => 'Nike SEO Description',
                'no_index' => false,
                'site_id' => 1,
                'canonical_url' => NULL,
                'slug' => 'nike-copy',
                'logo' => 'logos/nike-copy.png'])
            ->once()
            ->andReturn($newBrand);

        $result = $this->service->handle(1, null, 1);

        $this->assertEquals('Nike (Copy)', $result['brand']->name);
    }

    public function testDuplicateBrandWithoutLogo(): void
    {
        $originalBrand = Mockery::mock(Brand::class)->makePartial();
        $originalBrand->id = 1;
        $originalBrand->name = 'Nike';

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->brandRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalBrand);

        $this->brandRepository
            ->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        $this->imageUploadService
            ->shouldNotReceive('duplicate');

        $newBrand = Mockery::mock(Brand::class)->makePartial();
        $newBrand->id = 2;

        $this->setCloneHistoryExpectations($originalBrand, $newBrand, 1, 2);

        $this->brandRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newBrand);

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Brand::class, $result['brand']);
    }

    public function testCloneBrandReturnsDetailedResults()
    {
        $originalBrand = Mockery::mock(Brand::class)->makePartial();
        $originalBrand->id = 1;
        $originalBrand->name = 'Nike';
        $originalBrand->site_id = 1;
        $originalBrand->logo = 'logos/nike.png';


        $newBrand = Mockery::mock(Brand::class)->makePartial();
        $newBrand->id = 2;
        $newBrand->site_id = 1;

        $this->setCloneHistoryExpectations($originalBrand, $newBrand, 1, 2);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->brandRepository->shouldReceive('find')->with(1)->andReturn($originalBrand);
        $this->brandRepository->shouldReceive('findBySlug')->andReturn(null);
        $this->brandRepository->shouldReceive('create')->andReturn($newBrand);

        $this->imageUploadService->shouldReceive('duplicate')->andReturn('logos/nike-copy.png');

        $result = $this->service->handle(1, null, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('brand', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('original_brand_id', $result);
        $this->assertArrayHasKey('cross_site', $result);
        $this->assertFalse($result['cross_site']);
        $this->assertContains('logo', $result['results']['success']);
        $this->assertContains('brand_created', $result['results']['success']);
    }

    public function testCloneBrandCrossSiteTracking()
    {
        $originalBrand = Mockery::mock(Brand::class)->makePartial();
        $originalBrand->id = 1;
        $originalBrand->name = 'Nike';
        $originalBrand->site_id = 1;

        $newBrand = Mockery::mock(Brand::class)->makePartial();
        $newBrand->id = 2;
        $newBrand->site_id = 2;

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->brandRepository->shouldReceive('find')->with(1)->andReturn($originalBrand);
        $this->brandRepository->shouldReceive('findBySlug')->andReturn(null);
        $this->brandRepository->shouldReceive('create')->andReturn($newBrand);
        $this->setCloneHistoryExpectations($originalBrand, $newBrand, 1, 2, 'cloned', 1, 2);

        $result = $this->service->handle(1, null, 2);

        $this->assertTrue($result['cross_site']);
        $this->assertContains('cross_site_clone_history', $result['results']['success']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}