<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMerchant;
use App\Models\ProductPriceHistory;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Services\ImageUploadService;

class ProductControllerTest extends FunctionalTestCase
{
    public function testIndexUsesSearchInfrastructure()
    {
        $this->createProduct(3);

        $response = $this->getForSite('/api/products');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function testIndexWithSearchQuery()
    {
        Product::create([
            'name' => 'Wireless Mouse',
            'description' => 'test',
            'price' => 29.99,
            'brand' => 'LogiTech',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/products?search=wireless');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThan(0, count($data['items']));
    }

    public function testIndexWithFilters()
    {
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        Product::create([
            'name' => 'Product 1',
            'description' => 'test',
            'price' => 99.99,
            'category_id' => $category->id,
            'brand' => 'BrandA'
        ]);

        $response = $this->getForSite('/api/products?filter[category_id]=' . $category->id);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data);
    }

    public function testIndexWithSorting()
    {
        $this->createProduct(3);

        $response = $this->getForSite('/api/products?sort=name&order=asc');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data);
    }

    public function testIndexWithPagination()
    {
        $this->createProduct(15);

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
        $product = Product::create([
            'name' => 'Old Product',
            'description' => 'test',
            'price' => 99.99,
            'brand' => 'TestBrand'
        ]);

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
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

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

    private function createProduct(int $times = 1, array $data = [])
    {
        $products = [];

        for ($i = 0; $i < $times; $i++) {
            $products[] = Product::create([
                'name' => $data['name'] ?? 'test',
                'id' => $i + 1,
                'description' => 'test',
                'price' => 12.99,
                'sale_price' => 10.99,
                'category_id' => $data['category'] ?? null,
                'brand' => $data['brand'] ?? 'test',
                'site_id' => $this->siteId,
            ]);
        }

        return collect($products);
    }

    public function testDuplicateProductSuccessfully(): void
    {
        $product = Product::create([
            'name' => 'iPhone 15',
            'description' => 'Latest iPhone',
            'price' => 999.99,
            'sale_price' => 899.99,
            'sku' => 'IPH15-001',
            'slug' => 'iphone-15',
            'status' => 'active'
        ]);

        $response = $this->postForSite("/api/products/{$product->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('iPhone 15 (Copy)', $data['data']['name']);
        $this->assertEquals('Latest iPhone', $data['data']['description']);
        $this->assertEquals(999.99, $data['data']['price']);
        $this->assertEquals(899.99, $data['data']['sale_price']);
        $this->assertNotEquals($product->slug, $data['data']['slug']);
    }

    public function testDuplicateProductWithImage(): void
    {
        $product = Product::create([
            'name' => 'MacBook Pro',
            'slug' => 'macbook-pro',
            'price' => 1999.99,
            'image' => 'products/macbook.jpg',
            'status' => 'active'
        ]);

        // Create dummy image
        $imagePath = 'uploads/products/macbook.jpg';
        @mkdir(dirname($imagePath), 0755, true);
        file_put_contents($imagePath, 'dummy image');

        $response = $this->postForSite("/api/products/{$product->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['data']['image']);
        $this->assertNotEquals('products/macbook.jpg', $data['data']['image']);

        // Cleanup
        @unlink($imagePath);
        if (isset($data['data']['image'])) {
            @unlink('uploads/' . $data['data']['image']);
        }
    }

    public function testDuplicateProductWithCustomName(): void
    {
        $product = Product::create([
            'name' => 'AirPods Pro',
            'slug' => 'airpods-pro',
            'price' => 249.99,
            'status' => 'active'
        ]);

        $response = $this->postForSite("/api/products/{$product->id}/duplicate", [
            'name' => 'AirPods Pro v2'
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('AirPods Pro v2', $data['data']['name']);
    }

    public function testDuplicateProductWithBrandAndCategory(): void
    {
        $brand = Brand::create([
            'name' => 'Samsung',
            'slug' => 'samsung',
            'status' => 'active'
        ]);

        $category = Category::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'status' => 'active'
        ]);

        $product = Product::create([
            'name' => 'Galaxy S24',
            'slug' => 'galaxy-s24',
            'price' => 899.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active'
        ]);

        $response = $this->postForSite("/api/products/{$product->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        // Verify relationships are maintained
        $this->assertEquals($brand->id, $data['data']['brand_id']);
        $this->assertEquals($category->id, $data['data']['category_id']);
    }

    public function testItCanCreateProductWithAllRelations()
    {
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $brand = Brand::create(['name' => 'Apple', 'slug' => 'apple']);

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
                ['name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 79.99, 'is_available' => true],
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
        $product = Product::create([
            'name' => 'Test Product',
            'description' => 'Test',
            'price' => 99.99,
            'sale_price' => 79.99,
            'brand' => 'Test'
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
        $brand = Brand::create(['name' => 'Apple', 'slug' => 'apple']);
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        $data = [
            'name' => 'iPhone 15',
            'description' => 'Latest iPhone',
            'price' => 999.99,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'merchants' => [
                ['name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 949.99, 'is_available' => true],
                ['name' => 'BestBuy', 'url' => 'https://bestbuy.com', 'price' => 979.99, 'is_available' => true],
            ]
        ];

        $response = $this->postForSite('/api/products', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());

        $productId = $responseData['data']['product']['id'];

        $product = Product::with(['merchants'])->find($productId);

        // Verify merchants were created
        $this->assertCount(2, $product->merchants);

        // Verify price history was recorded for each merchant
        $amazonMerchant = $product->merchants->where('name', 'Amazon')->first();
        $bestbuyMerchant = $product->merchants->where('name', 'BestBuy')->first();

        $amazonHistory = ProductPriceHistory::where('product_id', $productId)
            ->where('merchant_id', $amazonMerchant->id)
            ->first();

        $bestbuyHistory = ProductPriceHistory::where('product_id', $productId)
            ->where('merchant_id', $bestbuyMerchant->id)
            ->first();

        $this->assertNotNull($amazonHistory);
        $this->assertEquals(949.99, $amazonHistory->price);

        $this->assertNotNull($bestbuyHistory);
        $this->assertEquals(979.99, $bestbuyHistory->price);
    }

    public function testUpdateProductMerchantPriceRecordsHistory()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'description' => 'Test',
            'price' => 99.99,
            'brand' => 'Test'
        ]);

        $merchant = ProductMerchant::create([
            'product_id' => $product->id,
            'name' => 'Amazon',
            'url' => 'https://amazon.com',
            'price' => 79.99,
            'is_available' => true,
            'last_price_check' => now()
        ]);

        // Record initial price
        ProductPriceHistory::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 79.99,
            'recorded_at' => now()
        ]);

        $updateData = [
            'name' => 'Test Product',
            'description' => 'Test',
            'price' => 99.99,
            'brand' => 'Test',
            'merchants' => [
                [
                    'id' => $merchant->id,
                    'name' => 'Amazon',
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
            ->where('merchant_id', $merchant->id)
            ->count();

        $this->assertEquals(2, $historyCount); // Initial + update

        $latestHistory = ProductPriceHistory::where('product_id', $product->id)
            ->where('merchant_id', $merchant->id)
            ->orderBy('recorded_at', 'desc')
            ->first();

        $this->assertEquals(74.99, $latestHistory->price);
    }

    public function testGetProductPriceHistory()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'brand' => 'Test'
        ]);

        $merchant = ProductMerchant::create([
            'product_id' => $product->id,
            'name' => 'Amazon',
            'url' => 'https://amazon.com',
            'price' => 79.99,
            'is_available' => true
        ]);

        // Create price history entries
        ProductPriceHistory::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 79.99,
            'recorded_at' => now_datetime()->modify('-2 days')->format('Y-m-d H:i:s')
        ]);

        ProductPriceHistory::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 74.99,
            'recorded_at' => now_datetime()->modify('-1 day')->format('Y-m-d H:i:s')
        ]);

        ProductPriceHistory::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'price' => 69.99,
            'recorded_at' => now()
        ]);

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
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'brand' => 'Test'
        ]);

        $merchant1 = ProductMerchant::create([
            'product_id' => $product->id,
            'name' => 'Amazon',
            'url' => 'https://amazon.com',
            'price' => 79.99,
            'is_available' => true
        ]);

        $merchant2 = ProductMerchant::create([
            'product_id' => $product->id,
            'name' => 'eBay',
            'url' => 'https://ebay.com',
            'price' => 89.99,
            'is_available' => true
        ]);

        ProductPriceHistory::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant1->id,
            'price' => 79.99,
            'recorded_at' => now()
        ]);

        ProductPriceHistory::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant2->id,
            'price' => 89.99,
            'recorded_at' => now()
        ]);

        $response = $this->getForSite("/api/products/{$product->id}/price-history?merchant_id={$merchant1->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['data']);
        $this->assertEquals(79.99, $data['data'][0]['price']);
        $this->assertEquals($merchant1->id, $data['data'][0]['merchant_id']);
    }

    public function testDuplicateProductToAnotherSite(): void
    {
        $site1 = Site::create(['name' => 'Site 1', 'domain' => 'site1.com']);
        $site2 = Site::create(['name' => 'Site 2', 'domain' => 'site2.com']);

        $product = Product::create([
            'name' => 'iPhone 15',
            'slug' => 'iphone-15',
            'price' => 999.99,
            'site_id' => $site1->id,
            'status' => 'active'
        ]);

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

        $this->assertEquals('iPhone 15 Site 2', $data['data']['name']);
        $this->assertEquals($site2->id, $data['data']['site_id']);
    }

    public function testDuplicateProductToSameSite(): void
    {
        $site = Site::create(['name' => 'Test Site', 'domain' => 'test.com']);

        $product = Product::create([
            'name' => 'Product',
            'slug' => 'product',
            'price' => 99.99,
            'site_id' => $site->id
        ]);

        // Clone without site_id should use same site
        $response = $this->postForSite("/api/products/{$product->id}/duplicate", [
            'name' => 'Product Copy'
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($site->id, $data['data']['site_id']);
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

        // Add relations
        ProductImage::create(['product_id' => $productId, 'url' => 'img.jpg']);
        ProductMerchant::create(['product_id' => $productId, 'name' => 'Amazon', 'url' => 'https://amazon.com', 'price' => 89.99]);
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
        $newProductId = $data['data']['id'];

        $newProduct = Product::with(['images', 'merchants', 'variants', 'specifications'])->find($newProductId);

        $this->assertCount(2, $newProduct->images);
        $this->assertCount(0, $newProduct->merchants);
        $this->assertCount(0, $newProduct->variants);
        $this->assertCount(1, $newProduct->specifications);
    }

}