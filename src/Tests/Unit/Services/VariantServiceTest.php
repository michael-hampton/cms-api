<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Collection;
use App\Models\ProductVariant;
use App\Repositories\Product\VariantRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use App\Services\Product\VariantService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class VariantServiceTest extends FunctionalTestCase
{
    protected VariantRepository $repository;
    protected VariantService $service;

    public function testSearchVariants(): void
    {
        $criteria = new SearchCriteria();
        $expectedResult = new PaginatedResult([], 0, 1, 10);

        $this->repository->shouldReceive('search')
            ->once()
            ->with($criteria)
            ->andReturn($expectedResult);

        $result = $this->service->searchVariants($criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function testGetVariant(): void
    {
        $variant = new ProductVariant([
            'id' => 1,
            'sku' => 'TEST-SKU',
            'name' => 'Test Variant'
        ]);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1, ['product', 'images', 'merchants'])
            ->andReturn($variant);

        $result = $this->service->getVariant(1);

        $this->assertNotNull($result);
        $this->assertEquals('TEST-SKU', $result->sku);
    }

    public function testGetVariantReturnsNullWhenNotFound(): void
    {
        $this->repository->shouldReceive('find')
            ->once()
            ->with(999, ['product', 'images', 'merchants'])
            ->andReturn(null);

        $result = $this->service->getVariant(999);

        $this->assertNull($result);
    }

    public function testUpdateVariant(): void
    {
        $variant = Mockery::mock(ProductVariant::class);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($variant);

        $updateData = [
            'sku' => 'NEW-SKU',
            'price' => 150
        ];

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, $updateData)
            ->andReturn($variant);

        $result = $this->service->updateVariant(1, $updateData);

        $this->assertInstanceOf(ProductVariant::class, $result);
    }

    public function testUpdateVariantReturnsFalseWhenNotFound(): void
    {
        $this->repository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->updateVariant(999, ['sku' => 'TEST']);

        $this->assertNull($result);
    }

    public function testDeleteVariant(): void
    {
        $variant = new ProductVariant(['id' => 1, 'sku' => 'TEST-SKU']);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($variant);

        $this->repository->shouldReceive('deleteVariantImages')
            ->once()
            ->with(1);

        $this->repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->deleteVariant(1);

        $this->assertTrue($result);
    }

    public function testDeleteVariantReturnsFalseWhenNotFound(): void
    {
        $this->repository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->deleteVariant(999);

        $this->assertFalse($result);
    }

    public function testDeleteVariantDeletesImagesFirst(): void
    {
        $variant = new ProductVariant(['id' => 1, 'sku' => 'TEST-SKU']);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($variant);

        $this->repository->shouldReceive('deleteVariantImages')
            ->once()
            ->with(1)
            ->ordered();

        $this->repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true)
            ->ordered();

        $result = $this->service->deleteVariant(1);

        $this->assertTrue($result);
    }

    public function testUpdateVariantImages(): void
    {
        $variant = new ProductVariant([
            'id' => 1,
            'product_id' => 10,
            'sku' => 'TEST-SKU'
        ]);

        $images = [
            [
                'url' => 'img1.jpg',
                'alt' => 'Image 1',
                'is_primary' => true,
                'sort_order' => 0
            ]
        ];

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($variant);

        $this->repository->shouldReceive('syncVariantImages')
            ->once()
            ->with(1, 10, $images);

        $result = $this->service->updateVariantImages(1, $images);

        $this->assertTrue($result);
    }

    public function testUpdateVariantImagesReturnsFalseWhenNotFound(): void
    {
        $this->repository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->updateVariantImages(999, []);

        $this->assertFalse($result);
    }

    public function testUpdateVariantImagesWithEmptyArray(): void
    {
        $variant = new ProductVariant([
            'id' => 1,
            'product_id' => 10,
            'sku' => 'TEST-SKU'
        ]);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($variant);

        $this->repository->shouldReceive('syncVariantImages')
            ->once()
            ->with(1, 10, []);

        $result = $this->service->updateVariantImages(1, []);

        $this->assertTrue($result);
    }

    public function testToggleVariantStatus(): void
    {
        $variant = new ProductVariant([
            'id' => 1,
            'sku' => 'TEST-SKU',
            'is_active' => true
        ]);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($variant);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, ['is_active' => false])
            ->andReturn($variant);

        $result = $this->service->toggleVariantStatus(1);

        $this->assertNotNull($result);
        $this->assertFalse($result['is_active']);
    }

    public function testToggleVariantStatusFromFalseToTrue(): void
    {
        $variant = new ProductVariant([
            'id' => 1,
            'sku' => 'TEST-SKU',
            'is_active' => false
        ]);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($variant);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, ['is_active' => true])
            ->andReturn($variant);

        $result = $this->service->toggleVariantStatus(1);

        $this->assertNotNull($result);
        $this->assertTrue($result['is_active']);
    }

    public function testToggleVariantStatusReturnsNullWhenNotFound(): void
    {
        $this->repository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->toggleVariantStatus(999);

        $this->assertNull($result);
    }

    public function testGetVariantsByProduct(): void
    {
        $variants = new Collection([
            new ProductVariant(['id' => 1, 'sku' => 'VAR-001']),
            new ProductVariant(['id' => 2, 'sku' => 'VAR-002'])
        ]);

        $this->repository->shouldReceive('getByProduct')
            ->once()
            ->with(10)
            ->andReturn($variants);

        $result = $this->service->getVariantsByProduct(10);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    public function testGetVariantsByProductReturnsEmptyCollection(): void
    {
        $this->repository->shouldReceive('getByProduct')
            ->once()
            ->with(10)
            ->andReturn(new Collection([]));

        $result = $this->service->getVariantsByProduct(10);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function testUpdateVariantWithPartialData(): void
    {
        $variant = new ProductVariant([
            'id' => 1,
            'sku' => 'ORIG-SKU',
            'price' => 100,
            'is_active' => true
        ]);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($variant);

        $updateData = ['price' => 150];

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, $updateData)
            ->andReturn($variant);

        $result = $this->service->updateVariant(1, $updateData);

        $this->assertInstanceOf(ProductVariant::class, $result);
    }

    public function testUpdateVariantImagesUsesCorrectProductId(): void
    {
        $variant = new ProductVariant([
            'id' => 5,
            'product_id' => 42,
            'sku' => 'TEST-SKU'
        ]);

        $images = [['url' => 'test.jpg', 'alt' => 'Test', 'is_primary' => true, 'sort_order' => 0]];

        $this->repository->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn($variant);

        $this->repository->shouldReceive('syncVariantImages')
            ->once()
            ->with(5, 42, $images);

        $result = $this->service->updateVariantImages(5, $images);

        $this->assertTrue($result);
    }

    public function testDeleteVariantCallsDeleteImagesBeforeDelete(): void
    {
        $variant = new ProductVariant(['id' => 1, 'sku' => 'TEST-SKU']);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($variant);

        // Verify order of operations
        $this->repository->shouldReceive('deleteVariantImages')
            ->once()
            ->with(1)
            ->globally()
            ->ordered();

        $this->repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true)
            ->globally()
            ->ordered();

        $result = $this->service->deleteVariant(1);

        $this->assertTrue($result);
    }

    public function testSearchVariantsPassesCriteria(): void
    {
        $criteria = new SearchCriteria();
        $criteria->setPage(2);
        $criteria->setPerPage(20);

        $expectedResult = new PaginatedResult([], 0, 2, 20);

        $this->repository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function ($arg) use ($criteria) {
                return $arg === $criteria;
            }))
            ->andReturn($expectedResult);

        $result = $this->service->searchVariants($criteria);

        $this->assertEquals(2, $result->getPage());
        $this->assertEquals(20, $result->getPerPage());
    }

    public function testCreateVariant(): void
    {
        $data = [
            'product_id' => 10,
            'sku' => 'NEW-SKU',
            'name' => 'New Variant',
            'price' => 100
        ];

        $createdVariant = new ProductVariant(array_merge(['id' => 1], $data));

        $this->repository->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($createdVariant);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1, ['product', 'images', 'merchants'])
            ->andReturn($createdVariant);

        $result = $this->service->createVariant($data);

        $this->assertInstanceOf(ProductVariant::class, $result);
        $this->assertEquals('NEW-SKU', $result->sku);
    }

    public function testCreateVariantWithImages(): void
    {
        $data = [
            'product_id' => 10,
            'sku' => 'NEW-SKU',
            'price' => 100,
            'images' => [
                [
                    'url' => 'img1.jpg',
                    'alt' => 'Image 1',
                    'is_primary' => true,
                    'sort_order' => 0
                ]
            ]
        ];

        $expectedData = [
            'product_id' => 10,
            'sku' => 'NEW-SKU',
            'price' => 100
        ];

        $createdVariant = new ProductVariant(array_merge(['id' => 1], $expectedData));

        $this->repository->shouldReceive('create')
            ->once()
            ->with($expectedData)
            ->andReturn($createdVariant);

        $this->repository->shouldReceive('syncVariantImages')
            ->once()
            ->with(1, 10, $data['images']);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1, ['product', 'images', 'merchants'])
            ->andReturn($createdVariant);

        $result = $this->service->createVariant($data);

        $this->assertInstanceOf(ProductVariant::class, $result);
    }

    public function testCreateVariantWithoutImages(): void
    {
        $data = [
            'product_id' => 10,
            'sku' => 'NO-IMG-SKU',
            'price' => 50
        ];

        $createdVariant = new ProductVariant(array_merge(['id' => 1], $data));

        $this->repository->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($createdVariant);

        $this->repository->shouldReceive('syncVariantImages')
            ->never();

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1, ['product', 'images', 'merchants'])
            ->andReturn($createdVariant);

        $result = $this->service->createVariant($data);

        $this->assertInstanceOf(ProductVariant::class, $result);
    }

    public function testCreateVariantWithEmptyImagesArray(): void
    {
        $data = [
            'product_id' => 10,
            'sku' => 'EMPTY-IMG-SKU',
            'price' => 75,
            'images' => []
        ];

        $expectedData = [
            'product_id' => 10,
            'sku' => 'EMPTY-IMG-SKU',
            'price' => 75
        ];

        $createdVariant = new ProductVariant(array_merge(['id' => 1], $expectedData));

        $this->repository->shouldReceive('create')
            ->once()
            ->with($expectedData)
            ->andReturn($createdVariant);

        $this->repository->shouldReceive('syncVariantImages')
            ->never();

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1, ['product', 'images', 'merchants'])
            ->andReturn($createdVariant);

        $result = $this->service->createVariant($data);

        $this->assertInstanceOf(ProductVariant::class, $result);
    }

    public function testCreateVariantReturnsWithRelationships(): void
    {
        $data = [
            'product_id' => 10,
            'sku' => 'REL-SKU',
            'price' => 100
        ];

        $createdVariant = new ProductVariant(array_merge(['id' => 1], $data));

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($createdVariant);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1, ['product', 'images', 'merchants'])
            ->andReturn($createdVariant);

        $result = $this->service->createVariant($data);

        $this->assertInstanceOf(ProductVariant::class, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(VariantRepository::class);
        $this->service = new VariantService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

}