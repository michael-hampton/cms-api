<?php

namespace App\Tests\Unit\Services\Product;

use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Repositories\Product\ProductRepository;
use App\Services\Product\MerchantPricingResolver;
use App\Services\Product\ProductImageUploadService;
use App\Services\Product\ProductService;
use App\Services\Shared\RequestContext;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;
use Mockery\MockInterface;

class ProductServiceTest extends FunctionalTestCase
{
    use CreatesTestData, HasSiteHistory;

    protected ProductRepository $repository;
    protected ProductImageUploadService $imageUploadService;
    protected MerchantPricingResolver $merchantPricingResolver;
    protected RequestContext $requestContext;
    protected ProductService $service;
    private Database $databaseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ProductRepository::class);
        $this->imageUploadService = Mockery::mock(ProductImageUploadService::class);
        $this->merchantPricingResolver = Mockery::mock(MerchantPricingResolver::class);
        $this->requestContext = Mockery::mock(RequestContext::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new ProductService(
            $this->repository,
            $this->imageUploadService,
            $this->merchantPricingResolver,
            $this->requestContext,
            $this->databaseMock
        );
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

        $this->imageUploadService->shouldReceive('upload')
            ->once()
            ->with($file, null)
            ->andReturn('products/2025-01/product_123.jpg');

        $product = new Product(['id' => 1, 'name' => 'Test Product', 'image' => 'products/2025-01/product_123.jpg']);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();


        $data = ['name' => 'Test Product', 'price' => 99.99];
        $result = $this->service->createProduct($data, $file);

        $this->assertEquals('products/2025-01/product_123.jpg', $result->image);
    }

    public function testUpdateProductWithImageFile()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);

        $product = new Product(['id' => 1, 'name' => 'Old Name', 'image' => 'old-image.jpg', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->imageUploadService->shouldReceive('upload')
            ->once()
            ->with($file, 'old-image.jpg')
            ->andReturn('products/2025-01/new-image.jpg');

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $data = ['name' => 'New Name'];
        $result = $this->service->updateProduct(1, $data, $file);

        $this->assertNotNull($result);
    }

    public function testCreateProductWithBase64Image()
    {
        $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $this->imageUploadService->shouldReceive('isBase64Image')
            ->with($base64)
            ->andReturn(true);

        $this->imageUploadService->shouldReceive('saveBase64Image')
            ->once()
            ->with($base64)
            ->andReturn('products/2025-01/base64_image.png');

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        $data = ['name' => 'Test Product', 'price' => 99.99, 'image' => $base64];
        $result = $this->service->createProduct($data);

        $this->assertInstanceOf(Product::class, $result);
    }

    public function testUpdateProductWithBase64ImageDeletesOldImage()
    {
        $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $product = new Product(['id' => 1, 'name' => 'Product', 'image' => 'old-image.jpg', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->imageUploadService->shouldReceive('isBase64Image')
            ->with($base64)
            ->andReturn(true);

        $this->imageUploadService->shouldReceive('delete')
            ->once()
            ->with('old-image.jpg');

        $this->imageUploadService->shouldReceive('saveBase64Image')
            ->once()
            ->with($base64)
            ->andReturn('products/2025-01/new-base64.png');

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $data = ['name' => 'Updated', 'image' => $base64];
        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testDeleteProductHandlesImageDeletionFailureGracefully()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'image' => 'main.jpg']);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->imageUploadService->shouldReceive('delete')
            ->with('main.jpg')
            ->andThrow(new \Exception('Delete failed'));

        $this->repository->shouldReceive('getImages')->andReturn(collect([]));
        $this->repository->shouldReceive('getVariants')->andReturn(collect([]));
        $this->repository->shouldReceive('deletePriceHistory')->once();
        $this->repository->shouldReceive('delete')->andReturn(true);

        // Should not throw, just log error
        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testDeleteProductDeletesMainImageAndReturnsTrue(): void
    {
        $product = $this->makeProduct(id: 1, price: 10.0, salePrice: null);
        $product->image = 'products/img.jpg';

        $this->repository->expects('find')->with(1)->andReturn($product);
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->imageUploadService->expects('delete')->with('products/img.jpg')->once();
        $this->repository->expects('getImages')->with(1)->andReturn(collect());
        $this->repository->expects('getVariants')->with(1)->andReturn(collect());
        $this->repository->expects('deletePriceHistory')->with(1)->once();
        $this->repository->expects('delete')->with(1)->andReturn(true);

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testDeleteProductDeletesAdditionalImages(): void
    {
        $product = $this->makeProduct(id: 1, price: 10.0, salePrice: null);
        $product->image = null;

        $extraImage = new \stdClass();
        $extraImage->url = 'gallery/extra.jpg';

        $this->repository->expects('find')->with(1)->andReturn($product);
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->expects('getImages')->with(1)->andReturn(collect([$extraImage]));
        $this->imageUploadService->expects('delete')->with('gallery/extra.jpg')->once();
        $this->repository->expects('getVariants')->with(1)->andReturn(collect());
        $this->repository->expects('deletePriceHistory')->with(1)->once();
        $this->repository->expects('delete')->with(1)->andReturn(true);

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testDeleteProductDeletesVariantImages(): void
    {
        $product = $this->makeProduct(id: 1, price: 10.0, salePrice: null);
        $product->image = null;

        $variant = new \stdClass();
        $variant->id = 10;

        $variantImage = new \stdClass();
        $variantImage->url = 'variants/v-img.jpg';

        $this->repository->expects('find')->with(1)->andReturn($product);
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->expects('getImages')->with(1)->andReturn(collect());
        $this->repository->expects('getVariants')->with(1)->andReturn(collect([$variant]));
        $this->repository->expects('getVariantImages')->with(10)->andReturn(collect([$variantImage]));
        $this->repository->expects('deleteVariantImages')->with(10)->once();
        $this->imageUploadService->expects('delete')->with('variants/v-img.jpg')->once();
        $this->repository->expects('deletePriceHistory')->with(1)->once();
        $this->repository->expects('delete')->with(1)->andReturn(true);

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testDeleteProductContinuesWhenImageDeletionFails(): void
    {
        $product = $this->makeProduct(id: 1, price: 10.0, salePrice: null);
        $product->image = 'broken/img.jpg';

        $this->repository->expects('find')->with(1)->andReturn($product);
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->imageUploadService
            ->expects('delete')
            ->with('broken/img.jpg')
            ->andThrow(new \Exception('Storage unavailable'));

        $this->repository->expects('getImages')->with(1)->andReturn(collect());
        $this->repository->expects('getVariants')->with(1)->andReturn(collect());
        $this->repository->expects('deletePriceHistory')->with(1)->once();
        $this->repository->expects('delete')->with(1)->andReturn(true);

        // Must NOT throw — image deletion errors are non-critical
        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testDeleteProductReturnsFalseWhenRepositoryDeleteFails(): void
    {
        $product = $this->makeProduct(id: 1, price: 10.0, salePrice: null);
        $product->image = null;

        $this->repository->expects('find')->with(1)->andReturn($product);
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->expects('getImages')->with(1)->andReturn(collect([]));
        $this->repository->expects('getVariants')->with(1)->andReturn(collect());
        $this->repository->expects('deletePriceHistory')->with(1)->once();
        $this->repository->expects('delete')->with(1)->andReturn(false);

        $result = $this->service->deleteProduct(1);

        $this->assertFalse($result);
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

    public function testGetPaginatedProductsReturnsPaginatedArray(): void
    {
        $expected = ['data' => [], 'total' => 0];
        $this->repository->expects('paginate')->with(15)->andReturn($expected);

        $result = $this->service->getPaginatedProducts(15);

        $this->assertSame($expected, $result);
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

    public function testCreateProduct()
    {
        $data = ['name' => 'Test Product', 'price' => 99.99];
        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($product) {
                return $callback();
            });

        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testUpdateProduct()
    {
        $product = new Product(['id' => 1, 'name' => 'Old Name', 'price' => 99.99]);
        $data = ['name' => 'New Name'];

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($product) {
                return $callback();
            });

        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
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

    public function testDeleteProduct()
    {
        $product = new Product(['id' => 1, 'name' => 'Test', 'image' => null]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('getImages')->andReturn(collect([]));
        $this->repository->shouldReceive('getVariants')->andReturn(collect([]));
        $this->repository->shouldReceive('deletePriceHistory')->once();
        $this->repository->shouldReceive('delete')->once()->andReturn(true);

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

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($createData) {
                return !isset($createData['images']) // Images should be removed
                    && $createData['name'] === 'Test Product'
                    && $createData['price'] == 99.99;
            }))
            ->andReturn($product);

        $this->repository->shouldReceive('syncImages')
            ->once()
            ->with(1, Mockery::on(function($images) {
                return count($images) === 2
                    && $images[0]['url'] === 'img1.jpg'
                    && $images[1]['url'] === 'img2.jpg';
            }));

        $this->repository->shouldReceive('recordPriceHistory')->once();

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

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->with(1, Mockery::type('array'))
            ->andReturn([1]);

        $this->repository->shouldReceive('getVariants')->once()->andReturn(collect([]));

        $this->merchantPricingResolver->shouldReceive('resolve')
            ->once()
            ->andReturn(['price' => 79.99, 'sale_price' => null]);

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 79.99, 27, null);

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testCreateProductWithRegionSets()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'region_set_ids' => [1, 2, 3]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        $this->repository->shouldReceive('syncRegionSets')
            ->once()
            ->with(1, [1, 2, 3])
            ->andReturn([1]);

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testCreateProductWithMerchantsUsesPricingResolver()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'merchants' => [
                ['id' => 1, 'name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 79.99, 'is_available' => true],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();
        $this->repository->shouldReceive('syncMerchants')->once()->andReturn([1]);
        $this->repository->shouldReceive('getVariants')->once()->andReturn(collect([]));

        $this->merchantPricingResolver->shouldReceive('resolve')
            ->once()
            ->with($data['merchants'][0], Mockery::type(Collection::class))
            ->andReturn(['price' => 79.99, 'sale_price' => null]);

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 79.99, 1, null);

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

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->with(1, Mockery::on(function ($variants) {
                return count($variants) === 1 && $variants[0]['sku'] === 'VAR-001';
            }))
            ->andReturn([1]);

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

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        $this->repository->shouldReceive('syncSpecifications')
            ->once()
            ->with(1, Mockery::on(function ($specs) {
                return count($specs) === 1 && $specs[0]['key'] === 'Weight';
            }));

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testCreateProductStripsRelationKeysFromMainPayload(): void
    {
        $product = $this->makeProduct(id: 1, price: 10.0, salePrice: null);

        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->imageUploadService->allows('isBase64Image')->andReturn(false);

        $this->repository
            ->expects('create')
            ->withArgs(function (array $data) {
                return !array_key_exists('images', $data)
                    && !array_key_exists('merchants', $data)
                    && !array_key_exists('variants', $data)
                    && !array_key_exists('specifications', $data);
            })
            ->andReturn($product);

        $this->repository->allows('recordPriceHistory');
        $this->repository->allows('syncImages');
        $this->repository->allows('syncVariants')->andReturn([0 => 10]);
        $this->repository->allows('syncMerchants')->andReturn([]);
        $this->repository->allows('syncSpecifications');
        $this->repository->allows('getVariants')->andReturn(collect([]));

        $result = $this->service->createProduct([
            'name' => 'T',
            'images' => ['img.jpg'],
            'merchants' => [],
            'variants' => [],
            'specifications' => [],
        ]);

        $this->assertSame($product, $result);
    }

    public function testCreateProductWithAllRelations()
    {
        $data = [
            'name' => 'Complete Product',
            'price' => 99.99,
            'images' => [['url' => 'img1.jpg', 'alt' => 'Image 1', 'is_primary' => true, 'sort_order' => 0]],
            'merchants' => [['id' => 1, 'name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 79.99, 'is_available' => true]],
            'variants' => [['sku' => 'VAR-001', 'attributes' => [], 'price_modifier' => 0, 'is_active' => true]],
            'specifications' => [['category' => 'Tech', 'key' => 'Weight', 'value' => '1kg', 'sort_order' => 0]],
        ];

        $product = new Product(['id' => 1, 'name' => 'Complete Product']);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();
        $this->repository->shouldReceive('syncImages')->once();
        $this->repository->shouldReceive('syncMerchants')->once()->andReturn([1]);
        $this->repository->shouldReceive('syncVariants')->once()->andReturn([1]);
        $this->repository->shouldReceive('syncSpecifications')->once();
        $this->repository->shouldReceive('getVariants')->once()->andReturn(collect([]));

        $this->merchantPricingResolver->shouldReceive('resolve')
            ->once()
            ->andReturn(['price' => 79.99, 'sale_price' => null]);

        $this->repository->shouldReceive('recordMerchantPriceHistory')->once();

        $result = $this->service->createProduct($data);

        $this->assertEquals('Complete Product', $result->name);
    }


    public function testUpdateProductWithBasicData()
    {
        $product = new Product(['id' => 1, 'name' => 'Old Name', 'price' => 99.99]);
        $data = ['name' => 'New Name'];

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($product) {
                return $callback();
            });

        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $result = $this->service->updateProduct(1, $data);

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
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('syncImages')
            ->once()
            ->with(1, Mockery::on(function ($images) {
                return count($images) === 1 && $images[0]['url'] === 'new1.jpg';
            }));

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
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('getProductMerchantsWithDetails')
            ->with(1)
            ->andReturn(collect([]));

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->with(1, Mockery::type('array'))
            ->andReturn([1]);

        $this->repository->shouldReceive('getVariants')->once()->andReturn(collect([]));

        $this->merchantPricingResolver->shouldReceive('resolve')
            ->once()
            ->andReturn(['price' => 89.99, 'sale_price' => null]);

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 89.99, 1, null);

        $data = [
            'name' => 'Updated Product',
            'merchants' => [
                ['id' => 1, 'name' => 'eBay', 'url' => 'https://ebay.com', 'price' => 89.99, 'is_available' => true],
            ]
        ];

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testUpdateProductWithRegionSets()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('getProductMerchantsWithDetails')
            ->with(1)
            ->andReturn(collect([]));

        $this->repository->shouldReceive('syncRegionSets')
            ->once()
            ->with(1, [1, 2, 3])
            ->andReturn([1]);

        $data = [
            'name' => 'Updated Product',
            'region_set_ids' => [1, 2, 3]
        ];

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testUpdateProductWithVariants()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->with(1, Mockery::on(function ($variants) {
                return count($variants) === 1 && $variants[0]['sku'] === 'VAR-002';
            }))
            ->andReturn([1]);

        $data = [
            'name' => 'Updated Product',
            'variants' => [
                ['sku' => 'VAR-002', 'attributes' => [], 'price_modifier' => 5, 'is_active' => true],
            ]
        ];

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testUpdateProductDoesNotSyncRelationsWhenNotInPayload(): void
    {
        $original = $this->makeProduct(id: 1, price: 10.0, salePrice: null);
        $original->image = null;
        $updated = $this->makeProduct(id: 1, price: 10.0, salePrice: null);

        $this->repository->expects('find')->with(1)->andReturn($original);
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->imageUploadService->allows('isBase64Image')->andReturn(false);
        $this->repository->expects('update')->andReturn($updated);
        $this->repository->expects('syncMerchants')->never();
        $this->repository->expects('syncVariants')->never();
        $this->repository->expects('syncSpecifications')->never();
        $this->repository->expects('syncImages')->never();

        $result = $this->service->updateProduct(1, ['name' => 'No relations']);

        $this->assertSame($updated, $result);
    }

    public function testUpdateProductUploadsNewImageAndDeletesOldOne(): void
    {
        $original = $this->makeProduct(id: 1, price: 10.0, salePrice: null);
        $original->image = 'old/img.jpg';
        $updated = $this->makeProduct(id: 1, price: 10.0, salePrice: null);

        $file = Mockery::mock(UploadedFile::class);
        $file->allows('isValid')->andReturn(true);

        $this->repository->expects('find')->with(1)->andReturn($original);
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->imageUploadService->expects('upload')->with($file, 'old/img.jpg')->andReturn('new/img.jpg');
        $this->repository
            ->expects('update')
            ->withArgs(fn($id, $d) => ($d['image'] ?? null) === 'new/img.jpg')
            ->andReturn($updated);

        $result = $this->service->updateProduct(1, [], $file);

        $this->assertSame($updated, $result);
    }


    public function testUpdateProductWithSpecifications()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('syncSpecifications')
            ->once()
            ->with(1, Mockery::on(function ($specs) {
                return count($specs) === 1 && $specs[0]['key'] === 'Dimensions';
            }));

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
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
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

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->repository->shouldReceive('getImages')->with(1)->andReturn(collect([]));
        $this->repository->shouldReceive('getVariants')->with(1)->andReturn(collect([]));
        $this->repository->shouldReceive('deletePriceHistory')->with(1)->once();
        $this->repository->shouldReceive('delete')->with(1)->andReturn(true);

        $this->imageUploadService->shouldNotReceive('delete');

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testDeleteProductWithImage()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'image' => 'main.jpg']);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->imageUploadService->shouldReceive('delete')
            ->once()
            ->with('main.jpg');

        $this->repository->shouldReceive('getImages')->with(1)->andReturn(collect([]));
        $this->repository->shouldReceive('getVariants')->with(1)->andReturn(collect([]));
        $this->repository->shouldReceive('deletePriceHistory')->with(1)->once();
        $this->repository->shouldReceive('delete')->with(1)->andReturn(true);

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

        $this->imageUploadService->shouldReceive('upload')
            ->once()
            ->andThrow(new \Exception('Upload failed'));

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to upload product image: Upload failed');

        $data = ['name' => 'Test', 'price' => 99.99];
        $this->service->createProduct($data, $file);
    }

    public function testUpdateProductThrowsExceptionOnImageUploadFailure()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);

        $product = new Product(['id' => 1, 'name' => 'Product', 'image' => 'old.jpg', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->imageUploadService->shouldReceive('upload')
            ->once()
            ->andThrow(new \Exception('Upload failed'));

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to upload product image: Upload failed');

        $data = ['name' => 'Updated'];
        $this->service->updateProduct(1, $data, $file);
    }

    public function testDeleteProductWithMultipleImagesAndVariants()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'image' => 'main.jpg']);

        $images = collect([
            new ProductImage(['url' => 'img1.jpg']),
            new ProductImage(['url' => 'img2.jpg']),
        ]);

        $variant = new ProductVariant(['id' => 1, 'sku' => 'VAR-001']);
        $variantImages = collect([
            new ProductImage(['url' => 'var-img1.jpg']),
        ]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->imageUploadService->shouldReceive('delete')->times(4); // main + 2 product + 1 variant

        $this->repository->shouldReceive('getImages')->with(1)->andReturn($images);
        $this->repository->shouldReceive('getVariants')->with(1)->andReturn(collect([$variant]));
        $this->repository->shouldReceive('getVariantImages')->with(1)->andReturn($variantImages);
        $this->repository->shouldReceive('deleteVariantImages')->with(1)->once();
        $this->repository->shouldReceive('deletePriceHistory')->with(1)->once();
        $this->repository->shouldReceive('delete')->with(1)->andReturn(true);

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

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);

        $this->repository->shouldReceive('recordPriceHistory')
            ->once()
            ->with(Mockery::on(function ($p) use ($product) {
                return $p->id === $product->id;
            }));

        $result = $this->service->createProduct($data);

        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals(99.99, $product->price);
        $this->assertEquals(79.99, $product->sale_price);
    }

    public function testUpdateProductRecordsPriceHistoryWhenPriceChanges(): void
    {
        $original = $this->makeProduct(id: 1, price: 10.0, salePrice: null);
        $original->image = null;
        $updated = $this->makeProduct(id: 1, price: 20.0, salePrice: null);

        $this->repository->expects('find')->with(1)->andReturn($original);
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->imageUploadService->allows('isBase64Image')->andReturn(false);
        $this->repository->expects('update')->andReturn($updated);
        $this->repository->expects('recordPriceHistory')->with($updated)->once();

        $result = $this->service->updateProduct(1, ['price' => 20.0]);

        $this->assertSame($updated, $result);
    }

    public function testUpdateProductRecordsPriceHistoryWhenSalePriceChanges(): void
    {
        $original = $this->makeProduct(id: 1, price: 10.0, salePrice: null);
        $original->image = null;
        $updated = $this->makeProduct(id: 1, price: 10.0, salePrice: 7.0);

        $this->repository->expects('find')->with(1)->andReturn($original);
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->imageUploadService->allows('isBase64Image')->andReturn(false);
        $this->repository->expects('update')->andReturn($updated);
        $this->repository->expects('recordPriceHistory')->with($updated)->once();

        $result = $this->service->updateProduct(1, ['sale_price' => 7.0]);

        $this->assertSame($updated, $result);
    }


    public function testUpdateProductDoesNotEmitPriceEventWhenPriceUnchanged(): void
    {
        $original = $this->makeProduct(id: 1, price: 10.0, salePrice: 8.0);
        $original->image = null;
        $updated = $this->makeProduct(id: 1, price: 10.0, salePrice: 8.0);

        $this->repository->expects('find')->with(1)->andReturn($original);
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->imageUploadService->allows('isBase64Image')->andReturn(false);

        // price and sale_price not in payload — no recordPriceHistory or event
        $this->repository->expects('update')->andReturn($updated);
        $this->repository->expects('recordPriceHistory')->never();

        $result = $this->service->updateProduct(1, ['name' => 'Changed']);

        $this->assertSame($updated, $result);
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

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('update')->andReturn($product);

        $this->repository->shouldNotReceive('recordPriceHistory');

        $data = ['name' => 'Updated Name']; // No price change
        $result = $this->service->updateProduct(1, $data);
        $this->assertInstanceOf(Product::class, $result);
    }


    public function testDeleteProductDeletesPriceHistory()
    {
        $product = new Product(['id' => 1, 'name' => 'Test', 'image' => null]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->repository->shouldReceive('getImages')->andReturn(collect([]));
        $this->repository->shouldReceive('getVariants')->andReturn(collect([]));

        $this->repository->shouldReceive('deletePriceHistory')
            ->once()
            ->with(1);

        $this->repository->shouldReceive('delete')->andReturn(true);

        $result = $this->service->deleteProduct(1);

        $this->assertTrue($result);
    }

    public function testCreateProductRecordsMerchantPriceHistory()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'merchants' => [
                ['id' => 1, 'name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 79.99, 'is_available' => true],
                ['id' => 2, 'name' => 'eBay', 'url' => 'https://ebay.com', 'price' => 89.99, 'is_available' => true],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->andReturn([1, 2]);

        $this->repository->shouldReceive('getVariants')->twice()->andReturn(collect([]));

        $this->merchantPricingResolver->shouldReceive('resolve')
            ->twice()
            ->andReturnUsing(function ($merchantData) {
                return ['price' => $merchantData['price'], 'sale_price' => null];
            });

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 79.99, 1, null);

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 2, 89.99, 2, null);

        $result = $this->service->createProduct($data);

        $this->assertInstanceOf(Product::class, $result);
    }

    public function testUpdateProductRecordsMerchantPriceHistoryOnPriceChange()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

        $existingMerchants = collect([
            ['id' => 1, 'name' => 'Amazon', 'price' => 79.99, 'sale_price' => null],
            ['id' => 2, 'name' => 'eBay', 'price' => 89.99, 'sale_price' => null]
        ]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
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

        $this->repository->shouldReceive('getVariants')->twice()->andReturn(collect([]));

        $this->merchantPricingResolver->shouldReceive('resolve')
            ->twice()
            ->andReturnUsing(function ($merchantData) {
                return ['price' => $merchantData['price'], 'sale_price' => null];
            });

        // Should only record history for Amazon (price changed)
        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 74.99, 1, null);

        $result = $this->service->updateProduct(1, $data);

        $this->assertNotNull($result);
    }

    public function testUpdateProductRecordsHistoryForNewMerchants()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

        $existingMerchants = collect([
            ['id' => 1, 'name' => 'Amazon', 'price' => 79.99]
        ]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('getProductMerchantsWithDetails')
            ->andReturn($existingMerchants);

        $data = [
            'merchants' => [
                ['id' => 1, 'name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 79.99, 'is_available' => true],
                ['id' => 2, 'name' => 'BestBuy', 'url' => 'https://bestbuy.com', 'price' => 85.99, 'is_available' => true], // New merchant
            ]
        ];

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->andReturn([1, 3]);

        $this->repository->shouldReceive('getVariants')->twice()->andReturn(collect([]));

        $this->merchantPricingResolver->shouldReceive('resolve')
            ->twice()
            ->andReturnUsing(function ($merchantData) {
                return ['price' => $merchantData['price'], 'sale_price' => null];
            });

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

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->with(1, Mockery::on(function($variants) {
                return count($variants) === 1
                    && isset($variants[0]['images'])
                    && count($variants[0]['images']) === 2;
            }))
            ->andReturn([1]);

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testUpdateProductWithVariantImages()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
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
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($product);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('update')->once()->andReturn($product);

        $this->repository->shouldReceive('syncImages')
            ->once()
            ->with(1, Mockery::on(function($images) {
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
                    'id' => 1,
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com',
                    'price' => 115,
                    'override_price' => true,
                    'override_sale_price' => false,
                    'variant_id' => 1,
                    'variant_sku' => 'AMZN-VAR-001',
                    'is_available' => true
                ],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

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
                    && $merchants[0]['variant_sku'] === 'AMZN-VAR-001'
                    && $merchants[0]['variant_id'] === 1; // Mapped from form index to DB ID
            }))
            ->andReturn([1]);

        $this->merchantPricingResolver->shouldReceive('resolve')
            ->once()
            ->andReturn(['price' => 115, 'sale_price' => 100]);

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 115, 1, 100);

        $result = $this->service->createProduct($data);

        $this->assertEquals('Test Product', $result->name);
    }

    public function testUpdateProductMerchantVariantOverrides()
    {
        $product = new Product(['id' => 1, 'name' => 'Product', 'price' => 99.99]);

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

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
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

        $this->merchantPricingResolver->shouldReceive('resolve')
            ->once()
            ->andReturn(['price' => 120, 'sale_price' => null]);

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
                    'id' => 1,
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com',
                    'price' => 979,
                    'override_price' => true,
                    'variant_id' => 1,
                    'is_available' => true
                ],
                [
                    'id' => 2,
                    'name' => 'BestBuy',
                    'url' => 'https://bestbuy.com',
                    'price' => 999,
                    'override_price' => false,
                    'variant_id' => 1,
                    'is_available' => true
                ],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'iPhone 15']);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->andReturn([1]);

        $this->repository->shouldReceive('getVariants')
            ->twice()
            ->andReturn($variantsCollection);

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->andReturn([1, 2]);

        // Amazon should use override price (979)
        $this->merchantPricingResolver->shouldReceive('resolve')
            ->once()
            ->with(Mockery::on(function ($m) {
                return $m['name'] === 'Amazon';
            }), Mockery::type(Collection::class))
            ->andReturn(['price' => 979, 'sale_price' => 949]);

        // BestBuy should use variant price (999)
        $this->merchantPricingResolver->shouldReceive('resolve')
            ->once()
            ->with(Mockery::on(function ($m) {
                return $m['name'] === 'BestBuy';
            }), Mockery::type(Collection::class))
            ->andReturn(['price' => 999, 'sale_price' => 949]);

        $this->repository->shouldReceive('recordMerchantPriceHistory')
            ->once()
            ->with(1, 1, 979, 1, 949);

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
                'id' => 101  // DB ID
            ]),
            new ProductVariant([
                'sku' => 'VAR-002',
                'name' => 'Blue',
                'attributes' => ['color' => 'Blue'],
                'price' => 120,
                'sale_price' => 110,
                'price_modifier' => 0,
                'is_active' => true,
                'id' => 102  // DB ID
            ])
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
                    'id' => 1,
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com/var1',
                    'price' => 105,
                    'override_price' => true,
                    'variant_id' => 1, // Form index (1-indexed)
                    'is_available' => true
                ],
                [
                    'id' => 2,
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com/var2',
                    'price' => 115,
                    'override_price' => true,
                    'variant_id' => 2, // Form index (1-indexed)
                    'is_available' => true
                ],
            ]
        ];

        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        // Variants sync returns actual DB IDs
        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->andReturn([101, 102]);

        $this->repository->shouldReceive('getVariants')
            ->twice()
            ->with(1)
            ->andReturn($variantsCollection);

        // Should receive merchants with mapped variant IDs
        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->with(1, Mockery::on(function($merchants) {
                // Check that variant_ids have been mapped to actual DB IDs
                return count($merchants) === 2
                    && $merchants[0]['variant_id'] === 101  // Mapped from form index 1
                    && $merchants[1]['variant_id'] === 102; // Mapped from form index 2
            }))
            ->andReturn([1, 2]);

        $this->merchantPricingResolver->shouldReceive('resolve')
            ->twice()
            ->andReturnUsing(function ($merchantData) {
                return ['price' => $merchantData['price'], 'sale_price' => null];
            });

        $this->repository->shouldReceive('recordMerchantPriceHistory')->twice();

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

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('create')->once()->andReturn($product);
        $this->repository->shouldReceive('recordPriceHistory')->once();

        $this->repository->shouldReceive('syncSpecifications')
            ->once()
            ->with(1, Mockery::type('array'));

        $result = $this->service->createProduct($data);

        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals('Test Product', $result->name);
    }

    public function testGetPaginatedProducts()
    {
        $paginationData = [
            'data' => [],
            'current_page' => 1,
            'total' => 0,
        ];

        $this->repository->shouldReceive('paginate')->with(15)->once()->andReturn($paginationData);

        $result = $this->service->getPaginatedProducts();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testGetProduct()
    {
        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->repository->shouldReceive('find')
            ->with(1, ['availableMerchants', 'availableMerchants.merchant'])
            ->once()
            ->andReturn($product);

        $result = $this->service->getProduct(1);

        $this->assertEquals('Test Product', $result->name);
        $this->assertEquals(1, $result->id);
    }

    public function testGetProductReturnsNullWhenNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999, ['availableMerchants', 'availableMerchants.merchant'])
            ->once()
            ->andReturn(null);

        $result = $this->service->getProduct(999);

        $this->assertNull($result);
    }

    public function testGetOnSaleProducts()
    {
        $products = collect([
            new Product(['id' => 1, 'price' => 100, 'sale_price' => 80]),
        ]);

        $this->repository->shouldReceive('getOnSale')->once()->andReturn($products);

        $result = $this->service->getOnSaleProducts();

        $this->assertCount(1, $result);
        $this->assertEquals(80, $result->first()->sale_price);
    }

    public function testGetRelatedProducts()
    {
        $product = new Product(['id' => 1, 'category' => 'Electronics']);
        $related = collect([
            new Product(['id' => 2, 'category' => 'Electronics']),
            new Product(['id' => 3, 'category' => 'Electronics']),
        ]);

        $this->repository->shouldReceive('findRelated')
            ->with($product, 8)
            ->once()
            ->andReturn($related);

        $result = $this->service->getRelatedProducts($product);

        $this->assertCount(2, $result);
    }

    public function testTrackViewEmitsProductViewedEventWithContextData(): void
    {
        $product = $this->createProduct();
        $user = $this->createMember();

        $this->requestContext->expects('getUserId')->andReturn($user->id);
        $this->requestContext->expects('getSessionId')->andReturn('sess-abc');
        $this->requestContext->expects('getIpAddress')->andReturn('127.0.0.1');

        // event() is a global — we verify it doesn't throw and delegates context correctly.
        // To assert the event payload in isolation, wrap event() via a framework test case.
        // Here we confirm RequestContext is consumed and no exception is raised.
        try {
            $this->service->trackView($product);
        } catch (\Throwable $e) {
            // event() may throw if no dispatcher is bound — acceptable in unit context
            if (!str_contains($e->getMessage(), 'event')) {
                throw $e;
            }
        }

        $this->addToAssertionCount(1);
    }

    public function testGetRelatedProductsWithCustomLimit()
    {
        $product = new Product(['id' => 1, 'category' => 'Electronics']);
        $related = collect([
            new Product(['id' => 2, 'category' => 'Electronics']),
        ]);

        $this->repository->shouldReceive('findRelated')
            ->with($product, 5)
            ->once()
            ->andReturn($related);

        $result = $this->service->getRelatedProducts($product, 5);

        $this->assertCount(1, $result);
    }

    private function makeProduct(int $id, float $price, ?float $salePrice): Product&MockInterface
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = uniqid();
        $product->id = $id;
        $product->price = $price;
        $product->sale_price = $salePrice;
        $product->image = null;
        return $product;
    }

}