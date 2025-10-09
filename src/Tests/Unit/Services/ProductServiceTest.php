<?php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Repositories\ProductRepositoryInterface;
use App\Repositories\ProductViewRepository;
use App\Services\ImageUploadService;
use App\Services\ProductService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProductServiceTest extends FunctionalTestCase
{
    protected $repository;
    protected $imageUploadService;
    protected ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ProductRepository::class);
        $this->imageUploadService = Mockery::mock(ImageUploadService::class);
        $productViewRepository = Mockery::mock(ProductViewRepository::class);

        $this->databaseMock = Mockery::mock(Database::class);

        $this->imageUploadService->shouldReceive('setAllowedMimeTypes')->andReturnSelf();
        $this->imageUploadService->shouldReceive('setMaxFileSize')->andReturnSelf();

        $this->service = new ProductService($this->repository, $this->imageUploadService, $productViewRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCreateProductWithImageFile()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);

        $this->imageUploadService->shouldReceive('uploadToPath')
            ->once()
            ->with($file, Mockery::type('string'))
            ->andReturn('products/2025-01/product_123.jpg');

        $product = new Product(['id' => 1, 'name' => 'Test Product', 'image' => 'products/2025-01/product_123.jpg']);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['image'] === 'products/2025-01/product_123.jpg';
            }))
            ->andReturn($product);

        $data = ['name' => 'Test Product', 'price' => 99.99];
        $result = $this->service->createProduct($data, $file);

        $this->assertEquals('Test Product', $result->name);
        $this->assertEquals('products/2025-01/product_123.jpg', $result->image);
    }

    public function testUpdateProductWithImageFile()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);

        $product = new Product(['id' => 1, 'name' => 'Old Name', 'image' => 'old-image.jpg']);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($product);

        $this->imageUploadService->shouldReceive('uploadToPath')
            ->once()
            ->with($file, Mockery::type('string'), 'old-image.jpg')
            ->andReturn('products/2025-01/new-image.jpg');

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($product);

        $data = ['name' => 'New Name'];
        $result = $this->service->updateProduct(1, $data, $file);

        $this->assertNotNull($result);
    }

    public function testDeleteProductDeletesImage()
    {
        $product = new Product(['id' => 1, 'name' => 'Test', 'image' => 'products/test.jpg']);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($product);

        $this->imageUploadService->shouldReceive('delete')
            ->once()
            ->with('products/test.jpg');

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->andReturn(true);

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testItCanGetAllProducts()
    {
        $products = collect([
            new Product(['id' => 1, 'name' => 'Product 1']),
            new Product(['id' => 2, 'name' => 'Product 2']),
        ]);

        $this->repository->shouldReceive('all')
            ->once()
            ->andReturn($products);

        $result = $this->service->getAllProducts();

        $this->assertCount(2, $result);
    }

    public function testItCanGetPaginatedProducts()
    {
        $this->repository->shouldReceive('paginate')
            ->with(15)
            ->once()
            ->andReturn([]);

        $result = $this->service->getPaginatedProducts();
        $this->assertIsArray( $result );
    }

    public function testItCanGetSingleProduct()
    {
        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $result = $this->service->getProduct(1);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testItCanCreateProduct()
    {
        $data = [
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 99.99,
            'sale_price' => 79.99,
            'category' => 'Electronics',
            'brand' => 'TestBrand',
        ];

        $product = new Product($data);

        $this->repository->shouldReceive('create')
            ->with($data)
            ->once()
            ->andReturn($product);

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testItCanUpdateProduct()
    {
        $product = new Product(['id' => 1, 'name' => 'Old Name']);
        $data = ['name' => 'New Name'];

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('update')
            ->with(1, $data)
            ->once()
            ->andReturn($product);

        $result = $this->service->updateProduct(1, $data);

        $this->assertInstanceOf(Product::class, $result);;
    }

    public function testItReturnsFalseWhenUpdatingNonExistentProduct()
    {
        $this->repository->shouldReceive('find')
            ->with(9999)
            ->once()
            ->andReturn(null);

        $result = $this->service->updateProduct(9999, ['name' => 'Test']);

        $this->assertNull($result);
    }

    public function testItCanDeleteProduct()
    {
        //Storage::fake('local');

        $product = new Product(['id' => 1, 'name' => 'Test', 'image' => null]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testItCanGetProductsByCategory()
    {
        $products = collect([new Product(['category' => 'Electronics'])]);

        $this->repository->shouldReceive('findByCategory')
            ->with('Electronics')
            ->once()
            ->andReturn($products);

        $result = $this->service->getProductsByCategory('Electronics');

        $this->assertCount(1, $result);
    }

    public function testItCanGetProductsByBrand()
    {
        $products = collect([new Product(['brand' => 'TestBrand'])]);

        $this->repository->shouldReceive('findByBrand')
            ->with('TestBrand')
            ->once()
            ->andReturn($products);

        $result = $this->service->getProductsByBrand('TestBrand');

        $this->assertCount(1, $result);
    }

    /** @test */
    public function testItCanGetProductsOnSale()
    {
        $products = collect([
            new Product(['price' => 100, 'sale_price' => 80])
        ]);

        $this->repository->shouldReceive('getOnSale')
            ->once()
            ->andReturn($products);

        $result = $this->service->getOnSaleProducts();

        $this->assertCount(1, $result);
    }

    public function testDuplicateProductSuccessfully(): void
    {
        $originalProduct = new Product([
            'id' => 1,
            'name' => 'iPhone 15',
            'description' => 'Latest iPhone',
            'image' => 'products/iphone15.jpg',
            'price' => 999.99,
            'sale_price' => 899.99,
            'brand_id' => 5,
            'category_id' => 10,
            'slug' => 'iphone-15'
        ]);

        $this->repository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalProduct);

        $this->repository
            ->shouldReceive('findBySlug')
            ->with('iphone-15-copy')
            ->once()
            ->andReturn(null);

        $this->imageUploadService
            ->shouldReceive('duplicate')
            ->with('products/iphone15.jpg')
            ->once()
            ->andReturn('products/iphone15-copy.jpg');

        $newProduct = new Product([
            'id' => 2,
            'name' => 'iPhone 15 (Copy)',
            'slug' => 'iphone-15-copy',
        ]);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['name'] === 'iPhone 15 (Copy)'
                    && $data['price'] === 999.99;
            }))
            ->andReturn($newProduct);

        $result = $this->service->duplicateProduct(1);

        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals('iPhone 15 (Copy)', $result->name);
    }

    public function testDuplicateProductWithoutImage(): void
    {
        $originalProduct = new Product([
            'id' => 1,
            'name' => 'Product',
            'image' => null,
            'slug' => 'product'
        ]);

        $this->repository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalProduct);

        $this->repository
            ->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        $this->imageUploadService
            ->shouldNotReceive('duplicate');

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn(new Product(['id' => 2]));

        $result = $this->service->duplicateProduct(1);

        $this->assertInstanceOf(Product::class, $result);
    }

    public function testDuplicateProductHandlesImageDuplicationFailure(): void
    {
        $originalProduct = new Product([
            'id' => 1,
            'name' => 'Product',
            'image' => 'products/test.jpg',
            'slug' => 'product'
        ]);

        $this->repository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalProduct);

        $this->imageUploadService
            ->shouldReceive('duplicate')
            ->once()
            ->andThrow(new \Exception('File error'));

        $this->repository
            ->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['image'] === null;
            }))
            ->andReturn(new Product(['id' => 2]));

        $result = $this->service->duplicateProduct(1);

        $this->assertInstanceOf(Product::class, $result);
    }
}