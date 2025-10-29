<?php

namespace App\Tests\Unit\Repositories;

use App\Models\ProductImage;
use App\Models\ProductMerchant;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Repositories\ProductRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductRepository();
    }

    /** @test */
    public function test_it_can_search_products_with_relationships(): void
    {
        // Arrange
        $product = $this->createProduct();
        $this->createProductImage($product->id, ['is_primary' => true]);
        $this->createProductMerchant($product->id);
        $this->createProductVariant($product->id);
        $this->createProductSpecification($product->id);

        // Act
        $criteria = new SearchCriteria();
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertGreaterThan(0, count($result->getData()));
        $foundProduct = $result->getData()[0];

        $this->assertNotEmpty($foundProduct['images']);
        $this->assertNotEmpty($foundProduct['availableMerchants']);
    }

    /** @test */
    public function test_find_by_slug_returns_correct_product(): void
    {
        // Arrange
        $product = $this->createProduct(['slug' => 'unique-product-slug']);

        // Act
        $found = $this->repository->findBySlug('unique-product-slug');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($product->id, $found->id);
        $this->assertEquals('unique-product-slug', $found->slug);
    }

    /** @test */
    public function test_find_by_slug_returns_null_when_not_found(): void
    {
        // Act
        $found = $this->repository->findBySlug('non-existent-slug');

        // Assert
        $this->assertNull($found);
    }

    /** @test */
    public function test_find_by_slug_and_site_filters_by_site(): void
    {
        $site = $this->createSite();
        // Arrange
        $this->createProduct(['slug' => 'test-product', 'site_id' => $this->siteId]);
        $this->createProduct(['slug' => 'test-product', 'site_id' => $site->id]);

        // Act
        $found = $this->repository->findBySlugAndSite('test-product', $this->siteId);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($this->siteId, $found->site_id);
    }

    /** @test */
    public function test_sync_images_removes_old_and_adds_new(): void
    {
        // Arrange
        $product = $this->createProduct();

        // Add initial images
        $this->createProductImage($product->id, ['url' => 'old-1.jpg', 'sort_order' => 0]);
        $this->createProductImage($product->id, ['url' => 'old-2.jpg', 'sort_order' => 1]);

        $newImages = [
            [
                'url' => 'new-1.jpg',
                'alt' => 'New Image 1',
                'is_primary' => true,
                'sort_order' => 0
            ],
            [
                'url' => 'new-2.jpg',
                'alt' => 'New Image 2',
                'is_primary' => false,
                'sort_order' => 1
            ],
        ];

        // Act
        $this->repository->syncImages($product->id, $newImages);

        // Assert
        $images = ProductImage::where('product_id', $product->id)
            ->orderBy('sort_order')
            ->get();
        $images = $images->toArray();

        $this->assertCount(2, $images);
        $this->assertEquals('new-1.jpg', $images[0]['url']);
        $this->assertEquals('new-2.jpg', $images[1]['url']);
        $this->assertEquals(1, $images[0]['is_primary']);
        $this->assertEquals(0, $images[1]['is_primary']);;

        // Old images should be gone
        $this->assertDatabaseMissing('product_images', [
            'product_id' => $product->id,
            'url' => 'old-1.jpg'
        ]);
    }

    /** @test */
    public function test_sync_images_handles_variant_images(): void
    {
        // Arrange
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $images = [
            [
                'url' => 'variant-image.jpg',
                'alt' => 'Variant Image',
                'is_primary' => false,
                'sort_order' => 0,
                'variant_id' => $variant->id
            ],
        ];

        // Act
        $this->repository->syncImages($product->id, $images);

        // Assert
        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'url' => 'variant-image.jpg'
        ]);
    }

    /** @test */
    public function test_get_images_returns_sorted_images(): void
    {
        // Arrange
        $product = $this->createProduct();
        $this->createProductImage($product->id, ['url' => 'image-2.jpg', 'sort_order' => 2]);
        $this->createProductImage($product->id, ['url' => 'image-0.jpg', 'sort_order' => 0]);
        $this->createProductImage($product->id, ['url' => 'image-1.jpg', 'sort_order' => 1]);

        // Act
        $images = $this->repository->getImages($product->id);
        $images = $images->toArray();

        // Assert
        $this->assertCount(3, $images);
        $this->assertEquals('image-0.jpg', $images[0]['url']);
        $this->assertEquals('image-1.jpg', $images[1]['url']);
        $this->assertEquals('image-2.jpg', $images[2]['url']);
    }

    /** @test */
    public function test_sync_merchants_updates_existing_and_creates_new(): void
    {
        // Arrange
        $product = $this->createProduct();

        // Create existing merchant
        $existingMerchant = $this->createProductMerchant($product->id, [
            'name' => 'Amazon',
            'price' => 99.99
        ]);

        $merchants = [
            [
                'name' => 'Amazon',  // Existing - should be kept
                'url' => 'https://amazon.com/product',
                'price' => 89.99,  // Price changed
                'is_available' => true
            ],
            [
                'name' => 'eBay',  // New
                'url' => 'https://ebay.com/product',
                'price' => 95.00,
                'is_available' => true
            ],
        ];

        // Act
        $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

        // Assert
        $this->assertCount(2, $merchantIds);
        $this->assertContains($existingMerchant->id, $merchantIds);

        $allMerchants = ProductMerchant::where('product_id', $product->id)->get();
        $this->assertCount(2, $allMerchants);

        $amazon = $allMerchants->where('name', 'Amazon')->first();
        $ebay = $allMerchants->where('name', 'eBay')->first();

        $this->assertEquals($existingMerchant->id, $amazon->id);
        $this->assertNotNull($ebay);
    }

    /** @test */
    public function test_sync_merchants_handles_variant_specific_merchants(): void
    {
        // Arrange
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $merchants = [
            [
                'name' => 'Amazon',
                'url' => 'https://amazon.com/product',
                'price' => 99.99,
                'is_available' => true,
                'variant_id' => $variant->id
            ],
        ];

        // Act
        $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

        // Assert
        $this->assertCount(1, $merchantIds);
        $this->assertDatabaseHas('product_merchants', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'name' => 'Amazon'
        ]);
    }

    /** @test */
    public function test_record_merchant_price_history_creates_record(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant = $this->createProductMerchant($product->id);

        // Act
        $history = $this->repository->recordMerchantPriceHistory($product->id, $merchant->id, 99.99);

        // Assert
        $this->assertNotNull($history);
        $this->assertEquals($product->id, $history->product_id);
        $this->assertEquals($merchant->id, $history->merchant_id);
        $this->assertEquals(99.99, $history->price);
        $this->assertDatabaseHas('product_price_history', [
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 99.99
        ]);
    }

    /** @test */
    public function test_record_merchant_price_history_rejects_negative_price(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant = $this->createProductMerchant($product->id);

        // Act
        $history = $this->repository->recordMerchantPriceHistory($product->id, $merchant->id, -10.00);

        // Assert
        $this->assertNull($history);
        $this->assertDatabaseMissing('product_price_history', [
            'product_id' => $product->id,
            'merchant_id' => $merchant->id
        ]);
    }

    /** @test */
    public function test_get_price_history_returns_all_for_product(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant1 = $this->createProductMerchant($product->id, ['name' => 'Amazon']);
        $merchant2 = $this->createProductMerchant($product->id, ['name' => 'eBay']);

        $this->repository->recordMerchantPriceHistory($product->id, $merchant1->id, 99.99);
        $this->repository->recordMerchantPriceHistory($product->id, $merchant2->id, 89.99);

        // Act
        $history = $this->repository->getPriceHistory($product->id);

        // Assert
        $this->assertCount(2, $history);
    }

    /** @test */
    public function test_get_price_history_filters_by_merchant(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant1 = $this->createProductMerchant($product->id, ['name' => 'Amazon']);
        $merchant2 = $this->createProductMerchant($product->id, ['name' => 'eBay']);

        $this->repository->recordMerchantPriceHistory($product->id, $merchant1->id, 99.99);
        $this->repository->recordMerchantPriceHistory($product->id, $merchant2->id, 89.99);

        // Act
        $history = $this->repository->getPriceHistory($product->id, $merchant1->id);

        // Assert
        $this->assertCount(1, $history);
        $this->assertEquals($merchant1->id, $history->first()->merchant_id);
    }

    /** @test */
    public function test_get_merchants_returns_all_product_merchants(): void
    {
        // Arrange
        $product = $this->createProduct();
        $this->createProductMerchant($product->id, ['name' => 'Amazon']);
        $this->createProductMerchant($product->id, ['name' => 'eBay']);

        // Act
        $merchants = $this->repository->getMerchants($product->id);

        // Assert
        $this->assertCount(2, $merchants);
    }

    /** @test */
    public function test_delete_merchants_removes_all_merchants(): void
    {
        // Arrange
        $product = $this->createProduct();
        $this->createProductMerchant($product->id);
        $this->createProductMerchant($product->id);

        // Act
        $this->repository->deleteMerchants($product->id);

        // Assert
        $count = $this->countRecords('product_merchants', ['product_id' => $product->id]);
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function test_sync_variants_removes_old_and_adds_new(): void
    {
        // Arrange
        $product = $this->createProduct();

        // Create old variant
        $oldVariant = $this->createProductVariant($product->id, ['sku' => 'OLD-SKU']);

        $newVariants = [
            [
                'sku' => 'NEW-SKU-1',
                'attributes' => ['color' => 'red', 'size' => 'M'],
                'price_modifier' => 5.00,
                'is_active' => true
            ],
            [
                'sku' => 'NEW-SKU-2',
                'attributes' => ['color' => 'blue', 'size' => 'L'],
                'price_modifier' => 10.00,
                'is_active' => true
            ],
        ];

        // Act
        $variantIds = $this->repository->syncVariants($product->id, $newVariants);

        // Assert
        $this->assertCount(2, $variantIds);

        // Old variant should be deleted
        $this->assertDatabaseMissing('product_variants', [
            'product_id' => $product->id,
            'sku' => 'OLD-SKU'
        ]);

        // New variants should exist
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'NEW-SKU-1'
        ]);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'NEW-SKU-2'
        ]);
    }

    /** @test */
    public function test_sync_variants_with_images_creates_variant_images(): void
    {
        // Arrange
        $product = $this->createProduct();

        $variants = [
            [
                'sku' => 'VAR-001',
                'attributes' => ['color' => 'red'],
                'price_modifier' => 0,
                'is_active' => true,
                'images' => [
                    [
                        'url' => 'variant-image-1.jpg',
                        'alt' => 'Red variant',
                        'is_primary' => true,
                        'sort_order' => 0
                    ]
                ]
            ],
        ];

        // Act
        $variantIds = $this->repository->syncVariants($product->id, $variants);

        // Assert
        $variant = ProductVariant::find($variantIds[0]);
        $this->assertNotNull($variant);

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'url' => 'variant-image-1.jpg'
        ]);
    }

    /** @test */
    public function test_sync_variant_images_replaces_existing_images(): void
    {
        // Arrange
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        // Create old image
        $this->createProductImage($product->id, [
            'variant_id' => $variant->id,
            'url' => 'old-variant-image.jpg'
        ]);

        $newImages = [
            [
                'url' => 'new-variant-image.jpg',
                'alt' => 'New image',
                'is_primary' => true,
                'sort_order' => 0
            ],
        ];

        // Act
        $this->repository->syncVariantImages($variant->id, $product->id, $newImages);

        // Assert
        $this->assertDatabaseMissing('product_images', [
            'variant_id' => $variant->id,
            'url' => 'old-variant-image.jpg'
        ]);

        $this->assertDatabaseHas('product_images', [
            'variant_id' => $variant->id,
            'url' => 'new-variant-image.jpg'
        ]);
    }

    /** @test */
    public function test_get_variant_images_returns_sorted_images(): void
    {
        // Arrange
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $this->createProductImage($product->id, [
            'variant_id' => $variant->id,
            'url' => 'image-2.jpg',
            'sort_order' => 2
        ]);
        $this->createProductImage($product->id, [
            'variant_id' => $variant->id,
            'url' => 'image-0.jpg',
            'sort_order' => 0
        ]);

        // Act
        $images = $this->repository->getVariantImages($variant->id);

        $images = $images->toArray();

        // Assert
        $this->assertCount(2, $images);
        $this->assertEquals('image-0.jpg', $images[0]['url']);
        $this->assertEquals('image-2.jpg', $images[1]['url']);
    }

    /** @test */
    public function test_delete_variant_images_removes_all_images(): void
    {
        // Arrange
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $this->createProductImage($product->id, ['variant_id' => $variant->id]);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        // Act
        $this->repository->deleteVariantImages($variant->id);

        // Assert
        $count = $this->countRecords('product_images', ['variant_id' => $variant->id]);
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function test_get_variants_returns_all_product_variants(): void
    {
        // Arrange
        $product = $this->createProduct();
        $this->createProductVariant($product->id);
        $this->createProductVariant($product->id);

        // Act
        $variants = $this->repository->getVariants($product->id);

        // Assert
        $this->assertCount(2, $variants);
    }

    /** @test */
    public function test_delete_variants_removes_all_variants(): void
    {
        // Arrange
        $product = $this->createProduct();
        $this->createProductVariant($product->id);
        $this->createProductVariant($product->id);

        // Act
        $this->repository->deleteVariants($product->id);

        // Assert
        $count = $this->countRecords('product_variants', ['product_id' => $product->id]);
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function test_sync_specifications_replaces_all_specs(): void
    {
        // Arrange
        $product = $this->createProduct();

        // Create old spec
        $this->createProductSpecification($product->id, [
            'category' => 'Old',
            'key' => 'old_key',
            'value' => 'old_value'
        ]);

        $newSpecs = [
            [
                'category' => 'Dimensions',
                'key' => 'width',
                'value' => '10cm',
                'sort_order' => 0
            ],
            [
                'category' => 'Dimensions',
                'key' => 'height',
                'value' => '20cm',
                'sort_order' => 1
            ],
        ];

        // Act
        $this->repository->syncSpecifications($product->id, $newSpecs);

        // Assert
        $specs = ProductSpecification::where('product_id', $product->id)
            ->orderBy('sort_order')
            ->get();

        $specs = $specs->toArray();

        $this->assertCount(2, $specs);
        $this->assertEquals('width', $specs[0]['key']);
        $this->assertEquals('height', $specs[1]['key']);

        // Old spec should be gone
        $this->assertDatabaseMissing('product_specifications', [
            'product_id' => $product->id,
            'key' => 'old_key'
        ]);
    }

    /** @test */
    public function test_get_specifications_returns_sorted_specs(): void
    {
        // Arrange
        $product = $this->createProduct();

        $this->createProductSpecification($product->id, [
            'key' => 'spec_2',
            'value' => 'value_2',
            'sort_order' => 2
        ]);
        $this->createProductSpecification($product->id, [
            'key' => 'spec_0',
            'value' => 'value_0',
            'sort_order' => 0
        ]);
        $this->createProductSpecification($product->id, [
            'key' => 'spec_1',
            'value' => 'value_1',
            'sort_order' => 1
        ]);

        // Act
        $specs = $this->repository->getSpecifications($product->id);

        $specs = $specs->toArray();

        // Assert
        $this->assertCount(3, $specs);
        $this->assertEquals('spec_0', $specs[0]['key']);
        $this->assertEquals('spec_1', $specs[1]['key']);
        $this->assertEquals('spec_2', $specs[2]['key']);
    }

    /** @test */
    public function test_delete_specifications_removes_all_specs(): void
    {
        // Arrange
        $product = $this->createProduct();
        $this->createProductSpecification($product->id);
        $this->createProductSpecification($product->id);

        // Act
        $this->repository->deleteSpecifications($product->id);

        // Assert
        $count = $this->countRecords('product_specifications', ['product_id' => $product->id]);
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function test_record_price_history_creates_record_for_product(): void
    {
        // Arrange
        $product = $this->createProduct(['price' => 99.99, 'sale_price' => 79.99]);

        // Act
        $history = $this->repository->recordPriceHistory($product);

        // Assert
        $this->assertNotNull($history);
        $this->assertEquals($product->id, $history->product_id);
        $this->assertNull($history->merchant_id);
        $this->assertEquals(99.99, $history->price);
        $this->assertEquals(79.99, $history->sale_price);
    }

    /** @test */
    public function test_record_price_history_rejects_negative_prices(): void
    {
        // Arrange
        $product = $this->createProduct(['price' => -10.00, 'sale_price' => -5.00]);

        // Act
        $history = $this->repository->recordPriceHistory($product);

        // Assert
        $this->assertNull($history);
    }

    /** @test */
    public function test_delete_price_history_removes_all_history(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant = $this->createProductMerchant($product->id);

        $this->repository->recordMerchantPriceHistory($product->id, $merchant->id, 99.99);
        $this->repository->recordMerchantPriceHistory($product->id, $merchant->id, 89.99);

        // Act
        $this->repository->deletePriceHistory($product->id);

        // Assert
        $count = $this->countRecords('product_price_history', ['product_id' => $product->id]);
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function test_find_related_excludes_current_product(): void
    {
        // Arrange
        $category = $this->createCategory(); // Assuming category_id exists
        $product1 = $this->createProduct(['category_id' => $category->id, 'is_active' => true]);
        $product2 = $this->createProduct(['category_id' => $category->id, 'is_active' => true]);
        $product3 = $this->createProduct(['category_id' => $category->id, 'is_active' => true]);

        // Act
        $related = $this->repository->findRelated($product1, 10);

        // Assert
        foreach ($related as $relatedProduct) {
            $this->assertNotEquals($product1->id, $relatedProduct->id);
        }
    }

    /** @test */
    public function test_find_related_only_returns_active_products(): void
    {
        // Arrange
        $category = $this->createCategory();
        $activeProduct = $this->createProduct(['category_id' => $category->id, 'is_active' => true]);
        $this->createProduct(['category_id' => $category->id, 'is_active' => false]);
        $this->createProduct(['category_id' => $category->id, 'is_active' => true]);

        // Act
        $related = $this->repository->findRelated($activeProduct, 10);

        // Assert
        foreach ($related as $relatedProduct) {
            $this->assertTrue((bool) $relatedProduct->is_active);
        }
    }

    /** @test */
    public function test_find_related_respects_limit(): void
    {
        // Arrange
        $category = $this->createCategory();
        $product = $this->createProduct(['category_id' => $category->id]);

        // Create more products than limit
        for ($i = 0; $i < 10; $i++) {
            $this->createProduct(['category_id' => $category->id, 'is_active' => true]);
        }

        // Act
        $related = $this->repository->findRelated($product, 5);

        // Assert
        $this->assertLessThanOrEqual(5, $related->count());
    }

    /** @test */
    public function test_get_recently_viewed_returns_active_products_in_order(): void
    {
        // Arrange
        $product1 = $this->createProduct(['is_active' => true]);
        $product2 = $this->createProduct(['is_active' => true]);
        $product3 = $this->createProduct(['is_active' => false]);

        $productIds = [$product1->id, $product2->id, $product3->id];

        // Act
        $viewed = $this->repository->getRecentlyViewed($productIds, 10);

        // Assert
        // Should only return active products
        foreach ($viewed as $product) {
            $this->assertTrue((bool) $product->is_active);
        }
    }

    /** @test */
    public function test_get_recently_viewed_returns_empty_collection_for_empty_array(): void
    {
        // Act
        $viewed = $this->repository->getRecentlyViewed([], 10);

        // Assert
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $viewed);
        $this->assertCount(0, $viewed);
    }

    /** @test */
    public function test_get_recently_viewed_respects_limit(): void
    {
        // Arrange
        $productIds = [];
        for ($i = 0; $i < 10; $i++) {
            $product = $this->createProduct(['is_active' => true]);
            $productIds[] = $product->id;
        }

        // Act
        $viewed = $this->repository->getRecentlyViewed($productIds, 3);

        // Assert
        $this->assertLessThanOrEqual(3, $viewed->count());
    }

    public function test_get_all_merchants_returns_unique_names(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $this->createProductMerchant($product1->id, ['name' => 'Amazon']);
        $this->createProductMerchant($product1->id, ['name' => 'eBay']);
        $this->createProductMerchant($product2->id, ['name' => 'Amazon']); // Duplicate
        $this->createProductMerchant($product2->id, ['name' => 'BestBuy']);

        $merchants = $this->repository->getAllMerchants();

        // Should return unique merchant names
        $this->assertGreaterThanOrEqual(3, $merchants->count());

        $merchantNames = $merchants->pluck('name')->toArray();
        $this->assertContains('Amazon', $merchantNames);
        $this->assertContains('eBay', $merchantNames);
        $this->assertContains('BestBuy', $merchantNames);
    }

    public function test_update_variant_updates_fields(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'OLD-SKU',
            'price_modifier' => 5.00,
            'is_active' => true
        ]);

        $updated = $this->repository->updateVariant($variant->id, [
            'sku' => 'NEW-SKU',
            'price_modifier' => 10.00,
            'is_active' => false
        ]);

        $this->assertTrue($updated);

        $variant = $variant->fresh();
        $this->assertEquals('NEW-SKU', $variant->sku);
        $this->assertEquals(10.00, $variant->price_modifier);
        $this->assertFalse($variant->is_active);
    }

    public function test_update_variant_returns_false_for_nonexistent(): void
    {
        $updated = $this->repository->updateVariant(9999, ['sku' => 'TEST']);
        $this->assertFalse($updated);
    }

    public function test_delete_variant_removes_variant_and_images(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        // Add images
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $deleted = $this->repository->deleteVariant($variant->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
        $this->assertCount(0, ProductImage::where('variant_id', $variant->id)->get());
    }

    public function test_delete_variant_returns_false_for_nonexistent(): void
    {
        $deleted = $this->repository->deleteVariant(9999);
        $this->assertFalse($deleted);
    }
}