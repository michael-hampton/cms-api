<?php

namespace App\Tests\Unit\Actions;

use App\Actions\CloneProduct;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMerchant;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Repositories\ProductRepository;
use App\Repositories\ProductViewRepository;
use App\Services\ImageUploadService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class CloneProductActionTest extends FunctionalTestCase
{
    use CreatesTestData, HasSiteHistory;

    protected $repository;
    protected $imageUploadService;
    protected CloneProduct $service;
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

        $this->service = new CloneProduct($this->repository, $this->imageUploadService, $productViewRepository);
    }

    public function testDuplicateProductSuccessfully(): void
    {
        $originalProduct = Mockery::mock(Product::class)->makePartial();
        $originalProduct->id = 1;
        $originalProduct->name = 'iPhone 15';
        $originalProduct->site_id = 1;
        $originalProduct->price = 999.99;
        $originalProduct->image = 'products/iphone15.jpg';

        $this->repository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalProduct);

        $this->repository
            ->shouldReceive('findBySlugAndSite')
            ->with('iphone-15-copy', 1)
            ->once()
            ->andReturn(null);

        $this->imageUploadService
            ->shouldReceive('duplicate')
            ->with('products/iphone15.jpg')
            ->once()
            ->andReturn('products/iphone15-copy.jpg');

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->name = 'iPhone 15 (Copy)';
        $newProduct->id = 2;

        $this->setCloneHistoryExpectations($originalProduct, $newProduct, 1, 2);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['name'] === 'iPhone 15 (Copy)'
                    && $data['price'] === 999.99;
            }))
            ->andReturn($newProduct);

        $this->setDuplicateExpectations();

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Product::class, $result['product']);
        $this->assertEquals('iPhone 15 (Copy)', $result['product']->name);
    }

    public function testDuplicateProductWithoutImage(): void
    {
        $originalProduct = Mockery::mock(Product::class)->makePartial();
        $originalProduct->id = 1;
        $originalProduct->site_id = 1;
        $originalProduct->name = 'product';

        $this->repository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalProduct);

        $this->repository
            ->shouldReceive('findBySlugAndSite')
            ->once()
            ->with('product-copy', 1)
            ->andReturn(null);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;
        $newProduct->name = 'product';

        $this->imageUploadService
            ->shouldNotReceive('duplicate');

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newProduct);

        $this->setDuplicateExpectations();
        $this->setCloneHistoryExpectations($originalProduct, $newProduct, 1, 2);

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Product::class, $result['product']);
        $this->assertEquals('product', $result['product']->name);
    }

    public function testDuplicateProductHandlesImageDuplicationFailure(): void
    {
        $originalProduct = Mockery::mock(Product::class)->makePartial();
        $originalProduct->id = 1;
        $originalProduct->site_id = 1;
        $originalProduct->name = 'Product';
        $originalProduct->image = 'products/test.jpg';

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
            ->shouldReceive('findBySlugAndSite')
            ->once()
            ->with('product-copy', 1)
            ->andReturn(null);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;

        $this->setCloneHistoryExpectations($originalProduct, $newProduct, 1, 2);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['image'] === null;
            }))
            ->andReturn($newProduct);

        $this->setDuplicateExpectations();

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Product::class, $result['product']);
    }

    public function testDuplicateProductWithBasicData()
    {
        $original = Mockery::mock(Product::class)->makePartial();
        $original->id = 1;
        $original->name = 'Original Product';
        $original->site_id = 1;
        $original->price = 99.99;

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($original);

        $this->repository->shouldReceive('findBySlugAndSite')
            ->with('original-product-copy', 1)
            ->once()
            ->andReturn(null);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;
        $newProduct->name = 'Original Product (Copy)';

        $this->setCloneHistoryExpectations($original, $newProduct, 1, 2);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['name'] === 'Original Product (Copy)'
                    && $data['price'] === 99.99
                    && $data['slug'] === 'original-product-copy'
                    && $data['status'] === 'draft';
            }))
            ->andReturn($newProduct);

        $this->repository->shouldReceive('getImages')->with(1)->andReturn(new Collection([]));
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->with(1)->andReturn(new Collection([]));
        $this->repository->shouldReceive('getVariants')->with(1)->andReturn(new Collection([]));
        $this->repository->shouldReceive('getSpecifications')->with(1)->andReturn(new Collection([]));

        $result = $this->service->handle(1);

        $this->assertEquals('Original Product (Copy)', $result['product']->name);
    }

    public function testDuplicateProductWithCustomName()
    {
        $original = Mockery::mock(Product::class)->makePartial();
        $original->id = 1;
        $original->site_id = 1;
        $original->name = 'Product';

        $this->repository->shouldReceive('find')->with(1)->andReturn($original);
        $this->repository->shouldReceive('findBySlugAndSite')->with('custom-name', 1)->andReturn(null);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;
        $newProduct->name = 'Custom Name';

        $this->setCloneHistoryExpectations($original, $newProduct, 1, 2);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['name'] === 'Custom Name';
            }))
            ->andReturn($newProduct);

        $this->repository->shouldReceive('getImages')->andReturn(new Collection([]));
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->andReturn(new Collection([]));
        $this->repository->shouldReceive('getVariants')->andReturn(new Collection([]));
        $this->repository->shouldReceive('getSpecifications')->andReturn(new Collection([]));

        $result = $this->service->handle(1, 'Custom Name');

        $this->assertEquals('Custom Name', $result['product']->name);
    }

    public function testDuplicateProductWithImage()
    {
        $original = Mockery::mock(Product::class)->makePartial();
        $original->id = 1;
        $original->site_id = 1;
        $original->name = 'Product';
        $original->image = 'products/original.jpg';

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($original);
        $this->repository->shouldReceive('findBySlugAndSite')
            ->once()
            ->with('product-copy', 1)
            ->andReturn(null);

        $this->imageUploadService->shouldReceive('duplicate')
            ->with('products/original.jpg')
            ->once()
            ->andReturn('products/original-copy.jpg');

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;

        $this->setCloneHistoryExpectations($original, $newProduct, 1, 2);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['image'] === 'products/original-copy.jpg';
            }))
            ->andReturn($newProduct);

        $this->repository->shouldReceive('getImages')->andReturn(new Collection([]));
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->andReturn(new Collection([]));
        $this->repository->shouldReceive('getVariants')->andReturn(new Collection([]));
        $this->repository->shouldReceive('getSpecifications')->andReturn(new Collection([]));

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Product::class, $result['product']);
    }

    public function testDuplicateProductWithAllRelations()
    {
        $original = Mockery::mock(Product::class)->makePartial();
        $original->id = 1;
        $original->name = 'Product';
        $original->site_id = 1;

        $images = new Collection([
            new ProductImage(['url' => 'img1.jpg', 'alt' => 'Alt 1', 'is_primary' => true, 'sort_order' => 0]),
        ]);

        $merchants = new Collection([
            ['name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 79.99, 'is_available' => true],
        ]);

        $variants = new Collection([
            new ProductVariant(['sku' => 'VAR-001', 'attributes' => ['color' => 'Red'], 'price_modifier' => 0, 'is_active' => true]),
        ]);

        $specifications = new Collection([
            new ProductSpecification(['category' => 'Tech', 'key' => 'Weight', 'value' => '1kg', 'sort_order' => 0]),
        ]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($original);
        $this->repository->shouldReceive('findBySlugAndSite')
            ->once()
            ->with('product-copy', 1)
            ->andReturn(null);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;

        $this->setCloneHistoryExpectations($original, $newProduct, 1, 2);;

        $this->repository->shouldReceive('create')->once()->andReturn($newProduct);

        $this->repository->shouldReceive('getImages')->with(1)->andReturn($images);
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->with(1)->andReturn($merchants);
        $this->repository->shouldReceive('getVariants')->with(1)->andReturn($variants);
        $this->repository->shouldReceive('getSpecifications')->with(1)->andReturn($specifications);

        $this->imageUploadService->shouldReceive('duplicate')
            ->with('img1.jpg')
            ->andReturn('img1-copy.jpg');

        $this->repository->shouldReceive('syncImages')
            ->once()
            ->with(2, Mockery::on(function($data) {
                return count($data) === 1 && $data[0]['url'] === 'img1-copy.jpg';
            }));

        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->with(2, Mockery::on(function($data) {
                return count($data) === 1 && $data[0]['name'] === 'Amazon';
            }));

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->with(2, Mockery::on(function($data) {
                return count($data) === 1 && $data[0]['sku'] === 'VAR-001-COPY';
            }));

        $this->repository->shouldReceive('syncSpecifications')
            ->once()
            ->with(2, Mockery::on(function($data) {
                return count($data) === 1 && $data[0]['key'] === 'Weight';
            }));

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Product::class, $result['product']);
    }

    public function testDuplicateProductHandlesSlugCollision()
    {
        $original = Mockery::mock(Product::class)->makePartial();
        $original->id = 1;
        $original->name = 'Product';
        $original->site_id = 1;

        $this->repository->shouldReceive('find')->with(1)->andReturn($original);

        // First attempt - collision
        $this->repository->shouldReceive('findBySlugAndSite')
            ->once()
            ->with('product-copy', 1)
            ->andReturn(new Product(['slug' => 'product-copy']));

        // Second attempt - collision
        $this->repository->shouldReceive('findBySlugAndSite')
            ->once()
            ->with('product-copy-1', 1)
            ->andReturn(new Product(['slug' => 'product-copy-1']));

        // Third attempt - available
        $this->repository->shouldReceive('findBySlugAndSite')
            ->once()
            ->with('product-copy-2', 1)
            ->andReturn(null);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;

        $this->setCloneHistoryExpectations($original, $newProduct, 1, 2);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['slug'] === 'product-copy-2';
            }))
            ->andReturn($newProduct);

        $this->repository->shouldReceive('getImages')->andReturn(new Collection([]));
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->andReturn(new Collection([]));
        $this->repository->shouldReceive('getVariants')->andReturn(new Collection([]));
        $this->repository->shouldReceive('getSpecifications')->andReturn(new Collection([]));

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Product::class, $result['product']);
    }

    public function testDuplicateProductThrowsExceptionWhenNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Product not found');

        $this->service->handle(999);
    }

    public function testDuplicateProductToAnotherSite(): void
    {
        $original = Mockery::mock(Product::class)->makePartial();
        $original->id = 1;
        $original->name = 'Product';
        $original->site_id = 1;

        $this->repository->shouldReceive('find')->with(1)->andReturn($original);

        $this->repository->shouldReceive('findBySlugAndSite')
            ->with('product-copy', 2)
            ->andReturn(null);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;
        $newProduct->site_id = 2;

        $this->setCloneHistoryExpectations($original, $newProduct, 1, 2, 'cloned', 1, 2);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['site_id'] === 2;
            }))
            ->andReturn($newProduct);

        $this->setDuplicateExpectations();

        $result = $this->service->handle(1, 'Product Copy', 2);

        $this->assertInstanceOf(Product::class, $result['product']);
    }

    public function testDuplicateProductWithSelectiveRelations(): void
    {
        $original = Mockery::mock(Product::class)->makePartial();
        $original->id = 1;
        $original->name = 'Product';
        $original->site_id = 1;

        $images = new Collection([new ProductImage(['url' => 'img.jpg'])]);
        $merchants = new Collection([new ProductMerchant(['name' => 'Amazon'])]);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;

        $this->setCloneHistoryExpectations($original, $newProduct, 1, 2);

        $this->repository->shouldReceive('find')->andReturn($original);
        $this->repository->shouldReceive('findBySlugAndSite')->andReturn(null);
        $this->repository->shouldReceive('create')->andReturn($newProduct);

        $this->repository->shouldReceive('getImages')->andReturn($images);
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->andReturn($merchants);
        $this->repository->shouldReceive('getVariants')->andReturn(new Collection([]));
        $this->repository->shouldReceive('getSpecifications')->andReturn(new Collection([]));

        // Only images should be synced
        $this->repository->shouldReceive('syncImages')->once();
        $this->repository->shouldNotReceive('syncMerchants');
        $this->repository->shouldNotReceive('syncVariants');
        $this->repository->shouldNotReceive('syncSpecifications');

        $this->imageUploadService->shouldReceive('duplicate')->andReturn('img-copy.jpg');

        $cloneRelations = [
            'images' => true,
            'merchants' => false,
            'variants' => false,
            'specifications' => false,
        ];

        $result = $this->service->handle(1, null, null, $cloneRelations);

        $this->assertInstanceOf(Product::class, $result['product']);
    }

    public function testDuplicateProductWithVariantImages()
    {
        $original = Mockery::mock(Product::class)->makePartial();
        $original->id = 1;
        $original->name = 'Product';
        $original->site_id = 1;

        $variantImage1 = new ProductImage(['url' => 'var-img1.jpg', 'alt' => 'Var 1', 'is_primary' => true, 'sort_order' => 0]);
        $variantImage2 = new ProductImage(['url' => 'var-img2.jpg', 'alt' => 'Var 2', 'is_primary' => false, 'sort_order' => 1]);

        $variant = new ProductVariant([
            'sku' => 'VAR-001',
            'attributes' => ['color' => 'Red'],
            'price_modifier' => 0,
            'is_active' => true
        ]);
        $variant->setRelation('images', collect([$variantImage1, $variantImage2]));

        $variants = new Collection([$variant]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($original);
        $this->repository->shouldReceive('findBySlugAndSite')->andReturn(null);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;

        $this->setCloneHistoryExpectations($original, $newProduct, 1, 2);
        $this->repository->shouldReceive('create')->once()->andReturn($newProduct);

        $this->repository->shouldReceive('getImages')->with(1)->andReturn(new Collection([]));
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->with(1)->andReturn(new Collection([]));
        $this->repository->shouldReceive('getVariants')->with(1)->andReturn($variants);
        $this->repository->shouldReceive('getSpecifications')->with(1)->andReturn(new Collection([]));

        $this->imageUploadService->shouldReceive('duplicate')
            ->with('var-img1.jpg')
            ->andReturn('var-img1-copy.jpg');

        $this->imageUploadService->shouldReceive('duplicate')
            ->with('var-img2.jpg')
            ->andReturn('var-img2-copy.jpg');

        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->with(2, Mockery::on(function($data) {
                return count($data) === 1
                    && $data[0]['sku'] === 'VAR-001-COPY'
                    && count($data[0]['images']) === 2
                    && $data[0]['images'][0]['url'] === 'var-img1-copy.jpg'
                    && $data[0]['images'][1]['url'] === 'var-img2-copy.jpg';
            }))
            ->andReturn([1]);

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Product::class, $result['product']);
    }

    public function testDuplicateProductWithVariantMerchants()
    {
        $original = Mockery::mock(Product::class)->makePartial();
        $original->id = 1;
        $original->name = 'Product';
        $original->site_id = 1;

        $variant = new ProductVariant([
            'id' => 1,
            'sku' => 'VAR-001',
            'price' => 100
        ]);
        $variant->setRelation('images', collect([]));

        $merchants = new Collection([
            [
                'name' => 'Amazon',
                'url' => 'https://amazon.com',
                'price' => 105,
                'override_price' => true,
                'override_sale_price' => false,
                'variant_id' => 1,
                'variant_sku' => 'AMZN-VAR-001',
                'is_available' => true,
                'variant' => [
                    'id' => 1,
                    'sku' => 'VAR-001'
                ]
            ]
        ]);

        $this->repository->shouldReceive('find')->with(1)->andReturn($original);
        $this->repository->shouldReceive('findBySlugAndSite')->andReturn(null);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;

        $this->setCloneHistoryExpectations($original, $newProduct, 1, 2);
        $this->repository->shouldReceive('create')->once()->andReturn($newProduct);

        $this->repository->shouldReceive('getImages')->with(1)->andReturn(new Collection([]));
        $this->repository->shouldReceive('getVariants')->with(1)->andReturn(collect([$variant]));
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->with(1)->andReturn($merchants);
        $this->repository->shouldReceive('getSpecifications')->with(1)->andReturn(new Collection([]));

        // Should create variant first, returning new ID
        $this->repository->shouldReceive('syncVariants')
            ->once()
            ->with(2, Mockery::on(function($data) {
                return count($data) === 1 && $data[0]['sku'] === 'VAR-001-COPY';
            }))
            ->andReturn([2]); // New variant ID

        // Should sync merchants with mapped variant ID
        $this->repository->shouldReceive('syncMerchants')
            ->once()
            ->with(2, Mockery::on(function($data) {
                return count($data) === 1
                    && $data[0]['variant_id'] === 2 // Mapped to new variant
                    && $data[0]['override_price'] === true
                    && $data[0]['variant_sku'] === 'AMZN-VAR-001';
            }))
            ->andReturn([1]);

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Product::class, $result['product']);
    }

    private function setDuplicateExpectations() {
        $this->repository
            ->shouldReceive('getImages')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->repository
            ->shouldReceive('getProductMerchantsWithDetails')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->repository
            ->shouldReceive('getVariants')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $this->repository
            ->shouldReceive('getSpecifications')
            ->with(1)
            ->once()
            ->andReturn(collect([]));
    }

    public function testCloneProductReturnsDetailedResults()
    {
        $originalProduct = Mockery::mock(Product::class)->makePartial();
        $originalProduct->id = 1;
        $originalProduct->name = 'iPhone';
        $originalProduct->site_id = 1;
        $originalProduct->image = 'products/iphone.jpg';

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;

        $this->repository->shouldReceive('find')->with(1)->andReturn($originalProduct);
        $this->repository->shouldReceive('findBySlugAndSite')->andReturn(null);
        $this->imageUploadService->shouldReceive('duplicate')->andReturn('products/iphone-copy.jpg');
        $this->repository->shouldReceive('create')->andReturn($newProduct);
        $this->setCloneHistoryExpectations($originalProduct, $newProduct, 1, 2);
        $this->setDuplicateExpectations();

        $result = $this->service->handle(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('product', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('original_product_id', $result);
        $this->assertArrayHasKey('cross_site', $result);
        $this->assertContains('main_image', $result['results']['success']);
        $this->assertContains('product_created', $result['results']['success']);
        $this->assertArrayHasKey('relations', $result['results']);
    }

    public function testCloneProductTracksRelationResults()
    {
        $originalProduct = Mockery::mock(Product::class)->makePartial();
        $originalProduct->id = 1;
        $originalProduct->name = 'Product';
        $originalProduct->site_id = 1;

        $images = new Collection([
            new ProductImage(['url' => 'img1.jpg', 'alt' => 'Alt 1']),
            new ProductImage(['url' => 'img2.jpg', 'alt' => 'Alt 2']),
        ]);

        $merchants = new Collection([
            ['name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 99.99],
        ]);

        $variants = new Collection([
            new ProductVariant(['sku' => 'VAR-001', 'price' => 100]),
        ]);

        $specifications = new Collection([
            new ProductSpecification(['key' => 'Weight', 'value' => '1kg']),
            new ProductSpecification(['key' => 'Color', 'value' => 'Red']),
        ]);

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;

        $this->repository->shouldReceive('find')->with(1)->andReturn($originalProduct);
        $this->repository->shouldReceive('findBySlugAndSite')->andReturn(null);
        $this->repository->shouldReceive('create')->andReturn($newProduct);
        $this->setCloneHistoryExpectations($originalProduct, $newProduct, 1, 2);

        $this->repository->shouldReceive('getImages')->with(1)->andReturn($images);
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->with(1)->andReturn($merchants);
        $this->repository->shouldReceive('getVariants')->with(1)->andReturn($variants);
        $this->repository->shouldReceive('getSpecifications')->with(1)->andReturn($specifications);

        // One image fails
        $this->imageUploadService->shouldReceive('duplicate')
            ->with('img1.jpg')->andReturn('img1-copy.jpg');
        $this->imageUploadService->shouldReceive('duplicate')
            ->with('img2.jpg')->andReturn(null);

        $this->repository->shouldReceive('syncImages')->once();
        $this->repository->shouldReceive('syncMerchants')->once();
        $this->repository->shouldReceive('syncVariants')->once()->andReturn([1]);
        $this->repository->shouldReceive('syncSpecifications')->once();

        $result = $this->service->handle(1);

        $this->assertEquals(1, $result['results']['relations']['images']['cloned']);
        $this->assertEquals(1, $result['results']['relations']['images']['failed']);
        $this->assertEquals(1, $result['results']['relations']['merchants']['cloned']); //todo here
        $this->assertEquals(1, $result['results']['relations']['variants']['cloned']);
        $this->assertEquals(2, $result['results']['relations']['specifications']['cloned']);
    }

    public function testCloneProductWithSelectiveRelations()
    {
        $originalProduct = Mockery::mock(Product::class)->makePartial();
        $originalProduct->id = 1;
        $originalProduct->name = 'Product';
        $originalProduct->site_id = 1;

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;

        $images = new Collection([new ProductImage(['url' => 'img.jpg'])]);
        $merchants = new Collection([['name' => 'Amazon']]);

        $this->repository->shouldReceive('find')->andReturn($originalProduct);
        $this->repository->shouldReceive('findBySlugAndSite')->andReturn(null);
        $this->repository->shouldReceive('create')->andReturn($newProduct);
        $this->setCloneHistoryExpectations($originalProduct, $newProduct, 1, 2);

        $this->repository->shouldReceive('getImages')->andReturn($images);
        $this->repository->shouldReceive('getProductMerchantsWithDetails')->andReturn($merchants);
        $this->repository->shouldReceive('getVariants')->never();
        $this->repository->shouldReceive('getSpecifications')->never();

        $this->imageUploadService->shouldReceive('duplicate')->andReturn('img-copy.jpg');
        $this->repository->shouldReceive('syncImages')->once();
        $this->repository->shouldReceive('syncMerchants')->once();

        $result = $this->service->handle(1, null, null, [
            'images' => true,
            'merchants' => true,
            'variants' => false,
            'specifications' => false,
        ]);

        $this->assertGreaterThan(0, $result['results']['relations']['images']['cloned']);
        $this->assertGreaterThan(0, $result['results']['relations']['merchants']['cloned']);
        $this->assertEquals(0, $result['results']['relations']['variants']['cloned']);
        $this->assertEquals(0, $result['results']['relations']['specifications']['cloned']); //todo here
    }

    public function testCloneProductCrossSiteTracking()
    {
        $originalProduct = Mockery::mock(Product::class)->makePartial();
        $originalProduct->id = 1;
        $originalProduct->name = 'Product';
        $originalProduct->site_id = 1;

        $newProduct = Mockery::mock(Product::class)->makePartial();
        $newProduct->id = 2;
        $newProduct->site_id = 2;

        $this->repository->shouldReceive('find')->with(1)->andReturn($originalProduct);
        $this->repository->shouldReceive('findBySlugAndSite')->with(Mockery::any(), 2)->andReturn(null);
        $this->repository->shouldReceive('create')->andReturn($newProduct);
        $this->setCloneHistoryExpectations($originalProduct, $newProduct, 1, 2, 'cloned', 1, 2);
        $this->setDuplicateExpectations();

        $result = $this->service->handle(1, 'Product Copy', 2);

        $this->assertTrue($result['cross_site']);
        $this->assertContains('cross_site_clone_history', $result['results']['success']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}