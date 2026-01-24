<?php

namespace App\Tests\Unit\Repositories;

use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Repositories\Product\VariantRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class VariantRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private VariantRepository $repository;

    public function testSearchReturnsVariants(): void
    {
        $product = $this->createProduct();
        $this->createProductVariant($product->id, ['sku' => 'TEST-SKU']);

        $criteria = new SearchCriteria();
        $result = $this->repository->search($criteria);

        $this->assertGreaterThan(0, count($result->getData()));
    }

    public function testSearchIncludesRelationships(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $criteria = new SearchCriteria();
        $result = $this->repository->search($criteria);

        $foundVariant = $result->getData()[0];
        $this->assertNotEmpty($foundVariant['product']);
        $this->assertNotEmpty($foundVariant['images']);
    }

    public function testGetByProduct(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $this->createProductVariant($product1->id, ['sku' => 'P1-VAR-001']);
        $this->createProductVariant($product1->id, ['sku' => 'P1-VAR-002']);
        $this->createProductVariant($product2->id, ['sku' => 'P2-VAR-001']);

        $variants = $this->repository->getByProduct($product1->id);

        $this->assertCount(2, $variants);
        foreach ($variants as $variant) {
            $this->assertEquals($product1->id, $variant->product_id);
        }
    }

    public function testGetByProductOrdersBySku(): void
    {
        $product = $this->createProduct();
        $this->createProductVariant($product->id, ['sku' => 'C-SKU']);
        $this->createProductVariant($product->id, ['sku' => 'A-SKU']);
        $this->createProductVariant($product->id, ['sku' => 'B-SKU']);

        $variants = $this->repository->getByProduct($product->id);
        $variants = $variants->toArray();

        $this->assertEquals('A-SKU', $variants[0]['sku']);
        $this->assertEquals('B-SKU', $variants[1]['sku']);
        $this->assertEquals('C-SKU', $variants[2]['sku']);
    }

    public function testGetByProductIncludesImages(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $variants = $this->repository->getByProduct($product->id);

        $this->assertCount(1, $variants);
        $this->assertNotEmpty($variants->first()->images);
    }

    public function testUpdate(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'OLD-SKU',
            'price' => 100,
            'is_active' => true
        ]);

        $updateData = [
            'sku' => 'NEW-SKU',
            'price' => 150,
            'is_active' => false
        ];

        $result = $this->repository->update($variant->id, $updateData);

        $this->assertInstanceOf(ProductVariant::class, $result);

        $updated = ProductVariant::find($variant->id);
        $this->assertEquals('NEW-SKU', $updated->sku);
        $this->assertEquals(150, $updated->price);
        $this->assertFalse($updated->is_active);
    }

    public function testUpdateReturnsFalseForNonExistent(): void
    {
        $result = $this->repository->update(9999, ['sku' => 'TEST']);

        $this->assertNull($result);
    }

    public function testUpdatePartialFields(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'ORIG-SKU',
            'price' => 100,
            'name' => 'Original Name'
        ]);

        $this->repository->update($variant->id, ['price' => 150]);

        $updated = ProductVariant::find($variant->id);
        $this->assertEquals('ORIG-SKU', $updated->sku);
        $this->assertEquals(150, $updated->price);
        $this->assertEquals('Original Name', $updated->name);
    }

    public function testDelete(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $result = $this->repository->delete($variant->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    }

    public function testDeleteReturnsFalseForNonExistent(): void
    {
        $result = $this->repository->delete(9999);

        $this->assertFalse($result);
    }

    public function testSyncVariantImagesReplacesExisting(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        // Create old images
        $this->createProductImage($product->id, [
            'variant_id' => $variant->id,
            'url' => 'old1.jpg'
        ]);
        $this->createProductImage($product->id, [
            'variant_id' => $variant->id,
            'url' => 'old2.jpg'
        ]);

        $newImages = [
            [
                'url' => 'new1.jpg',
                'alt' => 'New 1',
                'is_primary' => true,
                'sort_order' => 0
            ],
            [
                'url' => 'new2.jpg',
                'alt' => 'New 2',
                'is_primary' => false,
                'sort_order' => 1
            ]
        ];

        $this->repository->syncVariantImages($variant->id, $product->id, $newImages);

        // Old images should be gone
        $this->assertDatabaseMissing('product_images', [
            'variant_id' => $variant->id,
            'url' => 'old1.jpg'
        ]);

        // New images should exist
        $images = ProductImage::where('variant_id', $variant->id)
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        $this->assertCount(2, $images);
        $this->assertEquals('new1.jpg', $images[0]['url']);
        $this->assertTrue((bool)$images[0]['is_primary']);
        $this->assertEquals('new2.jpg', $images[1]['url']);
        $this->assertFalse((bool)$images[1]['is_primary']);
    }

    public function testSyncVariantImagesWithEmptyArray(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $this->repository->syncVariantImages($variant->id, $product->id, []);

        $count = ProductImage::where('variant_id', $variant->id)->count();
        $this->assertEquals(0, $count);
    }

    public function testSyncVariantImagesSetsProductId(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $images = [
            [
                'url' => 'test.jpg',
                'alt' => 'Test',
                'is_primary' => true,
                'sort_order' => 0
            ]
        ];

        $this->repository->syncVariantImages($variant->id, $product->id, $images);

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'url' => 'test.jpg'
        ]);
    }

    public function testSyncVariantImagesHandlesMissingOptionalFields(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $images = [
            ['url' => 'test.jpg']
        ];

        $this->repository->syncVariantImages($variant->id, $product->id, $images);

        $image = ProductImage::where('variant_id', $variant->id)->first();
        $this->assertNotNull($image);
        $this->assertEquals('test.jpg', $image->url);
        $this->assertNull($image->alt);
        $this->assertFalse((bool)$image->is_primary);
        $this->assertEquals(0, $image->sort_order);
    }

    public function testSyncVariantImagesHandlesSortOrder(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $images = [
            ['url' => 'img1.jpg', 'sort_order' => 2],
            ['url' => 'img2.jpg', 'sort_order' => 0],
            ['url' => 'img3.jpg', 'sort_order' => 1]
        ];

        $this->repository->syncVariantImages($variant->id, $product->id, $images);

        $sortedImages = ProductImage::where('variant_id', $variant->id)
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        $this->assertEquals('img2.jpg', $sortedImages[0]['url']);
        $this->assertEquals('img3.jpg', $sortedImages[1]['url']);
        $this->assertEquals('img1.jpg', $sortedImages[2]['url']);
    }

    public function testDeleteVariantImages(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $this->createProductImage($product->id, ['variant_id' => $variant->id]);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $this->repository->deleteVariantImages($variant->id);

        $count = ProductImage::where('variant_id', $variant->id)->count();
        $this->assertEquals(0, $count);
    }

    public function testDeleteVariantImagesDoesNotAffectOtherVariants(): void
    {
        $product = $this->createProduct();
        $variant1 = $this->createProductVariant($product->id);
        $variant2 = $this->createProductVariant($product->id);

        $this->createProductImage($product->id, ['variant_id' => $variant1->id]);
        $this->createProductImage($product->id, ['variant_id' => $variant2->id]);

        $this->repository->deleteVariantImages($variant1->id);

        $count1 = ProductImage::where('variant_id', $variant1->id)->count();
        $count2 = ProductImage::where('variant_id', $variant2->id)->count();

        $this->assertEquals(0, $count1);
        $this->assertEquals(1, $count2);
    }

    public function testSearchWithFilters(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $this->createProductVariant($product1->id, ['sku' => 'ACTIVE', 'is_active' => true]);
        $this->createProductVariant($product2->id, ['sku' => 'INACTIVE', 'is_active' => false]);

        $criteria = new SearchCriteria();
        $result = $this->repository->search($criteria);

        $this->assertGreaterThanOrEqual(2, count($result->getData()));
    }

    public function testGetByProductReturnsEmptyCollectionForNoVariants(): void
    {
        $product = $this->createProduct();

        $variants = $this->repository->getByProduct($product->id);

        $this->assertCount(0, $variants);
    }

    public function testSyncVariantImagesPreservesOtherVariantImages(): void
    {
        $product = $this->createProduct();
        $variant1 = $this->createProductVariant($product->id);
        $variant2 = $this->createProductVariant($product->id);

        $this->createProductImage($product->id, [
            'variant_id' => $variant1->id,
            'url' => 'v1-img.jpg'
        ]);
        $this->createProductImage($product->id, [
            'variant_id' => $variant2->id,
            'url' => 'v2-img.jpg'
        ]);

        $newImages = [
            ['url' => 'v1-new.jpg', 'is_primary' => true, 'sort_order' => 0]
        ];

        $this->repository->syncVariantImages($variant1->id, $product->id, $newImages);

        // Variant 1 images should be replaced
        $this->assertDatabaseMissing('product_images', [
            'variant_id' => $variant1->id,
            'url' => 'v1-img.jpg'
        ]);
        $this->assertDatabaseHas('product_images', [
            'variant_id' => $variant1->id,
            'url' => 'v1-new.jpg'
        ]);

        // Variant 2 images should remain
        $this->assertDatabaseHas('product_images', [
            'variant_id' => $variant2->id,
            'url' => 'v2-img.jpg'
        ]);
    }

    public function testUpdateOnlyChangesSpecifiedFields(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'ORIG-SKU',
            'name' => 'Original',
            'price' => 100,
            'sale_price' => 90,
            'is_active' => true
        ]);

        $this->repository->update($variant->id, ['name' => 'Updated Name']);

        $updated = ProductVariant::find($variant->id);
        $this->assertEquals('ORIG-SKU', $updated->sku);
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals(100, $updated->price);
        $this->assertEquals(90, $updated->sale_price);
        $this->assertTrue($updated->is_active);
    }

    public function testCreate(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'NEW-SKU',
            'name' => 'New Variant',
            'price' => 99.99,
            'sale_price' => 89.99,
            'is_active' => true,
            'attributes' => [
                'size' => 'XS'
            ]
        ];

        $result = $this->repository->create($data);

        $this->assertInstanceOf(ProductVariant::class, $result);
        $this->assertEquals('NEW-SKU', $result->sku);
        $this->assertEquals('New Variant', $result->name);
        $this->assertEquals(99.99, $result->price);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'NEW-SKU',
            'name' => 'New Variant',
            'price' => 99.99,
            'sale_price' => 89.99,
            'is_active' => true
        ]);
    }

    public function testCreateWithMinimalData(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'MIN-SKU',
            'price' => 50.00,
            'attributes' => [
                'size' => 'XS'
            ]
        ];

        $result = $this->repository->create($data);

        $this->assertInstanceOf(ProductVariant::class, $result);
        $this->assertEquals('MIN-SKU', $result->sku);
        $this->assertEquals(50.00, $result->price);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'MIN-SKU',
            'price' => 50.00
        ]);
    }

    public function testCreateWithPriceModifier(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'MOD-SKU',
            'price' => 100.00,
            'price_modifier' => 15.00,
            'attributes' => [
                'size' => 'XS'
            ]
        ];

        $result = $this->repository->create($data);

        $this->assertInstanceOf(ProductVariant::class, $result);
        $this->assertEquals(15.00, $result->price_modifier);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'MOD-SKU',
            'price_modifier' => 15.00
        ]);
    }

    public function testCreateReturnsVariantWithId(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'ID-TEST-SKU',
            'price' => 75.00,
            'attributes' => [
                'size' => 'XS'
            ]
        ];

        $result = $this->repository->create($data);

        $this->assertNotNull($result->id);
        $this->assertGreaterThan(0, $result->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new VariantRepository();
    }

}