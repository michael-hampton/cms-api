<?php

namespace App\Tests\Unit\Repositories\Product;

use App\Framework\Support\Str;
use App\Models\Merchant;
use App\Models\ProductImage;
use App\Models\ProductMerchant;
use App\Models\ProductSpecification;
use App\Models\ProductSpecificationGroup;
use App\Models\ProductVariant;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductSpecificationGroupRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ProductRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ProductRepository $repository;
    private ProductSpecificationGroupRepository $specificationGroupRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->specificationGroupRepository = new ProductSpecificationGroupRepository();
        $this->repository = new ProductRepository($this->specificationGroupRepository);;
    }

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

    public function test_find_by_slug_returns_null_when_not_found(): void
    {
        // Act
        $found = $this->repository->findBySlug('non-existent-slug');

        // Assert
        $this->assertNull($found);
    }

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
        $this->repository->syncVariantImages($product->id, $variant->id, $images);

        // Assert
        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'url' => 'variant-image.jpg'
        ]);
    }

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

    public function test_sync_merchants_updates_existing_and_creates_new(): void
    {
        // Arrange
        $product = $this->createProduct();

        $merchant = $this->createMerchant(['name' => 'Amazon']);;

        // Create existing merchant
        $existingMerchant = $this->createProductMerchant($product->id, [
            'price' => 99.99,
            'merchant_id' => $merchant->id,
        ]);

        $merchants = [
            [
                'name' => $merchant->name,  // Existing - should be kept
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

        $this->assertContains($merchant->id, $merchantIds);

        $allMerchants = ProductMerchant::where('product_id', $product->id)->get();

        $this->assertEquals(2, $allMerchants->count());

        $amazonLookup = Merchant::where('name', 'Amazon')->first();
        $ebayLookup = Merchant::where('name', 'eBay')->first();

        $amazon = $allMerchants->where('merchant_id', $amazonLookup->id)->first();
        $ebay = $allMerchants->where('merchant_id', $ebayLookup->id)->first();

        $this->assertEquals($merchant->id, $amazon->id);
        $this->assertNotNull($ebay);
    }

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

        $amazonLookup = Merchant::where('name', 'Amazon')->first();
        $this->assertDatabaseHas('product_merchants', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'merchant_id' => $amazonLookup->id
        ]);
    }

    public function test_record_merchant_price_history_creates_record(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant = $this->createMerchant();
        $productMerchant = $this->createProductMerchant($product->id);

        // Act
        $history = $this->repository->recordMerchantPriceHistory($product->id, $productMerchant->id, 99.99, $merchant->id);

        // Assert
        $this->assertNotNull($history);
        $this->assertEquals($product->id, $history->product_id);
        $this->assertEquals($merchant->id, $history->product_merchant_id);
        $this->assertEquals(99.99, $history->price);
        $this->assertDatabaseHas('product_price_history', [
            'product_id' => $product->id,
            'product_merchant_id' => $merchant->id,
            'price' => 99.99
        ]);
    }

    public function test_record_merchant_price_history_rejects_negative_price(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant = $this->createMerchant();
        $productMerchant = $this->createProductMerchant($product->id, ['merchant_id' => $merchant->id]);

        // Act
        $history = $this->repository->recordMerchantPriceHistory($product->id, $productMerchant->id, -10.00, $merchant->id);

        // Assert
        $this->assertNull($history);
        $this->assertDatabaseMissing('product_price_history', [
            'product_id' => $product->id,
            'product_merchant_id' => $merchant->id
        ]);
    }

    public function test_get_price_history_returns_all_for_product(): void
    {
        $merchant1 = $this->createMerchant(['name' => 'Amazon']);
        $merchant2 = $this->createMerchant(['name' => 'eBay']);

        // Arrange
        $product = $this->createProduct();
        $productMerchant1 = $this->createProductMerchant($product->id, ['product_merchant_id' => $merchant1->id]);
        $productMerchant2 = $this->createProductMerchant($product->id, ['product_merchant_id' => $merchant2->id]);

        $this->repository->recordMerchantPriceHistory($product->id, $productMerchant1->id, 99.99, $merchant1->id);
        $this->repository->recordMerchantPriceHistory($product->id, $productMerchant2->id, 89.99, $merchant2->id);;

        // Act
        $history = $this->repository->getPriceHistory($product->id);

        // Assert
        $this->assertCount(2, $history);
    }

    public function test_get_price_history_filters_by_merchant(): void
    {
        $merchant1 = $this->createMerchant(['name' => 'Amazon']);
        $merchant2 = $this->createMerchant(['name' => 'eBay']);

        // Arrange
        $product = $this->createProduct();
        $productMerchant1 = $this->createProductMerchant($product->id, ['merchant_id' => $merchant1->id]);
        $productMerchant2 = $this->createProductMerchant($product->id, ['merchant_id' => $merchant2->id]);

        $this->repository->recordMerchantPriceHistory($product->id, $productMerchant1->id, 99.99, $merchant1->id);
        $this->repository->recordMerchantPriceHistory($product->id, $productMerchant2->id, 89.99, $merchant2->id);

        // Act
        $history = $this->repository->getPriceHistory($product->id, $merchant1->id);

        // Assert
        $this->assertCount(1, $history);
        $this->assertEquals($merchant1->id, $history->first()->product_merchant_id);
    }

    public function test_get_merchants_returns_all_product_merchants(): void
    {
        $merchant1 = $this->createMerchant(['name' => 'Amazon']);
        $merchant2 = $this->createMerchant(['name' => 'eBay']);

        // Arrange
        $product = $this->createProduct();
        $this->createProductMerchant($product->id, ['merchant_id' => $merchant1->id]);
        $this->createProductMerchant($product->id, ['merchant_id' => $merchant2->id]);

        // Act
        $merchants = $this->repository->getMerchants($product->id);

        // Assert
        $this->assertCount(2, $merchants);
    }

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

    public function test_record_price_history_rejects_negative_prices(): void
    {
        // Arrange
        $product = $this->createProduct(['price' => -10.00, 'sale_price' => -5.00]);

        // Act
        $history = $this->repository->recordPriceHistory($product);

        // Assert
        $this->assertNull($history);
    }

    public function test_delete_price_history_removes_all_history(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant = $this->createMerchant();
        $productMerchant = $this->createProductMerchant($product->id, ['merchant_id' => $merchant->id]);

        $this->repository->recordMerchantPriceHistory($product->id, $productMerchant->id, 99.99, $merchant->id);
        $this->repository->recordMerchantPriceHistory($product->id, $productMerchant->id, 89.99, $merchant->id);

        // Act
        $this->repository->deletePriceHistory($product->id);

        // Assert
        $count = $this->countRecords('product_price_history', ['product_id' => $product->id]);
        $this->assertEquals(0, $count);
    }

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

    public function test_get_recently_viewed_returns_active_products_in_order(): void
    {
        // Arrange
        $product1 = $this->createProduct(['is_active' => true]);
        $product2 = $this->createProduct(['is_active' => true]);
        $product3 = $this->createProduct(['is_active' => false]);

        $productIds = [$product1->id, $product2->id, $product3->id];

        // Act
        $viewed = $this->repository->getActiveProducts($productIds, 10);

        // Assert
        // Should only return active products
        foreach ($viewed as $product) {
            $this->assertTrue((bool) $product->is_active);
        }
    }

    public function test_get_recently_viewed_returns_empty_collection_for_empty_array(): void
    {
        // Act
        $viewed = $this->repository->getActiveProducts([], 10);

        // Assert
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $viewed);
        $this->assertCount(0, $viewed);
    }

    public function test_get_recently_viewed_respects_limit(): void
    {
        // Arrange
        $productIds = [];
        for ($i = 0; $i < 10; $i++) {
            $product = $this->createProduct(['is_active' => true]);
            $productIds[] = $product->id;
        }

        // Act
        $viewed = $this->repository->getActiveProducts($productIds, 3);

        // Assert
        $this->assertLessThanOrEqual(3, $viewed->count());
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

    public function test_sync_merchants_creates_lookup_entries(): void
    {
        $product = $this->createProduct();

        $merchants = [
            ['name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 99.99, 'is_available' => true],
            ['name' => 'eBay', 'url' => 'https://ebay.com', 'price' => 95.00, 'is_available' => true],
        ];

        $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

        $this->assertCount(2, $merchantIds);

        // Check lookup table
        $this->assertEquals(2, Merchant::count());
        $this->assertDatabaseHas('merchants', ['name' => 'Amazon']);
        $this->assertDatabaseHas('merchants', ['name' => 'eBay']);

        // Check product_merchants
        $productMerchants = ProductMerchant::where('product_id', $product->id)->get();
        $this->assertCount(2, $productMerchants);
    }

    public function test_sync_merchants_reuses_existing_lookup(): void
    {
        $product = $this->createProduct();

        // First sync
        $merchants1 = [
            ['name' => 'Amazon', 'url' => 'https://amazon.com/1', 'price' => 99.99, 'is_available' => true],
        ];
        $this->repository->syncMerchants($product->id, $merchants1);

        $merchantCountAfterFirst = Merchant::count();
        $this->assertEquals(1, $merchantCountAfterFirst);

        // Second sync with same merchant name
        $merchants2 = [
            ['name' => 'Amazon', 'url' => 'https://amazon.com/2', 'price' => 89.99, 'is_available' => true],
        ];
        $this->repository->syncMerchants($product->id, $merchants2);

        // Should still only have 1 merchant in lookup
        $this->assertEquals(1, Merchant::count());

        // But product_merchant should be updated
        $pm = ProductMerchant::where('product_id', $product->id)->first();
        $this->assertEquals(89.99, $pm->price);
        $this->assertEquals('https://amazon.com/2', $pm->url);
    }

    public function test_get_all_merchant_lookups_returns_all(): void
    {
        Merchant::create(['name' => 'Amazon', 'slug' => Str::slug('Amazon')]);;
        Merchant::create(['name' => 'eBay', 'slug' => Str::slug('eBay')]);;
        Merchant::create(['name' => 'BestBuy', 'slug' => Str::slug('BestBuy')]);;;

        $merchants = $this->repository->getAllMerchantLookups();

        $this->assertCount(3, $merchants);
        $this->assertEquals('Amazon', $merchants->first()->name);
    }

    public function test_get_product_merchants_with_details(): void
    {
        $product = $this->createProduct();
        $amazonLookup = Merchant::create(['name' => 'Amazon', 'slug' => Str::slug('Amazon')]);;;

        ProductMerchant::create([
            'product_id' => $product->id,
            'merchant_id' => $amazonLookup->id,
            'url' => 'https://amazon.com',
            'price' => 99.99,
            'is_available' => true
        ]);

        $merchants = $this->repository->getProductMerchantsWithDetails($product->id);

        $this->assertCount(1, $merchants);
        $this->assertEquals('Amazon', $merchants->first()['name']);
        $this->assertEquals(99.99, $merchants->first()['price']);
    }

    public function test_sync_images_updates_primary_flag(): void
    {
        // Arrange
        $product = $this->createProduct();

        // Create initial images with img1 as primary
        $this->createProductImage($product->id, [
            'url' => 'img1.jpg',
            'is_primary' => true,
            'sort_order' => 0
        ]);
        $this->createProductImage($product->id, [
            'url' => 'img2.jpg',
            'is_primary' => false,
            'sort_order' => 1
        ]);

        // Now sync with img2 as primary
        $newImages = [
            [
                'url' => 'img1.jpg',
                'alt' => 'Image 1',
                'is_primary' => false, // Changed
                'sort_order' => 0
            ],
            [
                'url' => 'img2.jpg',
                'alt' => 'Image 2',
                'is_primary' => true, // Changed
                'sort_order' => 1
            ],
        ];

        // Act
        $this->repository->syncImages($product->id, $newImages);

        // Assert
        $images = ProductImage::where('product_id', $product->id)
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        $this->assertCount(2, $images);
        $this->assertEquals('img1.jpg', $images[0]['url']);
        $this->assertEquals(0, $images[0]['is_primary']); // No longer primary
        $this->assertEquals('img2.jpg', $images[1]['url']);
        $this->assertEquals(1, $images[1]['is_primary']); // Now primary
    }

    public function test_sync_merchants_with_variant_overrides(): void
    {
        // Arrange
        $product = $this->createProduct(['price' => 100]);
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'VAR-001',
            'price' => 120,
            'sale_price' => 110
        ]);

        $merchants = [
            [
                'name' => 'Amazon',
                'url' => 'https://amazon.com/product',
                'price' => 125, // Override price
                'override_price' => true,
                'override_sale_price' => false,
                'variant_id' => $variant->id,
                'variant_sku' => 'AMZN-VAR-001',
                'is_available' => true
            ],
            [
                'name' => 'eBay',
                'url' => 'https://ebay.com/product',
                'price' => 115, // Use as sale price
                'override_price' => false,
                'override_sale_price' => true,
                'variant_id' => $variant->id,
                'variant_sku' => null, // Use variant SKU
                'is_available' => true
            ],
        ];

        // Act
        $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

        // Assert
        $this->assertCount(2, $merchantIds);

        $amazonLookup = Merchant::where('name', 'Amazon')->first();
        $ebayLookup = Merchant::where('name', 'eBay')->first();

        $amazon = ProductMerchant::where('merchant_id', $amazonLookup->id)
            ->where('variant_id', $variant->id)
            ->first();

        $ebay = ProductMerchant::where('merchant_id', $ebayLookup->id)
            ->where('variant_id', $variant->id)
            ->first();

        $this->assertNotNull($amazon);
        $this->assertNotNull($ebay);

        // Amazon assertions
        $this->assertTrue($amazon->override_price);
        $this->assertFalse($amazon->override_sale_price);
        $this->assertEquals(125, $amazon->price);
        $this->assertEquals('AMZN-VAR-001', $amazon->variant_sku);
        $this->assertEquals(125, $amazon->effective_price); // Uses override

        // eBay assertions
        $this->assertFalse($ebay->override_price);
        $this->assertTrue($ebay->override_sale_price);
        $this->assertEquals(115, $ebay->price);
        $this->assertNull($ebay->variant_sku);
        $this->assertEquals(120, $ebay->effective_price); // Uses variant price
        $this->assertEquals(110, $ebay->effective_sale_price); // Uses override as sale price
    }

    public function test_sync_merchants_multiple_variants_same_merchant(): void
    {
        // Arrange
        $product = $this->createProduct();
        $variant1 = $this->createProductVariant($product->id, ['sku' => 'VAR-001', 'price' => 100]);
        $variant2 = $this->createProductVariant($product->id, ['sku' => 'VAR-002', 'price' => 120]);

        $merchants = [
            [
                'name' => 'Amazon',
                'url' => 'https://amazon.com/var1',
                'price' => 95,
                'override_price' => true,
                'variant_id' => $variant1->id,
                'is_available' => true
            ],
            [
                'name' => 'Amazon',
                'url' => 'https://amazon.com/var2',
                'price' => 115,
                'override_price' => true,
                'variant_id' => $variant2->id,
                'is_available' => true
            ],
        ];

        // Act
        $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

        // Assert
        $this->assertCount(2, $merchantIds);

        $amazonLookup = Merchant::where('name', 'Amazon')->first();
        $productMerchants = ProductMerchant::where('product_id', $product->id)
            ->where('merchant_id', $amazonLookup->id)
            ->get();

        $this->assertCount(2, $productMerchants);

        $pm1 = $productMerchants->where('variant_id', $variant1->id)->first();
        $pm2 = $productMerchants->where('variant_id', $variant2->id)->first();

        $this->assertEquals(95, $pm1->price);
        $this->assertEquals(115, $pm2->price);
    }

    public function test_get_product_merchants_with_variant_details(): void
    {
        // Arrange
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'VAR-001',
            'name' => 'Red Variant',
            'price' => 100,
            'sale_price' => 90,
            'attributes' => ['color' => 'red']
        ]);

        $merchant = $this->createMerchant(['name' => 'Amazon']);
        $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'variant_id' => $variant->id,
            'price' => 105,
            'override_price' => true,
            'variant_sku' => 'CUSTOM-SKU'
        ]);

        // Act
        $merchants = $this->repository->getProductMerchantsWithDetails($product->id);

        // Assert
        $this->assertCount(1, $merchants);

        $merchantData = $merchants->first();
        $this->assertEquals('Amazon', $merchantData['name']);
        $this->assertEquals($variant->id, $merchantData['variant_id']);
        $this->assertTrue($merchantData['override_price']);
        $this->assertEquals('CUSTOM-SKU', $merchantData['variant_sku']);
        $this->assertEquals(105, $merchantData['effective_price']);
        $this->assertEquals(90, $merchantData['effective_sale_price']); // From variant
        $this->assertEquals('CUSTOM-SKU', $merchantData['effective_sku']);

        $this->assertNotNull($merchantData['variant']);
        $this->assertEquals('VAR-001', $merchantData['variant']['sku']);
        $this->assertEquals('Red Variant', $merchantData['variant']['name']);
    }

    public function test_sync_merchants_updates_existing_variant_merchant(): void
    {
        // Arrange
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $merchant = $this->createMerchant(['name' => 'Amazon']);

        $existingPM = $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'variant_id' => $variant->id,
            'price' => 100,
            'override_price' => false,
            'variant_sku' => 'OLD-SKU'
        ]);

        $merchants = [
            [
                'id' => $existingPM->id,
                'name' => 'Amazon',
                'url' => 'https://amazon.com/updated',
                'price' => 110,
                'override_price' => true,
                'variant_id' => $variant->id,
                'variant_sku' => 'NEW-SKU',
                'is_available' => true
            ],
        ];

        // Act
        $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

        // Assert
        $this->assertCount(1, $merchantIds);

        $this->assertContains($existingPM->id, $merchantIds);

        $updated = ProductMerchant::find($existingPM->id);
        $this->assertEquals(110, $updated->price);
        $this->assertTrue($updated->override_price);
        $this->assertEquals('NEW-SKU', $updated->variant_sku);
    }

    public function test_sync_merchants_with_separate_sale_price(): void
    {
        // Arrange
        $product = $this->createProduct(['price' => 100]);
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'VAR-001',
            'price' => 120,
            'sale_price' => 110
        ]);

        $merchants = [
            [
                'name' => 'Amazon',
                'url' => 'https://amazon.com/product',
                'price' => 125,
                'sale_price' => 115,
                'override_price' => true,
                'override_sale_price' => true,
                'variant_id' => $variant->id,
                'variant_sku' => 'AMZN-VAR-001',
                'is_available' => true
            ],
            [
                'name' => 'eBay',
                'url' => 'https://ebay.com/product',
                'price' => 120, // Will use variant price
                'sale_price' => 110,    // Will use variant sale price
                'override_price' => false,
                'override_sale_price' => false,
                'variant_id' => $variant->id,
                'variant_sku' => null,
                'is_available' => true
            ],
        ];

        // Act
        $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

        // Assert
        $this->assertCount(2, $merchantIds);

        $amazonLookup = Merchant::where('name', 'Amazon')->first();
        $ebayLookup = Merchant::where('name', 'eBay')->first();

        $amazon = ProductMerchant::where('merchant_id', $amazonLookup->id)
            ->where('variant_id', $variant->id)
            ->first();

        $ebay = ProductMerchant::where('merchant_id', $ebayLookup->id)
            ->where('variant_id', $variant->id)
            ->first();

        $this->assertNotNull($amazon);
        $this->assertNotNull($ebay);

        // Amazon assertions
        $this->assertTrue($amazon->override_price);
        $this->assertTrue($amazon->override_sale_price);
        $this->assertEquals(125, $amazon->price);
        $this->assertEquals(115, $amazon->sale_price);
        $this->assertEquals('AMZN-VAR-001', $amazon->variant_sku);
        $this->assertEquals(125, $amazon->effective_price);
        $this->assertEquals(115, $amazon->effective_sale_price);
        $this->assertEquals(8, $amazon->discount_percentage); // (125-115)/125 * 100
        $this->assertTrue($amazon->has_discount);

        // eBay assertions
        $this->assertFalse($ebay->override_price);
        $this->assertFalse($ebay->override_sale_price);
        $this->assertNull($ebay->variant_sku);
        $this->assertEquals(120, $ebay->effective_price); // From variant
        $this->assertEquals(110, $ebay->effective_sale_price); // From variant
        $this->assertEquals(8, $ebay->discount_percentage);
        $this->assertTrue($ebay->has_discount);
    }

    public function test_record_merchant_price_history_with_sale_price(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant = $this->createMerchant();
        $productMerchant = $this->createProductMerchant($product->id, ['merchant_id' => $merchant->id]);

        // Act
        $history = $this->repository->recordMerchantPriceHistory(
            $product->id,
            $productMerchant->id,
            99.99,
            $merchant->id,
            79.99
        );

        // Assert
        $this->assertNotNull($history);
        $this->assertEquals($product->id, $history->product_id);
        $this->assertEquals($merchant->id, $history->product_merchant_id);
        $this->assertEquals(99.99, $history->price);
        $this->assertEquals(79.99, $history->sale_price);
    }

    public function test_get_product_pages_returns_pages_with_product_blocks(): void
    {
        $product = $this->createProduct();
        $page1 = $this->createPage(['title' => 'Review Page']);
        $page2 = $this->createPage(['title' => 'Deal Page']);

        $this->createBlock($page1->id, ['type' => 'product', 'data' => [
            'product_id' => $product->id,
            'name' => 'Test Product'
        ]]);

        $this->createBlock($page2->id, ['type' => 'deal', 'data' => [
            'product_id' => $product->id,
            'productName' => 'Test Product'
        ]]);

        $pages = $this->repository->getProductPages($product->id);

        $this->assertCount(2, $pages);
        $pageTitles = $pages->pluck('title')->toArray();
        $this->assertContains('Review Page', $pageTitles);
        $this->assertContains('Deal Page', $pageTitles);
    }

    public function test_get_product_pages_includes_comparison_blocks(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $page = $this->createPage(['title' => 'Comparison']);

        $this->createBlock($page->id, ['type' => 'product-comparison', 'data' => [
            'product_a_id' => $product1->id,
            'product_b_id' => $product2->id,
            'productA' => 'Product A',
            'productB' => 'Product B'
        ]]);

        $pagesForProduct1 = $this->repository->getProductPages($product1->id);
        $pagesForProduct2 = $this->repository->getProductPages($product2->id);

        $this->assertCount(1, $pagesForProduct1);
        $this->assertCount(1, $pagesForProduct2);
        $this->assertEquals($page->id, $pagesForProduct1->first()->id);
    }

    public function test_get_product_pages_returns_empty_when_no_references(): void
    {
        $product = $this->createProduct();

        $pages = $this->repository->getProductPages($product->id);

        $this->assertCount(0, $pages);
    }

    public function test_sync_specifications_creates_groups(): void
    {
        $product = $this->createProduct();

        $specs = [
            ['category' => 'Technical', 'key' => 'Weight', 'value' => '1kg', 'sort_order' => 0],
            ['category' => 'Technical', 'key' => 'Power', 'value' => '100W', 'sort_order' => 1],
        ];

        $this->repository->syncSpecifications($product->id, $specs);

        // Verify group was created
        $group = ProductSpecificationGroup::whereRaw('LOWER(name) = ?', ['technical'])->first();
        $this->assertNotNull($group);

        // Verify specifications are linked to group
        $productSpecs = ProductSpecification::where('product_id', $product->id)->get();
        $this->assertCount(2, $productSpecs);

        foreach ($productSpecs as $spec) {
            $this->assertEquals($group->id, $spec->specification_group_id);
        }
    }

    public function test_sync_specifications_reuses_existing_groups(): void
    {
        $product = $this->createProduct();

        // Create existing group
        $existingGroup = ProductSpecificationGroup::create([
            'name' => 'Dimensions',
            'slug' => 'dimensions'
        ]);

        $specs = [
            ['category' => 'dimensions', 'key' => 'Width', 'value' => '10cm', 'sort_order' => 0],
        ];

        $this->repository->syncSpecifications($product->id, $specs);

        // Verify no duplicate group was created
        $groupCount = ProductSpecificationGroup::whereRaw('LOWER(name) = ?', ['dimensions'])->count();
        $this->assertEquals(1, $groupCount);

        // Verify specification uses existing group
        $spec = ProductSpecification::where('product_id', $product->id)->first();
        $this->assertEquals($existingGroup->id, $spec->specification_group_id);
    }

    public function testSyncSpecificationsCreatesGroupsCaseInsensitive()
    {
        $product = $this->createProduct();

        $specifications = [
            ['category' => 'Dimensions', 'key' => 'Width', 'value' => '10cm', 'sort_order' => 0],
            ['category' => 'dimensions', 'key' => 'Height', 'value' => '20cm', 'sort_order' => 1],
        ];

        $this->repository->syncSpecifications($product->id, $specifications);

        // Verify only one group was created (case-insensitive)
        $groupCount = ProductSpecificationGroup::whereRaw('LOWER(name) = ?', ['dimensions'])->count();
        $this->assertEquals(1, $groupCount);

        // Verify both specifications were created
        $specs = ProductSpecification::where('product_id', $product->id)->get();
        $this->assertCount(2, $specs);
    }

    public function test_find_product_merchant_returns_correct_merchant(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant = $this->createMerchant(['name' => 'Amazon']);
        $productMerchant = $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'price' => 99.99
        ]);

        // Act
        $found = $this->repository->findProductMerchant($product->id, $merchant->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($productMerchant->id, $found->id);
        $this->assertEquals($product->id, $found->product_id);
        $this->assertEquals($merchant->id, $found->merchant_id);
    }

    public function test_find_product_merchant_returns_null_when_not_found(): void
    {
        // Arrange
        $product = $this->createProduct();

        // Act
        $found = $this->repository->findProductMerchant($product->id, 9999);

        // Assert
        $this->assertNull($found);
    }

    public function test_create_product_merchant_creates_new_merchant(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant = $this->createMerchant(['name' => 'Amazon']);

        $data = [
            'merchant_id' => $merchant->id,
            'url' => 'https://amazon.com/product',
            'price' => 99.99,
            'sale_price' => 89.99,
            'is_available' => true
        ];

        // Act
        $productMerchant = $this->repository->createProductMerchant($product->id, $data);

        // Assert
        $this->assertNotNull($productMerchant);
        $this->assertEquals($product->id, $productMerchant->product_id);
        $this->assertEquals($merchant->id, $productMerchant->merchant_id);
        $this->assertEquals(99.99, $productMerchant->price);
        $this->assertEquals(89.99, $productMerchant->sale_price);
        $this->assertTrue($productMerchant->is_available);

        $this->assertDatabaseHas('product_merchants', [
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 99.99
        ]);
    }

    public function test_update_product_merchant_updates_fields(): void
    {
        // Arrange
        $product = $this->createProduct();
        $merchant = $this->createMerchant();
        $productMerchant = $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'price' => 99.99,
            'is_available' => true
        ]);

        $updateData = [
            'price' => 89.99,
            'sale_price' => 79.99,
            'is_available' => false,
            'url' => 'https://updated-url.com'
        ];

        // Act
        $updated = $this->repository->updateProductMerchant($productMerchant->id, $updateData);

        // Assert
        $this->assertTrue($updated);

        $refreshed = ProductMerchant::find($productMerchant->id);
        $this->assertEquals(89.99, $refreshed->price);
        $this->assertEquals(79.99, $refreshed->sale_price);
        $this->assertFalse($refreshed->is_available);
        $this->assertEquals('https://updated-url.com', $refreshed->url);
    }

    public function test_update_product_merchant_returns_false_for_nonexistent(): void
    {
        // Act
        $updated = $this->repository->updateProductMerchant(9999, ['price' => 100]);

        // Assert
        $this->assertFalse($updated);
    }

    public function test_get_products_by_merchant_returns_products_with_merchant_data(): void
    {
        // Arrange
        $merchant = $this->createMerchant(['name' => 'Amazon']);
        $brand = $this->createBrand(['name' => 'Test Brand']);
        $category = $this->createCategory(['name' => 'Electronics']);

        $product1 = $this->createProduct([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true
        ]);
        $product2 = $this->createProduct([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true
        ]);
        $product3 = $this->createProduct([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => false  // Inactive product
        ]);

        $this->createProductImage($product1->id, ['is_primary' => true]);
        $this->createProductImage($product2->id, ['is_primary' => true]);

        // Create product merchants
        $pm1 = $this->createProductMerchant($product1->id, [
            'merchant_id' => $merchant->id,
            'price' => 99.99,
            'sale_price' => 89.99
        ]);
        $pm2 = $this->createProductMerchant($product2->id, [
            'merchant_id' => $merchant->id,
            'price' => 149.99
        ]);
        $this->createProductMerchant($product3->id, [
            'merchant_id' => $merchant->id,
            'price' => 199.99
        ]);

        // Act
        $products = $this->repository->getProductsByMerchant($merchant->id);

        // Assert
        $this->assertCount(2, $products); // Only active products

        $foundProduct1 = $products->firstWhere('id', $product1->id);
        $foundProduct2 = $products->firstWhere('id', $product2->id);

        $this->assertNotNull($foundProduct1);
        $this->assertNotNull($foundProduct2);

        // Check merchant data is attached
        $this->assertNotNull($foundProduct1->merchant_data);
        $this->assertEquals($pm1->id, $foundProduct1->merchant_data->id);
        $this->assertEquals(99.99, $foundProduct1->merchant_data->price);
        $this->assertEquals(89.99, $foundProduct1->merchant_data->sale_price);

        $this->assertNotNull($foundProduct2->merchant_data);
        $this->assertEquals($pm2->id, $foundProduct2->merchant_data->id);
        $this->assertEquals(149.99, $foundProduct2->merchant_data->price);

        // Check relationships are loaded
        $this->assertNotNull($foundProduct1->brand);
        $this->assertNotNull($foundProduct1->category);
        $this->assertNotEmpty($foundProduct1->images);
    }

    public function test_get_products_by_merchant_returns_empty_collection_when_no_products(): void
    {
        // Arrange
        $merchant = $this->createMerchant();

        // Act
        $products = $this->repository->getProductsByMerchant($merchant->id);

        // Assert
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $products);
        $this->assertCount(0, $products);
    }

    public function test_get_products_by_merchant_filters_inactive_products(): void
    {
        // Arrange
        $merchant = $this->createMerchant();
        $activeProduct = $this->createProduct(['is_active' => true]);
        $inactiveProduct = $this->createProduct(['is_active' => false]);

        $this->createProductMerchant($activeProduct->id, ['merchant_id' => $merchant->id]);
        $this->createProductMerchant($inactiveProduct->id, ['merchant_id' => $merchant->id]);

        // Act
        $products = $this->repository->getProductsByMerchant($merchant->id);

        // Assert
        $this->assertCount(1, $products);
        $this->assertEquals($activeProduct->id, $products->first()->id);
    }

}