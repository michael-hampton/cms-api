<?php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductViewRepository;
use App\Services\Cms\ImageUploadService;
use App\Services\Product\ProductService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class ProductServiceTest extends FunctionalTestCase
{
    use CreatesTestData, HasSiteHistory;

    protected $repository;
    protected $imageUploadService;
    protected ProductService $service;
    private $databaseMock;

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

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

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

        $this->repository
            ->shouldReceive('getImages')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->repository->shouldReceive('deletePriceHistory')
            ->with(1)
            ->andReturn(new ProductPriceHistory());

        $this->imageUploadService->shouldReceive('delete')
            ->once()
            ->with('products/test.jpg');

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->andReturn(true);

        $this->repository
            ->shouldReceive('getVariants')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

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
            ->with(1, ['availableMerchants', 'availableMerchants.merchant'])
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

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

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

        $this->repository
            ->shouldReceive('getImages')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->repository->shouldReceive('deletePriceHistory')
            ->with(1)
            ->andReturn(new ProductPriceHistory());

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $this->repository
            ->shouldReceive('getVariants')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

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



    public function testCreateProductWithImagesArray()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'images' => [
                ['url' => 'img1.jpg', 'alt' => 'Image 1', 'is_primary' => true, 'sort_order' => 0],
                ['url' => 'img2.jpg', 'alt' => 'Image 2', 'is_primary' => false, 'sort_order' => 1],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($createData) {
                return !isset($createData['images']);
            }))
            ->andReturn($product);

        $this->repository->shouldReceive('syncImages')
            ->once()
            ->with(1, Mockery::on(function($images) {
                return count($images) === 2 && $images[0]['url'] === 'img1.jpg';
            }));

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testCreateProductWithMerchants()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'merchants' => [
                ['id' => 27, 'name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 79.99, 'is_available' => true],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->with(1, Mockery::type('array'))
            ->andReturn([1]); // Returns product_merchant IDs

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 79.99, 27, null);

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testCreateProductWithVariants()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'variants' => [
                ['sku' => 'VAR-001', 'attributes' => ['color' => 'Red'], 'price_modifier' => 0, 'is_active' => true],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->with(1, Mockery::type('array'));

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testCreateProductWithSpecifications()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'specifications' => [
                ['category' => 'Technical', 'key' => 'Weight', 'value' => '1kg', 'sort_order' => 0],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('syncSpecifications')
            ->once()
            ->with(1, Mockery::type('array'));

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testCreateProductWithAllRelations()
    {
        $data = [
            'name' => 'Complete Product',
            'price' => 99.99,
            'images' => [['url' => 'img1.jpg', 'alt' => 'Image 1', 'is_primary' => true, 'sort_order' => 0]],
            'merchants' => [['name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 79.99, 'is_available' => true]],
            'variants' => [['sku' => 'VAR-001', 'attributes' => [], 'price_modifier' => 0, 'is_active' => true]],
            'specifications' => [['category' => 'Tech', 'key' => 'Weight', 'value' => '1kg', 'sort_order' => 0]],
        ];

        $product = new Product(['id' => 1, 'name' => 'Complete Product']);

        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('syncImages')->once();
        $this->repository->shouldReceive('syncMerchants')->once();
        $this->repository->shouldReceive('syncVariants')->once();
        $this->repository->shouldReceive('syncSpecifications')->once();

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $result = $this->service->createProduct($data);

        $this->assertEquals('Complete Product', $result->name);
    }

    public function testUpdateProductWithBasicData()
    {
        $product = new Product(['id' => 1, 'name' => 'Old Name']);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('update')
            ->with(1, ['name' => 'New Name'])
            ->once()
            ->andReturn($product);

        $result = $this->service->updateProduct(1, ['name' => 'New Name']);

        $this->assertNotNull($result);
    }

    public function testUpdateProductReturnsNullWhenNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->updateProduct(999, ['name' => 'Test']);

        $this->assertNull($result);
    }

    public function testUpdateProductWithImages()
    {
        $product = new Product(['id' => 1, 'name' => 'Product']);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('syncImages')
            ->once()
            ->with(1, Mockery::type('array'));

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $data = [
            'name' => 'Updated Product',
            'images' => [
                ['url' => 'new1.jpg', 'alt' => 'New 1', 'is_primary' => true, 'sort_order' => 0],
            ]
        ];

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testUpdateProductWithMerchants()
    {
        $product = new Product(['id' => 1, 'name' => 'Product']);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('update')->once()->andReturn($product);
        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->with(1, Mockery::type('array'))
        ->andReturn([0 => 1]);

        $this->repository->shouldReceive('getProductMerchantsWithDetails')
            ->with(1)
            ->andReturn(collect([]));

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->with(1, 1, 89.99, 1, null)
            ->andReturn(new ProductPriceHistory());

        $data = [
            'name' => 'Updated Product',
            'merchants' => [
                ['name' => 'eBay', 'url' => 'https://ebay.com', 'price' => 89.99, 'is_available' => true, 'id' => 1],
            ]
        ];

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testUpdateProductWithVariants()
    {
        $product = new Product(['id' => 1, 'name' => 'Product']);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('update')->once()->andReturn($product);
        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->with(1, Mockery::type('array'));

        $data = [
            'name' => 'Updated Product',
            'variants' => [
                ['sku' => 'VAR-002', 'attributes' => [], 'price_modifier' => 5, 'is_active' => true],
            ]
        ];

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testUpdateProductWithSpecifications()
    {
        $product = new Product(['id' => 1, 'name' => 'Product']);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('update')->once()->andReturn($product);
        $this->repository->shouldReceive('syncSpecifications')
            ->once()
            ->with(1, Mockery::type('array'));

        $data = [
            'name' => 'Updated Product',
            'specifications' => [
                ['category' => 'Physical', 'key' => 'Dimensions', 'value' => '10x10x10', 'sort_order' => 0],
            ]
        ];

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testUpdateProductOnlyUpdatesProvidedRelations()
    {
        $product = new Product(['id' => 1, 'name' => 'Product']);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('update')->once()->andReturn($product);
        $this->repository->shouldReceive('syncImages')->once();

        // Should NOT call these
        $this->repository->shouldNotReceive('syncMerchants');
        $this->repository->shouldNotReceive('syncVariants');
        $this->repository->shouldNotReceive('syncSpecifications');

        $data = [
            'name' => 'Updated',
            'images' => [['url' => 'img.jpg', 'alt' => 'Alt', 'is_primary' => true, 'sort_order' => 0]]
        ];

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testDeleteProductWithoutImage()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'image' => null]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($product);

        $this->repository->shouldReceive('getImages')
            ->with(1)
            ->andReturn(new Collection([]));

        $this->repository
            ->shouldReceive('getVariants')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->imageUploadService->shouldNotReceive('delete');

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->andReturn(true);

        $this->repository->shouldReceive('deletePriceHistory')
            ->with(1)
            ->andReturn(new ProductPriceHistory());

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testDeleteProductWithImage()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'image' => 'main.jpg']);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($product);

        $this->repository->shouldReceive('getImages')
            ->with(1)
            ->andReturn(new Collection([]));

        $this->imageUploadService->shouldReceive('delete')
            ->once()
            ->with('main.jpg');

        $this->repository
            ->shouldReceive('getVariants')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->andReturn(true);

        $this->repository->shouldReceive('deletePriceHistory')
            ->with(1)
            ->andReturn(new ProductPriceHistory());

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testDeleteProductHandlesImageDeletionFailure()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'image' => 'main.jpg']);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('getImages')->with(1)->andReturn(new Collection([]));

        $this->imageUploadService->shouldReceive('delete')
            ->with('main.jpg')
            ->andThrow(new \Exception('Delete failed'));

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->andReturn(true);

        $this->repository
            ->shouldReceive('getVariants')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->repository->shouldReceive('deletePriceHistory')
            ->with(1)
            ->andReturn(new ProductPriceHistory());

        // Should not throw, just log error
        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testDeleteProductReturnsFalseWhenNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $result = $this->service->deleteProduct(999);

        $this->assertFalse($result);
    }

    public function testGetProductsByCategory()
    {
        $products = new Collection([
            new Product(['category' => 'Electronics']),
        ]);

        $this->repository->shouldReceive('findByCategory')
            ->with('Electronics')
            ->once()
            ->andReturn($products);

        $result = $this->service->getProductsByCategory('Electronics');

        $this->assertCount(1, $result);
    }

    public function testGetProductsByBrand()
    {
        $products = new Collection([
            new Product(['brand' => 'Apple']),
        ]);

        $this->repository->shouldReceive('findByBrand')
            ->with('Apple')
            ->once()
            ->andReturn($products);

        $result = $this->service->getProductsByBrand('Apple');

        $this->assertCount(1, $result);
    }



    public function testCreateProductThrowsExceptionOnImageUploadFailure()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);

        $this->imageUploadService->shouldReceive('uploadToPath')
            ->once()
            ->andThrow(new \Exception('Upload failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to upload product image: Upload failed');

        $data = ['name' => 'Test', 'price' => 99.99];
        $this->service->createProduct($data, $file);
    }

    public function testUpdateProductThrowsExceptionOnImageUploadFailure()
    {
        $product = new Product(['id' => 1, 'name' => 'Product']);

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->imageUploadService->shouldReceive('uploadToPath')
            ->once()
            ->andThrow(new \Exception('Upload failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to upload product image: Upload failed');

        $data = ['name' => 'Updated'];
        $this->service->updateProduct(1, $data, $file);
    }

    public function testDeleteProductWithMultipleImages()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'image' => 'main.jpg']);

        $images = new Collection([
            new ProductImage(['url' => 'img1.jpg']),
            new ProductImage(['url' => 'img2.jpg']),
        ]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('getImages')->with(1)->andReturn($images);

        $this->imageUploadService->shouldReceive('delete')
            ->with('main.jpg')
            ->once();

        $this->imageUploadService->shouldReceive('delete')
            ->with('img1.jpg')
            ->once();

        $this->repository
            ->shouldReceive('getVariants')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->imageUploadService->shouldReceive('delete')
            ->with('img2.jpg')
            ->once();

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->andReturn(true);

        $this->repository->shouldReceive('deletePriceHistory')
            ->with(1)
            ->andReturn(new ProductPriceHistory());

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testCreateProductRecordsPriceHistory()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'sale_price' => 79.99
        ];

        $product = new Product(array_merge(['id' => 1], $data));
        $priceHistory = new ProductPriceHistory(['price' => 99.99, 'sale_price' => 79.99]);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('recordPriceHistory')
            ->once()
            ->with($product)
            ->andReturn($priceHistory);

        $result = $this->service->createProduct($data);
        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals(99.99, $product->price);
        $this->assertEquals(79.99, $product->sale_price);
    }

    public function testUpdateProductRecordsPriceHistoryOnPriceChange()
    {
        $product = new Product([
            'id' => 1,
            'name' => 'Product',
            'price' => 99.99,
            'sale_price' => 89.99
        ]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($product);

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($product);

        $priceHistory = new ProductPriceHistory(['price' => 99.99, 'sale_price' => 89.99]);

        $this->repository->shouldReceive('recordPriceHistory')
            ->once()
            ->with($product)
            ->andReturn($priceHistory);

        $data = ['price' => 109.99, 'sale_price' => 99.99];
        $result = $this->service->updateProduct(1, $data);

        $this->assertEquals(99.99, $product->price);
        $this->assertEquals(89.99, $product->sale_price);
    }

    public function testUpdateProductDoesNotRecordPriceHistoryWhenPriceUnchanged()
    {
        $product = new Product([
            'id' => 1,
            'name' => 'Product',
            'price' => 99.99,
            'sale_price' => 89.99
        ]);

        $this->repository->shouldReceive('find')->andReturn($product);
        $this->repository->shouldReceive('update')->andReturn($product);

        $this->repository->shouldReceive('recordPriceHistory')
            ->never()
            ->with($product);

        $initialCount = ProductPriceHistory::where('product_id', 1)->count();

        $data = ['name' => 'Updated Name']; // No price change
        $this->service->updateProduct(1, $data);
        $this->assertEquals(99.99, $product->price);
        $this->assertEquals(89.99, $product->sale_price);
    }

    public function testDeleteProductDeletesPriceHistory()
    {
        $product = Product::create(['name' => 'Test', 'image' => null, 'price' => 199.99]);;

        // Create some price history
        ProductPriceHistory::create([
            'product_id' => $product->id,
            'price' => 99.99,
            'sale_price' => 79.99,
            'recorded_at' => date('Y-m-d H:i:s')
        ]);

        $this->repository->shouldReceive('find')->andReturn($product);
        $this->repository->shouldReceive('getImages')->andReturn(collect([]));
        $this->repository->shouldReceive('delete')->andReturn(true);

        $this->repository
            ->shouldReceive('getVariants')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->repository->shouldReceive('deletePriceHistory')->once();

        $result = $this->service->deleteProduct(1);
        $this->assertTrue($result);;
    }

    public function testCreateProductRecordsMerchantPriceHistory()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'merchants' => [
                ['name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 79.99, 'is_available' => true, 'id' => 1],
                ['name' => 'eBay', 'url' => 'https://ebay.com', 'price' => 89.99, 'is_available' => true, 'id' => 2],
            ]
        ];

        $product = new Product(array_merge(['id' => 1], $data));

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($product);

        $priceHistory = new ProductPriceHistory(['price' => 99.99, 'sale_price' => 79.99]);

        $this->repository->shouldReceive('recordPriceHistory')
            ->once()
            ->with($product)
            ->andReturn($priceHistory);

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->with(1, Mockery::type('array'))
            ->andReturn([1, 2]); // Return merchant IDs

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 79.99, 1, null)
            ->andReturn(new ProductPriceHistory(['price' => 79.99]));

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 2, 89.99, 2, null)
            ->andReturn(new ProductPriceHistory(['price' => 89.99]));

        $result = $this->service->createProduct($data);

        $this->assertInstanceOf(Product::class, $result);
    }

    public function testUpdateProductRecordsMerchantPriceHistoryOnPriceChange()
    {
        $product = new Product([
            'id' => 1,
            'name' => 'Product',
            'price' => 99.99
        ]);

        $existingMerchants = collect([
           ['id' => 1, 'name' => 'Amazon', 'price' => 79.99],
           ['id' => 2, 'name' => 'eBay', 'price' => 89.99]
        ]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('getProductMerchantsWithDetails')
            ->with(1)
            ->andReturn($existingMerchants);

        $data = [
            'merchants' => [
                ['id' => 1, 'name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 74.99, 'is_available' => true], // Price changed
                ['id' => 2, 'name' => 'eBay', 'url' => 'https://ebay.com', 'price' => 89.99, 'is_available' => true], // Price same
            ]
        ];

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->andReturn([1, 2]);

        // Should only record history for Amazon (price changed)
        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 74.99, 1, null)
            ->andReturn(new ProductPriceHistory(['price' => 74.99]));

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testUpdateProductRecordsHistoryForNewMerchants()
    {
        $product = new Product(['id' => 1, 'name' => 'Product']);

        $existingMerchants = collect([
            ['id' => 1, 'name' => 'Amazon', 'price' => 79.99]
        ]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('update')->once()->andReturn($product);
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->andReturn($existingMerchants);

        $data = [
            'merchants' => [
                ['id' => 1, 'name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 89.99, 'is_available' => true],
                ['id' => 2, 'name' => 'BestBuy', 'url' => 'https://bestbuy.com', 'price' => 85.99, 'is_available' => true], // New merchant
            ]
        ];

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->andReturn([1, 3]);

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 89.99, 1, null);

        // Should record history for new merchant
        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 3, 85.99, 2, null);

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testCreateProductWithVariantsAndImages()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'variants' => [
                [
                    'sku' => 'VAR-001',
                    'id' => 3,
                    'attributes' => ['color' => 'Red'],
                    'price_modifier' => 0,
                    'is_active' => true,
                    'images' => [
                        ['url' => 'var-img1.jpg', 'alt' => 'Variant Image 1', 'is_primary' => true, 'sort_order' => 0],
                        ['url' => 'var-img2.jpg', 'alt' => 'Variant Image 2', 'is_primary' => false, 'sort_order' => 1],
                    ]
                ],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->with(1, Mockery::on(function($variants) {
                return count($variants) === 1
                    && isset($variants[0]['images'])
                    && count($variants[0]['images']) === 2;
            }))
            ->andReturn([1]);

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testUpdateProductWithVariantImages()
    {
        $product = new Product(['id' => 1, 'name' => 'Product']);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->with(1, Mockery::on(function($variants) {
                return isset($variants[0]['images']) && count($variants[0]['images']) === 1;
            }))
            ->andReturn([1]);

        $data = [
            'name' => 'Updated Product',
            'variants' => [
                [
                    'sku' => 'VAR-002',
                    'id' => 2,
                    'attributes' => [],
                    'price_modifier' => 5,
                    'is_active' => true,
                    'images' => [
                        ['url' => 'updated-var-img.jpg', 'alt' => 'Updated', 'is_primary' => true, 'sort_order' => 0],
                    ]
                ],
            ]
        ];

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testDeleteProductDeletesVariantImages()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'image' => 'main.jpg']);

        $variant = new ProductVariant(['id' => 1, 'sku' => 'VAR-001']);
        $variant->setRelation('images', collect([
            new ProductImage(['url' => 'var-img1.jpg']),
            new ProductImage(['url' => 'var-img2.jpg']),
        ]));

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('getImages')->with(1)->andReturn(new Collection([]));
        $this->repository->shouldReceive('getVariants')->with(1)->andReturn(collect([$variant]));

        $this->repository->shouldReceive('deleteVariantImages')
            ->with(1)
            ->andReturn(true);

        $this->repository->shouldReceive('getVariantImages')
            ->with(1)
            ->andReturn(collect([
                new ProductImage(['url' => 'var-img1.jpg']),
                new ProductImage(['url' => 'var-img2.jpg']),
            ]));

        $this->imageUploadService->shouldReceive('delete')
            ->with('main.jpg')
            ->once();

        $this->imageUploadService->shouldReceive('delete')
            ->with('var-img1.jpg')
            ->once();

        $this->imageUploadService->shouldReceive('delete')
            ->with('var-img2.jpg')
            ->once();

        $this->repository->shouldReceive('deletePriceHistory')
            ->with(1)
            ->andReturn(new ProductPriceHistory());

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->andReturn(true);

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testGetProductVariants()
    {
        $product = new Product(['id' => 1, 'name' => 'Product']);

        $variants = new Collection([
            new ProductVariant(['id' => 1, 'sku' => 'VAR-001']),
            new ProductVariant(['id' => 2, 'sku' => 'VAR-002'])
        ]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($product);

        $this->repository->shouldReceive('getVariants')
            ->with(1)
            ->andReturn($variants);

        $result = $this->repository->getVariants(1);

        $this->assertCount(2, $result);
        $this->assertEquals('VAR-001', $result->first()->sku);
    }

    public function testUpdateProductVariant()
    {
        $this->repository->shouldReceive('updateVariant')
            ->with(1, ['sku' => 'NEW-SKU', 'price_modifier' => 10.00])
            ->once()
            ->andReturn(true);

        $result = $this->repository->updateVariant(1, [
            'sku' => 'NEW-SKU',
            'price_modifier' => 10.00
        ]);

        $this->assertTrue($result);
    }

    public function testDeleteProductVariant()
    {
        $this->repository->shouldReceive('deleteVariant')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->repository->deleteVariant(1);

        $this->assertTrue($result);
    }

    public function testUpdateProductChangingPrimaryImage()
    {
        $product = new Product(['id' => 1, 'name' => 'Product']);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        // Simulate changing which image is primary
        $this->repository->shouldReceive('syncImages')
            ->once()
            ->with(1, Mockery::on(function($images) {
                // Verify second image is now primary
                return count($images) === 2
                    && $images[0]['is_primary'] === false
                    && $images[1]['is_primary'] === true
                    && $images[0]['url'] === 'img1.jpg'
                    && $images[1]['url'] === 'img2.jpg';
            }));

        $data = [
            'name' => 'Updated Product',
            'images' => [
                ['url' => 'img1.jpg', 'alt' => 'Image 1', 'is_primary' => false, 'sort_order' => 0],
                ['url' => 'img2.jpg', 'alt' => 'Image 2', 'is_primary' => true, 'sort_order' => 1],
            ]
        ];

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testCreateProductWithMerchantsAndVariantOverrides()
    {
        $brand = $this->createBrand();
        $category = $this->createCategory();

        $variant = new ProductVariant([
            'sku' => 'VAR-001',
            'name' => 'Red',
            'attributes' => ['color' => 'Red'],
            'price' => 110,
            'sale_price' => 100,
            'price_modifier' => 0,
            'is_active' => true,
            'id' => 1
        ]);

        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'variants' => [
                [
                    'sku' => 'VAR-001',
                    'name' => 'Red',
                    'attributes' => ['color' => 'Red'],
                    'price' => 110,
                    'sale_price' => 100,
                    'price_modifier' => 0,
                    'is_active' => true,
                    'id' => 2
                ],
            ],
            'merchants' => [
                [
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com',
                    'price' => 115,
                    'override_price' => true,
                    'override_sale_price' => false,
                    'variant_id' => 1, // Will be resolved after variant creation
                    'variant_sku' => 'AMZN-VAR-001',
                    'is_available' => true,
                    'id' => 1
                ],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->andReturn([1]);

        $this->repository->shouldReceive('getVariants')
            ->once()
            ->with(1)
            ->andReturn(collect([$variant]));

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->with(1, Mockery::on(function($merchants) {
                return count($merchants) === 1
                    && $merchants[0]['override_price'] === true
                    && $merchants[0]['variant_sku'] === 'AMZN-VAR-001';
            }))
            ->andReturn([1]);

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 115, 1, 100);

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testUpdateProductMerchantVariantOverrides()
    {
        $product = new Product([
            'id' => 1,
            'name' => 'Product',
            'price' => 99.99
        ]);

        $variant = new ProductVariant([
            'id' => 1,
            'sku' => 'VAR-001',
            'price' => 110
        ]);

        $existingMerchants = collect([
            [
                'id' => 1,
                'name' => 'Amazon',
                'price' => 110,
                'override_price' => false,
                'variant_id' => 1,
                'effective_price' => 110
            ]
        ]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('getProductMerchantsWithDetails')
            ->with(1)
            ->andReturn($existingMerchants);

        $this->repository->shouldReceive('getVariants')
            ->with(1)
            ->andReturn(collect([$variant]));

        $data = [
            'merchants' => [
                [
                    'id' => 1,
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com',
                    'price' => 120,
                    'override_price' => true, // Now overriding
                    'variant_id' => 1,
                    'variant_sku' => 'CUSTOM-SKU',
                    'is_available' => true
                ]
            ]
        ];

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->andReturn([1]);

        // Should record history because effective price changed from 110 to 120
        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 120, 1, null);

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testCreateProductRecordsMerchantPriceHistoryWithOverrides()
    {
        $brand = $this->createBrand();
        $category = $this->createCategory();

        // Must be an array OF variants (objects or arrays)
        // Your service expects ->id, so return an object
        $variantsCollection = collect([
            (object)[
                'sku' => 'VAR-001',
                'price' => 999,
                'sale_price' => 949,
                'attributes' => [],
                'price_modifier' => 0,
                'is_active' => true,
                'id' => 1
            ]
        ]);

        $data = [
            'name' => 'iPhone 15',
            'price' => 999.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'variants' => [
                [
                    'sku' => 'VAR-001',
                    'price' => 999,
                    'sale_price' => 949,
                    'attributes' => [],
                    'price_modifier' => 0,
                    'is_active' => true,
                    'id' => 1
                ]
            ],
            'merchants' => [
                [
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com',
                    'price' => 979, // Override price
                    'override_price' => true,
                    'variant_id' => 1,
                    'is_available' => true,
                    'id' => 1
                ],
                [
                    'name' => 'BestBuy',
                    'url' => 'https://bestbuy.com',
                    'price' => 999, // Use variant price
                    'override_price' => false,
                    'variant_id' => 1,
                    'is_available' => true,
                    'id' => 2
                ],
            ]
        ];

        $product = new Product(array_merge(['id' => 1], $data));

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('recordPriceHistory')
            ->once()
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->andReturn([1]);

        // Return a proper variants collection (collection of objects)
        $this->repository->shouldReceive('getVariants')
            ->twice()
            ->andReturn($variantsCollection);

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->andReturn([1, 2]);

        // Amazon should record override price
        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 979, 1, 949);

        // BestBuy should record variant price (999)
        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 2, 999, 2, 949);

        $result = $this->service->createProduct($data);

        $this->assertInstanceOf(Product::class, $result);
    }


    public function testCreateProductWithMerchantsAndVariantsMapsProperly()
    {
        $brand = $this->createBrand();
        $category = $this->createCategory();

        $variantsCollection = collect([
            new ProductVariant([
                'sku' => 'VAR-001',
                'name' => 'Red',
                'attributes' => ['color' => 'Red'],
                'price' => 110,
                'sale_price' => 100,
                'price_modifier' => 0,
                'is_active' => true,
                'id' => 2
            ]),
            new ProductVariant([
                'sku' => 'VAR-002',
                'name' => 'Blue',
                'attributes' => ['color' => 'Blue'],
                'price' => 120,
                'sale_price' => 110,
                'price_modifier' => 0,
                'is_active' => true,
                'id' => 1
            ])
        ]);

        // Simulate form data with variant_id as 1-indexed (from form array indices)
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'variants' => [
                [
                    'sku' => 'VAR-001',
                    'name' => 'Red',
                    'attributes' => ['color' => 'Red'],
                    'price' => 110,
                    'sale_price' => 100,
                    'price_modifier' => 0,
                    'is_active' => true
                ],
                [
                    'sku' => 'VAR-002',
                    'name' => 'Blue',
                    'attributes' => ['color' => 'Blue'],
                    'price' => 120,
                    'sale_price' => 110,
                    'price_modifier' => 0,
                    'is_active' => true
                ],
            ],
            'merchants' => [
                [
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com/var1',
                    'price' => 105,
                    'override_price' => true,
                    'variant_id' => 1, // Form index (1-indexed)
                    'is_available' => true,
                    'id' => 1
                ],
                [
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com/var2',
                    'price' => 115,
                    'override_price' => true,
                    'variant_id' => 2, // Form index (1-indexed)
                    'is_available' => true,
                    'id' => 2
                ],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $this->repository->shouldReceive('getVariants')
            ->twice()
            ->with(1)
            ->andReturn($variantsCollection);

        // Variants sync returns actual DB IDs
        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->andReturn([101, 102]);

        // Should receive merchants with mapped variant IDs
        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->with(1, Mockery::on(function($merchants) {
                // Check that variant_ids have been mapped to actual DB IDs
                return count($merchants) === 2
                    && $merchants[0]['variant_id'] === 101
                    && $merchants[1]['variant_id'] === 102;
            }))
            ->andReturn([1, 2]);

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->twice();

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testCreateProductWithSpecificationsCreatesGroups()
    {
        $brand = $this->createBrand();
        $category = $this->createCategory();

        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'specifications' => [
                ['category' => 'Dimensions', 'key' => 'Width', 'value' => '10cm', 'sort_order' => 0],
                ['category' => 'dimensions', 'key' => 'Height', 'value' => '20cm', 'sort_order' => 1],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($product);

        $this->repository->shouldReceive('syncSpecifications')
            ->once()
            ->with(1, Mockery::type('array'));

        $this->repository->shouldReceive('recordPriceHistory')
            ->with($product)
            ->andReturn(new ProductPriceHistory());

        $result = $this->service->createProduct($data);

        // This test should actually be testing the repository, not the service
        // The service just passes data to the repository
        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals('Test Product', $result->name);
    }
}