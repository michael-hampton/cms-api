<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Brand;
use App\Models\Product;

class BrandControllerTest extends FunctionalTestCase
{
    public function testIndexReturnsBrandsList()
    {
        Brand::create(['name' => 'Apple', 'slug' => 'apple', 'site_id' => $this->siteId]);;
        Brand::create(['name' => 'Nike', 'slug' => 'nike', 'site_id' => $this->siteId]);;;

        $response = $this->getForSite('/api/brands');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function testIndexWithSearchQuery()
    {
        Brand::create(['name' => 'Apple Inc', 'slug' => 'apple-inc', 'site_id' => $this->siteId]);
        Brand::create(['name' => 'Nike Sports', 'slug' => 'nike-sports', 'site_id' => $this->siteId]);

        $response = $this->getForSite('/api/brands?q=apple');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Apple Inc', $data['items'][0]['name']);
    }

    public function testStoreCreatesNewBrand()
    {
        $brandData = [
            'name' => 'Samsung',
            'description' => 'Electronics company',
            'website' => 'https://samsung.com',
            'site_id' => $this->siteId
        ];

        $response = $this->postForSite('/api/brands', $brandData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Samsung', $data['data']['brand']['name']);
        $this->assertEquals('samsung', $data['data']['brand']['slug']);
    }

    public function testStoreWithLogo()
    {
        $files = [
            'logo' => $this->createUploadedFile('logo.png', 'image/png'),
        ];

        $response = $this->postForSite('/api/brands', ['name' => 'Adidas', 'site_id' => $this->siteId], $files);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data['data']['brand']['logo']);
    }

    public function testShowReturnsBrandById()
    {
        $brand = Brand::create(['name' => 'Puma', 'slug' => 'puma']);

        $response = $this->getForSite("/api/brands/{$brand->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Puma', $data['data']['brand']['name']);
    }

    public function testShowReturnsBrandBySlug()
    {
        Brand::create(['name' => 'Reebok', 'slug' => 'reebok']);

        $response = $this->getForSite('/api/brands/reebok');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Reebok', $data['data']['brand']['name']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->get('/api/brands/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesExistingBrand()
    {
        $brand = Brand::create(['name' => 'Old Brand', 'slug' => 'old-brand']);

        $updateData = [
            'name' => 'New Brand',
            'description' => 'Updated description'
        ];

        $response = $this->putForSite("/api/brands/{$brand->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('New Brand', $data['data']['brand']['name']);
        $this->assertEquals('new-brand', $data['data']['brand']['slug']);
    }

    public function testDestroyDeletesBrand()
    {
        $brand = Brand::create(['name' => 'Test Brand', 'slug' => 'test-brand']);

        $response = $this->deleteForSite("/api/brands/{$brand->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Brand::find($brand->id));
    }

    public function testDestroyReturns404ForNonexistent()
    {
        $response = $this->deleteForSite('/api/brands/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCheckDeletableForBrandWithoutProducts()
    {
        $brand = Brand::create(['name' => 'Test Brand', 'slug' => 'test-brand']);

        $response = $this->getForSite("/api/brands/{$brand->id}/check-delete");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['can_delete']);
        $this->assertEquals(0, $data['data']['products_count']);
    }

    public function testAlternativesReturnsOtherBrands()
    {
        $brand1 = Brand::create(['name' => 'Brand 1', 'slug' => 'brand-1']);
        Brand::create(['name' => 'Brand 2', 'slug' => 'brand-2']);
        Brand::create(['name' => 'Brand 3', 'slug' => 'brand-3']);

        $response = $this->getForSite("/api/brands/{$brand1->id}/alternatives");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']['brands']['data']);
    }

    public function testMergeBrands()
    {
        $source = Brand::create(['name' => 'Source Brand', 'slug' => 'source-brand']);
        $target = Brand::create(['name' => 'Target Brand', 'slug' => 'target-brand']);

        $response = $this->postForSite('/api/brands/merge', [
            'source_brand_id' => $source->id,
            'target_brand_id' => $target->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Brand::find($source->id));
    }

    public function testDuplicateBrandSuccessfully(): void
    {
        $brand = Brand::create([
            'name' => 'Nike',
            'description' => 'Sports brand',
            'website' => 'https://nike.com',
            'slug' => 'nike',
            'status' => 'active'
        ]);

        $response = $this->postForSite("/api/brands/{$brand->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Nike (Copy)', $data['data']['name']);
        $this->assertEquals('Sports brand', $data['data']['description']);
        $this->assertNotEquals($brand->slug, $data['data']['slug']);
    }

    public function testDuplicateBrandWithLogo(): void
    {
        $brand = Brand::create([
            'name' => 'Adidas',
            'slug' => 'adidas',
            'logo' => 'logos/adidas.png',
            'status' => 'active'
        ]);

        // Create dummy logo file
        $logoPath = 'uploads/logos/adidas.png';
        @mkdir(dirname($logoPath), 0755, true);
        file_put_contents($logoPath, 'dummy logo content');

        $response = $this->postForSite("/api/brands/{$brand->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['data']['logo']);
        $this->assertNotEquals('logos/adidas.png', $data['data']['logo']);

        // Cleanup
        @unlink($logoPath);
        if (isset($data['data']['logo'])) {
            @unlink('uploads/' . $data['data']['logo']);
        }
    }

    public function testDuplicateBrandWithProducts(): void
    {
        $brand = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'status' => 'active'
        ]);

        // Create products for the brand
        Product::create([
            'name' => 'iPhone',
            'brand_id' => $brand->id,
            'price' => 999.99,
            'slug' => 'iphone'
        ]);

        Product::create([
            'name' => 'MacBook',
            'brand_id' => $brand->id,
            'price' => 1999.99,
            'slug' => 'macbook'
        ]);

        $response = $this->postForSite("/api/brands/{$brand->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        // Verify brand was duplicated
        $this->assertEquals('Apple (Copy)', $data['data']['name']);

        // Verify products still belong to original brand
        $originalBrand = Brand::find($brand->id);
        $this->assertEquals(2, $originalBrand->products()->count());

        // Verify new brand has no products
        $newBrand = Brand::find($data['data']['id']);
        $this->assertEquals(0, $newBrand->products()->count());
    }

    public function testDuplicateBrandWithSeoFields(): void
    {
        $brand = Brand::create([
            'name' => 'Nike',
            'slug' => 'nike',
            'status' => 'active',
            'seo_title' => 'Nike SEO Title',
            'seo_description' => 'Nike SEO Description',
            'no_index' => false,
            'canonical_url' => 'https://example.com/nike'
        ]);

        $response = $this->postForSite("/api/brands/{$brand->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Nike (Copy)', $data['data']['name']);
        $this->assertEquals('Nike SEO Title', $data['data']['seo_title']);
        $this->assertEquals('Nike SEO Description', $data['data']['seo_description']);
        $this->assertEquals(0, $data['data']['no_index']);
        $this->assertNull($data['data']['canonical_url']);
    }

//    public function testActiveReturnsOnlyActiveBrands()
//    {
//        Brand::create(['name' => 'Active Brand', 'slug' => 'active-brand', 'is_active' => true]);
//        Brand::create(['name' => 'Inactive Brand', 'slug' => 'inactive-brand', 'is_active' => false]);
//
//        $response = $this->get('/api/brands/active');
//
//        $this->assertEquals(200, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//        $this->assertCount(1, $data['data']['brands']);
//        $this->assertEquals('Active Brand', $data['data']['brands'][0]['name']);
//    }
}