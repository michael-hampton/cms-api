<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Brand;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BrandControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsBrandsList()
    {
        $this->createBrand();
        $this->createBrand();

        $response = $this->getForSite('/api/brands');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function testIndexWithSearchQuery()
    {
        $this->createBrand(['name' => 'Apple Inc', 'slug' => 'apple-inc']);
        $this->createBrand(['name' => 'Nike Sports', 'slug' => 'nike-sports']);

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
        $brand = $this->createBrand(['name' => 'Puma', 'slug' => 'puma']);

        $response = $this->getForSite("/api/brands/{$brand->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Puma', $data['data']['brand']['name']);
    }

    public function testShowReturnsBrandBySlug()
    {
        $this->createBrand(['name' => 'Reebok', 'slug' => 'reebok']);

        $response = $this->getForSite('/api/brands/reebok');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Reebok', $data['data']['brand']['name']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->getForSite('/api/brands/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesExistingBrand()
    {
        $brand = $this->createBrand(['name' => 'Old Brand', 'slug' => 'old-brand']);

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
        $brand = $this->createBrand();

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
        $brand = $this->createBrand();

        $response = $this->getForSite("/api/brands/{$brand->id}/check-delete");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['can_delete']);
        $this->assertEquals(0, $data['data']['products_count']);
    }

    public function testAlternativesReturnsOtherBrands()
    {
        $brand1 = $this->createBrand(['name' => 'Brand 1', 'slug' => 'brand-1']);
        $brand2 = $this->createBrand();
        $brand3 = $this->createBrand();

        $response = $this->getForSite("/api/brands/{$brand1->id}/alternatives");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']['brands']['data']);
    }

    public function testMergeBrands()
    {
        $source = $this->createBrand();
        $target = $this->createBrand();

        $response = $this->postForSite('/api/brands/merge', [
            'source_brand_id' => $source->id,
            'target_brand_id' => $target->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Brand::find($source->id));
    }

    public function testDuplicateBrandSuccessfully(): void
    {
        $brand = $this->createBrand(['name' => 'Nike', 'slug' => 'nike', 'description' => 'Sports brand']);;

        $response = $this->postForSite("/api/brands/{$brand->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Nike (Copy)', $data['data']['brand']['name']);
        $this->assertEquals('Sports brand', $data['data']['brand']['description']);
        $this->assertNotEquals($brand->slug, $data['data']['brand']['slug']);
    }

    public function testDuplicateBrandWithLogo(): void
    {
        $brand = $this->createBrand(['logo' => 'logos/adidas.png']);

        // Create dummy logo file
        $logoPath = 'logos/adidas.png';
        $logoPath = getcwd() . '/' . $logoPath;
        @mkdir(dirname($logoPath), 0755, true);
        file_put_contents($logoPath, 'dummy logo content');

        $response = $this->postForSite("/api/brands/{$brand->id}/duplicate", [], [], [], true);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['data']['brand']['logo']);
        $this->assertNotEquals('logos/adidas.png', $data['data']['brand']['logo']);

        // Cleanup
        @unlink($logoPath);
        if (isset($data['data']['logo'])) {
            @unlink('uploads/' . $data['data']['logo']);
        }
    }

    public function testDuplicateBrandWithProducts(): void
    {
        $brand = $this->createBrand(['name' => 'Apple', 'slug' => 'apple']);

        // Create products for the brand
        $this->createProduct(['brand_id' => $brand->id]);
        $this->createProduct(['brand_id' => $brand->id]);

        $response = $this->postForSite("/api/brands/{$brand->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        // Verify brand was duplicated
        $this->assertEquals('Apple (Copy)', $data['data']['brand']['name']);

        // Verify products still belong to original brand
        $originalBrand = Brand::find($brand->id);
        $this->assertEquals(2, $originalBrand->products()->count());

        // Verify new brand has no products
        $newBrand = Brand::find($data['data']['brand']['id']);
        $this->assertEquals(0, $newBrand->products()->count());
    }

    public function testDuplicateBrandWithSeoFields(): void
    {
        $brand = $this->createBrand([
            'name' => 'Nike',
            'slug' => 'nike',
            'description' => 'Sports brand',
            'seo_title' => 'Nike SEO Title',
            'seo_description' => 'Nike SEO Description',
            'no_index' => false,
            'canonical_url' => 'https://example.com/nike'
        ]);;

        $response = $this->postForSite("/api/brands/{$brand->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Nike (Copy)', $data['data']['brand']['name']);
        $this->assertEquals('Nike SEO Title', $data['data']['brand']['seo_title']);
        $this->assertEquals('Nike SEO Description', $data['data']['brand']['seo_description']);
        $this->assertEquals(0, $data['data']['brand']['no_index']);
        $this->assertNull($data['data']['brand']['canonical_url']);
    }

    public function testBulkDeleteSuccessfully(): void
    {
        $brand1 = $this->createBrand();
        $brand2 = $this->createBrand();

        $response = $this->postForSite('/api/brands/bulk-delete', [
            'ids' => [$brand1->id, $brand2->id]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['result']['deleted']);
        $this->assertCount(0, $data['result']['failed']);

        // Verify deletion
        $this->assertNull(Brand::find($brand1->id));
        $this->assertNull(Brand::find($brand2->id));
    }

    public function testBulkDeleteFailsWhenProductsExist(): void
    {
        $brand = $this->createBrand();
        $this->createProduct(['brand_id' => $brand->id]);

        $response = $this->postForSite('/api/brands/bulk-delete', [
            'ids' => [$brand->id]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(0, $data['result']['deleted']);
        $this->assertCount(1, $data['result']['failed']);
        $this->assertStringContainsString('associated products', $data['result']['failed'][0]['reason']);
    }
}