<?php

namespace App\Tests\Unit\Actions;

use App\Actions\CloneBrand;
use App\Framework\Database\Database;
use App\Models\Brand;
use App\Repositories\BrandRepository;
use App\Services\BrandService;
use App\Services\ImageUploadService;
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

        $this->assertEquals('Nike (Copy)', $result->name);
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

        $this->assertInstanceOf(Brand::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}