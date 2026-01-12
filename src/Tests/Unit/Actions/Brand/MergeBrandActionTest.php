<?php

namespace App\Tests\Unit\Actions\Brand;

use App\Actions\Brand\MergeBrand;
use App\Framework\Database\Database;
use App\Models\Brand;
use App\Models\Product;
use App\Repositories\Cms\BrandRepository;
use App\Services\Cms\ImageUploadService;
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
        $product->id = 10;
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

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['source_brand_id']);
        $this->assertEquals(2, $result['target_brand_id']);
    }

    public function testMergeBrandsReturnsDetailedResults()
    {
        $sourceBrand = Mockery::mock(Brand::class)->makePartial();
        $sourceBrand->logo = '/uploads/source.png';
        $sourceBrand->id = 1;

        $targetBrand = Mockery::mock(Brand::class)->makePartial();
        $targetBrand->id = 2;

        $product1 = Mockery::mock(Product::class)->makePartial();
        $product1->id = 10;
        $product1->shouldReceive('save')->once();

        $product2 = Mockery::mock(Product::class)->makePartial();
        $product2->id = 11;
        $product2->shouldReceive('save')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')->with(1)->andReturn($sourceBrand);
        $this->brandRepository->shouldReceive('find')->with(2)->andReturn($targetBrand);
        $this->brandRepository->shouldReceive('getProductsByBrandId')->with(1)
            ->andReturn(collect([$product1, $product2]));

        $this->setCloneHistoryExpectations($sourceBrand, $targetBrand, 1, 2, 'merged');
        $this->imageUploadService->shouldReceive('delete')->once();
        $this->brandRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertArrayHasKey('results', $result);
        $this->assertContains('products_reassigned', $result['results']['success']);
        $this->assertContains('merge_history', $result['results']['success']);
        $this->assertContains('logo_deleted', $result['results']['success']);
        $this->assertContains('brand_deleted', $result['results']['success']);
        $this->assertEquals(2, $result['results']['products_reassigned']);
    }

    public function testMergeBrandsWithoutLogo()
    {
        $sourceBrand = Mockery::mock(Brand::class)->makePartial();
        $sourceBrand->logo = null;
        $sourceBrand->id = 1;

        $targetBrand = Mockery::mock(Brand::class)->makePartial();
        $targetBrand->id = 2;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')->with(1)->andReturn($sourceBrand);
        $this->brandRepository->shouldReceive('find')->with(2)->andReturn($targetBrand);
        $this->brandRepository->shouldReceive('getProductsByBrandId')->with(1)
            ->andReturn(collect([]));

        $this->setCloneHistoryExpectations($sourceBrand, $targetBrand, 1, 2, 'merged');
        $this->imageUploadService->shouldReceive('delete')->never();
        $this->brandRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertTrue($result['success']);
        $this->assertNotContains('logo_deleted', $result['results']['success']);
    }

    public function testMergeBrandsThrowsExceptionForSameBrand()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot merge a brand with itself');

        $this->service->handle(1, 1);
    }

    public function testMergeBrandsThrowsExceptionWhenSourceNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')->with(999)->andReturn(null);
        $this->brandRepository->shouldReceive('find')->with(2)->andReturn(new Brand());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('One or both brands not found');

        $this->service->handle(999, 2);
    }

    public function testMergeBrandsThrowsExceptionWhenTargetNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')->with(1)->andReturn(new Brand());
        $this->brandRepository->shouldReceive('find')->with(999)->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('One or both brands not found');

        $this->service->handle(1, 999);
    }

    public function testMergeBrandsHandlesProductReassignmentFailure()
    {
        $sourceBrand = Mockery::mock(Brand::class)->makePartial();
        $sourceBrand->logo = null;
        $sourceBrand->id = 1;

        $targetBrand = Mockery::mock(Brand::class)->makePartial();
        $targetBrand->id = 2;

        $product1 = Mockery::mock(Product::class)->makePartial();
        $product1->id = 10;
        $product1->shouldReceive('save')->once();

        $product2 = Mockery::mock(Product::class)->makePartial();
        $product2->id = 11;
        $product2->shouldReceive('save')->once()->andThrow(new \Exception('Save failed'));

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')->with(1)->andReturn($sourceBrand);
        $this->brandRepository->shouldReceive('find')->with(2)->andReturn($targetBrand);
        $this->brandRepository->shouldReceive('getProductsByBrandId')->with(1)
            ->andReturn(collect([$product1, $product2]));

        $this->setCloneHistoryExpectations($sourceBrand, $targetBrand, 1, 2, 'merged');
        $this->brandRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['results']['products_reassigned']);
        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('reassign_product', $result['results']['failed'][0]['operation']);
        $this->assertEquals(11, $result['results']['failed'][0]['product_id']);
    }

    public function testMergeBrandsHandlesLogoDeletionFailure()
    {
        $sourceBrand = Mockery::mock(Brand::class)->makePartial();
        $sourceBrand->logo = '/uploads/source.png';
        $sourceBrand->id = 1;

        $targetBrand = Mockery::mock(Brand::class)->makePartial();
        $targetBrand->id = 2;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')->with(1)->andReturn($sourceBrand);
        $this->brandRepository->shouldReceive('find')->with(2)->andReturn($targetBrand);
        $this->brandRepository->shouldReceive('getProductsByBrandId')->with(1)
            ->andReturn(collect([]));

        $this->setCloneHistoryExpectations($sourceBrand, $targetBrand, 1, 2, 'merged');

        $this->imageUploadService->shouldReceive('delete')
            ->with('/uploads/source.png')
            ->once()
            ->andThrow(new \Exception('File not found'));

        $this->brandRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('delete_logo', $result['results']['failed'][0]['operation']);
    }

    public function testMergeBrandsRollsBackOnDeleteFailure()
    {
        $sourceBrand = Mockery::mock(Brand::class)->makePartial();
        $sourceBrand->id = 1;

        $targetBrand = Mockery::mock(Brand::class)->makePartial();
        $targetBrand->id = 2;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                try {
                    return $callback();
                } catch (\Exception $e) {
                    throw $e;
                }
            });

        $this->brandRepository->shouldReceive('find')->with(1)->andReturn($sourceBrand);
        $this->brandRepository->shouldReceive('find')->with(2)->andReturn($targetBrand);
        $this->brandRepository->shouldReceive('getProductsByBrandId')->with(1)
            ->andReturn(collect([]));

        $this->setCloneHistoryExpectations($sourceBrand, $targetBrand, 1, 2, 'merged');

        $this->brandRepository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andThrow(new \Exception('Delete failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Delete failed');

        $this->service->handle(1, 2);
    }

    public function testMergeBrandsWithNoProducts()
    {
        $sourceBrand = Mockery::mock(Brand::class)->makePartial();
        $sourceBrand->logo = null;
        $sourceBrand->id = 1;

        $targetBrand = Mockery::mock(Brand::class)->makePartial();
        $targetBrand->id = 2;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')->with(1)->andReturn($sourceBrand);
        $this->brandRepository->shouldReceive('find')->with(2)->andReturn($targetBrand);
        $this->brandRepository->shouldReceive('getProductsByBrandId')->with(1)
            ->andReturn(collect([]));

        $this->setCloneHistoryExpectations($sourceBrand, $targetBrand, 1, 2, 'merged');
        $this->brandRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['results']['products_reassigned']);
        $this->assertContains('products_reassigned', $result['results']['success']);
    }



    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}