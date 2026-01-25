<?php

namespace App\Tests\Functional\Controllers\Product;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductComparisonControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_compare_endpoint_requires_ids_parameter(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/compare');

        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Product IDs are required', $data['message']);
    }

    public function test_compare_endpoint_requires_at_least_two_products(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/compare?ids=1');

        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('At least 2 products required for comparison', $data['message']);
    }

    public function test_compare_endpoint_rejects_more_than_four_products(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/compare?ids=1,2,3,4,5');

        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Maximum 4 products can be compared', $data['message']);
    }

    public function test_compare_endpoint_returns_error_for_nonexistent_product(): void
    {
        $response = $this->getForSiteUnauthenticated('/api/compare?ids=9999,9998');

        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_compare_endpoint_returns_not_comparable_when_no_shared_specs(): void
    {
        $group1 = $this->createSpecificationGroup(['name' => 'Group 1']);
        $group2 = $this->createSpecificationGroup(['name' => 'Group 2']);

        $product1 = $this->createProduct();
        $this->createProductSpecification($product1->id, [
            'specification_group_id' => $group1->id,
            'key' => 'Spec A',
            'value' => 'Value 1'
        ]);

        $product2 = $this->createProduct();
        $this->createProductSpecification($product2->id, [
            'specification_group_id' => $group2->id,
            'key' => 'Spec B',
            'value' => 'Value 2'
        ]);

        $response = $this->getForSiteUnauthenticated("/api/compare?ids={$product1->id},{$product2->id}");

        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('At least 2 products must share specification groups', $data['message']);
    }

    protected function createSpecificationGroup(array $attributes = [])
    {
        return \App\Models\ProductSpecificationGroup::create(array_merge([
            'name' => 'Test Group',
            'slug' => 'test-group-' . uniqid(),
            'is_active' => true,
            'sort_order' => 0
        ], $attributes));
    }

    public function test_compare_endpoint_returns_comparison_data_successfully(): void
    {
        $group = $this->createSpecificationGroup(['name' => 'Dimensions']);

        $product1 = $this->createProduct(['name' => 'Product A']);
        $this->createProductSpecification($product1->id, [
            'specification_group_id' => $group->id,
            'key' => 'Width',
            'value' => '100cm'
        ]);
        $this->createProductSpecification($product1->id, [
            'specification_group_id' => $group->id,
            'key' => 'Height',
            'value' => '200cm'
        ]);

        $product2 = $this->createProduct(['name' => 'Product B']);
        $this->createProductSpecification($product2->id, [
            'specification_group_id' => $group->id,
            'key' => 'Width',
            'value' => '120cm'
        ]);
        $this->createProductSpecification($product2->id, [
            'specification_group_id' => $group->id,
            'key' => 'Height',
            'value' => '200cm'
        ]);

        $response = $this->getForSiteUnauthenticated("/api/compare?ids={$product1->id},{$product2->id}");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['comparable']);

        // Check products
        $this->assertCount(2, $data['data']['products']);
        $this->assertEquals('Product A', $data['data']['products'][0]['name']);
        $this->assertEquals('Product B', $data['data']['products'][1]['name']);

        // Check specification groups
        $this->assertCount(1, $data['data']['specification_groups']);
        $this->assertEquals('Dimensions', $data['data']['specification_groups'][0]['name']);

        // Check shared specifications
        $specs = $data['data']['specification_groups'][0]['specifications'];
        $this->assertCount(2, $specs);
        $this->assertArrayHasKey('Width', $specs);
        $this->assertArrayHasKey('Height', $specs);

        // Check differences
        $this->assertCount(1, $data['data']['differences']);
        $this->assertEquals('Width', $data['data']['differences'][0]['key']);
    }

    public function test_compare_endpoint_only_shows_shared_specifications(): void
    {
        $group = $this->createSpecificationGroup(['name' => 'Test Group']);

        $product1 = $this->createProduct();
        $this->createProductSpecification($product1->id, [
            'specification_group_id' => $group->id,
            'key' => 'Shared',
            'value' => 'Value 1'
        ]);
        $this->createProductSpecification($product1->id, [
            'specification_group_id' => $group->id,
            'key' => 'Unique to P1',
            'value' => 'Value'
        ]);

        $product2 = $this->createProduct();
        $this->createProductSpecification($product2->id, [
            'specification_group_id' => $group->id,
            'key' => 'Shared',
            'value' => 'Value 2'
        ]);

        $response = $this->getForSiteUnauthenticated("/api/compare?ids={$product1->id},{$product2->id}");

        $data = json_decode($response->getContent(), true);

        $specs = $data['data']['specification_groups'][0]['specifications'];


        $this->assertCount(1, $specs);
        $this->assertArrayHasKey('Shared', $specs);
        $this->assertArrayNotHasKey('Unique to P1', $specs);
    }

    public function test_compare_view_redirects_when_no_ids(): void
    {
        $response = $this->getForSiteUnauthenticated('/compare');

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_compare_view_redirects_when_invalid_product_count(): void
    {
        $response = $this->getForSiteUnauthenticated('/compare?ids=1');

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_compare_view_shows_comparison_page(): void
    {
        $group = $this->createSpecificationGroup(['name' => 'Dimensions']);

        $product1 = $this->createProduct(['name' => 'Bed A']);
        $this->createProductSpecification($product1->id, [
            'specification_group_id' => $group->id,
            'key' => 'Width',
            'value' => '140cm'
        ]);

        $product2 = $this->createProduct(['name' => 'Bed B']);
        $this->createProductSpecification($product2->id, [
            'specification_group_id' => $group->id,
            'key' => 'Width',
            'value' => '160cm'
        ]);

        $response = $this->getForSiteUnauthenticated("/compare?ids={$product1->id},{$product2->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Product Comparison', $response->getContent());
        $this->assertStringContainsString('Bed A', $response->getContent());
        $this->assertStringContainsString('Bed B', $response->getContent());
    }
}