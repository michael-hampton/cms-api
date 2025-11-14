<?php

namespace App\Tests\Unit\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Models\Brand;
use App\Models\Product;
use App\Repositories\BrandRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use App\Services\BrandService;
use App\Services\ImageUploadService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BrandServiceTest extends FunctionalTestCase
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

        $this->service = new BrandService(
            $this->brandRepository,
            $this->imageUploadService,
            $this->databaseMock
        );
    }

    public function testCreateBrandGeneratesSlugWhenNotProvided()
    {
        $data = ['name' => 'Apple Inc', 'description' => 'Tech company'];

        $mockedBrand = Mockery::mock(Brand::class);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('findBySlug')
            ->with('apple-inc')
            ->once()
            ->andReturn(null);

        $this->brandRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($arg) {
                return $arg['name'] === 'Apple Inc' && $arg['slug'] === 'apple-inc';
            }))
            ->andReturn($mockedBrand);

        $result = $this->service->createBrand($data, $this->siteId);

        $this->assertInstanceOf(Brand::class, $result);
        $this->assertSame($mockedBrand, $result);
    }

    public function testCreateBrandUploadsLogoWhenProvided()
    {
        $data = ['name' => 'Nike'];
        $logoFile = Mockery::mock(UploadedFile::class);
        $logoFile->shouldReceive('isValid')->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->imageUploadService->shouldReceive('upload')
            ->with($logoFile)
            ->once()
            ->andReturn('/uploads/logo.png');

        $this->brandRepository->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        $mockedBrand = Mockery::mock(Brand::class);
        $this->brandRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($arg) =>
                isset($arg['logo']) && $arg['logo'] === '/uploads/logo.png'
            ))
            ->andReturn($mockedBrand);

        $result = $this->service->createBrand($data, $this->siteId, $logoFile);

        $this->assertInstanceOf(Brand::class, $result);
    }

    public function testUpdateBrandRegeneratesSlugWhenNameChanged()
    {
        $brand = Mockery::mock(Brand::class)->makePartial();
        $brand->name = 'Old Name';
        $brand->slug = 'old-name';
        $brand->logo = null;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->brandRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($brand);

        $this->brandRepository->shouldReceive('findBySlug')
            ->with('new-name')
            ->once()
            ->andReturn(null);

        $this->brandRepository->shouldReceive('update')
            ->with(1, Mockery::on(fn($data) => $data['slug'] === 'new-name'))
            ->once()
            ->andReturn($brand);

        $result = $this->service->updateBrand(1, ['name' => 'New Name']);

        $this->assertInstanceOf(Brand::class, $result);
    }

    public function testDeleteBrandThrowsExceptionWhenProductsExist()
    {
        $this->expectException(CannotDeleteException::class);

        $brand = Mockery::mock(Brand::class)->makePartial();

        $this->brandRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($brand);

        $this->brandRepository->shouldReceive('getProductsByBrandId')
            ->with(1)
            ->once()
            ->andReturn(collect([Mockery::mock(Product::class)]));

        $this->service->delete(1);
    }

    public function testDeleteBrandWithoutProducts()
    {
        $brand = Mockery::mock(Brand::class)->makePartial();
        $brand->logo = null;

        $this->brandRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($brand);

        $this->brandRepository->shouldReceive('getProductsByBrandId')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $brand->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $result = $this->service->delete(1);

        $this->assertTrue($result);
    }

    public function testDeleteBrandReassignsProducts()
    {
        $brand = Mockery::mock(Brand::class)->makePartial();
        $brand->logo = '/uploads/logo.png';
        $reassignBrand = Mockery::mock(Brand::class);
        $products = Mockery::mock();

        $this->brandRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($brand);

        $this->brandRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($reassignBrand);

        $this->brandRepository->shouldReceive('getProductsByBrandId')
            ->with(1)
            ->once()
            ->andReturn(collect([Mockery::mock(Product::class)]));

        $brand->shouldReceive('products')
            ->once()
            ->andReturn($products);

        $products->shouldReceive('update')
            ->with(['brand_id' => 2])
            ->once();

        $this->imageUploadService->shouldReceive('delete')
            ->with('/uploads/logo.png')
            ->once();

        $brand->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $result = $this->service->delete(1, 2);

        $this->assertTrue($result);
    }

    public function testCheckDeletable()
    {
        $brand = Mockery::mock(Brand::class);
        $products = Mockery::mock();

        $this->brandRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($brand);

        $brand->shouldReceive('products')
            ->once()
            ->andReturn($products);

        $products->shouldReceive('count')
            ->once()
            ->andReturn(5);

        $result = $this->service->checkDeletable(1);

        $this->assertFalse($result['can_delete']);
        $this->assertEquals(5, $result['products_count']);
        $this->assertTrue($result['requires_reassignment']);
    }

    public function testGetAlternativeBrands()
    {
        $alternatives = new Collection([
            Mockery::mock(Brand::class),
            Mockery::mock(Brand::class)
        ]);

        $this->brandRepository->shouldReceive('getAlternatives')
            ->with(1)
            ->once()
            ->andReturn($alternatives);

        $result = $this->service->getAlternativeBrands(1);

        $this->assertCount(2, $result);
    }

    public function testSearch()
    {
        $criteria = new SearchCriteria(
            searchQuery: 'Nike',
            page: 1,
            perPage: 20
        );

        $result = new PaginatedResult([], 0, 1, 20);

        $this->brandRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::type(SearchCriteria::class))
            ->andReturn($result);

        $response = $this->service->search($criteria);

        $this->assertInstanceOf(PaginatedResult::class, $response);
    }

    public function testGetAllBrands()
    {
        $result = $this->service->getAllBrands();
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
    }

    public function testGetActiveBrands()
    {
        $brands = collect([Mockery::mock(Brand::class)]);

        $this->brandRepository->shouldReceive('getActiveBrands')
            ->once()
            ->andReturn($brands);

        $result = $this->service->getActiveBrands();

        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
        $this->assertCount(1, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}