<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

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
            'brand' => 'LogiTech'
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

        $response = $this->post('/api/products', $data, $files);
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

        $response = $this->put("/api/products/{$product->id}", $data, $files);
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

        $response = $this->post('/api/products', $data);

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
        $response = $this->post('/api/products', []);

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

        $response = $this->post('/api/products', $data);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testItCanShowASingleProduct()
    {
        $products = $this->createProduct();

        $response = $this->get("/api/products/{$products->first()->id}");

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('product', $data['data']);
        $this->assertEquals($products->first()->id, $data['data']['product']['id']);
        $this->assertEquals($products->first()->name, $data['data']['product']['name']);
    }

    public function testItReturns404WhenProductNotFound()
    {
        $response = $this->get('/api/products/9999');

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Product not found', $data['data']['message']);
    }

    public function testItCanUpdateAProduct()
    {
        $products = $this->createProduct();
        $product = $products->first();

        $response = $this->put("/api/products/{$product->id}", [
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
        $response = $this->put('/api/products/9999', [
            'name' => 'Test'
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testItCanDeleteAProduct()
    {
        $products = $this->createProduct();

        $response = $this->deleteJson("/api/products/{$products->first()->id}");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testItReturns404WhenDeletingNonExistentProduct()
    {
        $response = $this->deleteJson('/api/products/9999');

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

        $response = $this->post('/api/products', $data);

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

        $response = $this->postJson("/api/products/{$product->id}/duplicate");

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

        $response = $this->postJson("/api/products/{$product->id}/duplicate");

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

        $response = $this->post("/api/products/{$product->id}/duplicate", [
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

        $response = $this->postJson("/api/products/{$product->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        // Verify relationships are maintained
        $this->assertEquals($brand->id, $data['data']['brand_id']);
        $this->assertEquals($category->id, $data['data']['category_id']);
    }
}