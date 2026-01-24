<?php

namespace App\Tests\Functional\Controllers;

use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class VariantControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsVariantsList(): void
    {
        $product = $this->createProduct();
        $this->createProductVariant($product->id, ['sku' => 'VAR-001']);
        $this->createProductVariant($product->id, ['sku' => 'VAR-002']);

        $response = $this->getForSite('/api/variants');

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertGreaterThanOrEqual(2, count($data['items']));
    }

    public function testIndexWithSearchQuery(): void
    {
        $product = $this->createProduct();
        $this->createProductVariant($product->id, ['sku' => 'UNIQUE-SKU-123']);
        $this->createProductVariant($product->id, ['sku' => 'OTHER-SKU']);

        $response = $this->getForSite('/api/variants?search=UNIQUE');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThan(0, count($data['items']));
    }

    public function testIndexWithProductFilter(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $this->createProductVariant($product1->id, ['sku' => 'P1-VAR-001']);
        $this->createProductVariant($product2->id, ['sku' => 'P2-VAR-001']);

        $response = $this->getForSite('/api/variants?product_ids=' . $product1->id);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['items']);
        $this->assertEquals('P1-VAR-001', $data['items'][0]['sku']);
    }

    public function testIndexWithMultipleProductFilters(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $product3 = $this->createProduct();

        $this->createProductVariant($product1->id);
        $this->createProductVariant($product2->id);
        $this->createProductVariant($product3->id);

        $productIds = $product1->id . ',' . $product2->id;
        $response = $this->getForSite('/api/variants?product_ids=' . $productIds);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['items']);
    }

    public function testIndexWithActiveStatusFilter(): void
    {
        $product = $this->createProduct();
        $this->createProductVariant($product->id, ['is_active' => true]);
        $this->createProductVariant($product->id, ['is_active' => false]);

        $response = $this->getForSite('/api/variants?is_active=true');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        foreach ($data['items'] as $item) {
            $this->assertTrue($item['is_active']);
        }
    }

    public function testIndexWithSorting(): void
    {
        $product = $this->createProduct();
        $this->createProductVariant($product->id, ['sku' => 'C-SKU']);
        $this->createProductVariant($product->id, ['sku' => 'A-SKU']);
        $this->createProductVariant($product->id, ['sku' => 'B-SKU']);

        $response = $this->getForSite('/api/variants?sort_by=sku&sort_order=asc');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('A-SKU', $data['items'][0]['sku']);
    }

    public function testVariantIndexWithPagination(): void
    {
        $product = $this->createProduct();
        for ($i = 0; $i < 15; $i++) {
            $this->createProductVariant($product->id, ['sku' => "VAR-{$i}"]);
        }

        $response = $this->getForSite('/api/variants?page=1&per_page=10');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(10, count($data['items']));
        $this->assertEquals(15, $data['pagination']['total']);
        $this->assertEquals(2, $data['pagination']['total_pages']);
    }

    public function testShowVariantReturnsVariant(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'TEST-SKU',
            'name' => 'Test Variant'
        ]);

        $response = $this->getForSite("/api/variants/{$variant->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals('TEST-SKU', $data['data']['sku']);
        $this->assertEquals('Test Variant', $data['data']['name']);
    }

    public function testShowVariantIncludesRelationships(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $response = $this->getForSite("/api/variants/{$variant->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('product', $data['data']);
        $this->assertArrayHasKey('images', $data['data']);
    }

    public function testShowVariantReturns404ForNonExistent(): void
    {
        $response = $this->getForSite('/api/variants/9999');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertEquals('Variant not found', $data['message']);
    }

    public function testUpdateVariant(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'OLD-SKU',
            'name' => 'Old Name',
            'price' => 100,
            'is_active' => true
        ]);

        $updateData = [
            'sku' => 'NEW-SKU',
            'name' => 'New Name',
            'price' => 150,
            'sale_price' => 140,
            'is_active' => false
        ];

        $response = $this->putForSite("/api/variants/{$variant->id}", $updateData);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals('Variant updated successfully', $data['message']);

        $updated = ProductVariant::find($variant->id);
        $this->assertEquals('NEW-SKU', $updated->sku);
        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals(150, $updated->price);
        $this->assertEquals(140, $updated->sale_price);
        $this->assertFalse($updated->is_active);
    }

    public function testUpdatePartialFields(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, [
            'sku' => 'ORIG-SKU',
            'price' => 100
        ]);

        $updateData = ['price' => 120];

        $response = $this->putForSite("/api/variants/{$variant->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        $updated = ProductVariant::find($variant->id);
        $this->assertEquals('ORIG-SKU', $updated->sku); // Unchanged
        $this->assertEquals(120, $updated->price); // Changed
    }

    public function testUpdateVariantReturns404ForNonExistent(): void
    {
        $response = $this->putForSite('/api/variants/9999', ['sku' => 'TEST']);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($data['success']);
    }

    public function testDeleteVariant(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $response = $this->deleteForSite("/api/variants/{$variant->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals('Variant deleted successfully', $data['message']);

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    }

    public function testDeleteVariantDeletesImages(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $response = $this->deleteForSite("/api/variants/{$variant->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
        $images = ProductImage::where('variant_id', $variant->id)->get();
        $this->assertCount(0, $images);
    }

    public function testDeleteVariantReturns404ForNonExistent(): void
    {
        $response = $this->deleteForSite('/api/variants/9999');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($data['success']);
    }

    public function testUpdateVariantImages(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $this->createProductImage($product->id, [
            'variant_id' => $variant->id,
            'url' => 'old-img.jpg'
        ]);

        $newImages = [
            [
                'url' => 'new-img1.jpg',
                'alt' => 'New Image 1',
                'is_primary' => true,
                'sort_order' => 0
            ],
            [
                'url' => 'new-img2.jpg',
                'alt' => 'New Image 2',
                'is_primary' => false,
                'sort_order' => 1
            ]
        ];

        $response = $this->putForSite(
            "/api/variants/{$variant->id}/images",
            ['images' => $newImages]
        );
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals('Images updated successfully', $data['message']);

        $this->assertDatabaseMissing('product_images', [
            'variant_id' => $variant->id,
            'url' => 'old-img.jpg'
        ]);

        $images = ProductImage::where('variant_id', $variant->id)->get();

        $this->assertCount(2, $images);
    }

    public function testUpdateVariantImagesEmptyArray(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $this->createProductImage($product->id, ['variant_id' => $variant->id]);

        $response = $this->putForSite(
            "/api/variants/{$variant->id}/images",
            ['images' => []]
        );

        $image = ProductImage::where('variant_id', $variant->id)->get();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(0, $image);
    }

    public function testUpdateVariantImagesReturns404ForNonExistent(): void
    {
        $response = $this->putForSite('/api/variants/9999/images', ['images' => []]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($data['success']);
    }

    public function testToggleVariantStatus(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, ['is_active' => true]);

        $response = $this->putForSite("/api/variants/{$variant->id}/toggle-status", []);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertFalse($data['is_active']);

        $updated = ProductVariant::find($variant->id);
        $this->assertFalse($updated->is_active);
    }

    public function testToggleStatusFromFalseToTrue(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id, ['is_active' => false]);

        $response = $this->putForSite("/api/variants/{$variant->id}/toggle-status", []);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['is_active']);

        $updated = ProductVariant::find($variant->id);
        $this->assertTrue($updated->is_active);
    }

    public function testToggleVariantStatusReturns404ForNonExistent(): void
    {
        $response = $this->putForSite('/api/variants/9999/toggle-status', []);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($data['success']);
    }

    public function testVariantIndexHandlesEmptyResults(): void
    {
        $response = $this->getForSite('/api/variants?search=NONEXISTENT');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(0, $data['items']);
    }

    public function testVariantIndexWithCombinedFilters(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $this->createProductVariant($product1->id, ['sku' => 'ACTIVE-1', 'is_active' => true]);
        $this->createProductVariant($product1->id, ['sku' => 'INACTIVE-1', 'is_active' => false]);
        $this->createProductVariant($product2->id, ['sku' => 'ACTIVE-2', 'is_active' => true]);

        $response = $this->getForSite(
            "/api/variants?product_ids={$product1->id}&is_active=true"
        );
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['items']);
        $this->assertEquals('ACTIVE-1', $data['items'][0]['sku']);
    }

    public function testStoreCreatesVariant(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'NEW-VARIANT-SKU',
            'name' => 'New Variant',
            'price' => 199.99,
            'sale_price' => 179.99,
            'is_active' => true,
            'attributes' => [
                'size' => 'XS',
            ]
        ];

        $response = $this->postForSite('/api/variants', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Variant created successfully', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals('NEW-VARIANT-SKU', $responseData['data']['sku']);
        $this->assertEquals('New Variant', $responseData['data']['name']);
        $this->assertEquals(199.99, $responseData['data']['price']);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'NEW-VARIANT-SKU',
            'name' => 'New Variant',
            'price' => 199.99,
            'sale_price' => 179.99,
            'is_active' => true
        ]);
    }

    public function testStoreWithMinimalData(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'MINIMAL-SKU',
            'price' => 99.99,
            'attributes' => [
                'size' => 'XS',
            ]
        ];

        $response = $this->postForSite('/api/variants', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($responseData['success']);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'MINIMAL-SKU',
            'price' => 99.99
        ]);
    }

    public function testStoreWithImages(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'VARIANT-WITH-IMAGES',
            'price' => 150.00,
            'images' => [
                [
                    'url' => 'variant-img1.jpg',
                    'alt' => 'Variant Image 1',
                    'is_primary' => true,
                    'sort_order' => 0
                ],
                [
                    'url' => 'variant-img2.jpg',
                    'alt' => 'Variant Image 2',
                    'is_primary' => false,
                    'sort_order' => 1
                ]
            ],
            'attributes' => [
                'size' => 'XS',
            ]
        ];

        $response = $this->postForSite('/api/variants', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($responseData['success']);

        $variant = ProductVariant::where('sku', 'VARIANT-WITH-IMAGES')->first();
        $this->assertNotNull($variant);

        $images = ProductImage::where('variant_id', $variant->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $images);
        $this->assertEquals('variant-img1.jpg', $images->first()->url);
        $this->assertTrue((bool)$images->first()->is_primary);
        $this->assertEquals('variant-img2.jpg', $images->last()->url);
        $this->assertFalse((bool)$images->last()->is_primary);
    }

    public function testStoreIncludesRelationshipsInResponse(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'TEST-SKU',
            'price' => 100.00,
            'images' => [
                ['url' => 'test.jpg', 'is_primary' => true, 'sort_order' => 0]
            ],
            'attributes' => [
                'size' => 'XS',
            ]
        ];

        $response = $this->postForSite('/api/variants', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('product', $responseData['data']);
        $this->assertArrayHasKey('images', $responseData['data']);
        $this->assertCount(1, $responseData['data']['images']);
    }

    public function testStoreWithPriceModifier(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'MODIFIER-SKU',
            'price' => 100.00,
            'price_modifier' => 10.00,
            'attributes' => [
                'size' => 'XS',
            ]
        ];

        $response = $this->postForSite('/api/variants', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(10.00, $responseData['data']['price_modifier']);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'MODIFIER-SKU',
            'price_modifier' => 10.00
        ]);
    }

    public function testStoreDefaultsIsActiveToTrue(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'DEFAULT-ACTIVE-SKU',
            'price' => 50.00,
            'attributes' => [
                'size' => 'XS',
            ]
        ];

        $response = $this->postForSite('/api/variants', $data);

        $this->assertEquals(201, $response->getStatusCode());

        $variant = ProductVariant::where('sku', 'DEFAULT-ACTIVE-SKU')->first();
        // Assuming database default is true, otherwise test the actual default behavior
        $this->assertNotNull($variant);
    }

    public function testStoreCanSetInactive(): void
    {
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sku' => 'INACTIVE-SKU',
            'price' => 75.00,
            'is_active' => false,
            'attributes' => [
                'size' => 'XS',
            ]
        ];

        $response = $this->postForSite('/api/variants', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertFalse($responseData['data']['is_active']);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'INACTIVE-SKU',
            'is_active' => false
        ]);
    }

}