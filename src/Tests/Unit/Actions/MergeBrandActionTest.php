<?php

namespace App\Tests\Unit\Actions;

use App\Actions\MergeBrand;
use App\Framework\Database\Database;
use App\Models\Brand;
use App\Models\Product;
use App\Repositories\BrandRepository;
use App\Services\BrandService;
use App\Services\ImageUploadService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class MergeBrandActionTest extends FunctionalTestCase
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

        $this->service = new MergeBrand(
            $this->brandRepository,
            $this->imageUploadService,
            $this->databaseMock
        );
    }

    public function testMergeBrandsReassignsProductsAndDeletesSource()
    {
        $sourceBrand = Mockery::mock(Brand::class)->makePartial();
        $sourceBrand->logo = '/uploads/source.png';
        $sourceBrand->id = 1;

        $targetBrand = Mockery::mock(Brand::class)->makePartial();
        $targetBrand->id = 2;

        $this->setCloneHistoryExpectations($sourceBrand, $targetBrand, 1, 2, 'merged');

        $product = Mockery::mock(Product::class)->makePartial();
        $product->shouldReceive('save')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($sourceBrand);

        $this->brandRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($targetBrand);

        $this->brandRepository->shouldReceive('getProductsByBrandId')
            ->with(1)
            ->once()
            ->andReturn(collect([$product]));

        $this->imageUploadService->shouldReceive('delete')
            ->with('/uploads/source.png')
            ->once();

        $this->brandRepository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertTrue($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}