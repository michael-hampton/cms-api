<?php

namespace App\Tests\Unit\Actions\Brand;

use App\Actions\Brand\BulkDeleteBrand;
use App\Framework\Database\Database;
use App\Models\Brand;
use App\Models\Product;
use App\Repositories\Cms\BrandRepository;
use App\Services\Cms\ImageUploadService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkDeleteBrandActionTest extends FunctionalTestCase
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

        $this->service = new BulkDeleteBrand(
            $this->brandRepository,
            $this->imageUploadService,
            $this->databaseMock
        );
    }

    public function testBulkDeleteSuccessfully(): void
    {
        $brand1 = Mockery::Mock(Brand::class)->makePartial();
        $brand1->logo = null;

        $brand2 = Mockery::mock(Brand::class)->makePartial();
        $brand2->logo = null;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($brand1);

        $this->brandRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($brand2);

        $this->brandRepository->shouldReceive('getProductsByBrandId')
            ->twice()
            ->andReturn(collect([]));

        $brand1->shouldReceive('delete')->once()->andReturn(true);
        $brand2->shouldReceive('delete')->once()->andReturn(true);

        $result = $this->service->handle([1, 2]);

        $this->assertCount(2, $result['deleted']);
        $this->assertCount(0, $result['failed']);
    }

    public function testBulkDeleteFailsWhenProductsExist(): void
    {
        $brand1 = Mockery::mock(Brand::class)->makePartial();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($brand1);

        $this->brandRepository->shouldReceive('getProductsByBrandId')
            ->with(1)
            ->once()
            ->andReturn(collect([Mockery::mock(Product::class)]));

        $result = $this->service->handle([1]);

        $this->assertCount(0, $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('associated products', $result['failed'][0]['reason']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}