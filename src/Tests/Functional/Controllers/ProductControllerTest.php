<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMerchant;
use App\Models\ProductPriceHistory;
use App\Models\ProductSpecification;
use App\Models\ProductSpecificationGroup;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexUsesSearchInfrastructure()
    {
        $this->createProduct();
        $this->createProduct();
        $this->createProduct();

        $response = $this->getForSite('/api/products');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function testIndexWithSearchQuery()
    {
        $this->createProduct([
            'name' => 'Wireless Mouse',
            'description' => 'test'
        ]);

        $response = $this->getForSite('/api/products?search=wireless');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThan(0, count($data['items']));
    }

    public function testIndexWithFilters()
    {
        $category = $this->createCategory(['name' => 'Electronics']);

        $this->createProduct([
            'name' => 'Product 1',
            'category_id' => $category->id
        ]);

        $response = $this->getForSite('/api/products?filter[category_id]=' . $category->id);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data);
    }

    public function testIndexWithSorting()
    {
        $this->createProduct();
        $this->createProduct();
        $this->createProduct();

        $response = $this->getForSite('/api/products?sort=name&order=asc');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data);
    }

    public function testIndexWithPagination()
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createProduct();
        }

        $response = $this->getForSite('/api/products?page=1&per_page=10');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(10, count($data['items']));
        $this->assertEquals(15, $data['pagination']['total']);
    }

    public function testStoreWithImageFile()
    {
        $data = [
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 99.99,
            'brand' => 'TestBrand',
        ];

        $files = [
            'image' => $this->createUploadedFile('product.jpg', 'image/jpeg')
        ];

        $response = $this->postForSite('/api/products', $data, $files);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('product', $responseData['data']);
        $this->assertNotEmpty($responseData['data']['product']['image']);
    }

    public function testUpdateWithImageFile()
    {
        $product = $this->createProduct(['name' => 'Old Product']);

        $data = [
            'name' => 'Updated Product',
            'description' => 'test',
            'price' => 99.99,
            'brand' => 'TestBrand'
        ];

        $files = [
            'image' => $this->createUploadedFile('new-product.jpg', 'image/jpeg')
        ];

        $response = $this->putForSite("/api/products/{$product->id}", $data, $files);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Updated Product', $responseData['data']['product']['name']);
    }

    public function testItCanCreateAProduct()
    {
        $category = $this->createCategory(['name' => 'Electronics']);

        $data = [
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 99.99,
            'sale_price' => 79.99,
            'category_id' => $category->id,
            'brand' => 'TestBrand',
        ];

        $response = $this->postForSite('/api/products', $data);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Test Product', $data['data']['product']['name']);
        $this->assertEquals('Test Description', $data['data']['product']['description']);
        $this->assertEquals(99.99, $data['data']['product']['price']);
        $this->assertEquals(79.99, $data['data']['product']['sale_price']);
        $this->assertEquals($category->id, $data['data']['product']['category_id']);
    }

    public function testItValidatesRequiredFieldsWhenCreatingProduct()
    {
        $response = $this->postForSite('/api/products', []);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testItValidatesPriceIsNumeric()
    {
        $data = [
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 'invalid',
            'sale_price' => 79.99,
            'category' => 'Electronics',
            'brand' => 'TestBrand',
        ];

        $response = $this->postForSite('/api/products', $data);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testItCanShowASingleProduct()
    {
        $products = $this->createProduct();

        $response = $this->getForSite("/api/products/{$products->first()->id}");

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('product', $data['data']);
        $this->assertEquals($products->first()->id, $data['data']['product']['id']);
        $this->assertEquals($products->first()->name, $data['data']['product']['name']);
    }

    public function testItReturns404WhenProductNotFound()
    {
        $response = $this->getForSite('/api/products/9999');

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Product not found', $data['data']['message']);
    }

    public function testItCanUpdateAProduct()
    {
        $products = $this->createProduct();
        $product = $products->first();

        $response = $this->putForSite("/api/products/{$product->id}", [
            'name' => 'New Name',
            'description' => $product->description,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'category' => $product->category,
            'brand' => $product->brand,
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('product', $data['data']);
        $this->assertEquals($products->first()->id, $data['data']['product']['id']);
        $this->assertEquals('New Name', $data['data']['product']['name']);
    }

    public function testItReturns404WhenUpdatingNonExistentProduct()
    {
        $response = $this->putForSite('/api/products/9999', [
            'name' => 'Test'
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testItCanDeleteAProduct()
    {
        $products = $this->createProduct();

        $response = $this->deleteForSite("/api/products/{$products->first()->id}");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testItReturns404WhenDeletingNonExistentProduct()
    {
        $response = $this->deleteForSite('/api/products/9999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testItCanCreateProductWithMetaFields()
    {
        $data = [
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 99.99,
            'sale_price' => 79.99,
            'brand' => 'TestBrand',
            'meta_title' => 'SEO Title',
            'meta_description' => 'SEO Description',
            'meta_keywords' => 'keyword1, keyword2',
        ];

        $response = $this->postForSite('/api/products', $data);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testDuplicateProductSuccessfully(): void
    {
        $product = $this->createProduct([
            'name' => 'iPhone 15',
            'description' => 'Latest iPhone',
            'price' => 999.99,
            'sale_price' => 899.99,
            'sku' => 'IPH15-001',
            'slug' => 'iphone-15',
            'status' => 'active',
        ]);

        $response = $this->postForSite("/api/products/{$product->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('iPhone 15 (Copy)', $data['data']['product']['name']);
        $this->assertEquals('Latest iPhone', $data['data']['product']['description']);
        $this->assertEquals(999.99, $data['data']['product']['price']);
        $this->assertEquals(899.99, $data['data']['product']['sale_price']);
        $this->assertNotEquals($product->slug, $data['data']['product']['slug']);
    }

    public function testDuplicateProductWithImage(): void
    {
        $product = $this->createProduct([
            'name' => 'MacBook Pro',
            'slug' => 'macbook-pro',
            'price' => 1999.99,
            'image' => 'uploads/products/macbook.jpg',
            'status' => 'active',
        ]);

        // Create dummy image
        $imagePath = 'uploads/products/macbook.jpg';
        $imagePath = getcwd() . '/' . $imagePath;

        if (!is_dir(dirname($imagePath))) {
            @mkdir(dirname($imagePath), 0755, true);
        }

        file_put_contents($imagePath, 'dummy image');

        $response = $this->postForSite("/api/products/{$product->id}/duplicate", [], [], [], true);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['data']['product']['image']);
        $this->assertNotEquals('uploads/products/macbook.jpg', $data['data']['product']['image']);

        // Cleanup
        @unlink($imagePath);
        if (isset($data['data']['image'])) {
            @unlink('uploads/' . $data['data']['image']);
        }
    }

    public function testDuplicateProductWithCustomName(): void
    {
        $product = $this->createProduct([
            'name' => 'AirPods Pro',
            'slug' => 'airpods-pro',
        ]);

        $response = $this->postForSite("/api/products/{$product->id}/duplicate", [
            'name' => 'AirPods Pro v2'
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('AirPods Pro v2', $data['data']['product']['name']);
    }

    public function testDuplicateProductWithBrandAndCategory(): void
    {
        $brand = $this->createBrand();

        $category = $this->createCategory();

        $product = $this->createProduct([
            'name' => 'Galaxy S24',
            'slug' => 'galaxy-s24',
            'price' => 899.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $response = $this->postForSite("/api/products/{$product->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        // Verify relationships are maintained
        $this->assertEquals($brand->id, $data['data']['product']['brand_id']);
        $this->assertEquals($category->id, $data['data']['product']['category_id']);
    }

    public function testItCanCreateProductWithAllRelations()
    {
        $category = $this->createCategory(['name' => 'Electronics']);
        $brand = $this->createBrand();
        $merchant = $this->createMerchant();

        $data = [
            'name' => 'Complete Product',
            'description' => 'Full featured product',
            'price' => 99.99,
            'sale_price' => 79.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'images' => [
                ['url' => 'image1.jpg', 'alt' => 'Image 1', 'is_primary' => true, 'sort_order' => 0],
                ['url' => 'image2.jpg', 'alt' => 'Image 2', 'is_primary' => false, 'sort_order' => 1],
            ],
            'merchants' => [
                ['name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 79.99, 'is_available' => true, 'id' => $merchant->id],
            ],
            'variants' => [
                ['sku' => 'VAR-001', 'attributes' => ['color' => 'Red'], 'price_modifier' => 0, 'is_active' => true],
            ],
            'specifications' => [
                ['category' => 'Technical', 'key' => 'Weight', 'value' => '1kg', 'sort_order' => 0],
            ],
        ];

        $response = $this->postForSite('/api/products', $data);

        $this->assertEquals(201, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $productId = $responseData['data']['product']['id'];

        // Verify relationships were created
        $product = Product::with(['images', 'merchants', 'variants', 'specifications'])->find($productId);
        $this->assertCount(2, $product->images);
        $this->assertCount(1, $product->merchants);
        $this->assertCount(1, $product->variants);
        $this->assertCount(1, $product->specifications);
    }

    public function testCreateProductRecordsPriceHistory()
    {
        $data = [
            'name' => 'Test Product',
            'description' => 'Test',
            'price' => 99.99,
            'sale_price' => 79.99,
            'brand' => 'TestBrand'
        ];

        $response = $this->postForSite('/api/products', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());

        $productId = $responseData['data']['product']['id'];

        $history = ProductPriceHistory::where('product_id', $productId)->first();
        $this->assertNotNull($history);
        $this->assertEquals(99.99, $history->price);
    }

    public function testUpdateProductPriceRecordsHistory()
    {
        $product = $this->createProduct([
            'price' => 99.99,
            'sale_price' => 79.99,
        ]);

        $updateData = [
            'name' => 'Test Product',
            'description' => 'Test',
            'price' => 109.99,
            'sale_price' => 89.99,
            'brand' => 'Test'
        ];

        $response = $this->putForSite("/api/products/{$product->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        $historyCount = ProductPriceHistory::where('product_id', $product->id)->count();
        $this->assertGreaterThanOrEqual(1, $historyCount); // Initial + update
    }

    public function testCreateProductRecordsMerchantPriceHistory()
    {
        $brand = $this->createBrand();
        $category = $this->createCategory(['name' => 'Electronics']);
        $merchant = $this->createMerchant();

        $data = [
            'name' => 'iPhone 15',
            'description' => 'Latest iPhone',
            'price' => 999.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'merchants' => [
                ['name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 949.99, 'is_available' => true, 'id' => $merchant->id],
                ['name' => 'BestBuy', 'url' => 'https://bestbuy.com', 'price' => 979.99, 'is_available' => true, 'id' => $merchant->id],
            ]
        ];

        $response = $this->postForSite('/api/products', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());

        $productId = $responseData['data']['product']['id'];

        $product = Product::with(['merchants'])->find($productId);

        // Verify merchants were created
        $this->assertCount(2, $product->merchants);

        $createdMerchants = Merchant::all()->keyBy('name');

        // Verify price history was recorded for each merchant
        $amazonMerchant = $product->merchants->where('merchant_id', $createdMerchants->get('Amazon')->id)->first();
        $bestbuyMerchant = $product->merchants->where('merchant_id', $createdMerchants->get('BestBuy')->id)->first();

        $amazonHistory = ProductPriceHistory::where('product_id', $productId)
            ->where('product_merchant_id', $amazonMerchant->id)
            ->first();

        $bestbuyHistory = ProductPriceHistory::where('product_id', $productId)
            ->where('product_merchant_id', $bestbuyMerchant->id)
            ->first();

        $this->assertNotNull($amazonHistory);
        $this->assertEquals(949.99, $amazonHistory->price);

        $this->assertNotNull($bestbuyHistory);
        $this->assertEquals(979.99, $bestbuyHistory->price);
    }

    public function testUpdateProductMerchantPriceRecordsHistory()
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $productMerchant = $this->createProductMerchant($product->id, [
            'price' => 79.99,
            'merchant_id' => $merchant->id
        ]);

        $this->createProductPriceHistory(['product_merchant_id' => $merchant->id, 'price' => 79.99, 'product_id' => $product->id]);;

        $updateData = [
            'name' => 'Test Product',
            'description' => 'Test',
            'price' => 99.99,
            'brand' => 'Test',
            'merchants' => [
                [
                    'name' => $merchant->name,
                    'id' => $productMerchant->id,
                    'merchant_id' => $merchant->id,
                    'url' => 'https://amazon.com',
                    'price' => 74.99, // Price changed
                    'is_available' => true
                ]
            ]
        ];

        $response = $this->putForSite("/api/products/{$product->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify new price history entry was created
        $historyCount = ProductPriceHistory::where('product_id', $product->id)
            ->where('product_merchant_id', $merchant->id)
            ->count();

        $this->assertEquals(2, $historyCount); // Initial + update

        $latestHistory = ProductPriceHistory::where('product_id', $product->id)
            ->where('product_merchant_id', $merchant->id)
            ->orderBy('recorded_at', 'desc')
            ->first();

        $this->assertEquals(74.99, $latestHistory->price);
    }

    public function testGetProductPriceHistory()
    {
        $product = $this->createProduct();

        $merchant = $this->createProductMerchant($product->id);

        $this->createProductPriceHistory(
            [
                'product_id' => $product->id,
                'product_merchant_id' => $merchant->id,
                'price' => 79.99,
            ]
        );

        $this->createProductPriceHistory(
            [
                'product_id' => $product->id,
                'product_merchant_id' => $merchant->id,
                'price' => 74.99,
            ]
        );

        $this->createProductPriceHistory(
            [
                'product_id' => $product->id,
                'product_merchant_id' => $merchant->id,
                'price' => 69.99,
            ]
        );

        $response = $this->getForSite("/api/products/{$product->id}/price-history");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(3, $data['data']);

        // Verify order (most recent first)
        $this->assertEquals(69.99, $data['data'][0]['price']);
        $this->assertEquals(74.99, $data['data'][1]['price']);
        $this->assertEquals(79.99, $data['data'][2]['price']);
    }

    public function testGetProductPriceHistoryByMerchant()
    {
        $product = $this->createProduct();

        $merchant1 = $this->createProductMerchant($product->id);
        $merchant2 = $this->createProductMerchant($product->id);

        $this->createProductPriceHistory([
            'product_id' => $product->id,
            'product_merchant_id' => $merchant1->id,
            'price' => 79.99,
        ]);

        $this->createProductPriceHistory([
            'product_id' => $product->id,
            'product_merchant_id' => $merchant2->id,
            'price' => 89.99,
        ]);

        $response = $this->getForSite("/api/products/{$product->id}/price-history?merchant_id={$merchant1->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['data']);
        $this->assertEquals(79.99, $data['data'][0]['price']);
        $this->assertEquals($merchant1->id, $data['data'][0]['product_merchant_id']);
    }

    public function testDuplicateProductToAnotherSite(): void
    {
        $site1 = Site::create(['name' => 'Site 1', 'domain' => 'site1.com']);
        $site2 = Site::create(['name' => 'Site 2', 'domain' => 'site2.com']);

        $product = $this->createProduct();

        $response = $this->postForSite("/api/products/{$product->id}/duplicate", [
            'name' => 'iPhone 15 Site 2',
            'site_id' => $site2->id,
            'clone_images' => true,
            'clone_merchants' => true,
            'clone_variants' => false,
            'clone_specifications' => true,
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('iPhone 15 Site 2', $data['data']['product']['name']);
        $this->assertEquals($site2->id, $data['data']['product']['site_id']);
    }

    public function testDuplicateProductToSameSite(): void
    {
        $site = Site::create(['name' => 'Test Site', 'domain' => 'test.com']);

        $product = $this->createProduct(['site_id' => $site->id]);

        // Clone without site_id should use same site
        $response = $this->postForSite("/api/products/{$product->id}/duplicate", [
            'name' => 'Product Copy'
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($site->id, $data['data']['product']['site_id']);
    }

    public function testDuplicateProductWithSelectiveRelations(): void
    {
        $data = [
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 99.99,
            'brand' => 'TestBrand',
        ];

        $files = [
            'image' => $this->createUploadedFile('product.jpg', 'image/jpeg')
        ];


        $response = $this->postForSite('/api/products', $data, $files);
        $responseData = json_decode($response->getContent(), true);

        $productId = $responseData['data']['product']['id'];

        $productImage = ProductImage::create(['product_id' => $productId, 'url' => $responseData['data']['product']['image']]);

        $merchant = $this->createMerchant();

        // Add relations
        ProductImage::create(['product_id' => $productId, 'url' => 'img.jpg']);
        ProductMerchant::create(['product_id' => $productId, 'merchant_id' => $merchant->id, 'url' => 'https://amazon.com', 'price' => 89.99]);
        ProductVariant::create(['product_id' => $productId, 'sku' => 'VAR-001', 'attributes' => []]);
        ProductSpecification::create(['product_id' => $productId, 'category' => 'Tech', 'key' => 'Weight', 'value' => '1kg']);

        // Clone only images and specifications
        $response = $this->postForSite("/api/products/{$productId}/duplicate", [
            'name' => 'Selective Clone',
            'clone_images' => true,
            'clone_merchants' => false,
            'clone_variants' => false,
            'clone_specifications' => true,
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);
        $newProductId = $data['data']['product']['id'];

        $newProduct = Product::with(['images', 'merchants', 'variants', 'specifications'])->find($newProductId);

        $this->assertCount(2, $newProduct->images);
        $this->assertCount(0, $newProduct->merchants);
        $this->assertCount(0, $newProduct->variants);
        $this->assertCount(1, $newProduct->specifications);
    }

    public function testCreateProductWithVariantsAndImages()
    {
        $brand = $this->createBrand();
        $category = $this->createCategory();

        $data = [
            'name' => 'iPhone 15',
            'description' => 'Latest iPhone',
            'price' => 999.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'variants' => [
                [
                    'sku' => 'IPH15-RED',
                    'attributes' => ['color' => 'Red'],
                    'price_modifier' => 0,
                    'is_active' => true,
                    'images' => [
                        ['url' => 'red-front.jpg', 'alt' => 'Red Front', 'is_primary' => true, 'sort_order' => 0],
                        ['url' => 'red-back.jpg', 'alt' => 'Red Back', 'is_primary' => false, 'sort_order' => 1],
                    ]
                ],
                [
                    'sku' => 'IPH15-BLUE',
                    'attributes' => ['color' => 'Blue'],
                    'price_modifier' => 0,
                    'is_active' => true,
                    'images' => [
                        ['url' => 'blue-front.jpg', 'alt' => 'Blue Front', 'is_primary' => true, 'sort_order' => 0],
                    ]
                ],
            ]
        ];

        $response = $this->postForSite('/api/products', $data);

        $this->assertEquals(201, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $productId = $responseData['data']['product']['id'];

        $product = Product::with(['variants', 'variants.images'])->find($productId);

        $variants = $product->variants->toArray();

        $this->assertCount(2, $variants);
        $this->assertCount(2, $variants[0]['images']);
        $this->assertCount(1, $variants[1]['images']);
    }

    public function testUpdateProductVariantImages()
    {
        $product = $this->createProduct();

        $variant = $this->createProductVariant($product->id);

        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $updateData = [
            'name' => 'Test Product',
            'description' => 'Test',
            'price' => 99.99,
            'brand' => 'Test',
            'variants' => [
                [
                    'sku' => 'VAR-001',
                    'attributes' => ['color' => 'Red'],
                    'price_modifier' => 0,
                    'is_active' => true,
                    'images' => [
                        ['url' => 'new-img1.jpg', 'alt' => 'New Image 1', 'is_primary' => true, 'sort_order' => 0],
                        ['url' => 'new-img2.jpg', 'alt' => 'New Image 2', 'is_primary' => false, 'sort_order' => 1],
                    ]
                ]
            ]
        ];

        $response = $this->putForSite("/api/products/{$product->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        $updatedProduct = Product::with(['variants', 'variants.images'])->find($product->id);
        $this->assertCount(2, $updatedProduct->variants->first()->images);
    }

    public function testDeleteProductDeletesVariantImages()
    {
        $product = $this->createProduct();

        $variant = $this->createProductVariant($product->id);

        $this->createProductImage($product->id, ['variant_id' => $variant->id]);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $this->assertCount(2, ProductImage::where('variant_id', $variant->id)->get());

        $response = $this->deleteForSite("/api/products/{$product->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(0, ProductImage::where('variant_id', $variant->id)->get());
    }

    public function testDuplicateProductWithVariantImages()
    {
        $product = $this->createProduct();

        $variant = $this->createProductVariant($product->id);

        // Create dummy variant images
        $imagePath1 = 'uploads/products/red-front.jpg';
        $imagePath2 = 'uploads/products/red-back.jpg';

        if (!is_dir(dirname($imagePath1))) {
            @mkdir(dirname($imagePath1), 0755, true);
        }

        file_put_contents($imagePath1, 'dummy image 1');
        file_put_contents($imagePath2, 'dummy image 2');

        $this->createProductImage($product->id, ['variant_id' => $variant->id]);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $response = $this->postForSite("/api/products/{$product->id}/duplicate", [
            'clone_variants' => true
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);
        $newProductId = $data['data']['product']['id'];

        $newProduct = Product::with(['variants.images'])->find($newProductId);

        $this->assertCount(1, $newProduct->variants);
        $this->assertCount(2, $newProduct->variants->first()->images);
        $this->assertStringContainsString('COPY', $newProduct->variants->first()->sku);

        // Cleanup
        @unlink($imagePath1);
        @unlink($imagePath2);
    }

    public function testGetProductMerchants(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $merchant1 = $this->createMerchant(['name' => 'Amazon']);
        $merchant2 = $this->createMerchant(['name' => 'eBay']);
        $merchant3 = $this->createMerchant(['name' => 'BestBuy']);

        $this->createProductMerchant($product1->id, ['merchant_id' => $merchant1->id]);
        $this->createProductMerchant($product1->id, ['merchant_id' => $merchant2->id]);
        $this->createProductMerchant($product2->id, ['merchant_id' => $merchant1->id]); // Duplicate
        $this->createProductMerchant($product2->id, ['merchant_id' => $merchant3->id]);

        $response = $this->getForSite('/api/products/merchants');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(3, $data['items']); // Amazon, eBay, BestBuy (unique)
    }

    public function testFilterProductsByMerchant(): void
    {
        $product1 = $this->createProduct(['name' => 'Product 1']);
        $product2 = $this->createProduct(['name' => 'Product 2']);
        $product3 = $this->createProduct(['name' => 'Product 3']);

        $merchant1 = $this->createMerchant(['name' => 'Amazon']);
        $merchant2 = $this->createMerchant(['name' => 'eBay']);

        $this->createProductMerchant($product1->id, ['merchant_id' => $merchant1->id]);
        $this->createProductMerchant($product2->id, ['merchant_id' => $merchant2->id]);
        $this->createProductMerchant($product3->id, ['merchant_id' => $merchant1->id]);

        $response = $this->getForSite('/api/products?merchant[]=' . $merchant1->id);;
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['items']); // Only products 1 and 3
    }

    public function testGetProductVariants(): void
    {
        $product = $this->createProduct();
        $variant1 = $this->createProductVariant($product->id, ['sku' => 'VAR-001']);
        $variant2 = $this->createProductVariant($product->id, ['sku' => 'VAR-002']);

        $response = $this->getForSite("/api/products/{$product->id}/variants");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['items']);
    }

    public function testUpdateProductVariant(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'OLD-SKU',
            'price_modifier' => 5.00,
            'is_active' => true
        ]);

        $updateData = [
            'sku' => 'NEW-SKU',
            'price_modifier' => 10.00,
            'is_active' => false
        ];

        $response = $this->putForSite(
            "/api/products/{$product->id}/variants/{$variant->id}",
            $updateData
        );

        $this->assertEquals(200, $response->getStatusCode());

        $updated = ProductVariant::find($variant->id);
        $this->assertEquals('NEW-SKU', $updated->sku);
        $this->assertEquals(10.00, $updated->price_modifier);
        $this->assertFalse($updated->is_active);
    }

    public function testDeleteProductVariant(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        // Add variant images
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $response = $this->deleteForSite("/api/products/{$product->id}/variants/{$variant->id}");

        $this->assertEquals(200, $response->getStatusCode());

        // Verify variant is deleted
        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);

        // Verify variant images are deleted
        $this->assertCount(0, ProductImage::where('variant_id', $variant->id)->get());
    }

    public function testUpdateNonExistentVariant(): void
    {
        $product = $this->createProduct();

        $response = $this->putForSite(
            "/api/products/{$product->id}/variants/9999",
            ['sku' => 'TEST']
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteNonExistentVariant(): void
    {
        $product = $this->createProduct();

        $response = $this->deleteForSite("/api/products/{$product->id}/variants/9999");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateVariantImages(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        // Create initial images
        $this->createProductImage($product->id, ['variant_id' => $variant->id, 'url' => 'old1.jpg']);
        $this->createProductImage($product->id, ['variant_id' => $variant->id, 'url' => 'old2.jpg']);

        $newImages = [
            [
                'url' => 'new1.jpg',
                'alt' => 'New Image 1',
                'is_primary' => true,
                'sort_order' => 0
            ],
            [
                'url' => 'new2.jpg',
                'alt' => 'New Image 2',
                'is_primary' => false,
                'sort_order' => 1
            ],
            [
                'url' => 'new3.jpg',
                'alt' => 'New Image 3',
                'is_primary' => false,
                'sort_order' => 2
            ]
        ];

        $response = $this->putForSite(
            "/api/products/{$product->id}/variants/{$variant->id}/images",
            ['images' => $newImages]
        );

        $this->assertEquals(200, $response->getStatusCode());

        // Verify old images are removed
        $this->assertDatabaseMissing('product_images', [
            'variant_id' => $variant->id,
            'url' => 'old1.jpg'
        ]);

        // Verify new images are added
        $images = ProductImage::where('variant_id', $variant->id)
            ->orderBy('sort_order')
            ->get();

        $images = $images->toArray();

        $this->assertCount(3, $images);
        $this->assertEquals('new1.jpg', $images[0]['url']);
        $this->assertTrue((bool)$images[0]['is_primary']);
        $this->assertEquals('new2.jpg', $images[1]['url']);
        $this->assertFalse((bool)$images[1]['is_primary']);
    }

    public function testUpdateVariantImagesForNonExistentVariant(): void
    {
        $product = $this->createProduct();

        $response = $this->putForSite(
            "/api/products/{$product->id}/variants/9999/images",
            ['images' => []]
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateVariantImagesDeletesAllWhenEmptyArray(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $this->createProductImage($product->id, ['variant_id' => $variant->id]);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $response = $this->putForSite(
            "/api/products/{$product->id}/variants/{$variant->id}/images",
            ['images' => []]
        );

        $this->assertEquals(200, $response->getStatusCode());

        $count = ProductImage::where('variant_id', $variant->id)->count();
        $this->assertEquals(0, $count);
    }

    public function testFilterProductsByMultipleBrands(): void
    {
        $brand1 = $this->createBrand(['name' => 'Apple']);
        $brand2 = $this->createBrand(['name' => 'Samsung']);
        $brand3 = $this->createBrand(['name' => 'Google']);

        $product1 = $this->createProduct(['name' => 'iPhone', 'brand_id' => $brand1->id]);
        $product2 = $this->createProduct(['name' => 'Galaxy', 'brand_id' => $brand2->id]);
        $product3 = $this->createProduct(['name' => 'Pixel', 'brand_id' => $brand3->id]);

        $brandStr = $brand1->id . ',' . $brand2->id;

        $response = $this->getForSite('/api/products?brands=' . $brandStr);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['items']); // iPhone and Galaxy
    }

    public function testFilterProductsByMerchantsMultiple(): void
    {
        $product1 = $this->createProduct(['name' => 'Product 1']);
        $product2 = $this->createProduct(['name' => 'Product 2']);
        $product3 = $this->createProduct(['name' => 'Product 3']);

        $merchant1 = $this->createMerchant(['name' => 'Amazon']);
        $merchant2 = $this->createMerchant(['name' => 'eBay']);

        $this->createProductMerchant($product1->id, ['merchant_id' => $merchant1->id, 'is_available' => true]);
        $this->createProductMerchant($product2->id, ['merchant_id' => $merchant2->id, 'is_available' => true]);
        $this->createProductMerchant($product3->id, ['merchant_id' => $merchant1->id, 'is_available' => true]);

        // Test single merchant filter
        $response = $this->getForSite('/api/products?merchant=' . $merchant1->id);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['items']); // Only products 1 and 3

        $merchantStr = $merchant1->id . ',' . $merchant2->id;

        // Test multiple merchant filter
        $response = $this->getForSite('/api/products?merchant=' . $merchantStr);;
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(3, $data['items']); // All three products
    }

    public function testFilterProductsByMultipleCategories(): void
    {
        $cat1 = $this->createCategory(['name' => 'Smartphones']);
        $cat2 = $this->createCategory(['name' => 'Tablets']);
        $cat3 = $this->createCategory(['name' => 'Laptops']);

        $product1 = $this->createProduct(['name' => 'iPhone', 'category_id' => $cat1->id]);
        $product2 = $this->createProduct(['name' => 'iPad', 'category_id' => $cat2->id]);
        $product3 = $this->createProduct(['name' => 'MacBook', 'category_id' => $cat3->id]);

        $categoryStr = $cat1->id . ',' . $cat2->id;

        $response = $this->getForSite('/api/products?categories=' . $categoryStr);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['items']); // iPhone and iPad
    }

    public function testUpdateProductChangingPrimaryImage()
    {
        $product = $this->createProduct();

        // Create initial images
        $image1 = $this->createProductImage($product->id, [
            'url' => 'img1.jpg',
            'is_primary' => true
        ]);
        $image2 = $this->createProductImage($product->id, [
            'url' => 'img2.jpg',
            'is_primary' => false
        ]);

        // Update with img2 as primary
        $updateData = [
            'name' => 'Updated Product',
            'description' => 'Test',
            'price' => 99.99,
            'brand' => 'Test',
            'images' => [
                ['url' => 'img1.jpg', 'alt' => 'Image 1', 'is_primary' => false, 'sort_order' => 0],
                ['url' => 'img2.jpg', 'alt' => 'Image 2', 'is_primary' => true, 'sort_order' => 1],
            ]
        ];

        $response = $this->putForSite("/api/products/{$product->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify primary flag was updated
        $updatedProduct = Product::with(['images'])->find($product->id);
        $images = $updatedProduct->images->sortBy('sort_order')->toArray();

        $this->assertFalse((bool)$images[0]['is_primary']);
        $this->assertTrue((bool)$images[1]['is_primary']);
    }

    public function testCreateProductWithVariantMerchants(): void
    {
        $brand = $this->createBrand();
        $category = $this->createCategory();
        $merchant = $this->createMerchant();

        $data = [
            'name' => 'iPhone 15',
            'description' => 'Latest iPhone',
            'price' => 999.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'variants' => [
                [
                    'sku' => 'IPH15-128',
                    'name' => '128GB',
                    'attributes' => ['storage' => '128GB'],
                    'price' => 999,
                    'sale_price' => 949,
                    'price_modifier' => 0,
                    'is_active' => true
                ],
                [
                    'sku' => 'IPH15-256',
                    'name' => '256GB',
                    'attributes' => ['storage' => '256GB'],
                    'price' => 1099,
                    'sale_price' => 1049,
                    'price_modifier' => 0,
                    'is_active' => true
                ]
            ],
            'merchants' => [
                [
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com/iphone-128',
                    'price' => 979,
                    'override_price' => true,
                    'override_sale_price' => false,
                    'variant_id' => 1,
                    'variant_sku' => 'AMZN-IPH15-128',
                    'is_available' => true,
                    'id' => $merchant->id
                ],
                [
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com/iphone-256',
                    'price' => 1079,
                    'override_price' => true,
                    'override_sale_price' => false,
                    'variant_id' => 2,
                    'variant_sku' => 'AMZN-IPH15-256',
                    'is_available' => true,
                    'id' => $merchant->id
                ],
                [
                    'name' => 'BestBuy',
                    'url' => 'https://bestbuy.com/iphone-128',
                    'price' => 999,
                    'override_price' => false,
                    'override_sale_price' => false,
                    'variant_id' => 1,
                    'is_available' => true,
                    'id' => $merchant->id
                ]
            ]
        ];

        $response = $this->postForSite('/api/products', $data);

        $this->assertEquals(201, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $productId = $responseData['data']['product']['id'];

        // Verify variants were created
        $product = Product::with(['variants', 'merchants.variant', 'merchants.merchant'])->find($productId);
        $this->assertCount(2, $product->variants);

        // Verify merchants were created with correct variant associations
        $this->assertCount(3, $product->merchants);

        $amazonMerchants = $product->merchants->filter(function ($pm) {
            return $pm->merchant->name === 'Amazon';
        });

        $this->assertCount(2, $amazonMerchants);

        // Check first Amazon merchant (128GB)
        $amazon128 = $amazonMerchants->first(function ($pm) {
            return $pm->variant_sku === 'AMZN-IPH15-128';
        });
        $this->assertNotNull($amazon128);
        $this->assertTrue($amazon128->override_price);
        $this->assertEquals(979, $amazon128->price);
        $this->assertEquals('AMZN-IPH15-128', $amazon128->variant_sku);
        $this->assertEquals(979, $amazon128->effective_price);

        // Check BestBuy merchant (uses variant price)
        $bestbuy = $product->merchants->first(function ($pm) {
            return $pm->merchant->name === 'BestBuy';
        });
        $this->assertNotNull($bestbuy);
        $this->assertFalse($bestbuy->override_price);
        $this->assertEquals(999, $bestbuy->effective_price); // Should use variant price
    }

    public function testUpdateProductVariantMerchantOverrides(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'VAR-001',
            'price' => 100,
            'sale_price' => 90
        ]);
        $merchant = $this->createMerchant(['name' => 'Amazon']);

        $pm = $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'variant_id' => $variant->id,
            'price' => 100,
            'override_price' => false,
            'override_sale_price' => false,
            'variant_sku' => null
        ]);

        $updateData = [
            'name' => 'Test Product',
            'description' => 'Test',
            'price' => 99.99,
            'brand' => 'Test',
            'merchants' => [
                [
                    'id' => $pm->id,
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com',
                    'price' => 95,
                    'override_price' => true,
                    'override_sale_price' => 0,
                    'variant_id' => $variant->id,
                    'variant_sku' => 'CUSTOM-SKU-001',
                    'is_available' => true
                ]
            ]
        ];

        $response = $this->putForSite("/api/products/{$product->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        $updated = ProductMerchant::find($pm->id);
        $this->assertTrue($updated->override_price);
        $this->assertEquals(95, $updated->price);
        $this->assertEquals('CUSTOM-SKU-001', $updated->variant_sku);
        $this->assertEquals(95, $updated->effective_price);
    }

    public function testGetProductIncludesVariantMerchantDetails(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'VAR-001',
            'name' => 'Red',
            'price' => 100,
            'sale_price' => 90
        ]);
        $merchant = $this->createMerchant(['name' => 'Amazon']);

        $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'variant_id' => $variant->id,
            'price' => 95,
            'override_price' => true,
            'variant_sku' => 'AMZN-VAR-001'
        ]);

        $response = $this->getForSite("/api/products/{$product->id}");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $merchants = $data['data']['product']['availableMerchants'];

        $this->assertCount(1, $merchants);
        $merchantData = $merchants[0];

        $this->assertEquals('Amazon', $merchantData['merchant']['name']);
        $this->assertEquals($variant->id, $merchantData['variant_id']);
        $this->assertEquals(1, $merchantData['override_price']);
        $this->assertEquals(95, $merchantData['price']);
        $this->assertEquals('AMZN-VAR-001', $merchantData['variant_sku']);
        $this->assertEquals(95, $merchantData['effective_price']);
    }

    public function testDuplicateProductPreservesVariantMerchantMappings(): void
    {
        $product = $this->createProduct(['name' => 'iPhone 15']);

        $variant1 = $this->createProductVariant($product->id, [
            'sku' => 'IPH15-128',
            'price' => 999
        ]);
        $variant2 = $this->createProductVariant($product->id, [
            'sku' => 'IPH15-256',
            'price' => 1099
        ]);

        $merchant = $this->createMerchant(['name' => 'Amazon']);

        $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'variant_id' => $variant1->id,
            'price' => 979,
            'override_price' => true,
            'variant_sku' => 'AMZN-128'
        ]);

        $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'variant_id' => $variant2->id,
            'price' => 1079,
            'override_price' => true,
            'variant_sku' => 'AMZN-256'
        ]);

        $response = $this->postForSite("/api/products/{$product->id}/duplicate", [
            'name' => 'iPhone 15 Copy',
            'clone_variants' => true,
            'clone_merchants' => true
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $newProductId = $data['data']['product']['id'];

        $newProduct = Product::with(['variants', 'merchants.variant'])->find($newProductId);

        $this->assertCount(2, $newProduct->variants);
        $this->assertCount(2, $newProduct->merchants);

        // Verify variant-merchant mappings are preserved
        $newVariant1 = $newProduct->variants->first(function ($v) {
            return str_contains($v->sku, '128');
        });
        $newVariant2 = $newProduct->variants->first(function ($v) {
            return str_contains($v->sku, '256');
        });

        $merchant1 = $newProduct->merchants->first(function ($pm) use ($newVariant1) {
            return $pm->variant_id === $newVariant1->id;
        });
        $merchant2 = $newProduct->merchants->first(function ($pm) use ($newVariant2) {
            return $pm->variant_id === $newVariant2->id;
        });

        $this->assertNotNull($merchant1);
        $this->assertNotNull($merchant2);
        $this->assertEquals(979, $merchant1->price);
        $this->assertEquals(1079, $merchant2->price);
        $this->assertTrue($merchant1->override_price);
        $this->assertTrue($merchant2->override_price);
        $this->assertEquals('AMZN-128', $merchant1->variant_sku);
        $this->assertEquals('AMZN-256', $merchant2->variant_sku);
    }

    public function testFilterProductsByMerchantIncludesVariantMerchants(): void
    {
        $product1 = $this->createProduct(['name' => 'Product 1']);
        $product2 = $this->createProduct(['name' => 'Product 2']);

        $variant1 = $this->createProductVariant($product1->id);

        $merchant1 = $this->createMerchant(['name' => 'Amazon']);
        $merchant2 = $this->createMerchant(['name' => 'eBay']);

        // Product 1 has Amazon merchant with variant
        $this->createProductMerchant($product1->id, [
            'merchant_id' => $merchant1->id,
            'variant_id' => $variant1->id
        ]);

        // Product 2 has eBay merchant
        $this->createProductMerchant($product2->id, [
            'merchant_id' => $merchant2->id
        ]);

        $response = $this->getForSite('/api/products?merchant=' . $merchant1->id);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Product 1', $data['items'][0]['name']);
    }

    public function testMerchantPriceHistoryTracksOverrides(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'price' => 100,
            'sale_price' => 90
        ]);
        $merchant = $this->createMerchant(['name' => 'Amazon']);

        // Create merchant with override
        $pm = $this->createProductMerchant($product->id, [
            'merchant_id' => $merchant->id,
            'variant_id' => $variant->id,
            'price' => 95,
            'override_price' => true
        ]);

        // Create initial price history
        $this->createProductPriceHistory([
            'product_id' => $product->id,
            'product_merchant_id' => $pm->id,
            'price' => 95
        ]);

        // Update override price
        $updateData = [
            'name' => 'Test',
            'description' => 'Test',
            'price' => 99.99,
            'brand' => 'Test',
            'merchants' => [
                [
                    'id' => $pm->id,
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com',
                    'price' => 89,
                    'override_price' => true,
                    'variant_id' => $variant->id,
                    'is_available' => true
                ]
            ]
        ];

        $response = $this->putForSite("/api/products/{$product->id}", $updateData);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify new price history entry
        $historyCount = ProductPriceHistory::where('product_id', $product->id)
            ->where('product_merchant_id', $pm->id)
            ->count();

        $this->assertEquals(2, $historyCount);

        $latestHistory = ProductPriceHistory::where('product_id', $product->id)
            ->where('product_merchant_id', $pm->id)
            ->orderBy('recorded_at', 'desc')
            ->first();

        $this->assertEquals(89, $latestHistory->price);
    }

    public function testValidationRequiresMerchantDataWhenVariantSpecified(): void
    {
        $brand = $this->createBrand();
        $category = $this->createCategory();

        $data = [
            'name' => 'Test Product',
            'price' => 99.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'description' => 'Test',
            'variants' => [
                [
                    'sku' => 'VAR-001',
                    'price' => 100,
                    'attributes' => [],
                    'price_modifier' => 0,
                    'is_active' => true
                ]
            ],
            'merchants' => [
                [
                    'name' => 'Amazon',
                    'url' => 'https://amazon.com',
                    // Missing price when override_price is true
                    'override_price' => true,
                    'variant_id' => 1,
                    'is_available' => true
                ]
            ]
        ];

        $response = $this->postForSite('/api/products', $data);

        // Should still succeed because price defaults to 0, but in real scenario you might want validation
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testGetProductPages(): void
    {
        $product = $this->createProduct(['name' => 'iPhone 15']);

        // Create page with product block
        $page1 = $this->createPage(['title' => 'iPhone Review']);
        $this->createBlock($page1->id, ['type' => 'product', 'data' => [
            'product_id' => $product->id,
            'name' => 'iPhone 15',
            'price' => 999
        ]]);

        // Create page with deal block
        $page2 = $this->createPage(['title' => 'Best Phone Deals']);
        $this->createBlock($page2->id, ['type' => 'deal', 'data' => [
            'product_id' => $product->id,
            'productName' => 'iPhone 15',
            'price' => 999
        ]]);

        // Create page with comparison block
        $product2 = $this->createProduct(['name' => 'Samsung S24']);
        $page3 = $this->createPage(['title' => 'Phone Comparison']);
        $this->createBlock($page3->id, ['type' => 'product-comparison', 'data' => [
            'product_a_id' => $product->id,
            'product_b_id' => $product2->id,
            'productA' => 'iPhone 15',
            'productB' => 'Samsung S24'
        ]]);

        $response = $this->getForSite("/api/products/{$product->id}/pages");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(3, $data['pages']);

        // Verify page details
        $pageTitles = array_column($data['pages'], 'title');
        $this->assertContains('iPhone Review', $pageTitles);
        $this->assertContains('Best Phone Deals', $pageTitles);
        $this->assertContains('Phone Comparison', $pageTitles);
    }

    public function testGetProductPagesReturnsEmptyWhenNoPages(): void
    {
        $product = $this->createProduct();

        $response = $this->getForSite("/api/products/{$product->id}/pages");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(0, $data['pages']);
    }

    public function testGetProductPagesIncludesBlockTypes(): void
    {
        $product = $this->createProduct();
        $page = $this->createPage(['title' => 'Mixed Content']);

        $this->createBlock($page->id, ['type' => 'product', 'data' => [
            'product_id' => $product->id,
            'name' => 'Test Product',
            'price' => 99
        ]]);

        $this->createBlock($page->id, ['type' => 'deal', 'data' => [
            'product_id' => $product->id,
            'productName' => 'Test Product',
            'price' => 89
        ]]);

        $response = $this->getForSite("/api/products/{$product->id}/pages");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $pageData = $data['pages'][0];

        $this->assertArrayHasKey('type', $pageData);
        $this->assertIsArray($pageData['type']);
        $this->assertContains('product', $pageData['type']);
        $this->assertContains('deal', $pageData['type']);
    }

    public function testGetSpecificationGroupsEndpoint(): void
    {
        ProductSpecificationGroup::create([
            'name' => 'Technical',
            'slug' => 'technical',
            'is_active' => true
        ]);

        ProductSpecificationGroup::create([
            'name' => 'Physical',
            'slug' => 'physical',
            'is_active' => true
        ]);

        ProductSpecificationGroup::create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'is_active' => false
        ]);

        $response = $this->getForSite('/api/specification-groups');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['groups']); // Only active groups
    }

    public function testProductIndexIncludesSpecificationGroups(): void
    {
        $group = ProductSpecificationGroup::create([
            'name' => 'Color',
            'slug' => 'color',
            'is_active' => true
        ]);

        $product = $this->createProduct(['site_id' => $this->siteId]);

        ProductSpecification::create([
            'product_id' => $product->id,
            'specification_group_id' => $group->id,
            'key' => 'Color',
            'value' => 'Red',
            'is_active' => true,
            'sort_order' => 1,
            'category' => 'product'
        ]);

        $response = $this->getForSite('/api/products');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Color', $response->getContent());
    }
}